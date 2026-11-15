<?php
/**
 * Restaurant → Reservations: the bookings list (upcoming first, with
 * Confirm / Seated / Cancel / No-show links) and the booking screen, where
 * staff also add phone bookings.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Admin;

use SidcraftRestaurant\Service\Bookings;
use SidcraftRestaurant\Service\Hours;

defined( 'ABSPATH' ) || exit;

/**
 * Class Bookings_Admin
 */
class Bookings_Admin {

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes_' . Bookings::TYPE, array( $this, 'boxes' ) );
		add_action( 'save_post_' . Bookings::TYPE, array( $this, 'save' ), 10, 2 );
		add_filter( 'manage_' . Bookings::TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Bookings::TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_sidrest_booking_status', array( $this, 'set_status' ) );
		add_action( 'pre_get_posts', array( $this, 'list_query' ) );
		add_filter( 'views_edit-' . Bookings::TYPE, array( $this, 'views' ) );
		add_filter( 'enter_title_here', array( $this, 'placeholder' ), 10, 2 );
		add_filter( 'display_post_states', array( $this, 'post_states' ), 10, 2 );
		add_action( 'admin_head-post.php', array( $this, 'hide_title' ) );
		add_action( 'admin_head-post-new.php', array( $this, 'hide_title' ) );
	}

	/**
	 * The title is generated: hide its box.
	 */
	public function hide_title() {
		$screen = get_current_screen();
		if ( $screen && Bookings::TYPE === $screen->post_type ) {
			echo '<style>#titlediv{display:none}</style>';
		}
	}

