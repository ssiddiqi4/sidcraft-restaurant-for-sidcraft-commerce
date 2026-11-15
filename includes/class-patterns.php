<?php
/**
 * Block patterns: drop menus, sections, single items, specials lists and QR
 * code cards into pages and block theme templates (Site Editor → Patterns).
 *
 * Blocks with no menu/section/item chosen show the first one; pick another
 * in the block's settings after inserting.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Patterns
 */
class Patterns {

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ), 21 );
	}

	/**
	 * Register the category and patterns.
	 */
	public function register() {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}
		register_block_pattern_category( 'sidcraft-restaurant', array( 'label' => __( 'Restaurant', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) );

		$h = static function ( $text, $level = 2 ) {
			return '<!-- wp:heading {"level":' . (int) $level . '} --><h' . (int) $level . ' class="wp-block-heading">' . esc_html( $text ) . '</h' . (int) $level . '><!-- /wp:heading -->';
		};
		$p = static function ( $text, $align = '' ) {
			$attrs = $align ? '{"align":"' . $align . '"}' : '';
			return '<!-- wp:paragraph ' . $attrs . ' --><p' . ( $align ? ' class="has-text-align-' . esc_attr( $align ) . '"' : '' ) . '>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->';
		};

		$patterns = array(
			'menu-page'      => array(
				__( 'Restaurant menu page', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				__( 'A heading, a short introduction and a full menu with section sidebar, search and filters.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				$h( __( 'Our menu', 'sidcraft-restaurant-for-sidcraft-commerce' ), 1 ) . $p( __( 'Fresh, seasonal and made in our kitchen every day.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) . '<!-- wp:sidrest/menu {"sidebar":"show","toolbar":"show","align":"wide"} /-->',
			),
			'menu-grid'      => array(
				__( 'Photo grid menu', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				__( 'A full-width menu with photos in a three-column grid.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'<!-- wp:sidrest/menu {"style":"grid","columns":3,"align":"wide"} /-->',
			),
			'drinks-luxe'    => array(
				__( 'Drinks menu (Luxe)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				__( 'A minimal, elegant menu for wine and cocktails.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				$h( __( 'Wine & drinks', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) . '<!-- wp:sidrest/menu {"style":"luxe","sidebar":"hide","toolbar":"hide"} /-->',
			),
			'order-online'   => array(
				__( 'Order online', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				__( 'The Ordering style: add dishes to the cart straight from the menu.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				$h( __( 'Order online', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) . $p( __( 'Pick your dishes and check out — we’ll have them ready.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) . '<!-- wp:sidrest/menu {"style":"ordering","sidebar":"show","align":"wide"} /-->',
			),
			'weekly-special' => array(
				__( 'Weekly specials section', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				__( 'One menu section with its note, in the photo list style.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'<!-- wp:sidrest/section {"style":"list"} /-->',
			),
			'chef-special'   => array(
				__( 'Chef’s special', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				__( 'A highlighted single dish next to a short story.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'<!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $h( __( 'Chef’s special', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) . $p( __( 'Every week our chef picks one dish to celebrate the season.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) . '</div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:sidrest/item {"style":"list"} /--></div><!-- /wp:column --></div><!-- /wp:columns -->',
			),
			'specials-list'  => array(
				__( 'Specials list (sidebar or footer)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				__( 'A compact list of items with the Special badge.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				$h( __( 'Today’s specials', 'sidcraft-restaurant-for-sidcraft-commerce' ), 3 ) . '<!-- wp:sidrest/items {"source":"badge","badge":"special","limit":4} /-->',
			),
			'qr-card'        => array(
				__( 'Table QR code card', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				__( 'A centred QR code that opens your menu, with a caption.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'<!-- wp:group {"layout":{"type":"constrained","contentSize":"360px"}} --><div class="wp-block-group">' . $h( __( 'Our menu, on your phone', 'sidcraft-restaurant-for-sidcraft-commerce' ), 3 ) . '<!-- wp:sidrest/qr {"size":220} /-->' . $p( __( 'Point your camera at the code — no app needed.', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'center' ) . '</div><!-- /wp:group -->',
			),
		);

		foreach ( $patterns as $slug => $pattern ) {
			register_block_pattern(
				'sidrest/' . $slug,
				array(
					'title'       => $pattern[0],
					'description' => $pattern[1],
					'categories'  => array( 'sidcraft-restaurant' ),
					'keywords'    => array( 'restaurant', 'menu', 'food' ),
					'content'     => $pattern[2],
				)
			);
		}
	}
}
