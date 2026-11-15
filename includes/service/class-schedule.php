<?php
/**
 * When an order is wanted: "As soon as possible" or a pickup / delivery
 * time in slots inside the opening hours, after the preparation time, with
 * an optional cap of orders per slot. Also refuses orders while ordering is
 * paused or the restaurant is closed (unless closed_mode is "later" and the
 * customer picks a later time).
 *
 * The choice is the checkout field sidrest_when: "asap" or "Y-m-d H:i" in
 * the site's time zone (readable on the order as it is). Orders keep it as
 * _sidrest_when (Unix time, 0 = as soon as possible) and _sidrest_ready_by.
 *
 * Only for carts of menu items, and only once opening hours are set.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Service;

use SidcraftCommerce\Cart\Cart;
use SidcraftCommerce\Data\Order;
use SidcraftRestaurant\Delivery\Delivery;
use SidcraftRestaurant\Delivery\Platforms;
use function SidcraftRestaurant\setting;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schedule
 */
class Schedule {

	const FIELD = 'sidrest_when';

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_filter( 'sidcraft_commerce_checkout_fields', array( $this, 'checkout_fields' ), 20 );
		add_filter( 'sidcraft_commerce_checkout_errors', array( $this, 'checkout_errors' ), 20, 2 );
		add_action( 'sidcraft_commerce_order_created', array( $this, 'order_created' ) );
	}

	/**
	 * Does scheduling apply to this cart?
	 *
	 * @param Cart|null $cart Cart.
	 * @return bool
	 */
	public static function applies( $cart = null ) {
		$cart = $cart ? $cart : Cart::instance();
		return Hours::configured() && ! $cart->is_empty() && Delivery::is_restaurant_cart( $cart );
	}

	/**
	 * Preparation time in minutes.
	 *
	 * @return int
	 */
	public static function prep_minutes() {
		return absint( Platforms::get( 'prep_minutes' ) );
	}

	/**
	 * Orders already booked per slot time (Unix), from now on.
	 *
	 * @return array<int,int>
	 */
	public static function booked() {
		static $memo = null;
		if ( null !== $memo ) {
			return $memo;
		}
		$ids  = get_posts(
			array(
				'post_type'      => \SidcraftCommerce\Data\Post_Types::ORDER,
				'post_status'    => array_diff( array_keys( \SidcraftCommerce\Data\Post_Types::order_statuses() ), array( 'sidccofo-cancelled', 'sidccofo-refunded' ) ),
				'posts_per_page' => 1000,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Upcoming slots only.
				'meta_query'     => array(
					array(
						'key'     => '_sidrest_when',
						'value'   => time() - HOUR_IN_SECONDS,
						'compare' => '>=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		$memo = array();
		if ( $ids ) {
			update_meta_cache( 'post', $ids );
			foreach ( $ids as $id ) {
				$when          = (int) get_post_meta( $id, '_sidrest_when', true );
				$memo[ $when ] = ( $memo[ $when ] ?? 0 ) + 1;
			}
		}
		return $memo;
	}

	/**
	 * Times that can still be chosen: "Y-m-d H:i" => [ Unix time, label ].
	 *
	 * @param int|null $now  Unix time.
	 * @param bool     $full Leave out slots that are fully booked (one more query).
	 * @return array
	 */
	public static function slots( $now = null, $full = true ) {
		$now  = null === $now ? time() : (int) $now;
		$step = max( 5, min( 120, (int) setting( 'slot_minutes', 15 ) ) );
		$cap  = $full ? absint( setting( 'slot_capacity', 0 ) ) : 0;
		$days = max( 0, min( 14, (int) setting( 'order_days', 0 ) ) );
		$tz   = wp_timezone();
		$last = ( new \DateTimeImmutable( '@' . $now ) )->setTimezone( $tz )->setTime( 23, 59, 59 )->modify( '+' . $days . ' day' )->getTimestamp();
		$from = $now + self::prep_minutes() * MINUTE_IN_SECONDS;
		$taken = $cap ? self::booked() : array();
		$out  = array();
		foreach ( Hours::intervals( $now, $days + 1 ) as $range ) {
			if ( $range[1] <= $from || $range[0] > $last ) {
				continue;
			}
			// First slot: on the step grid, counted from the opening time.
			$t = $range[0];
			if ( $t < $from ) {
				$t += (int) ceil( ( $from - $t ) / ( $step * 60 ) ) * $step * 60;
			}
			for ( ; $t < $range[1] && $t <= $last; $t += $step * 60 ) {
				if ( $cap && ( $taken[ $t ] ?? 0 ) >= $cap ) {
					continue;
				}
				$key         = wp_date( 'Y-m-d H:i', $t );
				$out[ $key ] = array( $t, ucfirst( Hours::day_time( $t ) ) );
			}
		}
		/**
		 * Filter the times customers can choose.
		 *
		 * @param array $out "Y-m-d H:i" => [ Unix time, label ].
		 * @param int   $now Unix time.
		 */
		return (array) apply_filters( 'sidrest_order_slots', $out, $now );
	}

	/**
	 * Can "as soon as possible" be chosen right now?
	 *
	 * @return bool
	 */
	public static function asap_available() {
		if ( ! Hours::taking_orders_now() || ! setting( 'asap', 1 ) ) {
			return false;
		}
		// Not when it would be ready after closing time.
		$range = Hours::interval_at( time() );
		return ! $range || time() + self::prep_minutes() * MINUTE_IN_SECONDS <= $range[1];
	}

	/**
	 * Choices for the checkout field.
	 *
	 * @return array value => label.
	 */
	public static function choices() {
		$out = array();
		if ( self::asap_available() ) {
			$prep         = self::prep_minutes();
			$out['asap']  = $prep
				/* translators: %d: minutes. */
				? sprintf( __( 'As soon as possible (about %d min)', 'sidcraft-restaurant-for-sidcraft-commerce' ), $prep )
				: __( 'As soon as possible', 'sidcraft-restaurant-for-sidcraft-commerce' );
		}
		$later = setting( 'order_ahead', 1 ) && ! setting( 'ordering_paused', 0 ) && ( Hours::taking_orders_now() || 'later' === setting( 'closed_mode', 'later' ) );
		if ( $later ) {
			foreach ( self::slots() as $key => $slot ) {
				$out[ $key ] = $slot[1];
			}
		}
		return $out;
	}

	/**
	 * The "When" field after "Pickup or delivery".
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function checkout_fields( $fields ) {
		$label = __( 'When would you like it?', 'sidcraft-restaurant-for-sidcraft-commerce' );
		if ( ! Delivery::shopper_context() ) {
			// Orders, emails and the admin: show the saved value ("asap" or the time).
			$fields[ self::FIELD ] = array( $label, 'select', false, 'off', 'full', array( 'asap' => __( 'As soon as possible', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) );
			return $fields;
		}
		if ( ! self::applies() ) {
			return $fields;
		}
		$choices = self::choices();
		if ( ! $choices ) {
			return $fields; // The checkout error explains (closed, paused or fully booked).
		}
		$field = array( self::FIELD => array( $label, 'select', true, 'off', 'full', $choices ) );
		// Right after "Pickup or delivery" when that is there, else first.
		if ( isset( $fields[ Delivery::FIELD ] ) ) {
			$pos = array_search( Delivery::FIELD, array_keys( $fields ), true ) + 1;
			return array_slice( $fields, 0, $pos, true ) + $field + array_slice( $fields, $pos, null, true );
		}
		return $field + $fields;
	}

	/**
	 * Refuse orders that can't be made at the chosen time.
	 *
	 * @param array $errors  field => message.
	 * @param array $billing Checkout fields.
	 * @return array
	 */
	public function checkout_errors( $errors, $billing ) {
		if ( ! self::applies() ) {
			return $errors;
		}
		if ( setting( 'ordering_paused', 0 ) ) {
			$errors[ self::FIELD ] = __( 'We’re not taking online orders right now. Please try again later.', 'sidcraft-restaurant-for-sidcraft-commerce' );
			return $errors;
		}
		$choices = self::choices();
		if ( ! $choices ) {
			$next = Hours::next_open();
			$errors[ self::FIELD ] = $next
				/* translators: %s: "today at 5:00 pm". */
				? sprintf( __( 'We’re closed right now and can’t take orders. We open %s.', 'sidcraft-restaurant-for-sidcraft-commerce' ), Hours::day_time( $next ) )
				: __( 'We’re closed and can’t take orders right now.', 'sidcraft-restaurant-for-sidcraft-commerce' );
			return $errors;
		}
		$when = (string) ( $billing[ self::FIELD ] ?? '' );
		if ( ! isset( $choices[ $when ] ) ) {
			$errors[ self::FIELD ] = __( 'This time is no longer available. Choose another time.', 'sidcraft-restaurant-for-sidcraft-commerce' );
		}
		return $errors;
	}

	/**
	 * Keep the time on the order as a number (kitchen screen, couriers, slot caps).
	 *
	 * @param Order $order Order.
	 */
	public function order_created( $order ) {
		$billing = $order->get_billing();
		$when    = (string) ( $billing[ self::FIELD ] ?? '' );
		if ( '' === $when ) {
			return;
		}
		$time = 0;
		if ( 'asap' !== $when ) {
			$date = date_create_immutable( $when, wp_timezone() );
			$time = $date ? $date->getTimestamp() : 0;
		}
		update_post_meta( $order->get_id(), '_sidrest_when', $time );
		update_post_meta( $order->get_id(), '_sidrest_ready_by', $time ? $time : time() + self::prep_minutes() * MINUTE_IN_SECONDS );
		/* translators: %s: time. */
		$order->add_note( $time ? sprintf( __( 'Wanted %s.', 'sidcraft-restaurant-for-sidcraft-commerce' ), Hours::day_time( $time ) ) : __( 'Wanted as soon as possible.', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
	}

	/**
	 * When an order should be ready (Unix time; 0 when unknown).
	 *
	 * @param Order $order Order.
	 * @return int
	 */
	public static function ready_by( Order $order ) {
		return (int) get_post_meta( $order->get_id(), '_sidrest_ready_by', true );
	}
}
