<?php
/**
 * Table Booking Unit: the reservation form.
 *
 * Shortcode fallback: [sidrest_booking_form title="Book a table"] (also [sidrest_booking]).
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Units;

use SidcraftRestaurant\Service\Bookings;
use function SidcraftRestaurant\label;

defined( 'ABSPATH' ) || exit;

/**
 * Class Booking_Unit
 */
class Booking_Unit extends Base_Unit {

	/** {@inheritDoc} */
	public function get_name() {
		return 'sidrest-booking-form';
	}

	/** {@inheritDoc} */
	public function get_title() {
		return __( 'Table Booking', 'sidcraft-restaurant-for-sidcraft-commerce' );
	}

	/** {@inheritDoc} */
	public function get_icon() {
		return '📅';
	}

	/** {@inheritDoc} */
	public function get_assets() {
		return array(
			'styles'  => array( 'sidrest-menu' ),
			'scripts' => array( 'sidrest-booking' ),
		);
	}

	/** {@inheritDoc} */
	protected function controls() {
		return array(
			'title' => array(
				'type'    => 'text',
				'label'   => __( 'Title', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => label( 'book_table' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $settings Settings.
	 */
	protected function render_html( array $settings ) {
		return Bookings::form_html( (string) $settings['title'] );
	}
}
