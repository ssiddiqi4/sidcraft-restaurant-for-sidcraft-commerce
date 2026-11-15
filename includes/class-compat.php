<?php
/**
 * Multilingual sites: WPML (with wpml-config.xml) and Polylang.
 *
 * Menus, sections, items and categories are translatable. Menus and sections
 * store IDs; at display time every ID is swapped for its translation in the
 * current language (see tid()), so a translated menu needs no re-ordering.
 * Front-end labels are registered as strings to translate.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Compat
 */
class Compat {

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_filter( 'pll_get_post_types', array( $this, 'pll_post_types' ), 10, 2 );
		add_filter( 'pll_get_taxonomies', array( $this, 'pll_taxonomies' ), 10, 2 );
		add_action( 'init', array( $this, 'register_strings' ), 30 );
		add_filter( 'sidrest_label', array( $this, 'translate_label' ), 10, 2 );
	}

	/**
	 * Polylang: our post types are translatable.
	 *
	 * @param array $types        Types.
	 * @param bool  $is_settings  On the settings screen.
	 * @return array
	 */
	public function pll_post_types( $types, $is_settings ) {
		if ( ! $is_settings ) {
			foreach ( array( Post_Types::MENU, Post_Types::SECTION, Post_Types::ITEM ) as $type ) {
				$types[ $type ] = $type;
			}
		}
		return $types;
	}

	/**
	 * Polylang: categories are translatable.
	 *
	 * @param array $taxonomies  Taxonomies.
	 * @param bool  $is_settings On the settings screen.
	 * @return array
	 */
	public function pll_taxonomies( $taxonomies, $is_settings ) {
		if ( ! $is_settings ) {
			$taxonomies[ Post_Types::CATEGORY ] = Post_Types::CATEGORY;
		}
		return $taxonomies;
	}

	/**
	 * Register custom labels with WPML String Translation / Polylang.
	 */
	public function register_strings() {
		$custom = (array) setting( 'labels', array() );
		foreach ( $custom as $key => $text ) {
			if ( '' === trim( (string) $text ) ) {
				continue;
			}
			if ( function_exists( 'pll_register_string' ) ) {
				pll_register_string( 'sidrest_' . $key, (string) $text, 'Sidcraft Restaurant' );
			}
			do_action( 'wpml_register_single_string', 'Sidcraft Restaurant', 'label_' . $key, (string) $text ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own hook.
		}
	}

	/**
	 * Translate a custom label.
	 *
	 * @param string $text Text.
	 * @param string $key  Key.
	 * @return string
	 */
	public function translate_label( $text, $key ) {
		$custom = (array) setting( 'labels', array() );
		if ( empty( $custom[ $key ] ) ) {
			return $text;
		}
		if ( function_exists( 'pll__' ) ) {
			return pll__( $text );
		}
		return (string) apply_filters( 'wpml_translate_single_string', $text, 'Sidcraft Restaurant', 'label_' . $key ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own hook.
	}
}
