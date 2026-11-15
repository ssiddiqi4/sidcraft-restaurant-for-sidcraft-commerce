<?php
/**
 * Courier APIs: DoorDash Drive and Uber Direct (used for Uber Eats and
 * Postmates). Each request books a courier from the restaurant to the
 * customer for an order that was already paid through Sidcraft Commerce.
 * The courier is billed to the restaurant's DoorDash or Uber account.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Delivery;

use SidcraftCommerce\Data\Order;

defined( 'ABSPATH' ) || exit;

/**
 * Class Couriers
 */
class Couriers {

	const DOORDASH_URL = 'https://openapi.doordash.com/drive/v2/deliveries';
	const UBER_AUTH    = 'https://auth.uber.com/oauth/v2/token';
	const UBER_API     = 'https://api.uber.com/v1/customers/';

	/**
	 * Request a courier.
	 *
	 * @param string $api         'doordash' | 'uber'.
	 * @param Order  $order       Paid order.
	 * @param string $external_id Our reference for this delivery.
	 * @return array|\WP_Error [ id, tracking_url, fee (float|null), status ].
	 */
	public static function request( $api, Order $order, $external_id ) {
		$missing = self::missing_settings( $api );
		if ( $missing ) {
			/* translators: %s: list of settings. */
			return new \WP_Error( 'sidrest_courier_settings', sprintf( __( 'Fill in these delivery settings first: %s.', 'sidcraft-restaurant-for-sidcraft-commerce' ), implode( ', ', $missing ) ) );
		}
		return 'uber' === $api ? self::uber( $order, $external_id ) : self::doordash( $order, $external_id );
	}

