<?php
/**
 * Bootstrap: loads files and attaches hooks.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Template loader.
	 *
	 * @var Templates
	 */
	public $templates;

	/**
	 * Renderer.
	 *
	 * @var Renderer
	 */
	public $renderer;

	/**
	 * Singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load and hook everything.
	 */
	public function boot() {
		foreach ( array(
			'includes/helpers.php',
			'includes/class-dietary.php',
			'includes/class-post-types.php',
			'includes/class-item.php',
			'includes/class-section.php',
			'includes/class-menu.php',
			'includes/class-templates.php',
			'includes/class-renderer.php',
			'includes/class-schema.php',
			'includes/class-ordering.php',
			'includes/class-frontend.php',
			'includes/class-units.php',
			'includes/class-blocks.php',
			'includes/class-patterns.php',
			'includes/class-widget.php',
			'includes/class-compat.php',
			'includes/delivery/class-platforms.php',
			'includes/delivery/class-couriers.php',
			'includes/delivery/class-delivery.php',
		) as $file ) {
			require_once SIDREST_PATH . $file;
		}

		$this->templates = new Templates();
		$this->renderer  = new Renderer();

		( new Post_Types() )->hooks();
		( new Schema() )->hooks();
		( new Ordering() )->hooks();
		( new Frontend() )->hooks();
		( new Units() )->hooks();
		( new Blocks() )->hooks();
		( new Patterns() )->hooks();
		( new Compat() )->hooks();
		( new Delivery\Delivery() )->hooks();
		add_action( 'widgets_init', array( __NAMESPACE__ . '\\Widget', 'register' ) );

		if ( is_admin() ) {
			require_once SIDREST_PATH . 'includes/admin/class-admin.php';
			require_once SIDREST_PATH . 'includes/admin/class-settings.php';
			require_once SIDREST_PATH . 'includes/admin/class-qr.php';
			require_once SIDREST_PATH . 'includes/admin/class-delivery-admin.php';
			( new Admin\Admin() )->hooks();
			( new Admin\Settings() )->hooks();
			( new Admin\Qr() )->hooks();
			( new Admin\Delivery_Admin() )->hooks();
		}

		/**
		 * Fires after Sidcraft Restaurant has loaded.
		 *
		 * @param Plugin $plugin This instance.
		 */
		do_action( 'sidrest_loaded', $this );
	}
}
