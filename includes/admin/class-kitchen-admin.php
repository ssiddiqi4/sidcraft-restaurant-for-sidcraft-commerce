<?php
/**
 * Restaurant → Kitchen: a full-screen board for a kitchen tablet. New paid
 * orders appear with a sound and can print a ticket by themselves; staff
 * move each order New → Preparing → Ready → Done. A drawer marks dishes
 * sold out for the day. Built by assets/js/sidrest-kitchen.js.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Admin;

use SidcraftRestaurant\Delivery\Delivery;
use SidcraftRestaurant\Post_Types;
use SidcraftRestaurant\Service\Kitchen;
use function SidcraftRestaurant\setting;

defined( 'ABSPATH' ) || exit;

/**
 * Class Kitchen_Admin
 */
class Kitchen_Admin {

	const PAGE = 'sidrest-kitchen';

	/**
	 * Page hook.
	 *
	 * @var string
	 */
	protected $hook = '';

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 15 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Submenu (second, after Menus).
	 */
	public function menu() {
		$this->hook = (string) add_submenu_page(
			'edit.php?post_type=' . Post_Types::MENU,
			__( 'Kitchen', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			__( 'Kitchen', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			Kitchen::cap(),
			self::PAGE,
			array( $this, 'render' ),
			1
		);
	}

	/**
	 * Full screen by default.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && $screen->id === $this->hook ? $classes . ' sidrest-kitchen-page' : $classes;
	}

	/**
	 * Script and style.
	 *
	 * @param string $hook Page hook.
	 */
	public function assets( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_style( 'sidrest-kitchen', SIDREST_URL . 'assets/css/sidrest-kitchen.css', array(), SIDREST_VERSION );
		wp_enqueue_script( 'sidrest-kitchen', SIDREST_URL . 'assets/js/sidrest-kitchen.js', array(), SIDREST_VERSION, true );
		wp_add_inline_script(
			'sidrest-kitchen',
			'window.sidrestKitchen = ' . wp_json_encode(
				array(
					'api'         => esc_url_raw( rest_url( Delivery::NS . '/kitchen/' ) ),
					'nonce'       => wp_create_nonce( 'wp_rest' ),
					'sound'       => (bool) setting( 'kitchen_sound', 1 ),
					'ticketWidth' => max( 40, min( 210, (int) setting( 'ticket_width', 80 ) ) ),
					'store'       => '' !== (string) setting( 'restaurant_name', '' ) ? (string) setting( 'restaurant_name', '' ) : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					'exit'        => admin_url( 'edit.php?post_type=' . Post_Types::MENU ),
					'i18n'        => array(
						'new'         => __( 'New', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'preparing'   => __( 'Preparing', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'ready'       => __( 'Ready', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'start'       => __( 'Start', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'markReady'   => __( 'Ready', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'done'        => __( 'Done', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'back'        => __( 'Back', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'print'       => __( 'Print', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'open'        => __( 'Open the order', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'empty'       => __( 'No orders', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'unpaid'      => __( 'Not paid yet', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'late'        => __( 'Late', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'later'       => __( 'Later', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'due'         => __( 'Due', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'placed'      => __( 'Placed', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'notes'       => __( 'Notes', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'allergens'   => __( 'Allergens', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'tip'         => __( 'Tip', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'total'       => __( 'Total', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'sound'       => __( 'Sound', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'soundOn'     => __( 'Tap to turn the sound on', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'autoPrint'   => __( 'Print new orders', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'soldOut'     => __( 'Sold out today', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'search'      => __( 'Find a dish', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'close'       => __( 'Close', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'full'        => __( 'Full screen', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'exitLabel'   => __( 'Exit', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'openNow'     => __( 'Taking orders', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'paused'      => __( 'Online ordering paused', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'closed'      => __( 'Closed now', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'offline'     => __( 'Connection lost — retrying…', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'newOrder'    => __( 'New order', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * The board (built by the script).
	 */
	public function render() {
		if ( ! current_user_can( Kitchen::cap() ) ) {
			return;
		}
		echo '<div class="wrap sidrest-kitchen" id="sidrest-kitchen"><h1 class="screen-reader-text">' . esc_html__( 'Kitchen', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</h1><p>' . esc_html__( 'Loading…', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p></div>';
		echo '<noscript><p>' . esc_html__( 'The kitchen screen needs JavaScript.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p></noscript>';
	}
}
