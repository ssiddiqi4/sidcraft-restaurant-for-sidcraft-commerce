<?php
/**
 * Restaurant Menu Unit: a whole menu with its sections.
 *
 * Shortcode: [sidrest_menu menu="12" style="grid" sidebar="show" toolbar="hide" columns="3"]
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Units;

use SidcraftRestaurant\Menu;
use SidcraftRestaurant\Plugin;
use SidcraftRestaurant\Post_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Class Menu_Unit
 */
class Menu_Unit extends Base_Unit {

	/** {@inheritDoc} */
	public function get_name() {
		return 'sidrest-menu';
	}

	/** {@inheritDoc} */
	public function get_title() {
		return __( 'Restaurant Menu', 'sidcraft-restaurant-for-sidcraft-commerce' );
	}

	/** {@inheritDoc} */
	public function get_icon() {
		return '🍽';
	}

	/** {@inheritDoc} */
	protected function controls() {
		return array(
			'menu'        => array(
				'type'    => 'select',
				'label'   => __( 'Menu', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => '',
				'options' => self::post_options( Post_Types::MENU, __( '— First menu —', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
			),
			'style'       => array(
				'type'    => 'select',
				'label'   => __( 'Style', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'layout',
				'default' => '',
				'options' => self::style_options( __( 'Menu’s own style', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
			),
			'columns'     => array(
				'type'    => 'number',
				'label'   => __( 'Columns (photo grid)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'layout',
				'default' => 3,
				'min'     => 1,
				'max'     => 4,
			),
			'sidebar'     => array(
				'type'    => 'select',
				'label'   => __( 'Sections sidebar', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'layout',
				'default' => '',
				'options' => self::toggle_options(),
			),
			'toolbar'     => array(
				'type'    => 'select',
				'label'   => __( 'Search, filters and sorting', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'layout',
				'default' => '',
				'options' => self::toggle_options(),
			),
			'show_title'  => array(
				'type'    => 'select',
				'label'   => __( 'Menu title', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => '',
				'options' => self::toggle_options(),
			),
			'show_images' => array(
				'type'    => 'select',
				'label'   => __( 'Photos', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => '',
				'options' => self::toggle_options(),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $settings Settings.
	 */
	protected function render_html( array $settings ) {
		$id   = self::id( $settings, 'menu' );
		$menu = $id ? Menu::get( $id ) : Menu::first();
		if ( ! $menu || ( ! $menu->is_visible() && ! current_user_can( 'edit_post', $menu->get_id() ) ) ) {
			return '';
		}
		return Plugin::instance()->renderer->menu( $menu, $settings );
	}
}
