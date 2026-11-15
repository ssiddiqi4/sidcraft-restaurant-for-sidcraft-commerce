<?php
/**
 * Kitchen screen data: the restaurant's live orders and their stage.
 *
 * Restaurant orders (every line a menu item) get _sidrest_kitchen = 1 when
 * placed. They show on the kitchen screen once paid, or straight away when
 * paid later (pay at the table, cash): stage _sidrest_stage new → preparing
 * → ready → done. "Ready" can email the customer; "Done" completes pickup
 * and dine-in orders (delivery orders follow their courier).
 *
 *   REST (sidcraft-restaurant/v1/kitchen/…, staff only)
 *     GET  orders                  live orders
 *     POST orders/(id)             stage=new|preparing|ready|done
 *     GET  items                   orderable items and whether sold out today
 *     POST items/(id)              sold_out=0|1
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Service;

use SidcraftCommerce\Data\Order;
use SidcraftCommerce\Data\Post_Types as Commerce_Types;
use SidcraftRestaurant\Delivery\Delivery;
use SidcraftRestaurant\Delivery\Platforms;
use SidcraftRestaurant\Item;
use SidcraftRestaurant\Ordering;
use SidcraftRestaurant\Post_Types;
use function SidcraftRestaurant\manage_cap;
use function SidcraftRestaurant\setting;

defined( 'ABSPATH' ) || exit;

/**
 * Class Kitchen
 */
class Kitchen {

