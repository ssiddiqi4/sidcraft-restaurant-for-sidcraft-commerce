<?php
/**
 * Registers the restaurant Units with Sidcraft Commerce, which makes each of
 * them a Sidcraft Page Builder Unit (with live previews) and a shortcode:
 *
 *     [sidrest_menu menu="12" style="luxe"]
 *     [sidrest_section section="34"]
 *     [sidrest_item item="56"]
 *     [sidrest_items source="section" section="34" limit="5"]
 *     [sidrest_qr menu="12"]
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Units
 */
class Units {

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'sidcraft_commerce_register_units', array( $this, 'register' ) );
		add_filter( 'sidcraft_commerce_editor_styles', array( $this, 'editor_styles' ) );
		foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $hook ) {
			add_action(
				$hook,
				static function ( $post_id ) {
					if ( class_exists( __NAMESPACE__ . '\\Units\\Base_Unit', false ) ) {
						Units\Base_Unit::flush_options( $post_id );
					}
				}
			);
		}
	}

	/**
	 * Register the Units.
	 *
	 * @param \SidcraftCommerce\Unit_Registry $registry Registry.
	 */
	public function register( $registry ) {
		require_once SIDREST_PATH . 'includes/units/abstract-unit.php';
		foreach ( array( 'menu', 'section', 'item', 'items', 'qr', 'booking', 'hours' ) as $name ) {
			require_once SIDREST_PATH . 'includes/units/class-unit-' . $name . '.php';
		}
		$registry->register( new Units\Menu_Unit() );
		$registry->register( new Units\Section_Unit() );
		$registry->register( new Units\Item_Unit() );
		$registry->register( new Units\Items_Unit() );
		$registry->register( new Units\Qr_Unit() );
		$registry->register( new Units\Booking_Unit() );
		$registry->register( new Units\Hours_Unit() );
	}

	/**
	 * Menu styles inside the Sidcraft Page Builder canvas.
	 *
	 * @param string[] $styles Stylesheet URLs.
	 * @return string[]
	 */
	public function editor_styles( $styles ) {
		$styles[] = add_query_arg( 'ver', SIDREST_VERSION, SIDREST_URL . 'assets/css/sidrest-menu.css' );
		return $styles;
	}

	/**
	 * A Unit by short name (menu, section, item, items, qr).
	 *
	 * @param string $name Short name.
	 * @return \SidcraftCommerce\Abstracts\Unit|null
	 */
	public static function get( $name ) {
		return \SidcraftCommerce\Plugin::instance()->units->get( 'sidrest-' . $name );
	}
}
