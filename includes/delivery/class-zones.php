<?php
/**
 * Delivery zones by distance: "up to 3 km: 2.99, up to 6 km: 4.99 (minimum
 * order 20)". The customer's address is turned into coordinates once
 * (OpenStreetMap Nominatim, or Google with an API key; cached for 30 days)
 * and the zone it falls in sets the delivery fee; farther than the last
 * zone, delivery isn't offered. An alternative to postcode lists.
 *
 * Settings (delivery): zones [ [ km, fee, min ] ], geocoder (osm|google),
 * google_key, store_lat, store_lng (empty: the restaurant's address is
 * looked up once).
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Delivery;

use SidcraftCommerce\Cart\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Class Zones
 */
class Zones {

	const SESSION = 'sidrest_distance';

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_filter( 'sidrest_fulfilment_choices', array( $this, 'choices' ), 20, 2 );
		add_filter( 'sidcraft_commerce_checkout_errors', array( $this, 'checkout_errors' ), 15, 2 );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Zones, nearest first: [ km, fee, min ].
	 *
	 * @return array[]
	 */
	public static function zones() {
		$out = array();
		foreach ( (array) Platforms::get( 'zones' ) as $row ) {
			$km = (float) ( $row['km'] ?? 0 );
			if ( $km > 0 ) {
				$out[] = array(
					'km'  => $km,
					'fee' => max( 0.0, (float) ( $row['fee'] ?? 0 ) ),
					'min' => max( 0.0, (float) ( $row['min'] ?? 0 ) ),
				);
			}
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $a['km'] <=> $b['km'];
			}
		);
		return $out;
	}

	/**
	 * Are zones in use?
	 *
	 * @return bool
	 */
	public static function active() {
		return (bool) self::zones();
	}

	/**
	 * The zone for a distance, or null when too far.
	 *
	 * @param float $km Distance.
	 * @return array|null
	 */
	public static function zone_for( $km ) {
		foreach ( self::zones() as $zone ) {
			if ( $km <= $zone['km'] ) {
				return $zone;
			}
		}
		return null;
	}

	/**
	 * Great-circle distance in km.
	 *
	 * @param float[] $a [ lat, lng ].
	 * @param float[] $b [ lat, lng ].
	 * @return float
	 */
	public static function km( array $a, array $b ) {
		$r    = 6371.0;
		$dlat = deg2rad( $b[0] - $a[0] );
		$dlng = deg2rad( $b[1] - $a[1] );
		$h    = sin( $dlat / 2 ) ** 2 + cos( deg2rad( $a[0] ) ) * cos( deg2rad( $b[0] ) ) * sin( $dlng / 2 ) ** 2;
		return round( 2 * $r * asin( min( 1.0, sqrt( $h ) ) ), 2 );
	}

	/**
	 * The restaurant's coordinates.
	 *
	 * @return float[]|null
	 */
	public static function store_point() {
		$lat = Platforms::get( 'store_lat' );
		$lng = Platforms::get( 'store_lng' );
		if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
			return array( (float) $lat, (float) $lng );
		}
		$address = implode( ', ', array_filter( array( Platforms::get( 'store_address' ), Platforms::get( 'store_city' ), Platforms::get( 'store_postcode' ), Platforms::get( 'store_country' ) ) ) );
		return '' === $address ? null : self::geocode( $address );
	}

	/**
	 * Coordinates of an address (cached 30 days; null when not found).
	 *
	 * @param string $address One-line address.
	 * @return float[]|null
	 */
	public static function geocode( $address ) {
		$address = trim( preg_replace( '/\s+/', ' ', (string) $address ) );
		if ( '' === $address ) {
			return null;
		}
		$key    = 'sidrest_geo_' . md5( strtolower( $address ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached ? $cached : null;
		}

		/**
		 * Short-circuit geocoding (tests, another service): return [ lat, lng ], or false for "not found".
		 *
		 * @param array|false|null $point   Null to let the plugin look it up.
		 *                                  (Its own lookups return null when the service can't be reached.)
		 * @param string           $address Address.
		 */
		$point = apply_filters( 'sidrest_geocode', null, $address );
		if ( null === $point ) {
			$point = 'google' === Platforms::get( 'geocoder' ) && '' !== (string) Platforms::get( 'google_key' ) ? self::google( $address ) : self::osm( $address );
		}
		if ( null === $point ) {
			return null; // The service didn't answer: try again next time.
		}
		$point = is_array( $point ) && 2 === count( $point ) ? array( (float) $point[0], (float) $point[1] ) : array();
		// "Not found" is remembered for a day only (the address may be fixed).
		set_transient( $key, $point, $point ? 30 * DAY_IN_SECONDS : DAY_IN_SECONDS );
		return $point ? $point : null;
	}

	/**
	 * OpenStreetMap Nominatim.
	 *
	 * @param string $address Address.
	 * @return float[]|false|null False: not found. Null: no answer.
	 */
	protected static function osm( $address ) {
		$response = wp_remote_get(
			add_query_arg(
				array(
					'format' => 'jsonv2',
					'limit'  => 1,
					'q'      => rawurlencode( $address ),
				),
				'https://nominatim.openstreetmap.org/search'
			),
			array(
				'timeout'    => 8,
				'user-agent' => 'SidcraftRestaurant/' . SIDREST_VERSION . ' (' . home_url( '/' ) . ')',
			)
		);
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return isset( $data[0]['lat'], $data[0]['lon'] ) ? array( $data[0]['lat'], $data[0]['lon'] ) : false;
	}

	/**
	 * Google Geocoding API.
	 *
	 * @param string $address Address.
	 * @return float[]|false|null False: not found. Null: no answer.
	 */
	protected static function google( $address ) {
		$response = wp_remote_get(
			add_query_arg(
				array(
					'address' => rawurlencode( $address ),
					'key'     => (string) Platforms::get( 'google_key' ),
				),
				'https://maps.googleapis.com/maps/api/geocode/json'
			),
			array( 'timeout' => 8 )
		);
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( isset( $data['status'] ) && ! in_array( $data['status'], array( 'OK', 'ZERO_RESULTS' ), true ) ) {
			return null; // Key, quota or billing problem: not the address's fault.
		}
		$loc = $data['results'][0]['geometry']['location'] ?? null;
		return is_array( $loc ) ? array( $loc['lat'], $loc['lng'] ) : false;
	}

	/**
	 * Distance of an address from the restaurant (km), or null.
	 *
	 * @param array $fields address_1, city, postcode, country.
	 * @return float|null
	 */
	public static function distance( array $fields ) {
		$store = self::store_point();
		if ( ! $store || '' === trim( (string) ( $fields['address_1'] ?? '' ) ) ) {
			return null;
		}
		$point = self::geocode( implode( ', ', array_filter( array( $fields['address_1'] ?? '', $fields['city'] ?? '', $fields['postcode'] ?? '', $fields['country'] ?? '' ) ) ) );
		return $point ? self::km( $store, $point ) : null;
	}

	/**
	 * Delivery fees by zone once the distance is known; "from …" before.
	 *
	 * @param array $choices key => [ label, fee, platform ].
	 * @param Cart  $cart    Cart.
	 * @return array
	 */
	public function choices( $choices, $cart ) {
		if ( ! self::active() ) {
			return $choices;
		}
		$km   = $cart->session()->get( self::SESSION, null );
		$zone = is_numeric( $km ) ? self::zone_for( (float) $km ) : null;
		foreach ( $choices as $key => $row ) {
			if ( '' === $row[2] ) {
				continue; // Pickup, table.
			}
			if ( is_numeric( $km ) && ! $zone ) {
				unset( $choices[ $key ] ); // Too far for delivery.
				continue;
			}
			$fee             = $zone ? $zone['fee'] : min( wp_list_pluck( self::zones(), 'fee' ) );
			$free            = (float) ( Platforms::platform( $row[2] )['free_over'] ?? 0 );
			$fee             = $free > 0 && max( 0.0, $cart->get_goods_subtotal() - $cart->get_discount() ) >= $free ? 0.0 : $fee;
			$choices[ $key ] = array(
				sprintf(
					/* translators: 1: platform, 2: fee, "free" or "from 2.99". */
					__( 'Delivery by %1$s (%2$s)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					Platforms::name( $row[2] ),
					$zone ? ( $fee > 0 ? Delivery::plain_price( $fee ) : __( 'free', 'sidcraft-restaurant-for-sidcraft-commerce' ) )
						/* translators: %s: lowest fee. */
						: sprintf( __( 'from %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), Delivery::plain_price( $fee ) )
				),
				$fee,
				$row[2],
			);
		}
		return $choices;
	}

	/**
	 * Check the address at checkout (distance, zone fee, zone minimum).
	 *
	 * @param array $errors  field => message.
	 * @param array $billing Checkout fields.
	 * @return array
	 */
	public function checkout_errors( $errors, $billing ) {
		if ( ! self::active() || isset( $errors[ Delivery::FIELD ] ) ) {
			return $errors;
		}
		$cart    = Cart::instance();
		$choices = Delivery::choices( $cart );
		$chosen  = (string) ( $billing[ Delivery::FIELD ] ?? '' );
		if ( ! isset( $choices[ $chosen ] ) || '' === $choices[ $chosen ][2] ) {
			return $errors;
		}
		$km = self::distance( $billing );
		if ( null === $km ) {
			$errors['address_1'] = __( 'We couldn’t find this address. Check it, or choose pickup.', 'sidcraft-restaurant-for-sidcraft-commerce' );
			return $errors;
		}
		$known = $cart->session()->get( self::SESSION, null );
		$cart->session()->set( self::SESSION, $km );
		$zone = self::zone_for( $km );
		if ( ! $zone ) {
			/* translators: %s: distance in km. */
			$errors['address_1'] = sprintf( __( 'Sorry, this address is %s km away, too far for delivery. Choose pickup instead.', 'sidcraft-restaurant-for-sidcraft-commerce' ), number_format_i18n( $km, 1 ) );
			return $errors;
		}
		if ( ! is_numeric( $known ) || ( self::zone_for( (float) $known ) ?? array() ) !== $zone ) {
			// The fee shown was for another (or no) zone.
			Delivery::refresh( $cart );
			$errors[ Delivery::FIELD ] = __( 'The delivery fee was updated for your address. Check the total, then place the order again.', 'sidcraft-restaurant-for-sidcraft-commerce' );
			return $errors;
		}
		if ( $zone['min'] > 0 && $cart->get_goods_subtotal() - $cart->get_discount() < $zone['min'] ) {
			/* translators: %s: amount. */
			$errors[ Delivery::FIELD ] = sprintf( __( 'The minimum order for delivery to your address is %s.', 'sidcraft-restaurant-for-sidcraft-commerce' ), Delivery::plain_price( $zone['min'] ) );
		}
		return $errors;
	}

	/**
	 * REST: POST address (from the checkout script when the address changes).
	 */
	public function routes() {
		register_rest_route(
			Delivery::NS,
			'/address',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Only this visitor's session.
				'callback'            => array( $this, 'rest_address' ),
			)
		);
	}

	/**
	 * Work out the distance for the typed address and re-price.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_address( \WP_REST_Request $request ) {
		$cart = Cart::instance();
		if ( ! self::active() || $cart->is_empty() ) {
			return rest_ensure_response( array( 'ok' => false ) );
		}
		if ( function_exists( '\SidcraftCommerce\rate_limit' ) && ! \SidcraftCommerce\rate_limit( 'sidrest_geo', 30, HOUR_IN_SECONDS ) ) {
			return rest_ensure_response( array( 'ok' => false ) );
		}
		$fields = array();
		foreach ( array( 'address_1', 'city', 'postcode', 'country' ) as $key ) {
			$fields[ $key ] = sanitize_text_field( (string) $request->get_param( $key ) );
		}
		$km = self::distance( $fields );
		$cart->session()->set( self::SESSION, $km );
		Delivery::refresh( $cart );
		$zone     = null === $km ? null : self::zone_for( $km );
		$response = rest_ensure_response(
			array(
				'ok'        => null !== $km,
				'km'        => $km,
				'delivers'  => (bool) $zone,
				'fragments' => \SidcraftCommerce\Fragments::collect( 'checkout' ),
				'choices'   => wp_list_pluck( Delivery::choices( $cart ), 0 ),
			)
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