	const STAGES = array( 'new', 'preparing', 'ready', 'done' );

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'sidcraft_commerce_order_created', array( $this, 'order_created' ), 5 );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'transition_post_status', array( $this, 'status_changed' ), 10, 3 );
	}

	/** Option bumped whenever the board would look different. */
	const REV = 'sidrest_kitchen_rev';

	/**
	 * The board changed: the next poll gets the orders again.
	 */
	public static function bump() {
		update_option( self::REV, (string) wp_rand( 1, PHP_INT_MAX ), false );
	}

	/**
	 * A restaurant order was paid, cancelled, completed…
	 *
	 * @param string   $new  New status.
	 * @param string   $old  Old status.
	 * @param \WP_Post $post Post.
	 */
	public function status_changed( $new, $old, $post ) {
		if ( $new !== $old && Commerce_Types::ORDER === $post->post_type && get_post_meta( $post->ID, '_sidrest_kitchen', true ) ) {
			self::bump();
		}
	}

	/**
	 * Staff who may use the kitchen screen.
	 *
	 * @return string
	 */
	public static function cap() {
		/**
		 * Filter the capability for the kitchen screen (e.g. edit_posts for kitchen staff accounts).
		 *
		 * @param string $cap Capability.
		 */
		return (string) apply_filters( 'sidrest_kitchen_capability', manage_cap() );
	}

	/**
	 * Mark restaurant orders.
	 *
	 * @param Order $order Order.
	 */
	public function order_created( $order ) {
		foreach ( $order->get_items() as $item ) {
			if ( ! get_post_meta( (int) $item['product_id'], Ordering::LINK, true ) ) {
				return;
			}
		}
		update_post_meta( $order->get_id(), '_sidrest_kitchen', 1 );
		update_post_meta( $order->get_id(), '_sidrest_stage', 'new' );
		self::bump();
	}

	/**
	 * Routes.
	 */
	public function routes() {
		$staff = static function () {
			return current_user_can( self::cap() );
		};
		$id    = array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		);
		register_rest_route(
			Delivery::NS,
			'/kitchen/orders',
			array(
				'methods'             => 'GET',
				'permission_callback' => $staff,
				'callback'            => array( $this, 'rest_orders' ),
			)
		);
		register_rest_route(
			Delivery::NS,
			'/kitchen/orders/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'permission_callback' => $staff,
				'callback'            => array( $this, 'rest_stage' ),
				'args'                => $id,
			)
		);
		register_rest_route(
			Delivery::NS,
			'/kitchen/items',
			array(
				'methods'             => 'GET',
				'permission_callback' => $staff,
				'callback'            => array( $this, 'rest_items' ),
			)
		);
		register_rest_route(
			Delivery::NS,
			'/kitchen/items/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'permission_callback' => $staff,
				'callback'            => array( $this, 'rest_sold_out' ),
				'args'                => $id,
			)
		);
	}

	/**
	 * Statuses whose orders the kitchen makes: paid, or to be paid on the spot.
	 *
	 * @return string[]
	 */
	public static function statuses() {
		return array_values( array_unique( array_merge( Commerce_Types::paid_statuses(), array( 'sidccofo-on-hold' ) ) ) );
	}

	/**
	 * Live orders (not done), oldest first.
	 *
	 * @return Order[]
	 */
	public static function live_orders() {
		$ids = get_posts(
			array(
				'post_type'      => Commerce_Types::ORDER,
				'post_status'    => self::statuses(),
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'date_query'     => array( array( 'after' => gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS ), 'column' => 'post_date_gmt' ) ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Recent restaurant orders only.
				'meta_query'     => array(
					array(
						'key'   => '_sidrest_kitchen',
						'value' => '1',
					),
					array(
						'key'     => '_sidrest_stage',
						'value'   => 'done',
						'compare' => '!=',
					),
				),
			)
		);
		_prime_post_caches( $ids, false, true );
		$orders = array_values( array_filter( array_map( array( Order::class, 'get' ), $ids ) ) );
		self::prime_items( $orders );
		return $orders;
	}

	/**
	 * Load the products and menu items of these orders in a few queries.
	 *
	 * @param Order[] $orders Orders.
	 */
	protected static function prime_items( array $orders ) {
		$products = array();
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $line ) {
				$products[] = (int) $line['product_id'];
			}
		}
		$products = array_values( array_unique( array_filter( $products ) ) );
		if ( ! $products ) {
			return;
		}
		_prime_post_caches( $products, false, true );
		$items = array();
		foreach ( $products as $product_id ) {
			$items[] = (int) get_post_meta( $product_id, Ordering::LINK, true );
		}
		$items = array_values( array_unique( array_filter( $items ) ) );
		if ( $items ) {
			_prime_post_caches( $items, false, true );
		}
	}

	/**
	 * How an order reaches the customer: [ kind (pickup|delivery|table), label ].
	 *
	 * @param Order $order Order.
	 * @return array
	 */
	public static function fulfilment( Order $order ) {
		$table = (string) get_post_meta( $order->get_id(), '_sidrest_table', true );
		if ( '' !== $table ) {
			/* translators: %s: table name. */
			return array( 'table', sprintf( __( 'Table %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $table ) );
		}
		$platform = Delivery::order_platform( $order );
		if ( '' !== $platform ) {
			/* translators: %s: delivery platform. */
			return array( 'delivery', sprintf( __( 'Delivery by %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), Platforms::name( $platform ) ) );
		}
		return array( 'pickup', __( 'Pickup', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
	}

	/**
	 * One order for the screen.
	 *
	 * @param Order $order Order.
	 * @return array
	 */
	public static function payload( Order $order ) {
		$billing = $order->get_billing();
		$items   = array();
		foreach ( $order->get_items() as $line ) {
			$item    = Ordering::item_for( (int) $line['product_id'] );
			$options = array();
			foreach ( (array) ( $line['options'] ?? array() ) as $row ) {
				$options[] = trim( (string) ( $row['label'] ?? '' ) . ': ' . (string) ( $row['value'] ?? '' ), ': ' );
			}
			$items[] = array(
				'name'      => (string) $line['name'],
				'qty'       => (int) $line['quantity'],
				'options'   => $options,
				'allergens' => $item ? array_values( Allergens::names( Allergens::of( $item->get_id() ) ) ) : array(),
			);
		}
		$when  = (int) get_post_meta( $order->get_id(), '_sidrest_when', true );
		$ready = Schedule::ready_by( $order );
		$ful   = self::fulfilment( $order );
		$tip   = (float) get_post_meta( $order->get_id(), '_sidrest_tip', true );
		return array(
			'id'       => $order->get_id(),
			'number'   => '#' . $order->get_id(),
			'placed'   => (int) get_post_time( 'U', true, $order->get_id() ),
			'stage'    => self::stage( $order ),
			'paid'     => $order->is_paid(),
			'payment'  => $order->get_payment_method_title(),
			'kind'     => $ful[0],
			'where'    => $ful[1],
			'when'     => $when ? Hours::day_time( $when ) : __( 'As soon as possible', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'readyBy'  => $ready,
			'later'    => $when > time() + HOUR_IN_SECONDS,
			'customer' => trim( (string) ( $billing['first_name'] ?? '' ) . ' ' . (string) ( $billing['last_name'] ?? '' ) ),
			'phone'    => (string) ( $billing['phone'] ?? '' ),
			'notes'    => (string) get_post_meta( $order->get_id(), '_sidccofo_notes', true ),
			'items'    => $items,
			'total'    => Delivery::plain_in( $order->get_total(), $order->get_currency() ),
			'tip'      => $tip > 0 ? Delivery::plain_in( $tip, $order->get_currency() ) : '',
			'edit'     => get_edit_post_link( $order->get_id(), 'raw' ),
		);
	}

	/**
	 * An order's stage.
	 *
	 * @param Order $order Order.
	 * @return string
	 */
	public static function stage( Order $order ) {
		$stage = (string) get_post_meta( $order->get_id(), '_sidrest_stage', true );
		return in_array( $stage, self::STAGES, true ) ? $stage : 'new';
	}

	/**
	 * Move an order to a stage.
	 *
	 * @param Order  $order Order.
	 * @param string $stage Stage.
	 */
	public static function set_stage( Order $order, $stage ) {
		$old = self::stage( $order );
		if ( $old === $stage || ! in_array( $stage, self::STAGES, true ) ) {
			return;
		}
		update_post_meta( $order->get_id(), '_sidrest_stage', $stage );
		update_post_meta( $order->get_id(), '_sidrest_stage_' . $stage, time() );
		$labels = array(
			'new'       => __( 'New', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'preparing' => __( 'Preparing', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'ready'     => __( 'Ready', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'done'      => __( 'Done', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		);
		/* translators: %s: stage. */
		$order->add_note( sprintf( __( 'Kitchen: %s.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $labels[ $stage ] ) );

		$kind = self::fulfilment( $order )[0];
		if ( 'ready' === $stage && setting( 'ready_email', 1 ) && 'delivery' !== $kind && ! get_post_meta( $order->get_id(), '_sidrest_ready_sent', true ) ) {
			update_post_meta( $order->get_id(), '_sidrest_ready_sent', 1 );
			$message = 'table' === $kind
				? __( 'Your order is ready and on its way to your table.', 'sidcraft-restaurant-for-sidcraft-commerce' )
				: __( 'Your order is ready. You can pick it up now.', 'sidcraft-restaurant-for-sidcraft-commerce' );
			\SidcraftCommerce\Plugin::instance()->emails->send_custom( $order, $order->get_email(), __( 'Your order is ready', 'sidcraft-restaurant-for-sidcraft-commerce' ), $message );
		}
		if ( 'done' === $stage && 'delivery' !== $kind && $order->is_paid() && 'sidccofo-completed' !== $order->get_status() ) {
			$order->set_status( 'sidccofo-completed', __( 'Handed over.', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
		}

		/**
		 * Fires when an order moves to another kitchen stage.
		 *
		 * @param Order  $order Order.
		 * @param string $stage New stage.
		 * @param string $old   Old stage.
		 */
		do_action( 'sidrest_kitchen_stage', $order, $stage, $old );
		self::bump();
	}

	/**
	 * GET orders (?rev=… answers "unchanged" when nothing moved).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_orders( \WP_REST_Request $request ) {
		$rev = (string) get_option( self::REV, '' );
		if ( '' === $rev ) {
			self::bump();
			$rev = (string) get_option( self::REV, '' );
		}
		$data = array(
			'rev'    => $rev,
			'now'    => time(),
			'open'   => Hours::taking_orders_now(),
			'paused' => (bool) setting( 'ordering_paused', 0 ),
		);
		// Nothing changed since the screen's last copy: no order queries.
		if ( $rev === (string) $request->get_param( 'rev' ) ) {
			$data['unchanged'] = true;
		} else {
			$data['orders'] = array_map( array( __CLASS__, 'payload' ), self::live_orders() );
		}
		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * POST orders/(id): change the stage.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_stage( \WP_REST_Request $request ) {
		$order = Order::get( (int) $request['id'] );
		$stage = sanitize_key( (string) $request->get_param( 'stage' ) );
		if ( ! $order || ! get_post_meta( $order->get_id(), '_sidrest_kitchen', true ) || ! in_array( $stage, self::STAGES, true ) ) {
			return new \WP_Error( 'sidrest_kitchen', __( 'This order can’t be changed here.', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 'status' => 400 ) );
		}
		self::set_stage( $order, $stage );
		return rest_ensure_response( self::payload( Order::get( $order->get_id() ) ) );
	}

	/**
	 * GET items: orderable items for the "Sold out today" list.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_items() {
		$ids = get_posts(
			array(
				'post_type'      => Post_Types::ITEM,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'title',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Admin screen.
				'meta_key'       => '_sidrest_orderable',
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		_prime_post_caches( $ids, false, true );
		$out = array();
		foreach ( $ids as $id ) {
			$out[] = array(
				'id'      => (int) $id,
				'name'    => get_the_title( $id ),
				'soldOut' => Availability::sold_out( $id ),
			);
		}
		return rest_ensure_response( array( 'items' => $out ) );
	}

	/**
	 * POST items/(id): sold out today, or back on sale.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_sold_out( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( Post_Types::ITEM !== get_post_type( $id ) ) {
			return new \WP_Error( 'sidrest_item', __( 'Item not found.', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 'status' => 404 ) );
		}
		Availability::set_sold_out( $id, (bool) $request->get_param( 'sold_out' ) );
		return rest_ensure_response(
			array(
				'id'      => $id,
				'soldOut' => Availability::sold_out( $id ),
			)
		);
	}
}
