<?php
/**
 * Delivery platforms: "Order on …" links, pickup or delivery at your own
 * checkout (paid with Sidcraft Commerce's payment gateways), courier
 * dispatch once the order is paid, and courier status updates.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Delivery;

use SidcraftCommerce\Cart\Cart;
use SidcraftCommerce\Data\Order;
use SidcraftCommerce\Data\Post_Types as Commerce_Types;
use SidcraftRestaurant\Ordering;
use function SidcraftRestaurant\price;

defined( 'ABSPATH' ) || exit;

/**
 * Class Delivery
 */
class Delivery {

	/** Checkout field and session key. */
	const FIELD = 'sidrest_fulfilment';

	/** Order status while a courier has the order. */
	const STATUS = 'sidrest-courier';

	/** Order meta: dispatch record. */
	const META = '_sidrest_dispatch';

	/** REST namespace. */
	const NS = 'sidcraft-restaurant/v1';

	/** Option holding the secret key of the courier status URL. */
	const KEY_OPTION = 'sidrest_courier_status_key';

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		// Register right away (no translation call: translations may not load
		// before init), then again on init with the translated label.
		$this->register_status( false );
		add_action( 'init', array( $this, 'register_translated_status' ), 0 );

		add_shortcode( 'sidrest_delivery', array( $this, 'shortcode' ) );
		add_action( 'sidrest_menu_top', array( $this, 'menu_links' ), 10, 2 );
		add_action( 'sidrest_single_item_summary', array( $this, 'item_links' ) );

