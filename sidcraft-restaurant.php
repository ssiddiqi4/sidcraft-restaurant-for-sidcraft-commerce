<?php
/**
 * Plugin Name:       Sidcraft Restaurant for Sidcraft Commerce
 * Description:       Restaurant menus and online ordering for Sidcraft Commerce: menus, styles, filters, QR codes, opening hours, order-ahead time slots, a kitchen screen, table ordering, tips, reservations, meal deals, allergens and delivery zones.
 * Version:           1.2.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Requires Plugins:  sidcraft-commerce-for-sidcraft-page-builder
 * Author:            Suhaib Siddiqi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sidcraft-restaurant-for-sidcraft-commerce
 * Domain Path:       /languages
 *
 * @package SidcraftRestaurant
 */

defined( 'ABSPATH' ) || exit;

define( 'SIDREST_VERSION', '1.2.0' );
define( 'SIDREST_FILE', __FILE__ );
define( 'SIDREST_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIDREST_URL', plugin_dir_url( __FILE__ ) );
define( 'SIDREST_MIN_COMMERCE', '0.9.0' );

/*
 * Everything starts once Sidcraft Commerce has loaded (it only fires this
 * when Sidcraft Page Builder is active too). Without it, show a notice.
 */
add_action(
	'sidcraft_commerce_loaded',
	static function () {
		if ( ! defined( 'SIDCRAFT_COMMERCE_VERSION' ) || version_compare( SIDCRAFT_COMMERCE_VERSION, SIDREST_MIN_COMMERCE, '<' ) ) {
			return; // The notice below explains.
		}
		require_once SIDREST_PATH . 'includes/class-plugin.php';
		\SidcraftRestaurant\Plugin::instance()->boot();
	}
);

add_action(
	'admin_notices',
	static function () {
		if ( class_exists( '\SidcraftRestaurant\Plugin', false ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Sidcraft Restaurant is inactive.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</strong> ';
		printf(
			/* translators: %s: version number. */
			esc_html__( 'It needs Sidcraft Commerce %s or newer, active together with Sidcraft Page Builder.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			esc_html( SIDREST_MIN_COMMERCE )
		);
		echo '</p></div>';
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		update_option( 'sidrest_flush_rewrites', 1, false );
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);
