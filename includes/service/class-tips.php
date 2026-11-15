<?php
/**
 * Tips at checkout: buttons for a few percentages, another amount, or none.
 * The tip is added to the order total (after tax, which it isn't part of)
 * and shown as its own line in the cart, checkout, order and emails.
 *
 * Checkout fields: sidrest_tip ("none", "p15", "custom") and
 * sidrest_tip_amount (the custom amount). The choice is kept in the cart
 * session so the totals show it; orders keep the amount as _sidrest_tip.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Service;

use SidcraftCommerce\Cart\Cart;
use SidcraftCommerce\Data\Order;
use SidcraftRestaurant\Delivery\Delivery;
use function SidcraftRestaurant\setting;

defined( 'ABSPATH' ) || exit;

/**
 * Class Tips
 */
class Tips {

	const FIELD = 'sidrest_tip';

	const AMOUNT = 'sidrest_tip_amount';

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_filter( 'sidcraft_commerce_checkout_fields', array( $this, 'checkout_fields' ), 30 );
		add_filter( 'sidcraft_commerce_cart_total', array( $this, 'add_to_total' ), 20, 2 );
		add_filter( 'sidcraft_commerce_totals_rows', array( $this, 'totals_row' ), 20, 2 );
		add_filter( 'sidcraft_commerce_checkout_errors', array( $this, 'checkout_errors' ), 40, 2 );
		add_action( 'sidcraft_commerce_order_created', array( $this, 'order_created' ) );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * REST: POST tip {tip, amount} from the checkout script.
	 */
	public function routes() {
		register_rest_route(
			Delivery::NS,
			'/tip',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Only this visitor's session.
				'callback'            => array( $this, 'rest_tip' ),
			)
		);
	}

	/**
	 * Save the tip and return fresh checkout fragments.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_tip( \WP_REST_Request $request ) {
		$cart = Cart::instance();
		self::save( $cart, (string) $request->get_param( 'tip' ), (float) str_replace( ',', '.', (string) $request->get_param( 'amount' ) ) );
		Delivery::refresh( $cart );
		$response = rest_ensure_response(
			array(
				'tip'       => self::amount( $cart ),
				'fragments' => \SidcraftCommerce\Fragments::collect( 'checkout' ),
				'total'     => $cart->get_total(),
			)
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Do tips apply to this cart?
	 *
	 * @param Cart|null $cart Cart.
	 * @return bool
	 */
	public static function applies( $cart = null ) {
		if ( ! setting( 'tips', 0 ) ) {
			return false;
		}
		$cart = $cart ? $cart : Cart::instance();
		if ( $cart->is_empty() || ! Delivery::is_restaurant_cart( $cart ) ) {
			return false;
		}
		if ( 'dinein' === setting( 'tip_for', 'all' ) ) {
			return 'table' === Delivery::current_choice( Delivery::choices( $cart ) );
		}
		return true;
	}

	/**
	 * Percentages offered.
	 *
	 * @return int[]
	 */
	public static function presets() {
		$out = array();
		foreach ( explode( ',', (string) setting( 'tip_presets', '10, 15, 20' ) ) as $p ) {
			$p = (int) trim( $p );
			if ( $p > 0 && $p <= 100 ) {
				$out[] = $p;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * What the tip is worked out on: goods after discounts.
	 *
	 * @param Cart $cart Cart.
	 * @return float
	 */
	public static function base( Cart $cart ) {
		return max( 0.0, (float) $cart->get_goods_subtotal() - (float) $cart->get_discount() );
	}

	/**
	 * The choice saved for this visitor: [ choice, custom amount ].
	 *
	 * @param Cart $cart Cart.
	 * @return array
	 */
	public static function saved( Cart $cart ) {
		$saved = (array) $cart->session()->get( 'sidrest_tip', array() );
		return array( (string) ( $saved[0] ?? 'none' ), (float) ( $saved[1] ?? 0 ) );
	}

	/**
	 * Save a choice.
	 *
	 * @param Cart   $cart   Cart.
	 * @param string $choice none|pN|custom.
	 * @param float  $amount Custom amount.
	 */
	public static function save( Cart $cart, $choice, $amount ) {
		$choice = self::clean_choice( $choice );
		$amount = max( 0.0, round( (float) $amount, 2 ) );
		$cart->session()->set( 'sidrest_tip', array( $choice, $amount ) );
		Delivery::prefill(
			$cart,
			array(
				self::FIELD  => $choice,
				self::AMOUNT => 'custom' === $choice ? (string) $amount : '',
			)
		);
	}

	/**
	 * A valid choice or "none".
	 *
	 * @param string $choice Choice.
	 * @return string
	 */
	public static function clean_choice( $choice ) {
		$choice = sanitize_key( (string) $choice );
		if ( 'custom' === $choice && setting( 'tip_custom', 1 ) ) {
			return 'custom';
		}
		if ( preg_match( '/^p(\d+)$/', $choice, $m ) && in_array( (int) $m[1], self::presets(), true ) ) {
			return $choice;
		}
		return 'none';
	}

	/**
	 * The tip for this cart, in the shopper's currency.
	 *
	 * @param Cart        $cart   Cart.
	 * @param string|null $choice Choice (default: saved).
	 * @param float|null  $amount Custom amount (default: saved).
	 * @return float
	 */
	public static function amount( Cart $cart, $choice = null, $amount = null ) {
		if ( ! self::applies( $cart ) ) {
			return 0.0;
		}
		list( $saved, $saved_amount ) = self::saved( $cart );
		$choice = self::clean_choice( null === $choice ? $saved : $choice );
		$amount = null === $amount ? $saved_amount : (float) $amount;
		if ( 'custom' === $choice ) {
			return \SidcraftCommerce\round_amount( max( 0.0, $amount ) );
		}
		if ( preg_match( '/^p(\d+)$/', $choice, $m ) ) {
			return \SidcraftCommerce\round_amount( self::base( $cart ) * (int) $m[1] / 100 );
		}
		return 0.0;
	}

	/**
	 * The two checkout fields.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	public function checkout_fields( $fields ) {
		$label = __( 'Add a tip', 'sidcraft-restaurant-for-sidcraft-commerce' );
		if ( ( is_admin() && ! wp_doing_ajax() ) || wp_doing_cron() || ! did_action( 'init' ) ) {
			return $fields; // Orders show the tip as a totals line instead.
		}
		$cart = Cart::instance();
		if ( ! self::applies( $cart ) ) {
			return $fields;
		}
		$base    = self::base( $cart );
		$choices = array( 'none' => __( 'No tip', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
		foreach ( self::presets() as $p ) {
			$choices[ 'p' . $p ] = sprintf( '%1$d%% (%2$s)', $p, Delivery::plain_price( \SidcraftCommerce\round_amount( $base * $p / 100 ) ) );
		}
		if ( setting( 'tip_custom', 1 ) ) {
			$choices['custom'] = __( 'Another amount', 'sidcraft-restaurant-for-sidcraft-commerce' );
		}
		$fields[ self::FIELD ] = array( $label, 'select', false, 'off', 'half', $choices );
		if ( setting( 'tip_custom', 1 ) ) {
			/* translators: %s: currency symbol. */
			$fields[ self::AMOUNT ] = array( sprintf( __( 'Tip amount (%s)', 'sidcraft-restaurant-for-sidcraft-commerce' ), trim( \SidcraftCommerce\currency_symbol() ) ), 'number', false, 'off', 'half' );
		}
		return $fields;
	}

	/**
	 * Add the tip to the cart total.
	 *
	 * @param float $total Total.
	 * @param Cart  $cart  Cart.
	 * @return float
	 */
	public function add_to_total( $total, $cart ) {
		return $cart instanceof Cart ? (float) $total + self::amount( $cart ) : $total;
	}

	/**
	 * A "Tip" line before the total.
	 *
	 * @param array[]    $rows   Totals rows.
	 * @param Cart|Order $source Cart or order.
	 * @return array[]
	 */
	public function totals_row( $rows, $source ) {
		if ( $source instanceof Cart ) {
			$tip = self::amount( $source );
		} elseif ( $source instanceof Order ) {
			$tip = (float) get_post_meta( $source->get_id(), '_sidrest_tip', true );
		} else {
			return $rows;
		}
		if ( $tip <= 0 ) {
			return $rows;
		}
		$row = array(
			'key'   => 'tip',
			'label' => __( 'Tip', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'value' => $source instanceof Order ? \SidcraftCommerce\format_in( $tip, $source->get_currency() ) : \SidcraftCommerce\format_price( $tip ),
			'class' => 'sidrest-totals__tip',
		);
		$out = array();
		$put = false;
		foreach ( (array) $rows as $r ) {
			if ( ! $put && 'total' === ( $r['key'] ?? '' ) ) {
				$out[] = $row;
				$put   = true;
			}
			$out[] = $r;
		}
		if ( ! $put ) {
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * The total shown must include the tip that is posted.
	 *
	 * @param array $errors  field => message.
	 * @param array $billing Checkout fields.
	 * @return array
	 */
	public function checkout_errors( $errors, $billing ) {
		$cart = Cart::instance();
		if ( ! self::applies( $cart ) ) {
			return $errors;
		}
		$choice = self::clean_choice( (string) ( $billing[ self::FIELD ] ?? 'none' ) );
		$amount = (float) str_replace( ',', '.', (string) ( $billing[ self::AMOUNT ] ?? '0' ) );
		if ( 'custom' === $choice && $amount > 10 * max( 1.0, self::base( $cart ) ) ) {
			$errors[ self::AMOUNT ] = __( 'Please check the tip amount.', 'sidcraft-restaurant-for-sidcraft-commerce' );
			return $errors;
		}
		if ( abs( self::amount( $cart, $choice, $amount ) - self::amount( $cart ) ) > 0.004 ) {
			self::save( $cart, $choice, $amount );
			$errors[ self::FIELD ] = __( 'Your total was updated with this tip. Check it, then place the order again.', 'sidcraft-restaurant-for-sidcraft-commerce' );
		}
		return $errors;
	}

	/**
	 * Keep the tip on the order (its total already includes it).
	 *
	 * @param Order $order Order.
	 */
	public function order_created( $order ) {
		$tip = self::amount( Cart::instance() );
		if ( $tip > 0 ) {
			update_post_meta( $order->get_id(), '_sidrest_tip', (string) $tip );
		}
	}
}
