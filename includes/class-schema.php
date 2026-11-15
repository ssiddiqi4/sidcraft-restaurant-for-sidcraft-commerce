<?php
/**
 * schema.org structured data for menus shown on the page (Menu →
 * MenuSection → MenuItem with Offers and suitableForDiet), printed once as
 * JSON-LD in the footer.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schema
 */
class Schema {

	/**
	 * Menus shown: id => [ name, url, sections ].
	 *
	 * @var array
	 */
	protected static $menus = array();

	/**
	 * Loose sections shown: [ name, description, items ].
	 *
	 * @var array
	 */
	protected static $sections = array();

	/**
	 * Loose items shown.
	 *
	 * @var Item[]
	 */
	protected static $items = array();

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'wp_footer', array( $this, 'output' ), 20 );
	}

	/**
	 * Record a menu.
	 *
	 * @param Menu  $menu     Menu.
	 * @param array $sections Each: section, items.
	 */
	public static function add_menu( Menu $menu, array $sections ) {
		if ( is_admin() || isset( self::$menus[ $menu->get_id() ] ) ) {
			return;
		}
		self::$menus[ $menu->get_id() ] = array(
			'menu'     => $menu,
			'sections' => $sections,
		);
	}

	/**
	 * Record a section shown on its own.
	 *
	 * @param Section $section Section.
	 * @param Item[]  $items   Items.
	 */
	public static function add_section( Section $section, array $items ) {
		if ( ! is_admin() ) {
			self::$sections[ $section->get_id() ] = array(
				'section' => $section,
				'items'   => $items,
			);
		}
	}

	/**
	 * Record items shown on their own.
	 *
	 * @param Item[] $items Items.
	 */
	public static function add_items( array $items ) {
		if ( ! is_admin() ) {
			foreach ( $items as $item ) {
				self::$items[ $item->get_id() ] = $item;
			}
		}
	}

	/**
	 * One MenuItem.
	 *
	 * @param Item $item Item.
	 * @return array
	 */
	public static function item( Item $item ) {
		$data = array(
			'@type' => 'MenuItem',
			'name'  => wp_strip_all_tags( $item->get_name() ),
		);
		if ( '' !== $item->get_description() ) {
			$data['description'] = $item->get_description();
		}
		if ( setting( 'item_pages', 1 ) ) {
			$data['url'] = $item->get_url();
		}
		$image = $item->get_image_id() ? wp_get_attachment_image_url( $item->get_image_id(), 'large' ) : '';
		if ( $image ) {
			$data['image'] = $image;
		}
		$currency = \SidcraftCommerce\currency();
		$offers   = array();
		foreach ( $item->get_current_prices() as $row ) {
			$offer = array(
				'@type'         => 'Offer',
				'price'         => number_format( (float) $row['price'], 2, '.', '' ),
				'priceCurrency' => $currency,
			);
			if ( '' !== $row['label'] ) {
				$offer['name'] = $row['label'];
			}
			$offers[] = $offer;
		}
		if ( $offers ) {
			$data['offers'] = 1 === count( $offers ) ? $offers[0] : $offers;
		}
		$all   = Dietary::all();
		$diets = array();
		foreach ( $item->get_dietary() as $slug ) {
			if ( ! empty( $all[ $slug ][3] ) ) {
				$diets[] = $all[ $slug ][3];
			}
		}
		if ( $diets ) {
			$data['suitableForDiet'] = 1 === count( $diets ) ? $diets[0] : $diets;
		}

		/**
		 * Filter one MenuItem's structured data.
		 *
		 * @param array $data Data.
		 * @param Item  $item Item.
		 */
		return (array) apply_filters( 'sidrest_schema_item', $data, $item );
	}

	/**
	 * One MenuSection.
	 *
	 * @param Section $section Section.
	 * @param Item[]  $items   Items.
	 * @return array
	 */
	public static function section( Section $section, array $items ) {
		$data = array(
			'@type'       => 'MenuSection',
			'name'        => wp_strip_all_tags( $section->get_title() ),
			'hasMenuItem' => array_map( array( __CLASS__, 'item' ), $items ),
		);
		if ( '' !== $section->get_note() ) {
			$data['description'] = $section->get_note();
		}
		return $data;
	}

	/**
	 * Print the JSON-LD.
	 */
	public function output() {
		if ( ! setting( 'schema', 1 ) || ( ! self::$menus && ! self::$sections && ! self::$items ) ) {
			return;
		}
		$graph = array();
		foreach ( self::$menus as $entry ) {
			$menu = array(
				'@type'          => 'Menu',
				'name'           => wp_strip_all_tags( $entry['menu']->get_title() ),
				'url'            => is_singular() ? get_permalink() : $entry['menu']->get_url(),
				'inLanguage'     => get_bloginfo( 'language' ),
				'hasMenuSection' => array(),
			);
			foreach ( $entry['sections'] as $block ) {
				$menu['hasMenuSection'][] = self::section( $block['section'], $block['items'] );
			}
			$graph[] = $menu;
		}
		if ( self::$sections ) {
			$menu = array(
				'@type'          => 'Menu',
				'hasMenuSection' => array(),
			);
			foreach ( self::$sections as $block ) {
				$menu['hasMenuSection'][] = self::section( $block['section'], $block['items'] );
			}
			$graph[] = $menu;
		}
		foreach ( self::$items as $item ) {
			$graph[] = self::item( $item );
		}

		$name = trim( (string) setting( 'restaurant_name', '' ) );
		if ( '' !== $name ) {
			$restaurant = array(
				'@type'   => 'Restaurant',
				'name'    => $name,
				'url'     => home_url( '/' ),
				'hasMenu' => 1 === count( $graph ) ? $graph[0] : $graph,
			);
			if ( '' !== trim( (string) setting( 'cuisine', '' ) ) ) {
				$restaurant['servesCuisine'] = array_map( 'trim', explode( ',', (string) setting( 'cuisine', '' ) ) );
			}
			$data = array_merge( array( '@context' => 'https://schema.org' ), $restaurant );
		} else {
			$data = array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			);
		}

		/**
		 * Filter the full JSON-LD block.
		 *
		 * @param array $data Data.
		 */
		$data = (array) apply_filters( 'sidrest_schema', $data );
		wp_print_inline_script_tag( wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ), array( 'type' => 'application/ld+json' ) );
	}
}