		add_filter( 'sidcraft_commerce_checkout_fields', array( $this, 'checkout_fields' ) );
		add_filter( 'sidcraft_commerce_cart_shipping', array( $this, 'shipping' ), 20, 2 );
		add_filter( 'sidcraft_commerce_checkout_errors', array( $this, 'checkout_errors' ), 10, 2 );
		add_action( 'sidcraft_commerce_payment_complete', array( $this, 'on_paid' ), 20 );

		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'init', array( $this, 'register_assets' ), 7 );
		add_action( 'wp_footer', array( $this, 'maybe_enqueue' ), 1 );
	}

	/*
	------------------------------------------------------------------
	 * Order status
	 * ----------------------------------------------------------------
	 */

	/**
	 * "Out for delivery": counts as paid, comes after Processing. The
	 * customer gets the tracking email from the shipment instead of a
	 * status email.
	 *
	 * @param bool $translate Whether to translate the label. Only true from
	 *                        init onwards (WordPress 6.7+ warns otherwise).
	 */
	protected function register_status( $translate = true ) {
		if ( ! method_exists( Commerce_Types::class, 'register_order_status' ) ) {
			return;
		}
		Commerce_Types::register_order_status(
			self::STATUS,
			array(
				'label' => $translate ? __( 'Out for delivery', 'sidcraft-restaurant-for-sidcraft-commerce' ) : 'Out for delivery',
				'paid'  => true,
				'after' => 'sidccofo-processing',
				'color' => '#1d4ed8',
			)
		);
	}

	/**
	 * Re-register the status with its translated label, on init.
	 */
	public function register_translated_status() {
		$this->register_status( true );
	}

	/*
	------------------------------------------------------------------
	 * "Order on …" links
	 * ----------------------------------------------------------------
	 */

	/**
	 * Buttons to the restaurant's store pages on delivery platforms.
	 *
	 * @param array $args heading (string|false), only (slugs).
	 * @return string
	 */
	public static function links_html( array $args = array() ) {
		$links = Platforms::links();
		if ( ! empty( $args['only'] ) ) {
			$links = array_intersect_key( $links, array_flip( (array) $args['only'] ) );
		}
		if ( ! $links ) {
			return '';
		}
		$heading = $args['heading'] ?? null;
		if ( null === $heading ) {
			$heading = trim( (string) Platforms::get( 'links_heading' ) );
			$heading = '' !== $heading ? $heading : __( 'Order for delivery on', 'sidcraft-restaurant-for-sidcraft-commerce' );
		}
		wp_enqueue_style( 'sidrest-menu' );
		$html = '<nav class="sidrest-delivery" style="' . esc_attr( \SidcraftRestaurant\Frontend::css_vars() ) . '" aria-label="' . esc_attr( false !== $heading && '' !== $heading ? $heading : __( 'Delivery platforms', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) . '">';
		if ( false !== $heading && '' !== $heading ) {
			$html .= '<span class="sidrest-delivery__heading">' . esc_html( $heading ) . '</span>';
		}
		$html .= '<ul class="sidrest-delivery__list">';
		foreach ( $links as $slug => $link ) {
			$html .= sprintf(
				'<li><a class="sidrest-delivery__link sidrest-delivery__link--%1$s" href="%2$s" target="_blank" rel="noopener nofollow">%3$s<span class="sidrest-sr"> %4$s</span></a></li>',
				esc_attr( $slug ),
				esc_url( $link[1] ),
				esc_html( $link[0] ),
				esc_html__( '(opens in a new tab)', 'sidcraft-restaurant-for-sidcraft-commerce' )
			);
		}
		return $html . '</ul></nav>';
	}

	/**
	 * [sidrest_delivery platforms="doordash,uber-eats" heading="Get it delivered"].
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'platforms' => '',
				'heading'   => null,
			),
			(array) $atts,
			'sidrest_delivery'
		);
		$args = array();
		if ( '' !== trim( (string) $atts['platforms'] ) ) {
			$args['only'] = array_map( 'sanitize_key', array_map( 'trim', explode( ',', (string) $atts['platforms'] ) ) );
		}
		if ( null !== $atts['heading'] ) {
			$args['heading'] = 'none' === $atts['heading'] ? false : sanitize_text_field( (string) $atts['heading'] );
		}
		return self::links_html( $args );
	}

	/**
	 * Links at the top of every menu.
	 *
	 * @param \SidcraftRestaurant\Menu|null $menu Menu.
	 * @param array                         $args Display options.
	 */
	public function menu_links( $menu, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Hook signature.
		if ( Platforms::get( 'links_on_menus' ) ) {
			echo self::links_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in links_html().
		}
	}

	/**
	 * Links on menu item pages.
	 *
	 * @param \SidcraftRestaurant\Item $item Item.
	 */
	public function item_links( $item ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Hook signature.
		if ( Platforms::get( 'links_on_items' ) ) {
			echo self::links_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in links_html().
		}
	}

	/*
	------------------------------------------------------------------
	 * Pickup or delivery at checkout
	 * ----------------------------------------------------------------
	 */

	/**
	 * The cart holds menu items only (a store that also sells other goods
	 * keeps its normal shipping for those orders).
	 *
	 * @param Cart $cart Cart.
	 * @return bool
	 */
	public static function is_restaurant_cart( Cart $cart ) {
		$found = false;
		foreach ( $cart->get_items() as $line ) {
			if ( ! empty( $line['gift_card'] ) ) {
				continue;
			}
			if ( ! get_post_meta( (int) $line['product_id'], Ordering::LINK, true ) ) {
				return false;
			}
			$found = true;
		}
		return $found;
	}

	/**
	 * Delivery fee for a platform and order subtotal.
	 *
	 * @param array $row      Platform settings.
	 * @param float $subtotal Subtotal after discounts.
	 * @return float
	 */
	public static function fee( array $row, $subtotal ) {
		$fee  = max( 0.0, (float) $row['fee'] );
		$free = '' !== (string) $row['free_over'] ? (float) $row['free_over'] : 0.0;
		if ( $free > 0 && $subtotal >= $free ) {
			return 0.0;
		}
		return \SidcraftCommerce\round_amount( $fee );
	}

	/**
	 * Choices for this cart: key => [ label, fee (float), platform slug ('' = pickup) ].
	 *
	 * @param Cart|null $cart Cart.
	 * @return array Empty when pickup/delivery doesn't apply.
	 */
	public static function choices( $cart = null ) {
		if ( ! Platforms::get( 'fulfilment' ) ) {
			return array();
		}
		$cart = $cart ? $cart : Cart::instance();
		if ( ! self::is_restaurant_cart( $cart ) ) {
			return array();
		}
		$subtotal = max( 0.0, $cart->get_goods_subtotal() - $cart->get_discount() );
		$out      = array();
		if ( Platforms::get( 'pickup' ) ) {
			$out['pickup'] = array( __( 'Pickup', 'sidcraft-restaurant-for-sidcraft-commerce' ), 0.0, '' );
		}
		if ( \SidcraftCommerce\setting( 'collect_address', 1 ) ) {
			foreach ( Platforms::checkout_platforms() as $slug => $row ) {
				$fee          = self::fee( $row, $subtotal );
				$out[ $slug ] = array(
					sprintf(
						/* translators: 1: platform, 2: fee or "free". */
						__( 'Delivery by %1$s (%2$s)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						Platforms::name( $slug ),
						$fee > 0 ? self::plain_price( $fee ) : __( 'free', 'sidcraft-restaurant-for-sidcraft-commerce' )
					),
					$fee,
					$slug,
				);
			}
		}

		/**
		 * Filter the pickup/delivery choices at checkout.
		 *
		 * @param array $out  key => [ label, fee, platform ].
		 * @param Cart  $cart Cart.
		 */
		return (array) apply_filters( 'sidrest_fulfilment_choices', $out, $cart );
	}

	/**
	 * The choice saved for this visitor (or the first one).
	 *
	 * @param array $choices Choices.
	 * @return string
	 */
	public static function current_choice( array $choices ) {
		if ( ! $choices ) {
			return '';
		}
		$saved = (string) Cart::instance()->session()->get( self::FIELD, '' );
		return isset( $choices[ $saved ] ) ? $saved : (string) array_key_first( $choices );
	}

	/**
	 * Add the "Pickup or delivery" field at the top of the checkout.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	public function checkout_fields( $fields ) {
		$choices = self::choices();
		if ( ! $choices ) {
			return $fields;
		}
		$current = self::current_choice( $choices );
		$options = array( $current => $choices[ $current ][0] ) + wp_list_pluck( $choices, 0 ); // Saved choice first, so it shows selected.
		return array(
			self::FIELD => array( __( 'Pickup or delivery', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'select', true, 'off', 'full', $options ),
		) + $fields;
	}

	/**
	 * The chosen option sets the shipping line: free for pickup, the
	 * platform's delivery fee otherwise.
	 *
	 * @param float $cost Shipping cost.
	 * @param Cart  $cart Cart.
	 * @return float
	 */
	public function shipping( $cost, $cart ) {
		if ( ! $cart instanceof Cart ) {
			return $cost;
		}
		$choices = self::choices( $cart );
		if ( ! $choices ) {
			return $cost;
		}
		return (float) $choices[ self::current_choice( $choices ) ][1];
	}

	/**
	 * Check the choice before the order is created.
	 *
	 * @param array $errors  field => message.
	 * @param array $billing Sanitized checkout fields.
	 * @return array
	 */
	public function checkout_errors( $errors, $billing ) {
		$cart    = Cart::instance();
		$choices = self::choices( $cart );
		if ( ! $choices ) {
			return $errors;
		}
		$chosen = (string) ( $billing[ self::FIELD ] ?? '' );
		if ( ! isset( $choices[ $chosen ] ) ) {
			$errors[ self::FIELD ] = __( 'Choose pickup or delivery.', 'sidcraft-restaurant-for-sidcraft-commerce' );
			return $errors;
		}
		if ( self::current_choice( $choices ) !== $chosen ) {
			// The total shown was for another choice (JavaScript off or a stale page).
			$cart->session()->set( self::FIELD, $chosen );
			$errors[ self::FIELD ] = __( 'Your total was updated for this choice. Check it, then place the order again.', 'sidcraft-restaurant-for-sidcraft-commerce' );
			return $errors;
		}
		if ( '' === $choices[ $chosen ][2] ) {
			return $errors; // Pickup.
		}

		if ( '' === trim( (string) ( $billing['address_1'] ?? '' ) ) ) {
			$errors['address_1'] = __( 'Enter the delivery address.', 'sidcraft-restaurant-for-sidcraft-commerce' );
		}
		if ( '' === trim( (string) ( $billing['phone'] ?? '' ) ) ) {
			$errors['phone'] = __( 'Enter a phone number so the courier can reach you.', 'sidcraft-restaurant-for-sidcraft-commerce' );
		}
		$min = (float) Platforms::get( 'min_order' );
		if ( $min > 0 && $cart->get_goods_subtotal() - $cart->get_discount() < $min ) {
			/* translators: %s: amount. */
			$errors[ self::FIELD ] = sprintf( __( 'The minimum order for delivery is %s.', 'sidcraft-restaurant-for-sidcraft-commerce' ), self::plain_price( $min ) );
		}
		if ( ! self::delivers_to( (string) ( $billing['postcode'] ?? '' ) ) ) {
			$errors['postcode'] = __( 'Sorry, we don’t deliver to this postcode. Choose pickup instead.', 'sidcraft-restaurant-for-sidcraft-commerce' );
		}
		return $errors;
	}

	/**
	 * A price as plain text ("$4.99"), for select options, notes and messages.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function plain_price( $amount ) {
		return html_entity_decode( wp_strip_all_tags( price( (float) $amount ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	/**
	 * An amount in an order's currency as plain text.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	public static function plain_in( $amount, $currency ) {
		return html_entity_decode( wp_strip_all_tags( \SidcraftCommerce\format_in( (float) $amount, $currency ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Are checkout fields being built for a shopper's checkout (not an
	 * admin screen, cron job or early request, where the cart isn't there)?
	 *
	 * @return bool
	 */
	public static function shopper_context() {
		return did_action( 'init' ) && ! wp_doing_cron() && ( ! is_admin() || wp_doing_ajax() );
	}

	/**
	 * Postcode inside the delivery area ("02139, 0214*, SW1*"; empty = everywhere).
	 *
	 * @param string $postcode Postcode.
	 * @return bool
	 */
	public static function delivers_to( $postcode ) {
		$rules = array_filter( array_map( 'trim', explode( ',', (string) Platforms::get( 'postcodes' ) ) ) );
		if ( ! $rules ) {
			return true;
		}
		$code = strtoupper( preg_replace( '/\s+/', '', $postcode ) );
		foreach ( $rules as $rule ) {
			$rule = strtoupper( preg_replace( '/\s+/', '', $rule ) );
			if ( '*' === substr( $rule, -1 ) ? 0 === strpos( $code, substr( $rule, 0, -1 ) ) : $code === $rule ) {
				return true;
			}
		}
		return false;
	}

	/*
	------------------------------------------------------------------
	 * Front-end script: re-price when the choice changes
	 * ----------------------------------------------------------------
	 */

	/**
	 * Register the checkout script.
	 */
	public function register_assets() {
		wp_register_script(
			'sidrest-delivery',
			SIDREST_URL . 'assets/js/sidrest-delivery.js',
			array(),
			SIDREST_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'sidrest-delivery',
			'sidrestDelivery',
			array(
				'route'        => esc_url_raw( rest_url( self::NS . '/fulfilment' ) ),
				'field'        => self::FIELD,
				// 1.2.0: tips and delivery zones by distance.
				'tipRoute'     => esc_url_raw( rest_url( self::NS . '/tip' ) ),
				'tipField'     => \SidcraftRestaurant\Service\Tips::FIELD,
				'tipAmount'    => \SidcraftRestaurant\Service\Tips::AMOUNT,
				'addressRoute' => Zones::active() ? esc_url_raw( rest_url( self::NS . '/address' ) ) : '',
			)
		);
	}

	/**
	 * Load it wherever Sidcraft Commerce's checkout script is.
	 */
	public function maybe_enqueue() {
		if ( ( Platforms::get( 'fulfilment' ) || \SidcraftRestaurant\setting( 'tips', 0 ) ) && wp_script_is( 'sidccofo-checkout', 'enqueued' ) ) {
			wp_enqueue_script( 'sidrest-delivery' );
		}
	}

	/**
	 * REST routes.
	 */
	public function routes() {
		register_rest_route(
			self::NS,
			'/fulfilment',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Public, like the store's own cart routes; only changes this visitor's session.
				'callback'            => array( $this, 'set_choice' ),
				'args'                => array(
					'choice' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/courier-status',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'status_permission' ),
				'callback'            => array( $this, 'courier_status' ),
			)
		);
	}

	/**
	 * POST fulfilment: save the choice and return fresh checkout fragments.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_choice( \WP_REST_Request $request ) {
		$cart    = Cart::instance();
		$choices = self::choices( $cart );
		$choice  = sanitize_key( (string) $request->get_param( 'choice' ) );
		if ( ! isset( $choices[ $choice ] ) ) {
			return new \WP_Error( 'sidrest_bad_choice', __( 'Choose pickup or delivery.', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 'status' => 400 ) );
		}
		$cart->session()->set( self::FIELD, $choice );
		self::prefill( $cart, array( self::FIELD => $choice ) );
		self::refresh( $cart );
		$response = rest_ensure_response(
			array(
				'fragments' => \SidcraftCommerce\Fragments::collect( 'checkout' ),
				'total'     => Cart::instance()->get_total(),
				'shipping'  => Cart::instance()->get_shipping(),
			)
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Show these values as chosen when the checkout form is next drawn
	 * (Sidcraft Commerce fills the form from the session's "customer").
	 *
	 * @param Cart  $cart   Cart.
	 * @param array $values field => value.
	 */
	public static function prefill( Cart $cart, array $values ) {
		$customer = (array) $cart->session()->get( 'customer', array() );
		$cart->session()->set( 'customer', array_merge( $customer, array_map( 'strval', $values ) ) );
	}

	/**
	 * Drop the cart's per-request totals so shipping is priced again with
	 * the new choice.
	 *
	 * @param Cart $cart Cart.
	 */
	public static function refresh( Cart $cart ) {
		if ( method_exists( $cart, 'forget_totals' ) ) {
			$cart->forget_totals(); // Sidcraft Commerce 2.0+.
			return;
		}
		$location = $cart->get_location();
		if ( ! empty( $location['known'] ) ) {
			$cart->set_location( $location['country'], $location['state'] ); // Same place; clears the cached totals.
			return;
		}
		try {
			$prop = new \ReflectionProperty( Cart::class, 'totals' );
			$prop->setAccessible( true );
			$prop->setValue( $cart, null );
		} catch ( \ReflectionException $e ) {
			unset( $e ); // Totals are computed lazily; nothing cached yet in this request.
		}
	}

	/*
	------------------------------------------------------------------
	 * Dispatch after payment
	 * ----------------------------------------------------------------
	 */

	/**
	 * The platform an order is delivered by ('' for pickup or none).
	 *
	 * @param Order $order Order.
	 * @return string
	 */
	public static function order_platform( Order $order ) {
		$billing = $order->get_billing();
		$slug    = (string) ( $billing[ self::FIELD ] ?? '' );
		return '' !== $slug && 'pickup' !== $slug && '' !== Platforms::name( $slug ) ? $slug : '';
	}

	/**
	 * A paid order: book the courier (or hand it on) for delivery orders.
	 *
	 * @param Order $order Order.
	 */
	public function on_paid( $order ) {
		if ( ! $order instanceof Order || '' === self::order_platform( $order ) ) {
			return;
		}
		if ( ! Platforms::get( 'auto_dispatch' ) ) {
			/* translators: %s: platform. */
			$order->add_note( sprintf( __( 'Delivery by %s: request the courier from this order’s Delivery box.', 'sidcraft-restaurant-for-sidcraft-commerce' ), Platforms::name( self::order_platform( $order ) ) ) );
			return;
		}
		self::dispatch( $order );
	}

	/**
	 * Request the courier for an order.
	 *
	 * @param Order $order Order.
	 * @param bool  $force Try again even after an earlier request.
	 * @return array|\WP_Error The dispatch record.
	 */
	public static function dispatch( Order $order, $force = false ) {
		$slug = self::order_platform( $order );
		if ( '' === $slug ) {
			return new \WP_Error( 'sidrest_not_delivery', __( 'This order is not a delivery order.', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
		}
		$record = self::record( $order );
		if ( $record && in_array( $record['status'], array( 'requested', 'sent', 'delivered' ), true ) && ! $force ) {
			return $record;
		}
		$row  = Platforms::platform( $slug );
		$mode = $row ? (string) $row['dispatch'] : 'manual';
		$name = Platforms::name( $slug );
		$ext  = 'sidrest-' . $order->get_id() . '-' . strtolower( wp_generate_password( 6, false ) );

		$record = array(
			'platform'     => $slug,
			'mode'         => $mode,
			'status'       => 'manual',
			'external_id'  => $ext,
			'id'           => '',
			'tracking_url' => '',
			'fee'          => null,
			'error'        => '',
			'time'         => time(),
		);

		if ( 'api' === $mode ) {
			$result = Couriers::request( Platforms::api( $slug ), $order, $ext );
			if ( is_wp_error( $result ) ) {
				$record['status'] = 'failed';
				$record['error']  = $result->get_error_message();
				/* translators: 1: platform, 2: error. */
				$order->add_note( sprintf( __( 'The %1$s courier could not be requested: %2$s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $name, $record['error'] ) );
				self::notify_staff( $order, $record );
			} else {
				$record['status']       = 'requested';
				$record['id']           = $result['id'];
				$record['tracking_url'] = $result['tracking_url'];
				$record['fee']          = $result['fee'];
				/* translators: 1: platform, 2: delivery ID, 3: courier cost. */
				$order->add_note( sprintf( __( '%1$s courier requested (delivery %2$s, courier cost %3$s).', 'sidcraft-restaurant-for-sidcraft-commerce' ), $name, $result['id'], null === $result['fee'] ? '—' : self::plain_price( $result['fee'] ) ) );
				self::save( $order, $record );
				self::add_tracking( $order, $name, $result['id'], $result['tracking_url'] );
				if ( in_array( $order->get_status(), array( 'sidccofo-processing' ), true ) ) {
					$order->set_status( self::STATUS );
				}
				return $record;
			}
		} elseif ( 'webhook' === $mode ) {
			$sent = self::send_webhook( $order, $slug, $ext );
			if ( is_wp_error( $sent ) ) {
				$record['status'] = 'failed';
				$record['error']  = $sent->get_error_message();
				/* translators: %s: error. */
				$order->add_note( sprintf( __( 'The delivery webhook failed: %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $record['error'] ) );
				self::notify_staff( $order, $record );
			} else {
				$record['status'] = 'sent';
				/* translators: %s: platform. */
				$order->add_note( sprintf( __( 'Delivery order sent to the webhook for %s.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $name ) );
			}
		} else {
			/* translators: %s: platform. */
			$order->add_note( sprintf( __( 'Book a %s courier for this order, then add the tracking link in the Delivery box.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $name ) );
			self::notify_staff( $order, $record );
		}
		self::save( $order, $record );
		return $record;
	}

	/**
	 * Add the courier's tracking link as a Commerce shipment (emails the
	 * customer the link; the order stays open until delivered).
	 *
	 * @param Order  $order  Order.
	 * @param string $name   Platform name.
	 * @param string $number Delivery ID.
	 * @param string $url    Tracking URL.
	 */
	public static function add_tracking( Order $order, $name, $number, $url ) {
		if ( ! class_exists( '\SidcraftCommerce\Shipments' ) || '' === (string) $number ) {
			return;
		}
		\SidcraftCommerce\Shipments::add(
			$order,
			array(
				'carrier_name' => $name,
				'number'       => $number,
				'url'          => $url,
			),
			'' !== (string) $url,
			false
		);
	}

	/**
	 * Post the paid order as JSON, signed with HMAC-SHA256.
	 *
	 * @param Order  $order Order.
	 * @param string $slug  Platform.
	 * @param string $ext   Delivery reference.
	 * @return true|\WP_Error
	 */
	protected static function send_webhook( Order $order, $slug, $ext ) {
		$url = trim( (string) Platforms::get( 'webhook_url' ) );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new \WP_Error( 'sidrest_webhook_url', __( 'Enter a webhook URL in Restaurant → Settings → Delivery.', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
		}
		$billing = $order->get_billing();
		$items   = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'name'     => (string) $item['name'],
				'sku'      => (string) ( $item['sku'] ?? '' ),
				'quantity' => (int) $item['quantity'],
				'price'    => (float) $item['price'],
				'total'    => (float) $item['total'],
				'options'  => \SidcraftCommerce\item_options_text( $item['options'] ?? array() ),
			);
		}
		$payload = array(
			'event'        => 'order.paid',
			'platform'     => $slug,
			'platform_name' => Platforms::name( $slug ),
			'reference'    => $ext,
			'order'        => array(
				'id'             => $order->get_id(),
				'date'           => $order->get_date(),
				'currency'       => $order->get_currency(),
				'subtotal'       => $order->get_subtotal(),
				'discount'       => $order->get_discount(),
				'delivery_fee'   => $order->get_shipping(),
				'tax'            => $order->get_tax(),
				'total'          => $order->get_total(),
				'payment_method' => $order->get_payment_method_title(),
				'transaction_id' => $order->get_transaction_id(),
				'notes'          => (string) get_post_meta( $order->get_id(), '_sidccofo_notes', true ),
				'items'          => $items,
			),
			'customer'     => array(
				'name'    => trim( ( $billing['first_name'] ?? '' ) . ' ' . ( $billing['last_name'] ?? '' ) ),
				'email'   => (string) ( $billing['email'] ?? '' ),
				'phone'   => Couriers::phone( (string) ( $billing['phone'] ?? '' ) ),
				'address' => Couriers::customer_address( $billing ),
			),
			'pickup'       => array(
				'name'         => Couriers::store_name(),
				'phone'        => Couriers::phone( (string) Platforms::get( 'store_phone' ) ),
				'address'      => Couriers::store_address(),
				'notes'        => (string) Platforms::get( 'store_notes' ),
				'ready_at'     => gmdate( 'c', time() + absint( Platforms::get( 'prep_minutes' ) ) * MINUTE_IN_SECONDS ),
			),
			'status_url'   => self::status_url(),
		);

		/**
		 * Filter the delivery webhook payload.
		 *
		 * @param array $payload Payload.
		 * @param Order $order   Order.
		 */
		$body     = wp_json_encode( apply_filters( 'sidrest_delivery_webhook_payload', $payload, $order ) );
		$headers  = array(
			'Content-Type'      => 'application/json',
			'X-Sidrest-Event'   => 'order.paid',
			'X-Sidrest-Platform' => $slug,
		);
		$secret   = (string) Platforms::get( 'webhook_secret' );
		if ( '' !== $secret ) {
			$headers['X-Sidrest-Signature'] = 'sha256=' . hash_hmac( 'sha256', (string) $body, $secret );
		}
		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => $headers,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			/* translators: %d: HTTP status. */
			return new \WP_Error( 'sidrest_webhook_status', sprintf( __( 'The webhook answered with HTTP %d.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $code ) );
		}
		return true;
	}

	/**
	 * Email the store when a courier needs booking by hand or a request failed.
	 *
	 * @param Order $order  Order.
	 * @param array $record Dispatch record.
	 */
	protected static function notify_staff( Order $order, array $record ) {
		$name = Platforms::name( $record['platform'] );
		if ( 'failed' === $record['status'] ) {
			/* translators: 1: order number, 2: platform. */
			$subject = sprintf( __( 'Order #%1$d: the %2$s courier request failed', 'sidcraft-restaurant-for-sidcraft-commerce' ), $order->get_id(), $name );
			/* translators: %s: error. */
			$body = sprintf( __( 'Error: %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $record['error'] ) . "\n\n";
		} else {
			/* translators: 1: order number, 2: platform. */
			$subject = sprintf( __( 'Order #%1$d: book a %2$s courier', 'sidcraft-restaurant-for-sidcraft-commerce' ), $order->get_id(), $name );
			$body    = '';
		}
		$body .= Couriers::one_line( Couriers::customer_address( $order->get_billing() ) ) . "\n\n" . admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );

		/**
		 * Filter who is emailed about delivery orders that need attention.
		 *
		 * @param string $to    Address.
		 * @param Order  $order Order.
		 */
		$to = (string) apply_filters( 'sidrest_delivery_staff_email', (string) get_option( 'admin_email' ), $order );
		if ( is_email( $to ) ) {
			wp_mail( $to, $subject, $body );
		}
	}

	/**
	 * The dispatch record on an order.
	 *
	 * @param Order $order Order.
	 * @return array|null
	 */
	public static function record( Order $order ) {
		$record = get_post_meta( $order->get_id(), self::META, true );
		return is_array( $record ) && isset( $record['status'] ) ? $record : null;
	}

	/**
	 * Save the dispatch record.
	 *
	 * @param Order $order  Order.
	 * @param array $record Record.
	 */
	public static function save( Order $order, array $record ) {
		update_post_meta( $order->get_id(), self::META, $record );

		/**
		 * Fires after a delivery order's dispatch record changes.
		 *
		 * @param array $record Record.
		 * @param Order $order  Order.
		 */
		do_action( 'sidrest_delivery_dispatched', $record, $order );
	}

	/*
	------------------------------------------------------------------
	 * Courier status updates (webhooks from DoorDash, Uber, or your hub)
	 * ----------------------------------------------------------------
	 */

	/**
	 * The secret key for the status URL (created on first use).
	 *
	 * @return string
	 */
	public static function status_key() {
		$key = (string) get_option( self::KEY_OPTION, '' );
		if ( '' === $key ) {
			$key = wp_generate_password( 32, false );
			update_option( self::KEY_OPTION, $key, false );
		}
		return $key;
	}

	/**
	 * URL couriers post status updates to.
	 *
	 * @return string
	 */
	public static function status_url() {
		return add_query_arg( 'key', rawurlencode( self::status_key() ), rest_url( self::NS . '/courier-status' ) );
	}

	/**
	 * Only requests carrying the secret key.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function status_permission( \WP_REST_Request $request ) {
		$key = (string) get_option( self::KEY_OPTION, '' );
		return '' !== $key && hash_equals( $key, (string) $request->get_param( 'key' ) );
	}

	/**
	 * A courier event: find the order by our delivery reference and move it on.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function courier_status( \WP_REST_Request $request ) {
		$raw = (string) $request->get_body();
		if ( ! preg_match( '/sidrest-(\d+)-[a-z0-9]{6}/', $raw, $m ) ) {
			return rest_ensure_response( array( 'ok' => false ) );
		}
		$order  = Order::get( (int) $m[1] );
		$record = $order ? self::record( $order ) : null;
		if ( ! $order || ! $record || ! hash_equals( (string) $record['external_id'], $m[0] ) ) {
			return rest_ensure_response( array( 'ok' => false ) );
		}

		$data   = json_decode( $raw, true );
		$data   = is_array( $data ) ? $data : array();
		$event  = strtolower( implode( ' ', self::status_words( $data ) ) );
		$name   = Platforms::name( $record['platform'] );
		$before = $record['status'];

		if ( preg_match( '/deliver(ed|y_complete)|dropped_off|drop_off_complete/', $event ) ) {
			$record['status'] = 'delivered';
			if ( ! in_array( $order->get_status(), array( 'sidccofo-completed', 'sidccofo-cancelled', 'sidccofo-refunded' ), true ) ) {
				/* translators: %s: platform. */
				$order->set_status( 'sidccofo-completed', sprintf( __( 'Delivered by %s.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $name ) );
			}
		} elseif ( preg_match( '/cancel/', $event ) ) {
			$record['status'] = 'cancelled';
			/* translators: %s: platform. */
			$order->add_note( sprintf( __( 'The %s courier delivery was cancelled. Request a new courier from the Delivery box.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $name ) );
			self::notify_staff( $order, array_merge( $record, array( 'status' => 'failed', 'error' => __( 'The courier delivery was cancelled.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) ) );
		} elseif ( preg_match( '/picked_up|pickup_complete|enroute_to_dropoff/', $event ) && 'picked_up' !== $before ) {
			$record['status'] = 'picked_up';
			/* translators: %s: platform. */
			$order->add_note( sprintf( __( 'The %s courier picked up the order.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $name ) );
			if ( 'sidccofo-processing' === $order->get_status() ) {
				$order->set_status( self::STATUS );
			}
		}
		if ( empty( $record['tracking_url'] ) ) {
			$url = self::find_value( $data, 'tracking_url' );
			if ( '' !== $url && wp_http_validate_url( $url ) ) {
				$record['tracking_url'] = esc_url_raw( $url );
				self::add_tracking( $order, $name, '' !== $record['id'] ? $record['id'] : $record['external_id'], $record['tracking_url'] );
			}
		}
		$record['last_event'] = substr( sanitize_text_field( $event ), 0, 120 );
		self::save( $order, $record );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Status-like values in an event (top level and one level down).
	 *
	 * @param array $data Event.
	 * @return string[]
	 */
	protected static function status_words( array $data ) {
		$words = array();
		foreach ( array( $data, (array) ( $data['data'] ?? array() ) ) as $level ) {
			foreach ( array( 'status', 'delivery_status', 'event_name', 'event', 'kind' ) as $key ) {
				if ( isset( $level[ $key ] ) && is_scalar( $level[ $key ] ) ) {
					$words[] = (string) $level[ $key ];
				}
			}
		}
		return $words;
	}

	/**
	 * A string value by key (top level or under "data").
	 *
	 * @param array  $data Event.
	 * @param string $key  Key.
	 * @return string
	 */
	protected static function find_value( array $data, $key ) {
		if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
			return $data[ $key ];
		}
		return isset( $data['data'][ $key ] ) && is_string( $data['data'][ $key ] ) ? $data['data'][ $key ] : '';
	}
}
