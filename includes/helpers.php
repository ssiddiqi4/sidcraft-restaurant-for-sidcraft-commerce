<?php
/**
 * Small helpers: settings, labels, styles, translation-aware IDs, prices.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Default settings.
 *
 * @return array
 */
function defaults() {
	return array(
		// General.
		'default_style'      => 'classic',
		'show_sidebar'       => 1,
		'show_toolbar'       => 1,
		'enable_search'      => 1,
		'enable_categories'  => 1,
		'enable_dietary'     => 1,
		'enable_sort'        => 1,
		'show_images'        => 1,
		'item_pages'         => 1,
		'show_legend'        => 1,
		'show_menu_title'    => 0,
		// Ordering.
		'hide_from_shop'     => 1,
		// Schema.
		'schema'             => 1,
		'restaurant_name'    => '',
		'cuisine'            => '',
		// Suppliers.
		'supplier_maps'      => 1,
		// Style.
		'accent'             => '#b45309',
		'text'               => '',
		'muted'              => '',
		'background'         => '',
		'card'               => '',
		'price_color'        => '',
		'border_color'       => '',
		'border_width'       => 1,
		'radius'             => 12,
		'heading_font'       => 'inherit',
		'body_font'          => 'inherit',
		'max_width'          => '',
		'sidebar_width'      => 220,
		'gap'                => 20,
		'image_ratio'        => '4/3',
		// Dietary icons and badges switched on.
		'dietary'            => Dietary::slugs(),
		'badges'             => array( 'new', 'special', 'chef', 'popular', 'seasonal' ),
		// Custom fields: [ key, label, on_menu (bool) ].
		'custom_fields'      => array(),
		// Labels (empty = default wording).
		'labels'             => array(),
		// Delivery platforms and courier settings (see Delivery\Platforms).
		'delivery'           => array(),
		// 1.2.0 — Opening hours (see Hours): weekday (0 = Sunday) => "11:00-14:30, 17:00-22:00".
		'hours'              => array(),
		'holidays'           => '',
		'ordering_paused'    => 0,
		'closed_mode'        => 'later',
		'hours_banner'       => 1,
		// Order ahead (see Schedule).
		'order_ahead'        => 1,
		'asap'               => 1,
		'slot_minutes'       => 15,
		'slot_capacity'      => 0,
		'order_days'         => 0,
		// Kitchen screen.
		'kitchen_sound'      => 1,
		'ready_email'        => 1,
		'ticket_width'       => 80,
		// Dine-in: one table name per line.
		'dine_in'            => 0,
		'tables'             => '',
		'pay_at_table'       => 1,
		// Tips.
		'tips'               => 0,
		'tip_presets'        => '10, 15, 20',
		'tip_custom'         => 1,
		'tip_for'            => 'all',
		// Allergens.
		'allergens'          => 1,
		'allergen_filter'    => 1,
		// Reservations.
		'bookings'           => 0,
		'booking_slot'       => 30,
		'booking_capacity'   => 20,
		'booking_max_party'  => 8,
		'booking_notice'     => 2,
		'booking_days'       => 60,
		'booking_confirm'    => 'auto',
		'booking_reminder'   => 1,
	);
}

/**
 * A setting with its default.
 *
 * @param string $key      Key.
 * @param mixed  $fallback Fallback when there is no default.
 * @return mixed
 */
function setting( $key, $fallback = null ) {
	$settings = all_settings();
	$value    = array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;

	/**
	 * Filter a Sidcraft Restaurant setting.
	 *
	 * @param mixed  $value Value.
	 * @param string $key   Key.
	 */
	return apply_filters( 'sidrest_setting', $value, $key );
}

/**
 * All settings merged over the defaults (cached per request).
 *
 * @param bool $reset Drop the cache (after saving).
 * @return array
 */
function all_settings( $reset = false ) {
	static $settings = null;
	if ( $reset ) {
		$settings = null;
		return array();
	}
	if ( null === $settings ) {
		$stored = get_option( 'sidrest_settings', null );
		if ( null === $stored && ! is_admin() ) {
			// Never saved yet: store an empty, autoloaded value so later requests
			// do not query for the missing option. (Admin hooks on add_option
			// flush rewrite rules, so this runs on the frontend only.)
			add_option( 'sidrest_settings', array(), '', true );
		}
		$settings = array_merge( defaults(), is_array( $stored ) ? $stored : array() );
	}
	return $settings;
}

/**
 * Front-end wording, each changeable under Restaurant → Settings → Labels.
 *
 * @return array key => default text.
 */
