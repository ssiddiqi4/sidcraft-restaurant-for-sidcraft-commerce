<?php
/**
 * Menu QR Code Unit: a QR code that opens a menu (or any link), drawn in
 * the browser, with an optional caption and download button.
 *
 * Shortcode: [sidrest_qr menu="12" size="200" caption="Scan to see our menu"]
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Units;

use SidcraftRestaurant\Menu;
use SidcraftRestaurant\Post_Types;
use function SidcraftRestaurant\label;

defined( 'ABSPATH' ) || exit;

/**
 * Class Qr_Unit
 */
class Qr_Unit extends Base_Unit {

	/** {@inheritDoc} */
	public function get_name() {
		return 'sidrest-qr';
	}

	/** {@inheritDoc} */
	public function get_title() {
		return __( 'Menu QR Code', 'sidcraft-restaurant-for-sidcraft-commerce' );
	}

	/** {@inheritDoc} */
	public function get_icon() {
		return '▣';
	}

	/** {@inheritDoc} */
	public function get_assets() {
		return array(
			'styles'  => array( 'sidrest-menu' ),
			'scripts' => array( 'sidrest-qr' ),
		);
	}

	/** {@inheritDoc} */
	protected function controls() {
		return array(
			'menu'     => array(
				'type'    => 'select',
				'label'   => __( 'Opens this menu', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => '',
				'options' => self::post_options( Post_Types::MENU, __( '— Use the link below —', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
			),
			'url'      => array(
				'type'        => 'text',
				'label'       => __( 'Or this link', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'description' => __( 'Empty: the page the code is on.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section'     => 'content',
				'default'     => '',
			),
			'size'     => array(
				'type'    => 'number',
				'label'   => __( 'Size (pixels)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'layout',
				'default' => 200,
				'min'     => 80,
				'max'     => 800,
			),
			'color'    => array(
				'type'    => 'color',
				'label'   => __( 'Code colour', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'layout',
				'default' => '#111111',
			),
			'caption'  => array(
				'type'    => 'text',
				'label'   => __( 'Caption', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => '',
			),
			'download' => array(
				'type'    => 'switch',
				'label'   => __( 'Download button', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => false,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $settings Settings.
	 */
	protected function render_html( array $settings ) {
		$menu = absint( $settings['menu'] ) ? Menu::get( absint( $settings['menu'] ) ) : null;
		$url  = $menu ? $menu->get_url() : esc_url_raw( (string) $settings['url'] );
		if ( '' === $url ) {
			$url = is_singular() ? (string) get_permalink() : home_url( '/' );
		}
		$size    = max( 80, min( 800, (int) $settings['size'] ) );
		$caption = '' !== trim( (string) $settings['caption'] ) ? (string) $settings['caption'] : label( 'scan' );
		return sprintf(
			'<figure class="sidrest-qr" data-sidrest-qr data-url="%1$s" data-size="%2$d" data-color="%3$s"><div class="sidrest-qr__code" style="width:%2$dpx;height:%2$dpx" role="img" aria-label="%4$s"></div><figcaption>%5$s</figcaption>%6$s<noscript><a href="%7$s">%7$s</a></noscript></figure>',
			esc_attr( $url ),
			$size,
			esc_attr( $settings['color'] ? $settings['color'] : '#111111' ),
			/* translators: %s: link the QR code opens. */
			esc_attr( sprintf( __( 'QR code for %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $url ) ),
			esc_html( $caption ),
			$settings['download'] ? '<button type="button" class="sidrest-chip" data-sidrest-qr-download>' . esc_html__( 'Download', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</button>' : '',
			esc_url( $url )
		);
	}
}