	/**
	 * Title placeholder (not shown).
	 *
	 * @param string   $text Placeholder.
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public function placeholder( $text, $post ) {
		return Bookings::TYPE === $post->post_type ? __( 'Guest name', 'sidcraft-restaurant-for-sidcraft-commerce' ) : $text;
	}

	/**
	 * Booking box.
	 */
	public function boxes() {
		add_meta_box( 'sidrest-booking', __( 'Booking', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render' ), Bookings::TYPE, 'normal', 'high' );
	}

	/**
	 * Booking fields.
	 *
	 * @param \WP_Post $post Booking.
	 */
	public function render( $post ) {
		wp_nonce_field( 'sidrest_booking_admin', '_sidrest_booking_nonce' );
		$b    = Bookings::get( $post->ID );
		$time = $b && $b['time'] ? $b['time'] : time() + DAY_IN_SECONDS;
		$rows = array(
			'date'  => array( __( 'Date', 'sidcraft-restaurant-for-sidcraft-commerce' ), '<input type="date" name="sidrest_b[date]" value="' . esc_attr( wp_date( 'Y-m-d', $time ) ) . '" required>' ),
			'time'  => array( __( 'Time', 'sidcraft-restaurant-for-sidcraft-commerce' ), '<input type="time" name="sidrest_b[time]" value="' . esc_attr( wp_date( 'H:i', $time ) ) . '" required>' ),
			'party' => array( __( 'Guests', 'sidcraft-restaurant-for-sidcraft-commerce' ), '<input type="number" min="1" max="500" name="sidrest_b[party]" value="' . esc_attr( (string) ( $b ? $b['party'] : 2 ) ) . '" class="small-text" required>' ),
			'name'  => array( __( 'Name', 'sidcraft-restaurant-for-sidcraft-commerce' ), '<input type="text" class="regular-text" name="sidrest_b[name]" value="' . esc_attr( $b ? $b['name'] : '' ) . '" required>' ),
			'phone' => array( __( 'Phone', 'sidcraft-restaurant-for-sidcraft-commerce' ), '<input type="tel" class="regular-text" name="sidrest_b[phone]" value="' . esc_attr( $b ? $b['phone'] : '' ) . '">' ),
			'email' => array( __( 'Email', 'sidcraft-restaurant-for-sidcraft-commerce' ), '<input type="email" class="regular-text" name="sidrest_b[email]" value="' . esc_attr( $b ? $b['email'] : '' ) . '">' ),
			'notes' => array( __( 'Notes', 'sidcraft-restaurant-for-sidcraft-commerce' ), '<textarea class="large-text" rows="3" name="sidrest_b[notes]">' . esc_textarea( $b ? $b['notes'] : '' ) . '</textarea>' ),
		);
		$status = '<select name="sidrest_b[status]">';
		foreach ( Bookings::status_labels() as $key => $label ) {
			$status .= '<option value="' . esc_attr( $key ) . '" ' . selected( $b ? $b['status'] : 'confirmed', $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$rows['status'] = array( __( 'Status', 'sidcraft-restaurant-for-sidcraft-commerce' ), $status . '</select>' );
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><th scope="row">' . esc_html( $row[0] ) . '</th><td>' . $row[1] . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
		}
		echo '</tbody></table>';
		echo '<p><label><input type="checkbox" name="sidrest_b[notify]" value="1"> ' . esc_html__( 'Email the guest about this booking (confirmed or cancelled)', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</label></p>';
		if ( $b && $b['token'] ) {
			echo '<p class="description">' . esc_html__( 'Guest link:', 'sidcraft-restaurant-for-sidcraft-commerce' ) . ' <code>' . esc_html( Bookings::link( $b ) ) . '</code></p>';
		}
	}

	/**
	 * Save the booking screen.
	 *
	 * @param int      $post_id Booking.
	 * @param \WP_Post $post    Booking.
	 */
	public function save( $post_id, $post ) {
		unset( $post );
		if ( ! isset( $_POST['_sidrest_booking_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_sidrest_booking_nonce'] ) ), 'sidrest_booking_admin' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw  = isset( $_POST['sidrest_b'] ) && is_array( $_POST['sidrest_b'] ) ? wp_unslash( $_POST['sidrest_b'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field below.
		$date = date_create_immutable( sanitize_text_field( (string) ( $raw['date'] ?? '' ) ) . ' ' . sanitize_text_field( (string) ( $raw['time'] ?? '' ) ), wp_timezone() );
		if ( ! $date ) {
			return;
		}
		$status = sanitize_key( (string) ( $raw['status'] ?? 'confirmed' ) );
		$old    = Bookings::get( $post_id );
		remove_action( 'save_post_' . Bookings::TYPE, array( $this, 'save' ), 10 );
		Bookings::save(
			array(
				'time'   => $date->getTimestamp(),
				'party'  => max( 1, absint( $raw['party'] ?? 1 ) ),
				'name'   => sanitize_text_field( (string) ( $raw['name'] ?? '' ) ),
				'email'  => strtolower( sanitize_email( (string) ( $raw['email'] ?? '' ) ) ),
				'phone'  => sanitize_text_field( (string) ( $raw['phone'] ?? '' ) ),
				'notes'  => sanitize_textarea_field( (string) ( $raw['notes'] ?? '' ) ),
				'status' => in_array( $status, Bookings::STATUSES, true ) ? $status : 'confirmed',
			),
			$post_id
		);
		if ( '' === (string) get_post_meta( $post_id, '_sidrest_b_token', true ) ) {
			update_post_meta( $post_id, '_sidrest_b_token', wp_generate_password( 32, false ) );
		}
		if ( 'private' !== get_post_status( $post_id ) ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'private',
				)
			);
		}
		add_action( 'save_post_' . Bookings::TYPE, array( $this, 'save' ), 10, 2 );
		$new = Bookings::get( $post_id );
		if ( ! empty( $raw['notify'] ) && in_array( $new['status'], array( 'confirmed', 'cancelled' ), true ) ) {
			Bookings::email_guest( $new, $new['status'] );
		} elseif ( $old && 'pending' === $old['status'] && 'confirmed' === $new['status'] ) {
			Bookings::email_guest( $new, 'confirmed' ); // Confirming a request always tells the guest.
		}
	}

	/**
	 * Columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public function columns( $cols ) {
		return array(
			'cb'             => $cols['cb'] ?? '',
			'sidrest_when'   => __( 'When', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'title'          => __( 'Guest', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'sidrest_party'  => __( 'Guests', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'sidrest_phone'  => __( 'Phone', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'sidrest_status' => __( 'Status', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'sidrest_notes'  => __( 'Notes', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		);
	}

	/**
	 * Column values.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Booking.
	 */
	public function column( $col, $post_id ) {
		$b = Bookings::get( $post_id );
		if ( ! $b ) {
			return;
		}
		switch ( $col ) {
			case 'sidrest_when':
				echo '<strong>' . esc_html( ucfirst( Hours::day_time( $b['time'] ) ) ) . '</strong><br><span class="description">' . esc_html( wp_date( (string) get_option( 'date_format' ), $b['time'] ) ) . '</span>';
				break;
			case 'sidrest_party':
				echo esc_html( (string) $b['party'] );
				break;
			case 'sidrest_phone':
				echo esc_html( $b['phone'] );
				break;
			case 'sidrest_status':
				$labels = Bookings::status_labels();
				echo '<span class="sidrest-bstatus sidrest-bstatus--' . esc_attr( $b['status'] ) . '">' . esc_html( $labels[ $b['status'] ] ) . '</span>';
				break;
			case 'sidrest_notes':
				echo esc_html( wp_html_excerpt( $b['notes'], 80, '…' ) );
				break;
		}
	}

	/**
	 * Quick status links.
	 *
	 * @param array    $actions Actions.
	 * @param \WP_Post $post    Post.
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( Bookings::TYPE !== $post->post_type ) {
			return $actions;
		}
		$b      = Bookings::get( $post->ID );
		$labels = array(
			'confirmed' => __( 'Confirm', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'seated'    => __( 'Seated', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'no_show'   => __( 'No-show', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'cancelled' => __( 'Cancel', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		);
		$out = array( 'edit' => $actions['edit'] ?? '' );
		foreach ( $labels as $status => $label ) {
			if ( $b && $b['status'] !== $status ) {
				$url            = wp_nonce_url( admin_url( 'admin-post.php?action=sidrest_booking_status&booking=' . $post->ID . '&status=' . $status ), 'sidrest_booking_status_' . $post->ID );
				$out[ $status ] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			}
		}
		if ( isset( $actions['trash'] ) ) {
			$out['trash'] = $actions['trash'];
		}
		return $out;
	}

	/**
	 * Quick status change (emails the guest on confirm and cancel).
	 */
	public function set_status() {
		$id = isset( $_GET['booking'] ) ? absint( $_GET['booking'] ) : 0;
		check_admin_referer( 'sidrest_booking_status_' . $id );
		$b      = Bookings::get( $id );
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( ! $b || ! current_user_can( 'edit_post', $id ) || ! in_array( $status, Bookings::STATUSES, true ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'sidcraft-restaurant-for-sidcraft-commerce' ), 403 );
		}
		update_post_meta( $id, '_sidrest_b_status', $status );
		if ( in_array( $status, array( 'confirmed', 'cancelled' ), true ) ) {
			Bookings::email_guest( Bookings::get( $id ), $status );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . Bookings::TYPE ) );
		exit;
	}

	/**
	 * The list: upcoming bookings by time (or today / past / all).
	 *
	 * @param \WP_Query $query Query.
	 */
	public function list_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || Bookings::TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List filter.
		$view = isset( $_GET['sidrest_view'] ) ? sanitize_key( wp_unslash( $_GET['sidrest_view'] ) ) : 'upcoming';
		$tz   = wp_timezone();
		$day  = ( new \DateTimeImmutable( 'today', $tz ) )->getTimestamp();
		$meta = array(
			'key'  => '_sidrest_b_time',
			'type' => 'NUMERIC',
		);
		if ( 'today' === $view ) {
			$meta += array(
				'value'   => array( $day, $day + DAY_IN_SECONDS - 1 ),
				'compare' => 'BETWEEN',
			);
		} elseif ( 'past' === $view ) {
			$meta += array(
				'value'   => time() - HOUR_IN_SECONDS,
				'compare' => '<',
			);
		} elseif ( 'all' !== $view ) {
			$meta += array(
				'value'   => time() - 3 * HOUR_IN_SECONDS,
				'compare' => '>=',
			);
		}
		$query->set( 'meta_query', array( $meta ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin list.
		$query->set( 'meta_key', '_sidrest_b_time' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'order', 'past' === $view ? 'DESC' : 'ASC' );
	}

	/**
	 * Today / Upcoming / Past / All links.
	 *
	 * @param array $views Views.
	 * @return array
	 */
	public function views( $views ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List filter.
		$view  = isset( $_GET['sidrest_view'] ) ? sanitize_key( wp_unslash( $_GET['sidrest_view'] ) ) : 'upcoming';
		$base  = admin_url( 'edit.php?post_type=' . Bookings::TYPE );
		$out   = array();
		$links = array(
			'today'    => __( 'Today', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'upcoming' => __( 'Upcoming', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'past'     => __( 'Past', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'all'      => __( 'All', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		);
		foreach ( $links as $key => $label ) {
			$out[ $key ] = '<a href="' . esc_url( add_query_arg( 'sidrest_view', $key, $base ) ) . '"' . ( $view === $key ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		return $out;
	}

	/**
	 * Bookings are private posts; "— Private" in the list says nothing.
	 *
	 * @param string[] $states Post states.
	 * @param \WP_Post $post   Post.
	 * @return string[]
	 */
	public function post_states( $states, $post ) {
		if ( $post instanceof \WP_Post && Bookings::TYPE === $post->post_type ) {
			unset( $states['private'] );
		}
		return $states;
	}
}
