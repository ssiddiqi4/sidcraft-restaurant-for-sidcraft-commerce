<?php
/**
 * Menu Section Unit: one section (title, note and its items) anywhere.
 *
 * Shortcode: [sidrest_section section="34" style="list"]
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Units;

use SidcraftRestaurant\Plugin;
use SidcraftRestaurant\Post_Types;
use SidcraftRestaurant\Section;

defined( 'ABSPATH' ) || exit;

/**
 * Class Section_Unit
 */
class Section_Unit extends Base_Unit {

	/** {@inheritDoc} */
	public function get_name() {
		return 'sidrest-section';
	}

	/** {@inheritDoc} */
	public function get_title() {
		return __( 'Menu Section', 'sidcraft-restaurant-for-sidcraft-commerce' );
	}

	/** {@inheritDoc} */
	public function get_icon() {
		return '📋';
	}

	/** {@inheritDoc} */
	protected function controls() {
		return array(
			'section'     => array(
				'type'    => 'select',
				'label'   => __( 'Section', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => '',
				'options' => self::post_options( Post_Types::SECTION, __( '— First section —', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
			),
			'style'       => array(
				'type'    => 'select',
				'label'   => __( 'Style', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'layout',
				'default' => '',
				'options' => self::style_options( __( 'Site default', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
			),
			'columns'     => array(
				'type'    => 'number',
				'label'   => __( 'Columns (photo grid)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'layout',
				'default' => 3,
				'min'     => 1,
				'max'     => 4,
			),
			'toolbar'     => array(
				'type'    => 'select',
				'label'   => __( 'Search, filters and sorting', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'layout',
				'default' => 'hide',
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
		$id      = self::id( $settings, 'section' );
		$section = $id ? Section::get( $id ) : Section::first();
		if ( ! $section ) {
			return '';
		}
		return Plugin::instance()->renderer->section( $section, $settings );
	}
}