	/**
	 * Settings an API still needs (labels), [] when ready.
	 *
	 * @param string $api 'doordash' | 'uber'.
	 * @return string[]
	 */
	public static function missing_settings( $api ) {
		$need = array(
			'store_phone'   => __( 'Restaurant phone', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'store_address' => __( 'Street address', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'store_city'    => __( 'City', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		);
		if ( 'uber' === $api ) {
			$need += array(
				'uber_client_id'   => __( 'Uber client ID', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'uber_secret'      => __( 'Uber client secret', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'uber_customer_id' => __( 'Uber customer ID', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			);
		} else {
			$need += array(
				'dd_developer_id' => __( 'DoorDash developer ID', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'dd_key_id'       => __( 'DoorDash key ID', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'dd_secret'       => __( 'DoorDash signing secret', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			);
		}
		$out = array();
		foreach ( $need as $key => $label ) {
			if ( '' === trim( (string) Platforms::get( $key ) ) ) {
				$out[] = $label;
			}
		}
		return $out;
	}

	/*
	------------------------------------------------------------------
	 * DoorDash Drive
	 * ----------------------------------------------------------------
	 */

	/**
	 * Create a DoorDash Drive delivery.
	 *
	 * @param Order  $order       Order.
	 * @param string $external_id Reference.
	 * @return array|\WP_Error
	 */
	protected static function doordash( Order $order, $external_id ) {
		$billing = $order->get_billing();
		$body    = array(
			'external_delivery_id'  => $external_id,
			'pickup_address'        => self::one_line( self::store_address() ),
			'pickup_business_name'  => self::store_name(),
			'pickup_phone_number'   => self::phone( (string) Platforms::get( 'store_phone' ) ),
			'pickup_instructions'   => self::limit( (string) Platforms::get( 'store_notes' ), 280 ),
			/* translators: %d: order number. */
			'pickup_reference_tag'  => sprintf( __( 'Order #%d', 'sidcraft-restaurant-for-sidcraft-commerce' ), $order->get_id() ),
			'dropoff_address'       => self::one_line( self::customer_address( $billing ) ),
			'dropoff_phone_number'  => self::phone( (string) ( $billing['phone'] ?? '' ) ),
			'dropoff_instructions'  => self::limit( (string) get_post_meta( $order->get_id(), '_sidccofo_notes', true ), 280 ),
			'order_value'           => \SidcraftCommerce\to_minor_units( $order->get_subtotal(), $order->get_currency() ),
		);
		$prep = absint( Platforms::get( 'prep_minutes' ) );
		if ( $prep > 0 ) {
			$body['pickup_time'] = gmdate( 'Y-m-d\TH:i:s\Z', time() + $prep * MINUTE_IN_SECONDS );
		}
		$body = array_filter(
			$body,
			static function ( $v ) {
				return '' !== $v && null !== $v;
			}
		);

		/**
		 * Filter the DoorDash Drive request body.
		 *
		 * @param array $body  Body.
		 * @param Order $order Order.
		 */
		$body = (array) apply_filters( 'sidrest_doordash_request', $body, $order );

		$response = wp_remote_post(
			self::DOORDASH_URL,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . self::doordash_jwt(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		$data = self::decode( $response, 'DoorDash' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return array(
			'id'           => (string) ( $data['external_delivery_id'] ?? $external_id ),
			'tracking_url' => (string) ( $data['tracking_url'] ?? '' ),
			'fee'          => isset( $data['fee'] ) ? \SidcraftCommerce\from_minor_units( (int) $data['fee'], $order->get_currency() ) : null,
			'status'       => (string) ( $data['delivery_status'] ?? 'created' ),
		);
	}

	/**
	 * A short-lived DoorDash JWT (HS256, DD-JWT-V1).
	 *
	 * @return string
	 */
	protected static function doordash_jwt() {
		$now     = time();
		$header  = array(
			'alg'    => 'HS256',
			'typ'    => 'JWT',
			'dd-ver' => 'DD-JWT-V1',
		);
		$payload = array(
			'aud' => 'doordash',
			'iss' => trim( (string) Platforms::get( 'dd_developer_id' ) ),
			'kid' => trim( (string) Platforms::get( 'dd_key_id' ) ),
			'exp' => $now + 300,
			'iat' => $now,
		);
		$secret  = self::b64url_decode( trim( (string) Platforms::get( 'dd_secret' ) ) );
		$signing = self::b64url( wp_json_encode( $header ) ) . '.' . self::b64url( wp_json_encode( $payload ) );
		return $signing . '.' . self::b64url( hash_hmac( 'sha256', $signing, $secret, true ) );
	}

	/*
	------------------------------------------------------------------
	 * Uber Direct (Uber Eats, Postmates)
	 * ----------------------------------------------------------------
	 */

	/**
	 * Create an Uber Direct delivery (quote, then delivery with that quote).
	 *
	 * @param Order  $order       Order.
	 * @param string $external_id Reference.
	 * @return array|\WP_Error
	 */
	protected static function uber( Order $order, $external_id ) {
		$token = self::uber_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$billing  = $order->get_billing();
		$base     = self::UBER_API . rawurlencode( trim( (string) Platforms::get( 'uber_customer_id' ) ) );
		$pickup   = wp_json_encode( self::uber_address( self::store_address() ) );
		$dropoff  = wp_json_encode( self::uber_address( self::customer_address( $billing ) ) );
		$headers  = array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
		);
		$quote    = self::decode(
			wp_remote_post(
				$base . '/delivery_quotes',
				array(
					'timeout' => 20,
					'headers' => $headers,
					'body'    => wp_json_encode(
						array(
							'pickup_address'  => $pickup,
							'dropoff_address' => $dropoff,
						)
					),
				)
			),
			'Uber'
		);
		if ( is_wp_error( $quote ) ) {
			return $quote;
		}

		$manifest = array();
		foreach ( $order->get_items() as $item ) {
			$manifest[] = array(
				'name'     => self::limit( (string) $item['name'], 100 ),
				'quantity' => max( 1, (int) $item['quantity'] ),
				'size'     => 'small',
				'price'    => \SidcraftCommerce\to_minor_units( (float) $item['price'], $order->get_currency() ),
			);
		}
		$body = array(
			'quote_id'              => (string) ( $quote['id'] ?? '' ),
			'external_id'           => $external_id,
			'pickup_name'           => self::store_name(),
			'pickup_address'        => $pickup,
			'pickup_phone_number'   => self::phone( (string) Platforms::get( 'store_phone' ) ),
			'pickup_notes'          => self::limit( (string) Platforms::get( 'store_notes' ), 280 ),
			'dropoff_name'          => trim( ( $billing['first_name'] ?? '' ) . ' ' . ( $billing['last_name'] ?? '' ) ),
			'dropoff_address'       => $dropoff,
			'dropoff_phone_number'  => self::phone( (string) ( $billing['phone'] ?? '' ) ),
			'dropoff_notes'         => self::limit( (string) get_post_meta( $order->get_id(), '_sidccofo_notes', true ), 280 ),
			'manifest_items'        => $manifest,
			/* translators: %d: order number. */
			'manifest_reference'    => sprintf( __( 'Order #%d', 'sidcraft-restaurant-for-sidcraft-commerce' ), $order->get_id() ),
			'manifest_total_value'  => \SidcraftCommerce\to_minor_units( $order->get_subtotal(), $order->get_currency() ),
		);
		$prep = absint( Platforms::get( 'prep_minutes' ) );
		if ( $prep > 0 ) {
			$body['pickup_ready_dt'] = gmdate( 'Y-m-d\TH:i:s\Z', time() + $prep * MINUTE_IN_SECONDS );
		}
		$body = array_filter(
			$body,
			static function ( $v ) {
				return '' !== $v && null !== $v;
			}
		);

		/**
		 * Filter the Uber Direct request body.
		 *
		 * @param array $body  Body.
		 * @param Order $order Order.
		 */
		$body = (array) apply_filters( 'sidrest_uber_request', $body, $order );

		$data = self::decode(
			wp_remote_post(
				$base . '/deliveries',
				array(
					'timeout' => 20,
					'headers' => $headers,
					'body'    => wp_json_encode( $body ),
				)
			),
			'Uber'
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return array(
			'id'           => (string) ( $data['id'] ?? $external_id ),
			'tracking_url' => (string) ( $data['tracking_url'] ?? '' ),
			'fee'          => isset( $data['fee'] ) ? \SidcraftCommerce\from_minor_units( (int) $data['fee'], (string) ( $data['currency'] ?? $order->get_currency() ) ) : null,
			'status'       => (string) ( $data['status'] ?? 'pending' ),
		);
	}

	/**
	 * OAuth token for Uber Direct (cached until shortly before it expires).
	 *
	 * @return string|\WP_Error
	 */
	protected static function uber_token() {
		$client = trim( (string) Platforms::get( 'uber_client_id' ) );
		$cache  = 'sidrest_uber_token_' . md5( $client );
		$token  = get_transient( $cache );
		if ( is_string( $token ) && '' !== $token ) {
			return $token;
		}
		$data = self::decode(
			wp_remote_post(
				self::UBER_AUTH,
				array(
					'timeout' => 20,
					'body'    => array(
						'client_id'     => $client,
						'client_secret' => trim( (string) Platforms::get( 'uber_secret' ) ),
						'grant_type'    => 'client_credentials',
						'scope'         => 'eats.deliveries',
					),
				)
			),
			'Uber'
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['access_token'] ) ) {
			return new \WP_Error( 'sidrest_uber_auth', __( 'Uber did not return an access token. Check the client ID and secret.', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
		}
		$ttl = max( 60, (int) ( $data['expires_in'] ?? 3600 ) - 300 );
		set_transient( $cache, (string) $data['access_token'], $ttl );
		return (string) $data['access_token'];
	}

	/**
	 * Uber's structured address.
	 *
	 * @param array $a street, street2, city, state, postcode, country.
	 * @return array
	 */
	protected static function uber_address( array $a ) {
		return array(
			'street_address' => array_values( array_filter( array( $a['street'], $a['street2'] ) ) ),
			'city'           => $a['city'],
			'state'          => $a['state'],
			'zip_code'       => $a['postcode'],
			'country'        => $a['country'],
		);
	}

	/*
	------------------------------------------------------------------
	 * Shared
	 * ----------------------------------------------------------------
	 */

	/**
	 * The restaurant's name.
	 *
	 * @return string
	 */
	public static function store_name() {
		$name = trim( (string) Platforms::get( 'store_name' ) );
		if ( '' === $name ) {
			$name = trim( (string) \SidcraftRestaurant\setting( 'restaurant_name', '' ) );
		}
		return '' !== $name ? $name : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * The restaurant's address parts.
	 *
	 * @return array
	 */
	public static function store_address() {
		return array(
			'street'   => trim( (string) Platforms::get( 'store_address' ) ),
			'street2'  => '',
			'city'     => trim( (string) Platforms::get( 'store_city' ) ),
			'state'    => trim( (string) Platforms::get( 'store_state' ) ),
			'postcode' => trim( (string) Platforms::get( 'store_postcode' ) ),
			'country'  => strtoupper( trim( (string) Platforms::get( 'store_country' ) ) ),
		);
	}

	/**
	 * The customer's address parts from the checkout fields.
	 *
	 * @param array $billing Billing fields.
	 * @return array
	 */
	public static function customer_address( array $billing ) {
		return array(
			'street'   => trim( (string) ( $billing['address_1'] ?? '' ) ),
			'street2'  => trim( (string) ( $billing['address_2'] ?? '' ) ),
			'city'     => trim( (string) ( $billing['city'] ?? '' ) ),
			'state'    => trim( (string) ( $billing['state'] ?? '' ) ),
			'postcode' => trim( (string) ( $billing['postcode'] ?? '' ) ),
			'country'  => strtoupper( trim( (string) ( $billing['country'] ?? '' ) ) ),
		);
	}

	/**
	 * "Street, Apt, City, ST 12345, US".
	 *
	 * @param array $a Address parts.
	 * @return string
	 */
	public static function one_line( array $a ) {
		$region = trim( $a['state'] . ' ' . $a['postcode'] );
		return implode( ', ', array_filter( array( $a['street'], $a['street2'], $a['city'], $region, $a['country'] ), 'strlen' ) );
	}

	/**
	 * Keep "+" and digits (couriers want E.164, e.g. +16505551234).
	 *
	 * @param string $phone Phone.
	 * @return string
	 */
	public static function phone( $phone ) {
		$phone = trim( $phone );
		$plus  = 0 === strpos( $phone, '+' ) ? '+' : '';
		$digit = preg_replace( '/\D+/', '', $phone );
		return '' === $digit ? '' : $plus . $digit;
	}

	/**
	 * Trim text to a length.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	protected static function limit( $text, $max ) {
		$text = trim( wp_strip_all_tags( $text ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	}

	/**
	 * JSON body of a 2xx response, or an error with the API's message.
	 *
	 * @param array|\WP_Error $response Response.
	 * @param string          $service  Service name for messages.
	 * @return array|\WP_Error
	 */
	protected static function decode( $response, $service ) {
		if ( is_wp_error( $response ) ) {
			/* translators: 1: service, 2: error. */
			return new \WP_Error( 'sidrest_courier_http', sprintf( __( '%1$s could not be reached: %2$s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $service, $response->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data = is_array( $data ) ? $data : array();
		if ( $code < 200 || $code >= 300 ) {
			$message = (string) ( $data['message'] ?? $data['error_description'] ?? $data['error'] ?? '' );
			if ( ! empty( $data['field_errors'] ) && is_array( $data['field_errors'] ) ) {
				$parts = array();
				foreach ( $data['field_errors'] as $field_error ) {
					$parts[] = is_array( $field_error ) ? trim( ( $field_error['field'] ?? '' ) . ': ' . ( $field_error['error'] ?? '' ) ) : (string) $field_error;
				}
				$message .= ' ' . implode( '; ', $parts );
			}
			/* translators: 1: service, 2: HTTP status, 3: message. */
			return new \WP_Error( 'sidrest_courier_api', trim( sprintf( __( '%1$s returned an error (%2$d). %3$s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $service, $code, $message ) ) );
		}
		return $data;
	}

	/**
	 * Base64url encode.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	protected static function b64url( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- JWT encoding.
	}

	/**
	 * Base64url decode (DoorDash signing secrets are base64url).
	 *
	 * @param string $data Data.
	 * @return string
	 */
	protected static function b64url_decode( $data ) {
		$data = strtr( $data, '-_', '+/' );
		$pad  = strlen( $data ) % 4;
		if ( $pad ) {
			$data .= str_repeat( '=', 4 - $pad );
		}
		$raw = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- JWT signing secret.
		return false === $raw ? '' : $raw;
	}
}
