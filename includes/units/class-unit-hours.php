<?php
/**
 * Opening Hours Unit: the week's opening hours, with today highlighted.
 *
 * Shortcode fallback: [sidrest_hours]
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Units;

use SidcraftRestaurant\Service\Hours;
use function SidcraftRestaurant\label;

defined( 'ABSPATH' ) || exit;

/**
 * Class Hours_Unit
 */
class Hours_Unit extends Base_Unit {

	/** {@inheritDoc} */
	public function get_name() {
		return 'sidrest-hours';
	}

	/** {@inheritDoc} */
	public function get_title() {
		return __( 'Opening Hours', 'sidcraft-restaurant-for-sidcraft-commerce' );
	}

	/** {@inheritDoc} */
	public function get_icon() {
		return '🕒';
	}

	/** {@inheritDoc} */
	protected function controls() {
		return array(
			'title' => array(
				'type'    => 'text',
				'label'   => __( 'Title', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => label( 'hours' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $settings Settings.
	 */
	protected function render_html( array $settings ) {
		if ( ! Hours::configured() ) {
			return current_user_can( \SidcraftRestaurant\manage_cap() ) ? '<p>' . esc_html__( 'Enter your opening hours under Restaurant → Settings → Hours.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>' : '';
		}
		$today = wp_date( 'l' );
		$html  = '<div class="sidrest-hours" style="' . esc_attr( \SidcraftRestaurant\Frontend::css_vars() ) . '">';
		if ( '' !== $settings['title'] ) {
			$html .= '<h3 class="sidrest-hours__title">' . esc_html( $settings['title'] ) . '</h3>';
		}
		$html .= '<dl class="sidrest-hours__list">';
		foreach ( Hours::week_rows() as $row ) {
			$cls   = $row[0] === $today ? ' class="is-today"' : '';
			$html .= '<div' . $cls . '><dt>' . esc_html( $row[0] ) . '</dt><dd>' . esc_html( $row[1] ) . '</dd></div>';
		}
		return $html . '</dl><p class="sidrest-hours__now" data-sidrest-open-status></p></div>';
	}
}
