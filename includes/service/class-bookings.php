<?php
/**
 * Table reservations.
 *
 * Guests pick a date and party size, see the times with room left (inside
 * the opening hours, after the minimum notice, never more guests seated at
 * once than the capacity), and book with their name, email and phone. They
 * get a confirmation (or "request received" when the restaurant confirms
 * by hand), a reminder the day before, and a link to cancel. Staff see and
 * manage bookings under Restaurant → Reservations.
 *
 * Bookings are private posts (sidrest_booking) with meta _sidrest_b_*:
 * time (Unix), party, name, email, phone, notes, status
 * (pending|confirmed|seated|cancelled|no_show), token, reminded.
 *
 *   REST (sidcraft-restaurant/v1/bookings/…)
 *     GET  times?date=Y-m-d&party=N   free times
 *     POST (root)                     date, time, party, name, email, phone, notes, website (honeypot)
 *   Guest link: ?sidrest_booking=<token> (view, cancel).
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Service;

use SidcraftRestaurant\Delivery\Delivery;
use function SidcraftRestaurant\setting;

defined( 'ABSPATH' ) || exit;

/**
 * Class Bookings
 */
class Bookings {

	const TYPE = 'sidrest_booking';

	const STATUSES = array( 'pending', 'confirmed', 'seated', 'cancelled', 'no_show' );

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'template_redirect', array( $this, 'guest_link' ), 1 );
		add_action( 'sidcraft_commerce_cleanup', array( $this, 'reminders' ) );
		add_shortcode( 'sidrest_booking', array( $this, 'shortcode' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Is the reservations feature on?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) setting( 'bookings', 0 );
	}

	/**
	 * Status labels.
	 *
	 * @return array
	 */
	public static function status_labels() {
		return array(
			'pending'   => __( 'Waiting for confirmation', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'confirmed' => __( 'Confirmed', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'seated'    => __( 'Seated', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'cancelled' => __( 'Cancelled', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'no_show'   => __( 'No-show', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		);
	}

	/**
	 * Post type (staff only; listed under Restaurant).
	 */
	public function register() {
		register_post_type(
			self::TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Reservations', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'singular_name'      => __( 'Reservation', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'add_new'            => __( 'Add reservation', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'add_new_item'       => __( 'Add a reservation', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'edit_item'          => __( 'Edit reservation', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'not_found'          => __( 'No reservations.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'not_found_in_trash' => __( 'No reservations in the trash.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'search_items'       => __( 'Search reservations', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'menu_name'          => __( 'Reservations', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				),
				'public'          => false,
				'show_ui'         => self::enabled(),
				'show_in_menu'    => 'edit.php?post_type=' . \SidcraftRestaurant\Post_Types::MENU,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array_fill_keys( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'delete_posts', 'publish_posts', 'read_private_posts', 'create_posts' ), \SidcraftRestaurant\manage_cap() ),
				'map_meta_cap'    => false,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	/*
	------------------------------------------------------------------
	 * Data
	 * ----------------------------------------------------------------
	 */

	/**
	 * A booking as an array.
	 *
	 * @param int $id Booking.
	 * @return array|null
	 */
	public static function get( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || self::TYPE !== $post->post_type ) {
			return null;
		}
		$m = static function ( $key ) use ( $post ) {
			return get_post_meta( $post->ID, '_sidrest_b_' . $key, true );
		};
		return array(
			'id'     => (int) $post->ID,
			'time'   => (int) $m( 'time' ),
			'party'  => (int) $m( 'party' ),
			'name'   => (string) $m( 'name' ),
			'email'  => (string) $m( 'email' ),
			'phone'  => (string) $m( 'phone' ),
			'notes'  => (string) $m( 'notes' ),
			'status' => in_array( $m( 'status' ), self::STATUSES, true ) ? (string) $m( 'status' ) : 'pending',
			'token'  => (string) $m( 'token' ),
		);
	}

	/**
	 * Create or update a booking.
	 *
	 * @param array $data time, party, name, email, phone, notes, status.
	 * @param int   $id   Existing booking (0 = new).
	 * @return int Booking id (0 on failure).
	 */
	public static function save( array $data, $id = 0 ) {
		$title = sprintf( '%1$s · %2$d · %3$s', $data['name'], (int) $data['party'], wp_date( 'Y-m-d H:i', (int) $data['time'] ) );
		if ( $id ) {
			wp_update_post(
				array(
					'ID'         => (int) $id,
					'post_title' => $title,
				)
			);
		} else {
			$id = (int) wp_insert_post(
				array(
					'post_type'   => self::TYPE,
					'post_status' => 'private',
					'post_title'  => $title,
				)
			);
			if ( ! $id ) {
				return 0;
			}
			update_post_meta( $id, '_sidrest_b_token', wp_generate_password( 32, false ) );
		}
		foreach ( array( 'time', 'party', 'name', 'email', 'phone', 'notes', 'status' ) as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				update_post_meta( $id, '_sidrest_b_' . $key, $data[ $key ] );
			}
		}
		return $id;
	}

	/**
	 * Booking by its guest token.
	 *
	 * @param string $token Token.
	 * @return array|null
	 */
	public static function by_token( $token ) {
		if ( ! preg_match( '/^[A-Za-z0-9]{32}$/', (string) $token ) ) {
			return null;
		}
		$ids = get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One lookup per guest link.
				'meta_key'       => '_sidrest_b_token',
				'meta_value'     => $token, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return $ids ? self::get( (int) $ids[0] ) : null;
	}

	/**
	 * Active bookings (not cancelled or no-show) starting between two times.
	 *
	 * @param int $from Unix time.
	 * @param int $to   Unix time.
	 * @return array[]
	 */
	public static function between( $from, $to ) {
		$ids = get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1000,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded by date.
				'meta_query'     => array(
					array(
						'key'     => '_sidrest_b_time',
						'value'   => array( (int) $from, (int) $to ),
						'compare' => 'BETWEEN',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		update_meta_cache( 'post', $ids );
		$out = array();
		foreach ( $ids as $id ) {
			$b = self::get( (int) $id );
			if ( $b && ! in_array( $b['status'], array( 'cancelled', 'no_show' ), true ) ) {
				$out[] = $b;
			}
		}
		return $out;
	}

	/**
	 * Guests at the tables at some point of [ $time, $time + duration ).
	 *
	 * @param int      $time    Unix time.
	 * @param array[]  $around  Bookings near that time.
	 * @param int|null $exclude Booking to leave out (when moving it).
	 * @return int
	 */
	public static function guests_at( $time, array $around, $exclude = null ) {
		$dur  = self::duration();
		$seat = 0;
		foreach ( $around as $b ) {
			if ( (int) $b['id'] !== (int) $exclude && abs( (int) $b['time'] - (int) $time ) < $dur ) {
				$seat += (int) $b['party'];
			}
		}
		return $seat;
	}

	/**
	 * How long a table is kept (seconds).
	 *
	 * @return int
	 */
	public static function duration() {
		return max( 30, (int) apply_filters( 'sidrest_booking_duration', 90 ) ) * MINUTE_IN_SECONDS;
	}

	/**
	 * Free times on a day for a party: "H:i" => Unix time.
	 *
	 * @param string   $date    Y-m-d.
	 * @param int      $party   Guests.
	 * @param int|null $exclude Booking being moved.
	 * @return array
	 */
	public static function times( $date, $party, $exclude = null ) {
		$tz  = wp_timezone();
		$day = date_create_immutable( $date . ' 00:00', $tz );
		if ( ! $day || $party < 1 || $party > self::max_party() ) {
			return array();
		}
		$now   = time();
		$first = $now + max( 0, (int) setting( 'booking_notice', 2 ) ) * HOUR_IN_SECONDS;
		$last  = $now + max( 1, (int) setting( 'booking_days', 60 ) ) * DAY_IN_SECONDS;
		$step  = max( 15, min( 120, (int) setting( 'booking_slot', 30 ) ) ) * MINUTE_IN_SECONDS;
		$cap   = max( 1, (int) setting( 'booking_capacity', 20 ) );
		$start = $day->getTimestamp();
		$end   = $day->modify( '+1 day' )->getTimestamp();
		$near  = self::between( $start - self::duration(), $end + self::duration() );
		$out   = array();
		$ranges = Hours::configured() ? Hours::intervals( $start + HOUR_IN_SECONDS, 1 ) : array( array( $start + 12 * HOUR_IN_SECONDS, $start + 22 * HOUR_IN_SECONDS ) );
		foreach ( $ranges as $range ) {
			// Last seating an hour before closing.
			for ( $t = $range[0]; $t <= $range[1] - HOUR_IN_SECONDS; $t += $step ) {
				if ( $t < $start || $t >= $end || $t < $first || $t > $last ) {
					continue;
				}
				if ( self::guests_at( $t, $near, $exclude ) + $party <= $cap ) {
					$out[ wp_date( 'H:i', $t ) ] = $t;
				}
			}
		}
		return $out;
	}

	/**
	 * Largest party bookable online.
	 *
	 * @return int
	 */
	public static function max_party() {
		return max( 1, min( 50, (int) setting( 'booking_max_party', 8 ) ) );
	}

	/*
	------------------------------------------------------------------
	 * REST
	 * ----------------------------------------------------------------
	 */

	/**
	 * Routes.
	 */
	public function routes() {
		register_rest_route(
			Delivery::NS,
			'/bookings/times',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'rest_times' ),
			)
		);
		register_rest_route(
			Delivery::NS,
			'/bookings',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Public form: honeypot, rate limit and capacity checks below.
				'callback'            => array( $this, 'rest_book' ),
			)
		);
	}

	/**
	 * GET times.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_times( \WP_REST_Request $request ) {
		if ( ! self::enabled() ) {
			return new \WP_Error( 'sidrest_bookings_off', __( 'Online booking is closed.', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 'status' => 404 ) );
		}
		$date  = (string) $request->get_param( 'date' );
		$party = absint( $request->get_param( 'party' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new \WP_Error( 'sidrest_date', __( 'Choose a date.', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 'status' => 400 ) );
		}
		$times = array();
		foreach ( self::times( $date, $party ) as $hm => $t ) {
			$times[] = array(
				'value' => $hm,
				'label' => wp_date( (string) get_option( 'time_format', 'g:i a' ), $t ),
			);
		}
		$response = rest_ensure_response( array( 'times' => $times ) );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * POST a booking.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_book( \WP_REST_Request $request ) {
		if ( ! self::enabled() ) {
			return new \WP_Error( 'sidrest_bookings_off', __( 'Online booking is closed.', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 'status' => 404 ) );
		}
		if ( '' !== (string) $request->get_param( 'website' ) ) {
			return rest_ensure_response( array( 'message' => __( 'Thank you! We’ve received your request.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) );
		}
		if ( function_exists( '\SidcraftCommerce\rate_limit' ) && ! \SidcraftCommerce\rate_limit( 'sidrest_booking', 5, HOUR_IN_SECONDS ) ) {
			return new \WP_Error( 'sidrest_rate', __( 'Too many tries. Please call us to book.', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 'status' => 429 ) );
		}
		$date  = (string) $request->get_param( 'date' );
		$hm    = (string) $request->get_param( 'time' );
		$party = absint( $request->get_param( 'party' ) );
		$name  = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$email = sanitize_email( (string) $request->get_param( 'email' ) );
		$phone = sanitize_text_field( (string) $request->get_param( 'phone' ) );
		$notes = sanitize_textarea_field( (string) $request->get_param( 'notes' ) );
		$errors = array();
		if ( '' === $name ) {
			$errors['name'] = __( 'Enter your name.', 'sidcraft-restaurant-for-sidcraft-commerce' );
		}
		if ( ! is_email( $email ) ) {
			$errors['email'] = __( 'Enter a valid email address.', 'sidcraft-restaurant-for-sidcraft-commerce' );
		}
		if ( '' === $phone ) {
			$errors['phone'] = __( 'Enter a phone number.', 'sidcraft-restaurant-for-sidcraft-commerce' );
		}
		$times = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? self::times( $date, $party ) : array();
		if ( ! isset( $times[ $hm ] ) ) {
			$errors['time'] = __( 'This time is no longer available. Choose another.', 'sidcraft-restaurant-for-sidcraft-commerce' );
		}
		if ( $errors ) {
			return new \WP_Error(
				'sidrest_booking_invalid',
				reset( $errors ),
				array(
					'status' => 400,
					'fields' => $errors,
				)
			);
		}
		$status = 'manual' === setting( 'booking_confirm', 'auto' ) ? 'pending' : 'confirmed';
		$id     = self::save(
			array(
				'time'   => $times[ $hm ],
				'party'  => $party,
				'name'   => $name,
				'email'  => strtolower( $email ),
				'phone'  => $phone,
				'notes'  => mb_substr( $notes, 0, 500 ),
				'status' => $status,
			)
		);
		if ( ! $id ) {
			return new \WP_Error( 'sidrest_booking', __( 'Your booking could not be saved. Please call us.', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 'status' => 500 ) );
		}
		$booking = self::get( $id );
		self::email_guest( $booking, $status );
		self::email_restaurant( $booking );
		return rest_ensure_response(
			array(
				'message' => 'confirmed' === $status
					/* translators: 1: party size, 2: "today at 7:00 pm". */
					? sprintf( _n( 'You’re booked: a table for %1$d, %2$s. We’ve emailed you the details.', 'You’re booked: a table for %1$d, %2$s. We’ve emailed you the details.', $party, 'sidcraft-restaurant-for-sidcraft-commerce' ), $party, Hours::day_time( $booking['time'] ) )
					/* translators: 1: party size, 2: "today at 7:00 pm". */
					: sprintf( __( 'Thank you! We’ve received your request for %1$d, %2$s, and will email you to confirm.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $party, Hours::day_time( $booking['time'] ) ),
			)
		);
	}

	/*
	------------------------------------------------------------------
	 * Emails
	 * ----------------------------------------------------------------
	 */

	/**
	 * Guest link.
	 *
	 * @param array $booking Booking.
	 * @return string
	 */
	public static function link( array $booking ) {
		return add_query_arg( 'sidrest_booking', $booking['token'], home_url( '/' ) );
	}

	/**
	 * Restaurant name for emails.
	 *
	 * @return string
	 */
	protected static function store() {
		$name = (string) setting( 'restaurant_name', '' );
		return '' !== $name ? $name : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * Email the guest: confirmed, pending, cancelled or a reminder.
	 *
	 * @param array  $booking Booking.
	 * @param string $kind    confirmed|pending|cancelled|reminder|changed.
	 */
	public static function email_guest( array $booking, $kind ) {
		if ( ! is_email( $booking['email'] ) ) {
			return;
		}
		$when  = Hours::day_time( $booking['time'] );
		$store = self::store();
		switch ( $kind ) {
			case 'pending':
				/* translators: %s: restaurant. */
				$subject = sprintf( __( 'Your booking request at %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $store );
				/* translators: 1: party, 2: when. */
				$lead = sprintf( __( 'We’ve received your request for a table for %1$d, %2$s. We’ll email you again to confirm.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $booking['party'], $when );
				break;
			case 'cancelled':
				/* translators: %s: restaurant. */
				$subject = sprintf( __( 'Your booking at %s is cancelled', 'sidcraft-restaurant-for-sidcraft-commerce' ), $store );
				/* translators: 1: party, 2: when. */
				$lead = sprintf( __( 'Your booking for %1$d, %2$s, is cancelled.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $booking['party'], $when );
				break;
			case 'reminder':
				/* translators: %s: restaurant. */
				$subject = sprintf( __( 'See you soon at %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $store );
				/* translators: 1: party, 2: when. */
				$lead = sprintf( __( 'A reminder of your booking for %1$d, %2$s.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $booking['party'], $when );
				break;
			default:
				/* translators: %s: restaurant. */
				$subject = sprintf( __( 'Your booking at %s is confirmed', 'sidcraft-restaurant-for-sidcraft-commerce' ), $store );
				/* translators: 1: party, 2: when. */
				$lead = sprintf( __( 'Your table for %1$d is booked, %2$s.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $booking['party'], $when );
		}
		$body = $lead . "\n\n";
		if ( 'cancelled' !== $kind ) {
			$body .= __( 'Need to change or cancel? Use this link:', 'sidcraft-restaurant-for-sidcraft-commerce' ) . "\n" . self::link( $booking ) . "\n\n";
		}
		$body .= $store . "\n" . home_url( '/' );
		wp_mail( $booking['email'], $subject, $body, array( 'From: ' . str_replace( array( "\r", "\n" ), '', $store ) . ' <' . get_option( 'admin_email' ) . '>' ) );
	}

	/**
	 * Tell the restaurant about a new booking.
	 *
	 * @param array $booking Booking.
	 */
	public static function email_restaurant( array $booking ) {
		$to = function_exists( '\SidcraftCommerce\setting' ) ? (string) \SidcraftCommerce\setting( 'admin_email', get_option( 'admin_email' ) ) : (string) get_option( 'admin_email' );
		if ( ! is_email( $to ) ) {
			return;
		}
		/* translators: 1: party, 2: when, 3: name. */
		$subject = sprintf( __( 'New booking: %1$d guests, %2$s (%3$s)', 'sidcraft-restaurant-for-sidcraft-commerce' ), $booking['party'], Hours::day_time( $booking['time'] ), $booking['name'] );
		$body    = implode(
			"\n",
			array_filter(
				array(
					$booking['name'],
					$booking['email'],
					$booking['phone'],
					$booking['notes'],
					'',
					admin_url( 'post.php?action=edit&post=' . $booking['id'] ),
				),
				'is_string'
			)
		);
		wp_mail( $to, $subject, $body, is_email( $booking['email'] ) ? array( 'Reply-To: ' . $booking['email'] ) : array() );
	}

	/**
	 * Hourly: remind guests of tomorrow's bookings (once each).
	 */
	public function reminders() {
		if ( ! self::enabled() || ! setting( 'booking_reminder', 1 ) ) {
			return;
		}
		foreach ( self::between( time() + 2 * HOUR_IN_SECONDS, time() + DAY_IN_SECONDS ) as $b ) {
			if ( 'confirmed' !== $b['status'] || get_post_meta( $b['id'], '_sidrest_b_reminded', true ) ) {
				continue;
			}
			update_post_meta( $b['id'], '_sidrest_b_reminded', time() );
			self::email_guest( $b, 'reminder' );
		}
	}

	/*
	------------------------------------------------------------------
	 * Guest link and form
	 * ----------------------------------------------------------------
	 */

	/**
	 * ?sidrest_booking=token: see the booking; a button cancels it.
	 */
	public function guest_link() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The token is the guest's key.
		if ( ! isset( $_GET['sidrest_booking'] ) ) {
			return;
		}
		nocache_headers();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$booking = self::by_token( sanitize_text_field( wp_unslash( $_GET['sidrest_booking'] ) ) );
		$title   = self::store();
		if ( ! $booking ) {
			wp_die( esc_html__( 'This booking link is no longer valid.', 'sidcraft-restaurant-for-sidcraft-commerce' ), esc_html( $title ), array( 'response' => 404 ) );
		}
		$can_cancel = in_array( $booking['status'], array( 'pending', 'confirmed' ), true ) && $booking['time'] > time();
		if ( $can_cancel && isset( $_POST['sidrest_cancel'], $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'sidrest_cancel_' . $booking['token'] ) ) {
			self::save( array( 'status' => 'cancelled' ) + $booking, $booking['id'] );
			self::email_guest( $booking, 'cancelled' );
			self::email_restaurant_cancel( $booking );
			wp_die( esc_html__( 'Your booking is cancelled. We hope to see you another time.', 'sidcraft-restaurant-for-sidcraft-commerce' ), esc_html( $title ), array( 'response' => 200 ) );
		}
		$labels = self::status_labels();
		$html   = '<p>' . esc_html(
			sprintf(
				/* translators: 1: name, 2: party, 3: when, 4: status. */
				__( '%1$s, table for %2$d, %3$s — %4$s.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				$booking['name'],
				$booking['party'],
				Hours::day_time( $booking['time'] ),
				$labels[ $booking['status'] ]
			)
		) . '</p>';
		if ( $can_cancel ) {
			$html .= '<form method="post">' . wp_nonce_field( 'sidrest_cancel_' . $booking['token'], '_wpnonce', true, false ) . '<p><button type="submit" name="sidrest_cancel" value="1" class="button">' . esc_html__( 'Cancel this booking', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</button></p></form>';
			$html .= '<p>' . esc_html__( 'To change the time, cancel and book again, or call us.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		}
		wp_die( wp_kses( $html, array( 'p' => array(), 'form' => array( 'method' => true ), 'input' => array( 'type' => true, 'name' => true, 'value' => true, 'id' => true ), 'button' => array( 'type' => true, 'name' => true, 'value' => true, 'class' => true ) ) ), esc_html( $title ), array( 'response' => 200 ) );
	}

	/**
	 * Tell the restaurant a guest cancelled.
	 *
	 * @param array $booking Booking.
	 */
	protected static function email_restaurant_cancel( array $booking ) {
		$to = (string) get_option( 'admin_email' );
		/* translators: 1: name, 2: when. */
		wp_mail( $to, sprintf( __( 'Booking cancelled: %1$s, %2$s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $booking['name'], Hours::day_time( $booking['time'] ) ), admin_url( 'post.php?action=edit&post=' . $booking['id'] ) );
	}

	/**
	 * [sidrest_booking] — the booking form.
	 *
	 * @param array $atts title.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'title' => '' ), (array) $atts );
		return self::form_html( (string) $atts['title'] );
	}

	/**
	 * The form.
	 *
	 * @param string $title Heading.
	 * @return string
	 */
	public static function form_html( $title = '' ) {
		if ( ! self::enabled() ) {
			return current_user_can( \SidcraftRestaurant\manage_cap() ) ? '<p>' . esc_html__( 'Turn on reservations under Restaurant → Settings → Reservations.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>' : '';
		}
		wp_enqueue_style( 'sidrest-menu' );
		wp_enqueue_script( 'sidrest-booking' );
		return \SidcraftRestaurant\Plugin::instance()->templates->render(
			'booking.php',
			array(
				'title'     => $title,
				'max_party' => self::max_party(),
				'min_date'  => wp_date( 'Y-m-d', time() + max( 0, (int) setting( 'booking_notice', 2 ) ) * HOUR_IN_SECONDS ),
				'max_date'  => wp_date( 'Y-m-d', time() + max( 1, (int) setting( 'booking_days', 60 ) ) * DAY_IN_SECONDS ),
			)
		);
	}

	/*
	------------------------------------------------------------------
	 * Privacy
	 * ----------------------------------------------------------------
	 */

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['sidcraft-restaurant-bookings'] = array(
			'exporter_friendly_name' => __( 'Restaurant reservations', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['sidcraft-restaurant-bookings'] = array(
			'eraser_friendly_name' => __( 'Restaurant reservations', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Bookings of an email.
	 *
	 * @param string $email Email.
	 * @return int[]
	 */
	protected static function ids_for( $email ) {
		return get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Privacy request.
				'meta_key'       => '_sidrest_b_email',
				'meta_value'     => strtolower( sanitize_email( (string) $email ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * Export.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public function export( $email ) {
		$data = array();
		foreach ( self::ids_for( $email ) as $id ) {
			$b      = self::get( $id );
			$data[] = array(
				'group_id'    => 'sidrest-bookings',
				'group_label' => __( 'Reservations', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'item_id'     => 'booking-' . $id,
				'data'        => array(
					array(
						'name'  => __( 'When', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'value' => wp_date( 'Y-m-d H:i', $b['time'] ),
					),
					array(
						'name'  => __( 'Guests', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'value' => (string) $b['party'],
					),
					array(
						'name'  => __( 'Name', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'value' => $b['name'],
					),
					array(
						'name'  => __( 'Phone', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'value' => $b['phone'],
					),
					array(
						'name'  => __( 'Notes', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'value' => $b['notes'],
					),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public function erase( $email ) {
		$ids = self::ids_for( $email );
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
		return array(
			'items_removed'  => (bool) $ids,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
