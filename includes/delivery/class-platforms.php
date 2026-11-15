<?php
/**
 * The food-delivery platforms Sidcraft Restaurant works with, and their
 * per-platform settings.
 *
 * Each platform can be used two ways:
 *
 *  1. "Order on …" link — a button to the restaurant's own store page on
 *     that platform. The order and its payment happen on the platform.
 *  2. Delivery from your own checkout — the customer orders on your site and
 *     pays with your Sidcraft Commerce payment gateways (Stripe, PayPal,
 *     Razorpay, PayU, Mercado Pago or any add-on gateway). The delivery fee
 *     you set is added at checkout, and once the order is paid a courier is
 *     requested:
 *       - api:     automatically, through the platform's courier API
 *                  (DoorDash Drive; Uber Direct for Uber Eats and Postmates);
 *       - webhook: the paid order is sent as signed JSON to a URL you choose
 *                  (your integration or an order hub such as Deliverect,
 *                  Otter or UrbanPiper that connects to the platform);
 *       - manual:  staff book the courier in the platform's merchant app and
 *                  paste the tracking link on the order.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Delivery;

use function SidcraftRestaurant\setting;

defined( 'ABSPATH' ) || exit;

/**
 * Class Platforms
 */
class Platforms {

	/**
	 * All platforms: slug => [ name, note, api ('' | 'doordash' | 'uber'), example store URL ].
	 *
	 * @return array
	 */
	public static function all() {
		$list = array(
			'uber-eats'     => array( 'Uber Eats', __( 'Worldwide. Courier API: Uber Direct.', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'uber', 'https://www.ubereats.com/store/…' ),
			'doordash'      => array( 'DoorDash', __( 'US, Canada, Australia, New Zealand, Japan, Europe (Wolt). Courier API: DoorDash Drive.', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'doordash', 'https://www.doordash.com/store/…' ),
			'grubhub'       => array( 'Grubhub', __( 'United States.', 'sidcraft-restaurant-for-sidcraft-commerce' ), '', 'https://www.grubhub.com/restaurant/…' ),
			'postmates'     => array( 'Postmates', __( 'United States. Part of Uber — courier API: Uber Direct.', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'uber', 'https://postmates.com/store/…' ),
			'just-eat'      => array( 'Just Eat Takeaway.com', __( 'Just Eat, Takeaway.com, Lieferando, Thuisbezorgd, Pyszne.pl, SkipTheDishes and more.', 'sidcraft-restaurant-for-sidcraft-commerce' ), '', 'https://www.just-eat.co.uk/restaurants-…' ),
			'deliveroo'     => array( 'Deliveroo', __( 'UK, Ireland, France, Belgium, Italy, UAE, Kuwait, Qatar, Singapore, Hong Kong.', 'sidcraft-restaurant-for-sidcraft-commerce' ), '', 'https://deliveroo.co.uk/menu/…' ),
			'delivery-hero' => array( 'Delivery Hero', __( 'foodpanda, Glovo, talabat, PedidosYa, foodora, efood, Baemin and more.', 'sidcraft-restaurant-for-sidcraft-commerce' ), '', 'https://www.foodpanda.com/…' ),
			'meituan'       => array( 'Meituan', __( 'China (美团外卖).', 'sidcraft-restaurant-for-sidcraft-commerce' ), '', 'https://waimai.meituan.com/…' ),
			'eleme'         => array( 'Ele.me', __( 'China (饿了么).', 'sidcraft-restaurant-for-sidcraft-commerce' ), '', 'https://www.ele.me/…' ),
			'zomato'        => array( 'Zomato', __( 'India.', 'sidcraft-restaurant-for-sidcraft-commerce' ), '', 'https://www.zomato.com/…/order' ),
			'swiggy'        => array( 'Swiggy', __( 'India.', 'sidcraft-restaurant-for-sidcraft-commerce' ), '', 'https://www.swiggy.com/restaurants/…' ),
			'grabfood'      => array( 'GrabFood', __( 'Southeast Asia.', 'sidcraft-restaurant-for-sidcraft-commerce' ), '', 'https://food.grab.com/…' ),
			'ifood'         => array( 'iFood', __( 'Brazil.', 'sidcraft-restaurant-for-sidcraft-commerce' ), '', 'https://www.ifood.com.br/delivery/…' ),
			'rappi'         => array( 'Rappi', __( 'Latin America.', 'sidcraft-restaurant-for-sidcraft-commerce' ), '', 'https://www.rappi.com/restaurantes/…' ),
		);

		/**
		 * Filter the delivery platforms: slug => [ name, note, api, example URL ].
		 * "api" is '' (webhook or manual dispatch only), 'doordash' or 'uber'.
		 *
		 * @param array $list Platforms.
		 */
		return (array) apply_filters( 'sidrest_delivery_platforms', $list );
	}

	/**
	 * Platform name.
	 *
	 * @param string $slug Slug.
	 * @return string
	 */
	public static function name( $slug ) {
		$all = self::all();
		return isset( $all[ $slug ] ) ? (string) $all[ $slug ][0] : '';
	}

	/**
	 * Courier API a platform supports ('' when none).
	 *
	 * @param string $slug Slug.
	 * @return string
	 */
	public static function api( $slug ) {
		$all = self::all();
		return isset( $all[ $slug ] ) ? (string) $all[ $slug ][2] : '';
	}

	/**
	 * Dispatch modes a platform offers: mode => label.
	 *
	 * @param string $slug Slug.
	 * @return array
	 */
	public static function modes( $slug ) {
		$modes = array();
		if ( '' !== self::api( $slug ) ) {
			/* translators: %s: courier API name. */
			$modes['api'] = sprintf( __( 'Automatic (%s API)', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'uber' === self::api( $slug ) ? 'Uber Direct' : 'DoorDash Drive' );
		}
		$modes['webhook'] = __( 'Send the paid order to my webhook', 'sidcraft-restaurant-for-sidcraft-commerce' );
		$modes['manual']  = __( 'Manual (staff book the courier)', 'sidcraft-restaurant-for-sidcraft-commerce' );
		return $modes;
	}

	/**
	 * Default settings for one platform.
	 *
	 * @return array
	 */
	public static function platform_defaults() {
		return array(
			'link'      => 0,
			'url'       => '',
			'checkout'  => 0,
			'fee'       => '',
			'free_over' => '',
			'dispatch'  => '',
		);
	}

	/**
	 * Delivery settings with defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		$stored = (array) setting( 'delivery', array() );
		$out    = array_merge( self::global_defaults(), $stored );
		$rows   = array();
		foreach ( array_keys( self::all() ) as $slug ) {
			$row = isset( $stored['platforms'][ $slug ] ) && is_array( $stored['platforms'][ $slug ] ) ? $stored['platforms'][ $slug ] : array();
			$row = array_merge( self::platform_defaults(), $row );
			if ( ! array_key_exists( $row['dispatch'], self::modes( $slug ) ) ) {
				$row['dispatch'] = '' !== self::api( $slug ) ? 'api' : 'manual';
			}
			$rows[ $slug ] = $row;
		}
		$out['platforms'] = $rows;
		return $out;
	}

	/**
	 * Global delivery defaults.
	 *
	 * @return array
	 */
	public static function global_defaults() {
		return array(
			'links_on_menus'   => 1,
			'links_on_items'   => 1,
			'links_heading'    => '',
			'fulfilment'       => 0,
			'pickup'           => 1,
			'min_order'        => '',
			'postcodes'        => '',
			'prep_minutes'     => 20,
			'auto_dispatch'    => 1,
			'store_name'       => '',
			'store_phone'      => '',
			'store_address'    => '',
			'store_city'       => '',
			'store_state'      => '',
			'store_postcode'   => '',
			'store_country'    => '',
			'store_notes'      => '',
			'dd_developer_id'  => '',
			'dd_key_id'        => '',
			'dd_secret'        => '',
			'uber_client_id'   => '',
			'uber_secret'      => '',
			'uber_customer_id' => '',
			'webhook_url'      => '',
			'webhook_secret'   => '',
			// 1.2.0: delivery zones by distance (see Zones).
			'zones'            => array(),
			'geocoder'         => 'osm',
			'google_key'       => '',
			'store_lat'        => '',
			'store_lng'        => '',
			'platforms'        => array(),
		);
	}

	/**
	 * One global delivery setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$s = self::settings();
		return $s[ $key ] ?? null;
	}

	/**
	 * One platform's settings.
	 *
	 * @param string $slug Slug.
	 * @return array|null
	 */
	public static function platform( $slug ) {
		$s = self::settings();
		return $s['platforms'][ $slug ] ?? null;
	}

	/**
	 * Platforms with an "Order on …" link: slug => [ name, url ].
	 *
	 * @return array
	 */
	public static function links() {
		$out = array();
		foreach ( self::settings()['platforms'] as $slug => $row ) {
			if ( ! empty( $row['link'] ) && '' !== (string) $row['url'] ) {
				$out[ $slug ] = array( self::name( $slug ), (string) $row['url'] );
			}
		}
		return $out;
	}

	/**
	 * Platforms offered as delivery at your own checkout: slug => row.
	 *
	 * @return array
	 */
	public static function checkout_platforms() {
		$out = array();
		foreach ( self::settings()['platforms'] as $slug => $row ) {
			if ( ! empty( $row['checkout'] ) ) {
				$out[ $slug ] = $row;
			}
		}
		return $out;
	}
}
