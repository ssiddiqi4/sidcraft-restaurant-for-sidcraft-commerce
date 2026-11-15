<?php
/**
 * Dietary and ethical icons (vegan, gluten-free, halal …).
 *
 * Each icon is a short code in a coloured circle, with the full name as its
 * accessible label and tooltip. Where schema.org has a matching diet, it is
 * added to the menu's structured data.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Dietary
 */
class Dietary {

	/**
	 * Slugs of the built-in icons (no translation needed; safe early).
	 *
	 * @return string[]
	 */
	public static function slugs() {
		return array( 'vegetarian', 'vegan', 'gluten-free', 'dairy-free', 'nut-free', 'egg-free', 'organic', 'halal', 'kosher', 'spicy', 'low-carb', 'sugar-free', 'local', 'fair-trade', 'sustainable' );
	}

	/**
	 * All icons: slug => [ label, code, color, diet (schema.org URL or '') ].
	 *
	 * @return array
	 */
	public static function all() {
		// Read several times per menu item: build the translated table once per locale.
		static $cache = array();
		$locale = function_exists( 'get_locale' ) ? get_locale() : '';
		if ( isset( $cache[ $locale ] ) ) {
			return (array) apply_filters( 'sidrest_dietary_icons', $cache[ $locale ] );
		}
		$diet = 'https://schema.org/';
		$all  = array(
			'vegetarian'  => array( __( 'Vegetarian', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'V', '#15803d', $diet . 'VegetarianDiet' ),
			'vegan'       => array( __( 'Vegan', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'VG', '#166534', $diet . 'VeganDiet' ),
			'gluten-free' => array( __( 'Gluten-free', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'GF', '#b45309', $diet . 'GlutenFreeDiet' ),
			'dairy-free'  => array( __( 'Dairy-free', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'DF', '#0369a1', $diet . 'LowLactoseDiet' ),
			'nut-free'    => array( __( 'Nut-free', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'NF', '#92400e', '' ),
			'egg-free'    => array( __( 'Egg-free', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'EF', '#a16207', '' ),
			'organic'     => array( __( 'Organic', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'O', '#4d7c0f', '' ),
			'halal'       => array( __( 'Halal', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'H', '#0f766e', $diet . 'HalalDiet' ),
			'kosher'      => array( __( 'Kosher', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'K', '#1d4ed8', $diet . 'KosherDiet' ),
			'spicy'       => array( __( 'Spicy', 'sidcraft-restaurant-for-sidcraft-commerce' ), '🌶', '#b91c1c', '' ),
			'low-carb'    => array( __( 'Low carb', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'LC', '#7c3aed', '' ),
			'sugar-free'  => array( __( 'Sugar-free', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'SF', '#be185d', $diet . 'DiabeticDiet' ),
			'local'       => array( __( 'Locally sourced', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'L', '#0e7490', '' ),
			'fair-trade'  => array( __( 'Fair trade', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'FT', '#854d0e', '' ),
			'sustainable' => array( __( 'Sustainably sourced', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'S', '#047857', '' ),
		);

		/**
		 * Filter the dietary icons. Add your own:
		 *     $icons['paleo'] = array( 'Paleo', 'P', '#6b21a8', '' );
		 *
		 * @param array $all slug => [ label, code, color, schema.org diet URL ].
		 */
		$cache[ $locale ] = $all;
		return (array) apply_filters( 'sidrest_dietary_icons', $all );
	}

	/**
	 * Icons switched on in the settings.
	 *
	 * @return array
	 */
	public static function enabled() {
		$on = (array) setting( 'dietary', self::slugs() );
		return array_intersect_key( self::all(), array_flip( $on ) );
	}

	/**
	 * One icon's HTML.
	 *
	 * @param string $slug Slug.
	 * @return string
	 */
	public static function icon( $slug ) {
		$all = self::all();
		if ( ! isset( $all[ $slug ] ) ) {
			return '';
		}
		list( $label, $code, $color ) = $all[ $slug ];
		return sprintf(
			'<span class="sidrest-diet sidrest-diet--%1$s" style="--sidrest-diet:%2$s" title="%3$s"><span aria-hidden="true">%4$s</span><span class="sidrest-sr">%3$s</span></span>',
			esc_attr( $slug ),
			esc_attr( $color ),
			esc_attr( $label ),
			esc_html( $code )
		);
	}
}
