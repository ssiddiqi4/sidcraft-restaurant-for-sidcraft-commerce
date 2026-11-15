<?php
/**
 * "Pay at the table": offered only to guests ordering at a table (see
 * Dine_In). The order goes to the kitchen straight away, on hold until
 * staff mark it paid.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Service;

use SidcraftCommerce\Data\Order;
use SidcraftCommerce\Payments\Gateway;
use function SidcraftRestaurant\setting;

defined( 'ABSPATH' ) || exit;

/**
 * Class Pay_At_Table
 */
class Pay_At_Table extends Gateway {

	/** {@inheritDoc} */
	public function get_id() {
		return 'sidrest_table';
	}

	/** {@inheritDoc} */
	public function get_default_title() {
		return __( 'Pay at the table', 'sidcraft-restaurant-for-sidcraft-commerce' );
	}

	/** {@inheritDoc} */
	public function get_title() {
		return $this->get_default_title();
	}

	/** {@inheritDoc} */
	public function get_description() {
		return __( 'Pay your server by card or cash when you’ve finished.', 'sidcraft-restaurant-for-sidcraft-commerce' );
	}

	/**
	 * Only for guests at a table. Set up under Restaurant → Settings → Dine-in.
	 *
	 * @return bool
	 */
	public function is_available() {
		$ok = setting( 'pay_at_table', 1 ) && '' !== Dine_In::current() && 'table' === \SidcraftRestaurant\Delivery\Delivery::current_choice( \SidcraftRestaurant\Delivery\Delivery::choices() );
		/** This filter is documented in Sidcraft Commerce's Gateway::is_available(). */
		return (bool) apply_filters( 'sidcraft_commerce_gateway_available', $ok, $this );
	}

	/** {@inheritDoc} */
	public function get_settings_fields() {
		return array(); // Its switch lives in the Restaurant settings.
	}

	/**
	 * Place the order on hold; the kitchen sees it at once.
	 *
	 * @param Order $order Order.
	 * @return array
	 */
	public function process_payment( Order $order ) {
		$order->set_status( 'sidccofo-on-hold', __( 'To be paid at the table.', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
		return array( 'redirect' => $order->get_received_url() );
	}
}
