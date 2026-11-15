<?php
/**
 * The 14 allergens that EU and UK food law asks restaurants to declare.
 * Each item lists the ones it contains (meta _sidrest_allergens); menus
 * show them and can hide dishes containing the ones a guest picks, item
 * pages list them, and the kitchen screen prints them on each ticket.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Service;

use function SidcraftRestaurant\setting;

defined( 'ABSPATH' ) || exit;

/**
 * Class Allergens
 */
class Allergens {

	const META = '_sidrest_allergens';

	/**
	 * Slug => name.
	 *
	 * @return array
	 */
	public static function all() {
		static $cache = null;
		if ( null === $cache ) {
			$cache = array(
				'gluten'      => __( 'Gluten', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'crustaceans' => __( 'Crustaceans', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'eggs'        => __( 'Eggs', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'fish'        => __( 'Fish', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'peanuts'     => __( 'Peanuts', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'soya'        => __( 'Soya', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'milk'        => __( 'Milk', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'nuts'        => __( 'Tree nuts', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'celery'      => __( 'Celery', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'mustard'     => __( 'Mustard', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'sesame'      => __( 'Sesame', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'sulphites'   => __( 'Sulphites', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'lupin'       => __( 'Lupin', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'molluscs'    => __( 'Molluscs', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			);
		}
		return $cache;
	}

	/**
	 * Is the allergen list switched on?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) setting( 'allergens', 1 );
	}

	/**
	 * An item's allergens (slugs).
	 *
	 * @param int $item_id Item.
	 * @return string[]
	 */
	public static function of( $item_id ) {
		return array_values( array_intersect( array_keys( self::all() ), (array) get_post_meta( (int) $item_id, self::META, true ) ) );
	}

	/**
	 * Save an item's allergens.
	 *
	 * @param int      $item_id Item.
	 * @param string[] $slugs   Slugs.
	 */
	public static function save( $item_id, array $slugs ) {
		update_post_meta( (int) $item_id, self::META, array_values( array_intersect( array_keys( self::all() ), array_map( 'sanitize_key', $slugs ) ) ) );
	}

	/**
	 * Slugs → names.
	 *
	 * @param string[] $slugs Slugs.
	 * @return string[]
	 */
	public static function names( array $slugs ) {
		$all = self::all();
		$out = array();
		foreach ( $slugs as $slug ) {
			if ( isset( $all[ $slug ] ) ) {
				$out[ $slug ] = $all[ $slug ];
			}
		}
		return $out;
	}

	/**
	 * "Contains: Milk, Eggs" for a card or item page.
	 *
	 * @param int $item_id Item.
	 * @return string HTML.
	 */
	public static function html( $item_id ) {
		if ( ! self::enabled() ) {
			return '';
		}
		$names = self::names( self::of( $item_id ) );
		if ( ! $names ) {
			return '';
		}
		return '<p class="sidrest-item__allergens"><span class="sidrest-item__allergens-label">' . esc_html( \SidcraftRestaurant\label( 'contains' ) ) . ':</span> ' . esc_html( implode( ', ', $names ) ) . '</p>';
	}
}
