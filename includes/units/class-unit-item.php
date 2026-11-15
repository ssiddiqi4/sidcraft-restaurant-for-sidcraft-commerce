<?php
/**
 * Menu Item Unit: one dish or drink, e.g. a "chef's special" highlight.
 *
 * Shortcode: [sidrest_item item="56" style="grid"]
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Units;

use SidcraftRestaurant\Item;
use SidcraftRestaurant\Plugin;
use SidcraftRestaurant\Post_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Class Item_Unit
 */
class Item_Unit extends Base_Unit {

	/** {@inheritDoc} */
	public function get_name() {
		return 'sidrest-item';
	}

	/** {@inheritDoc} */
	public function get_title() {
		return __( 'Menu Item', 'sidcraft-restaurant-for-sidcraft-commerce' );
	}

	/** {@inheritDoc} */
	public function get_icon() {
		return '🍝';
	}

	/** {@inheritDoc} */
	protected function controls() {
		$styles = self::style_options( __( 'Photo grid', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
		unset( $styles['grid'] );
		return array(
			'item'        => array(
				'type'    => 'select',
				'label'   => __( 'Menu item', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => '',
				'options' => self::post_options( Post_Types::ITEM, __( '— Latest item —', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
			),
			'style'       => array(
				'type'    => 'select',
				'label'   => __( 'Style', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'layout',
				'default' => '',
				'options' => $styles,
			),
			'show_images' => array(
				'type'    => 'select',
				'label'   => __( 'Photo', 'sidcraft-restaurant-for-sidcraft-commerce' ),
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
		$id = self::id( $settings, 'item' );
		if ( ! $id ) {
			$ids = get_posts(
				array(
					'post_type'      => Post_Types::ITEM,
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			$id  = $ids ? (int) $ids[0] : 0;
		}
		$item = $id ? Item::get( $id ) : null;
		if ( ! $item || ( ! $item->is_visible() && ! current_user_can( 'edit_post', $item->get_id() ) ) ) {
			return '';
		}
		if ( '' === $settings['style'] ) {
			$settings['style'] = 'grid';
		}
		return Plugin::instance()->renderer->single_item( $item, $settings );
	}
}
