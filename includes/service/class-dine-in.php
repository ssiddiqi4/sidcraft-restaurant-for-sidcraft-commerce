<?php
/**
 * Ordering at the table. Each table has its own QR code (Restaurant → QR
 * codes): it opens the menu with ?sidrest_table=…&k=… (signed, so guests
 * can't type another table), and the menu's script remembers the table in
 * the cart session. At checkout "Dine-in at table 7" is then offered first,
 * with no delivery fee and no address needed, and the guest can pay now or
 * choose "Pay at the table" (the order goes to the kitchen unpaid).
 *
 * Settings: dine_in, tables (one name per line), pay_at_table.
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
 * Class Dine_In
 */
class Dine_In {

	const SESSION = 'sidrest_table';

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'sidrest_fulfilment_choices', array( $this, 'choice' ), 5, 2 );
		add_filter( 'sidcraft_commerce_checkout_fields', array( $this, 'no_address' ), 40 );
		add_filter( 'sidcraft_commerce_gateways', array( $this, 'gateway' ) );
		add_action( 'sidcraft_commerce_order_created', array( $this, 'order_created' ) );
	}

	/**
	 * Is dine-in on?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) setting( 'dine_in', 0 ) && (bool) self::tables();
	}

	/**
	 * Table names.
	 *
	 * @return string[]
	 */
	public static function tables() {
		$out = array();
		foreach ( preg_split( '/[\r\n]+/', (string) setting( 'tables', '' ) ) as $name ) {
			$name = trim( sanitize_text_field( $name ) );
			if ( '' !== $name ) {
				$out[] = $name;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Signature for a table link.
	 *
	 * @param string $table Table name.
	 * @return string
	 */
	public static function sign( $table ) {
		return substr( hash_hmac( 'sha256', 'table|' . $table, wp_salt( 'auth' ) ), 0, 10 );
	}

	/**
	 * The link a table's QR code opens.
	 *
	 * @param string $url   Menu or page URL.
	 * @param string $table Table name.
	 * @return string
	 */
	public static function link( $url, $table ) {
		return add_query_arg(
			array(
				'sidrest_table' => rawurlencode( $table ),
				'k'             => self::sign( $table ),
			),
			$url
		);
	}

	/**
	 * The table this visitor is ordering for ('' = none).
	 *
	 * @param Cart|null $cart Cart.
	 * @return string
	 */
	public static function current( $cart = null ) {
		if ( ! self::enabled() ) {
			return '';
		}
		$cart  = $cart ? $cart : Cart::instance();
		$saved = (array) $cart->session()->get( self::SESSION, array() );
		$name  = (string) ( $saved[0] ?? '' );
		// A table is remembered for 4 hours (the same meal).
		if ( '' === $name || (int) ( $saved[1] ?? 0 ) < time() - 4 * HOUR_IN_SECONDS || ! in_array( $name, self::tables(), true ) ) {
			return '';
		}
		return $name;
	}

	/**
	 * REST: POST table {table, k} from the menu script, DELETE to stop.
	 */
	public function routes() {
		register_rest_route(
			Delivery::NS,
			'/table',
			array(
				array(
					'methods'             => 'POST',
					'permission_callback' => '__return_true', // Only this visitor's session; the signature proves the table.
					'callback'            => array( $this, 'rest_set' ),
				),
				array(
					'methods'             => 'DELETE',
					'permission_callback' => '__return_true',
					'callback'            => array( $this, 'rest_clear' ),
				),
			)
		);
	}

	/**
	 * Remember the table.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_set( \WP_REST_Request $request ) {
		$table = sanitize_text_field( rawurldecode( (string) $request->get_param( 'table' ) ) );
		if ( ! self::enabled() || ! in_array( $table, self::tables(), true ) || ! hash_equals( self::sign( $table ), (string) $request->get_param( 'k' ) ) ) {
			return new \WP_Error( 'sidrest_table', __( 'This table code isn’t valid. Ask the staff for help.', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 'status' => 400 ) );
		}
		$cart = Cart::instance();
		$cart->session()->set( self::SESSION, array( $table, time() ) );
		$cart->session()->set( Delivery::FIELD, 'table' );
		Delivery::prefill( $cart, array( Delivery::FIELD => 'table' ) );
		/* translators: %s: table name. */
		return rest_ensure_response( array( 'message' => sprintf( __( 'You’re ordering for table %s.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $table ) ) );
	}

	/**
	 * Forget the table.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_clear() {
		Cart::instance()->session()->set( self::SESSION, array() );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * "Dine-in at table 7" first among pickup / delivery.
	 *
	 * @param array $choices key => [ label, fee, platform ].
	 * @param Cart  $cart    Cart.
	 * @return array
	 */
	public function choice( $choices, $cart ) {
		$table = self::current( $cart );
		if ( '' === $table ) {
			return $choices;
		}
		/* translators: %s: table name. */
		return array( 'table' => array( sprintf( __( 'Dine-in at table %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $table ), 0.0, '' ) ) + (array) $choices;
	}

	/**
	 * At the table (or picking up), the address isn't needed.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	public function no_address( $fields ) {
		if ( ( is_admin() && ! wp_doing_ajax() ) || ! did_action( 'init' ) || ! isset( $fields[ Delivery::FIELD ] ) ) {
			return $fields;
		}
		$choices = Delivery::choices();
		$current = Delivery::current_choice( $choices );
		if ( ! isset( $choices[ $current ] ) || '' !== $choices[ $current ][2] ) {
			return $fields; // Delivery: keep the address.
		}
		foreach ( array( 'address_1', 'city', 'postcode', 'state' ) as $key ) {
			if ( isset( $fields[ $key ] ) ) {
				$fields[ $key ][2] = false;
			}
		}
		return $fields;
	}

	/**
	 * Add "Pay at the table".
	 *
	 * @param array $gateways Gateways.
	 * @return array
	 */
	public function gateway( $gateways ) {
		require_once SIDREST_PATH . 'includes/service/class-pay-at-table.php';
		$gateways[] = new Pay_At_Table();
		return $gateways;
	}

	/**
	 * Keep the table on the order.
	 *
	 * @param Order $order Order.
	 */
	public function order_created( $order ) {
		$billing = $order->get_billing();
		$table   = self::current();
		if ( 'table' === (string) ( $billing[ Delivery::FIELD ] ?? '' ) && '' !== $table ) {
			update_post_meta( $order->get_id(), '_sidrest_table', $table );
			/* translators: %s: table name. */
			$order->add_note( sprintf( __( 'Dine-in at table %s.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $table ) );
		}
	}
}