function default_labels() {
	// Called for every label on every menu item: translate the table once per locale.
	static $cache = array();
	$locale = function_exists( 'get_locale' ) ? get_locale() : '';
	if ( isset( $cache[ $locale ] ) ) {
		return $cache[ $locale ];
	}
	return $cache[ $locale ] = array(
		'search'         => __( 'Search the menu', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'all'            => __( 'All', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'categories'     => __( 'Categories', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'dietary'        => __( 'Dietary', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'sort'           => __( 'Sort', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'sort_menu'      => __( 'Menu order', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'sort_name_asc'  => __( 'Name (A–Z)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'sort_name_desc' => __( 'Name (Z–A)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'sort_price_asc' => __( 'Price (low to high)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'sort_price_dsc' => __( 'Price (high to low)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'sections'       => __( 'Sections', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'no_results'     => __( 'No dishes match your search.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'clear'          => __( 'Clear filters', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'legend'         => __( 'Dietary key', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'add'            => __( 'Add', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'choose'         => __( 'Choose', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'view_order'     => __( 'Your order', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'details'        => __( 'Details', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'suppliers'      => __( 'Where it comes from', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'related'        => __( 'You may also like', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'reviews'        => __( 'Reviews', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'scan'           => __( 'Scan to see our menu', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'badge_new'      => __( 'New', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'badge_special'  => __( 'Special', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'badge_chef'     => __( 'Chef’s pick', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'badge_popular'  => __( 'Popular', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'badge_seasonal' => __( 'Seasonal', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'badge_sale'     => __( 'Sale', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		// 1.2.0.
		'sold_out'        => __( 'Sold out today', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		/* translators: %s: times and days, e.g. "7:00 am–11:00 am, Mon, Tue". */
		'available_times' => __( 'Served %s', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'not_now'         => __( 'Not served right now', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'allergens'       => __( 'Allergens', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'contains'        => __( 'Contains', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'avoid'           => __( 'Hide dishes with', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'hours'           => __( 'Opening hours', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'book_table'      => __( 'Book a table', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		'deal_choose'     => __( 'Choose your %s', 'sidcraft-restaurant-for-sidcraft-commerce' ),
	);
}

/**
 * One front-end label (custom wording if set).
 *
 * @param string $key Label key.
 * @return string
 */
function label( $key ) {
	$custom = (array) setting( 'labels', array() );
	if ( isset( $custom[ $key ] ) && '' !== trim( (string) $custom[ $key ] ) ) {
		$text = (string) $custom[ $key ];
	} else {
		$defaults = default_labels();
		$text     = $defaults[ $key ] ?? $key;
	}

	/**
	 * Filter a front-end label.
	 *
	 * @param string $text Text.
	 * @param string $key  Key.
	 */
	return (string) apply_filters( 'sidrest_label', $text, $key );
}

/**
 * Menu styles: slug => label.
 *
 * @return array
 */
function styles() {
	return (array) apply_filters(
		'sidrest_styles',
		array(
			'classic'  => __( 'Classic (name … price)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'grid'     => __( 'Photo grid', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'list'     => __( 'Photo list', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'compact'  => __( 'Compact (two columns)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'luxe'     => __( 'Luxe (minimal)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'ordering' => __( 'Ordering (add to cart)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		)
	);
}

/**
 * Item badges: slug => label key.
 *
 * @return array slug => label.
 */
function badge_options() {
	$out = array();
	foreach ( array( 'new', 'special', 'chef', 'popular', 'seasonal' ) as $slug ) {
		$out[ $slug ] = label( 'badge_' . $slug );
	}
	return (array) apply_filters( 'sidrest_badges', $out );
}

/**
 * The ID of the post in the current language (WPML / Polylang), else as is.
 *
 * @param int    $id   Post or term ID.
 * @param string $type Post type or taxonomy.
 * @return int
 */
function tid( $id, $type ) {
	$id = (int) $id;
	if ( ! $id ) {
		return 0;
	}
	if ( function_exists( 'pll_get_post' ) && post_type_exists( $type ) ) {
		$translated = pll_get_post( $id );
		return $translated ? (int) $translated : $id;
	}
	return (int) apply_filters( 'wpml_object_id', $id, $type, true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own hook.
}

/**
 * Load posts, their meta and their terms in three queries instead of three per
 * post. Item, section and product objects read them from the cache after this.
 * IDs go through tid() first, the same as Item::get() and Section::get().
 *
 * @param int[]  $ids  Post IDs.
 * @param string $type Post type (for the translation lookup).
 * @return void
 */
function prime_posts( array $ids, $type ) {
	if ( ! function_exists( '_prime_post_caches' ) ) {
		return;
	}
	$out = array();
	foreach ( $ids as $id ) {
		$id = tid( (int) $id, $type );
		if ( $id ) {
			$out[ $id ] = $id;
		}
	}
	if ( $out ) {
		_prime_post_caches( array_values( $out ), true, true );
	}
}

/**
 * Load the Sidcraft Commerce products linked to orderable items in one batch.
 *
 * @param Item[] $items Items (their meta already cached).
 * @return void
 */
function prime_products( array $items ) {
	$ids = array();
	foreach ( $items as $item ) {
		$id = (int) $item->meta( 'product_id' );
		if ( $id && $item->meta( 'orderable' ) ) {
			$ids[ $id ] = $id;
		}
	}
	if ( $ids && function_exists( '_prime_post_caches' ) ) {
		_prime_post_caches( array_values( $ids ), false, true );
	}
}

/**
 * Format a price with the store's currency settings.
 *
 * @param float $amount Amount.
 * @return string
 */
function price( $amount ) {
	return \SidcraftCommerce\format_price( (float) $amount );
}

/**
 * Parse a typed price into a decimal string ('' when empty or invalid).
 *
 * @param mixed $raw Typed price.
 * @return string
 */
function parse_price( $raw ) {
	return \SidcraftCommerce\parse_price( $raw );
}

/**
 * Capability for managing menus and settings.
 *
 * @return string
 */
function manage_cap() {
	return (string) apply_filters( 'sidrest_manage_capability', 'edit_others_posts' );
}

/**
 * All terms of a taxonomy (empty ones included), fetched once per request.
 *
 * Shared with Sidcraft Builder Pro through the same non-persistent cache
 * group and key, so editor screens that list the same terms from both
 * plugins run a single query.
 *
 * @param string $taxonomy Taxonomy name.
 * @return \WP_Term[]
 */
function all_terms( $taxonomy ) {
	wp_cache_add_non_persistent_groups( array( 'sidcraft_term_lists' ) );
	$key   = $taxonomy . ':' . wp_cache_get_last_changed( 'terms' );
	$terms = wp_cache_get( $key, 'sidcraft_term_lists' );
	if ( false === $terms ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		$terms = is_array( $terms ) ? $terms : array();
		wp_cache_set( $key, $terms, 'sidcraft_term_lists' );
	}
	return $terms;
}
