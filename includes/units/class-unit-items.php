<?php
/**
 * Menu Items Unit: a short list of items for sidebars, footers and teasers
 * (a section, a category, items with a badge, or the newest items).
 *
 * Shortcode: [sidrest_items source="badge" badge="special" limit="4"]
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Units;

use SidcraftRestaurant\Item;
use SidcraftRestaurant\Plugin;
use SidcraftRestaurant\Post_Types;
use SidcraftRestaurant\Section;
use function SidcraftRestaurant\badge_options;
use function SidcraftRestaurant\prime_posts;

defined( 'ABSPATH' ) || exit;

/**
 * Class Items_Unit
 */
class Items_Unit extends Base_Unit {

	/** {@inheritDoc} */
	public function get_name() {
		return 'sidrest-items';
	}

	/** {@inheritDoc} */
	public function get_title() {
		return __( 'Menu Items List', 'sidcraft-restaurant-for-sidcraft-commerce' );
	}

	/** {@inheritDoc} */
	public function get_icon() {
		return 'star-filled';
	}

	/** {@inheritDoc} */
	protected function controls() {
		$cats = array( '' => __( '— Any —', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
		$terms = self::$choices ? \SidcraftRestaurant\all_terms( Post_Types::CATEGORY ) : array();
		foreach ( $terms as $term ) {
			$cats[ $term->slug ] = $term->name;
		}
		return array(
			'source'           => array(
				'type'    => 'select',
				'label'   => __( 'Show', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => 'latest',
				'options' => array(
					'latest'   => __( 'Newest items', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'section'  => __( 'Items in a section', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'category' => __( 'Items in a category', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'badge'    => __( 'Items with a badge', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'promo'    => __( 'Items on promotion now', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				),
			),
			'section'          => array(
				'type'      => 'select',
				'label'     => __( 'Section', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section'   => 'content',
				'default'   => '',
				'options'   => self::post_options( Post_Types::SECTION, __( '— Choose —', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'condition' => array( 'source' => 'section' ),
			),
			'category'         => array(
				'type'      => 'select',
				'label'     => __( 'Category', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section'   => 'content',
				'default'   => '',
				'options'   => $cats,
				'condition' => array( 'source' => 'category' ),
			),
			'badge'            => array(
				'type'      => 'select',
				'label'     => __( 'Badge', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section'   => 'content',
				'default'   => 'special',
				'options'   => badge_options(),
				'condition' => array( 'source' => 'badge' ),
			),
			'limit'            => array(
				'type'    => 'number',
				'label'   => __( 'Number of items', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => 5,
				'min'     => 1,
				'max'     => 30,
			),
			'show_images'      => array(
				'type'    => 'switch',
				'label'   => __( 'Photos', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => true,
			),
			'show_prices'      => array(
				'type'    => 'switch',
				'label'   => __( 'Prices', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => true,
			),
			'show_description' => array(
				'type'    => 'switch',
				'label'   => __( 'Descriptions', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'section' => 'content',
				'default' => false,
			),
		);
	}

	/**
	 * Items for the chosen source.
	 *
	 * @param array $settings Settings.
	 * @return Item[]
	 */
	public static function query( array $settings ) {
		$limit = max( 1, min( 30, (int) ( $settings['limit'] ?? 5 ) ) );
		$items = array();
		switch ( $settings['source'] ?? 'latest' ) {
			case 'section':
				$section = Section::get( absint( $settings['section'] ?? 0 ) );
				$items   = $section ? array_slice( $section->get_items(), 0, $limit ) : array();
				break;
			case 'badge':
			case 'promo':
				$pool = get_posts(
					array(
						'post_type'      => Post_Types::ITEM,
						'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Filtered in PHP below; small set.
						'fields'         => 'ids',
						'no_found_rows'  => true,
					)
				);
				prime_posts( $pool, Post_Types::ITEM );
				foreach ( $pool as $id ) {
					$item = Item::get( $id );
					if ( ! $item ) {
						continue;
					}
					$match = 'promo' === $settings['source'] ? $item->promo_active() : isset( $item->get_badges()[ $settings['badge'] ?? '' ] );
					if ( $match ) {
						$items[] = $item;
					}
					if ( count( $items ) >= $limit ) {
						break;
					}
				}
				break;
			default:
				$args = array(
					'post_type'      => Post_Types::ITEM,
					'posts_per_page' => $limit,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				);
				if ( 'category' === ( $settings['source'] ?? '' ) && '' !== ( $settings['category'] ?? '' ) ) {
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Single-term lookup.
					$args['tax_query'] = array(
						array(
							'taxonomy' => Post_Types::CATEGORY,
							'field'    => 'slug',
							'terms'    => sanitize_title( $settings['category'] ),
						),
					);
				}
				$ids = get_posts( $args );
				prime_posts( $ids, Post_Types::ITEM );
				foreach ( $ids as $id ) {
					$item = Item::get( $id );
					if ( $item ) {
						$items[] = $item;
					}
				}
		}
		return array_values( array_filter( $items, static fn( $i ) => $i->is_visible() ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $settings Settings.
	 */
	protected function render_html( array $settings ) {
		return Plugin::instance()->renderer->items_list(
			self::query( $settings ),
			array(
				'show_images'      => (bool) $settings['show_images'],
				'show_prices'      => (bool) $settings['show_prices'],
				'show_description' => (bool) $settings['show_description'],
			)
		);
	}
}
