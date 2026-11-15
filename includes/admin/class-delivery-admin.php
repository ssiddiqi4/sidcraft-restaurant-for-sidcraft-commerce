<?php
/**
 * The Delivery box on Sidcraft Commerce orders: pickup or delivery, the
 * platform, courier status and tracking, "Request courier" and a field for
 * a tracking link booked by hand.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Admin;

use SidcraftCommerce\Data\Order;
use SidcraftCommerce\Data\Post_Types as Commerce_Types;
use SidcraftRestaurant\Delivery\Delivery;
use SidcraftRestaurant\Delivery\Platforms;

defined( 'ABSPATH' ) || exit;

/**
 * Class Delivery_Admin
 */
class Delivery_Admin {

	const ACTION = 'sidrest_request_courier';

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes_' . Commerce_Types::ORDER, array( $this, 'meta_box' ) );
		add_action( 'save_post_' . Commerce_Types::ORDER, array( $this, 'save' ), 8 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'request_courier' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Add the box to delivery and pickup orders.
	 *
	 * @param \WP_Post $post Order post.
	 */
	public function meta_box( $post ) {
		$order = Order::get( $post->ID );
		if ( ! $order ) {
			return;
		}
		$billing = $order->get_billing();
		if ( empty( $billing[ Delivery::FIELD ] ) ) {
			return;
		}
		add_meta_box( 'sidrest-delivery', __( 'Delivery', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render' ), Commerce_Types::ORDER, 'side', 'high' );
	}

	/**
	 * Box contents.
	 *
	 * @param \WP_Post $post Order post.
	 */
	public function render( $post ) {
		$order = Order::get( $post->ID );
		if ( ! $order ) {
			return;
		}
		$slug = Delivery::order_platform( $order );
		if ( '' === $slug ) {
			echo '<p><strong>' . esc_html__( 'Pickup', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</strong></p>';
			return;
		}
		$record = Delivery::record( $order );
		$name   = Platforms::name( $slug );
		$states = array(
			'requested' => __( 'Courier requested', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'picked_up' => __( 'Picked up', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'delivered' => __( 'Delivered', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'cancelled' => __( 'Courier cancelled', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'failed'    => __( 'Request failed', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'sent'      => __( 'Sent to webhook', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'manual'    => __( 'Waiting for a courier to be booked', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		);

		/* translators: %s: platform. */
		echo '<p><strong>' . esc_html( sprintf( __( 'Delivery by %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $name ) ) . '</strong></p>';
		if ( $record ) {
			echo '<p>' . esc_html( $states[ $record['status'] ] ?? $record['status'] ) . '</p>';
			if ( ! empty( $record['error'] ) && in_array( $record['status'], array( 'failed' ), true ) ) {
				echo '<p class="description" style="color:#b32d2e">' . esc_html( $record['error'] ) . '</p>';
			}
			if ( ! empty( $record['id'] ) ) {
				/* translators: %s: delivery ID. */
				echo '<p class="description">' . esc_html( sprintf( __( 'Delivery ID: %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $record['id'] ) ) . '</p>';
			}
			if ( isset( $record['fee'] ) ) {
				/* translators: %s: amount. */
				echo '<p class="description">' . esc_html( sprintf( __( 'Courier cost: %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), Delivery::plain_price( (float) $record['fee'] ) ) ) . '</p>';
			}
			if ( ! empty( $record['tracking_url'] ) ) {
				echo '<p><a href="' . esc_url( $record['tracking_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Track the courier', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</a></p>';
			}
		} elseif ( ! $order->is_paid() ) {
			echo '<p class="description">' . esc_html__( 'The courier is requested once the order is paid.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		}

		$row  = Platforms::platform( $slug );
		$mode = $row ? $row['dispatch'] : 'manual';
		if ( $order->is_paid() && 'manual' !== $mode && ( ! $record || in_array( $record['status'], array( 'failed', 'cancelled', 'manual' ), true ) ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION . '&order=' . $order->get_id() ), self::ACTION . '_' . $order->get_id() );
			$txt = $record && 'manual' !== $record['status'] ? __( 'Request a courier again', 'sidcraft-restaurant-for-sidcraft-commerce' ) : __( 'Request courier', 'sidcraft-restaurant-for-sidcraft-commerce' );
			echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html( $txt ) . '</a></p>';
		}

		if ( ! $record || empty( $record['tracking_url'] ) ) {
			wp_nonce_field( 'sidrest_tracking_' . $order->get_id(), 'sidrest_tracking_nonce' );
			echo '<p><label for="sidrest-tracking-url">' . esc_html__( 'Booked the courier yourself? Tracking link', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</label>';
			echo '<input type="url" class="widefat" id="sidrest-tracking-url" name="sidrest_tracking_url" placeholder="https://"></p>';
			echo '<p><label for="sidrest-tracking-ref">' . esc_html__( 'Delivery reference (optional)', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</label>';
			echo '<input type="text" class="widefat" id="sidrest-tracking-ref" name="sidrest_tracking_ref"></p>';
			echo '<p class="description">' . esc_html__( 'Saved with the order. The customer is emailed the link.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		}
	}

	/**
	 * Save a tracking link entered by hand.
	 *
	 * @param int $post_id Order ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST['sidrest_tracking_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sidrest_tracking_nonce'] ) ), 'sidrest_tracking_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$url = isset( $_POST['sidrest_tracking_url'] ) ? esc_url_raw( wp_unslash( $_POST['sidrest_tracking_url'] ), array( 'http', 'https' ) ) : '';
		if ( '' === $url ) {
			return;
		}
		$ref   = isset( $_POST['sidrest_tracking_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['sidrest_tracking_ref'] ) ) : '';
		$order = Order::get( $post_id );
		$slug  = $order ? Delivery::order_platform( $order ) : '';
		if ( '' === $slug ) {
			return;
		}
		$record = Delivery::record( $order );
		$record = is_array( $record ) ? $record : array(
			'platform'    => $slug,
			'mode'        => 'manual',
			'external_id' => 'sidrest-' . $order->get_id() . '-' . strtolower( wp_generate_password( 6, false ) ),
			'fee'         => null,
			'error'       => '',
		);
		$record['status']       = 'requested';
		$record['id']           = '' !== $ref ? $ref : (string) ( $record['id'] ?? '' );
		$record['tracking_url'] = $url;
		$record['time']         = time();
		Delivery::save( $order, $record );
		Delivery::add_tracking( $order, Platforms::name( $slug ), '' !== $record['id'] ? $record['id'] : (string) $order->get_id(), $url );
		// The status dropdown on this screen is saved next; choose "Out for delivery" there.
	}

	/**
	 * "Request courier" button.
	 */
	public function request_courier() {
		$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		check_admin_referer( self::ACTION . '_' . $id );
		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'You can’t edit this order.', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
		}
		$order  = Order::get( $id );
		$result = $order ? Delivery::dispatch( $order, true ) : new \WP_Error( 'sidrest_no_order', '' );
		$ok     = ! is_wp_error( $result ) && in_array( $result['status'], array( 'requested', 'sent' ), true );
		wp_safe_redirect( add_query_arg( 'sidrest_courier', $ok ? 'ok' : 'failed', admin_url( 'post.php?post=' . $id . '&action=edit' ) ) );
		exit;
	}

	/**
	 * Result of "Request courier".
	 */
	public function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$state = isset( $_GET['sidrest_courier'] ) ? sanitize_key( wp_unslash( $_GET['sidrest_courier'] ) ) : '';
		if ( 'ok' === $state ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Courier requested.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p></div>';
		} elseif ( 'failed' === $state ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The courier could not be requested. See the Delivery box and the order notes.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p></div>';
		}
	}
}
