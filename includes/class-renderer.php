<?php
/**
 * Builds the HTML for menus, sections, items and item lists, and tells the
 * schema collector what was shown.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Renderer
 */
class Renderer {

	/**
	 * Instances rendered on this page (for unique IDs).
	 *
	 * @var int
	 */
	protected static $count = 0;

	/**
	 * Display options with defaults.
	 *
	 * @param array     $args Requested options.
	 * @param Menu|null $menu Menu (its own display options are the fallback).
	 * @return array
	 */
	public function args( array $args, $menu = null ) {
		$display = $menu ? $menu->get_display() : array(
			'style'   => (string) setting( 'default_style', 'classic' ),
			'sidebar' => (bool) setting( 'show_sidebar', 1 ),
			'toolbar' => (bool) setting( 'show_toolbar', 1 ),
		);
		$style   = isset( $args['style'] ) && array_key_exists( (string) $args['style'], styles() ) ? (string) $args['style'] : $display['style'];
		$pick    = static function ( $value, $fallback ) {
			if ( 'show' === $value || true === $value || '1' === $value || 1 === $value ) {
				return true;
			}
			if ( 'hide' === $value || false === $value || '0' === $value || 0 === $value ) {
				return false;
			}
			return (bool) $fallback;
		};
		return array(
			'style'       => $style,
			'sidebar'     => $pick( $args['sidebar'] ?? '', $display['sidebar'] ),
			'toolbar'     => $pick( $args['toolbar'] ?? '', $display['toolbar'] ),
			'show_title'  => $pick( $args['show_title'] ?? '', setting( 'show_menu_title', 0 ) ),
			'show_images' => $pick( $args['show_images'] ?? '', setting( 'show_images', 1 ) ),
			'columns'     => ! empty( $args['columns'] ) ? max( 1, min( 4, (int) $args['columns'] ) ) : 3,
			'heading'     => in_array( $args['heading'] ?? '', array( 'h2', 'h3', 'h4' ), true ) ? $args['heading'] : 'h2',
		);
	}

	/**
	 * A whole menu.
	 *
	 * @param Menu  $menu Menu.
	 * @param array $args Display options (style, sidebar, toolbar, show_title, show_images, columns).
	 * @return string
	 */
	public function menu( Menu $menu, array $args = array() ) {
		$args     = $this->args( $args, $menu );
		$sections = array();
		$all      = $menu->get_sections();
		// Every item of the menu in one batch, not one post/meta/term query per item.
		$ids = array();
		foreach ( $all as $section ) {
			$ids = array_merge( $ids, $section->get_item_ids() );
		}
		prime_posts( $ids, Post_Types::ITEM );
		foreach ( $all as $section ) {
			$items = $section->get_items();
			if ( $items ) {
				$sections[] = array(
					'section' => $section,
					'items'   => $items,
				);
			}
		}
		Schema::add_menu( $menu, $sections );
		return $this->wrap( $sections, $args, $menu );
	}

	/**
	 * One section on its own (with its title and note).
	 *
	 * @param Section $section Section.
	 * @param array   $args    Display options.
	 * @return string
	 */
	public function section( Section $section, array $args = array() ) {
		$args            = $this->args( $args );
		$args['sidebar'] = false;
		$items           = $section->get_items();
		if ( ! $items ) {
			return '';
		}
		$sections = array(
			array(
				'section' => $section,
				'items'   => $items,
			),
		);
		Schema::add_section( $section, $items );
		return $this->wrap( $sections, $args, null );
	}

	/**
	 * Wrapper shared by menus and sections: toolbar, sidebar, sections, legend.
	 *
	 * @param array     $sections Each: section, items.
	 * @param array     $args     Display options.
	 * @param Menu|null $menu     Menu.
	 * @return string
	 */
	protected function wrap( array $sections, array $args, $menu ) {
		++self::$count;
		$shown = array();
		foreach ( $sections as $block ) {
			$shown = array_merge( $shown, $block['items'] );
		}
		prime_products( $shown );
		$uid        = 'sidrest-' . self::$count;
		$categories = array();
		$diets      = array();
		foreach ( $sections as $block ) {
			foreach ( $block['items'] as $item ) {
				foreach ( $item->get_categories() as $term ) {
					$categories[ $term->slug ] = $term->name;
				}
				$diets = array_merge( $diets, $item->get_dietary() );
			}
		}
		asort( $categories, SORT_NATURAL | SORT_FLAG_CASE );
		$diets = array_values( array_intersect( array_keys( Dietary::enabled() ), array_unique( $diets ) ) );

		return Plugin::instance()->templates->render(
			'menu.php',
			array(
				'menu'       => $menu,
				'sections'   => $sections,
				'args'       => $args,
				'uid'        => $uid,
				'categories' => setting( 'enable_categories', 1 ) ? $categories : array(),
				'diets'      => $diets,
				'renderer'   => $this,
			)
		);
	}

	/**
	 * One item card in a given style.
	 *
	 * @param Item  $item  Item.
	 * @param array $args  Display options (style, show_images, heading).
	 * @param int   $order Position (for "menu order" sorting).
	 * @return string
	 */
	public function item( Item $item, array $args, $order = 0 ) {
		return Plugin::instance()->templates->render(
			'item.php',
			array(
				'item'  => $item,
				'args'  => $args,
				'order' => (int) $order,
			)
		);
	}

	/**
	 * A single item on its own (Unit, block, pattern).
	 *
	 * @param Item  $item Item.
	 * @param array $args Display options.
	 * @return string
	 */
	public function single_item( Item $item, array $args = array() ) {
		$args = $this->args( $args );
		Schema::add_items( array( $item ) );
		return '<div class="sidrest sidrest--style-' . esc_attr( $args['style'] ) . ' sidrest--single" style="' . esc_attr( Frontend::css_vars() ) . '"><div class="sidrest-items sidrest-items--' . esc_attr( $args['style'] ) . ' sidrest-items--cols-1">' . $this->item( $item, $args ) . '</div></div>';
	}

	/**
	 * A short list of items (widgets, sidebars, footers).
	 *
	 * @param Item[] $items Items.
	 * @param array  $args  show_images, show_prices, show_description.
	 * @return string
	 */
	public function items_list( array $items, array $args = array() ) {
		if ( ! $items ) {
			return '';
		}
		return Plugin::instance()->templates->render(
			'items-list.php',
			array(
				'items' => $items,
				'args'  => array_merge(
					array(
						'show_images'      => true,
						'show_prices'      => true,
						'show_description' => false,
					),
					$args
				),
			)
		);
	}

	/**
	 * Prices HTML for an item: every price row, with the promotion shown.
	 *
	 * @param Item $item Item.
	 * @return string
	 */
	public static function prices_html( Item $item ) {
		$rows = $item->get_current_prices();
		if ( ! $rows ) {
			return '';
		}
		$out = '<div class="sidrest-prices' . ( count( $rows ) > 1 ? ' sidrest-prices--multi' : '' ) . '">';
		foreach ( $rows as $row ) {
			$out .= '<span class="sidrest-price">';
			if ( '' !== $row['label'] ) {
				$out .= '<span class="sidrest-price__label">' . esc_html( $row['label'] ) . '</span> ';
			}
			if ( $row['price'] < $row['regular'] ) {
				$out .= '<del class="sidrest-price__was"><span class="sidrest-sr">' . esc_html__( 'Was', 'sidcraft-restaurant-for-sidcraft-commerce' ) . ' </span>' . esc_html( price( $row['regular'] ) ) . '</del> ';
				$out .= '<ins class="sidrest-price__now"><span class="sidrest-sr">' . esc_html__( 'Now', 'sidcraft-restaurant-for-sidcraft-commerce' ) . ' </span>' . esc_html( price( $row['price'] ) ) . '</ins>';
			} else {
				$out .= '<span class="sidrest-price__now">' . esc_html( price( $row['price'] ) ) . '</span>';
			}
			$out .= '</span>';
		}
		return $out . '</div>';
	}

	/**
	 * Badges HTML.
	 *
	 * @param Item $item Item.
	 * @return string
	 */
	public static function badges_html( Item $item ) {
		$badges = $item->get_badges();
		if ( ! $badges ) {
			return '';
		}
		$out = '<ul class="sidrest-badges">';
		foreach ( $badges as $slug => $text ) {
			$out .= '<li class="sidrest-badge sidrest-badge--' . esc_attr( $slug ) . '">' . esc_html( $text ) . '</li>';
		}
		return $out . '</ul>';
	}

	/**
	 * Dietary icons HTML.
	 *
	 * @param Item $item Item.
	 * @return string
	 */
	public static function dietary_html( Item $item ) {
		$diets = $item->get_dietary();
		if ( ! $diets ) {
			return '';
		}
		$out = '<span class="sidrest-diets">';
		foreach ( $diets as $slug ) {
			$out .= Dietary::icon( $slug );
		}
		return $out . '</span>';
	}

	/**
	 * Custom fields HTML.
	 *
	 * @param Item $item      Item.
	 * @param bool $menu_only Only "show on menu" fields.
	 * @return string
	 */
	public static function fields_html( Item $item, $menu_only = true ) {
		$fields = $item->get_custom_fields( $menu_only );
		if ( ! $fields ) {
			return '';
		}
		$out = '<dl class="sidrest-fields">';
		foreach ( $fields as $field ) {
			$out .= '<div class="sidrest-fields__row sidrest-fields__row--' . esc_attr( $field['key'] ) . '"><dt>' . esc_html( $field['label'] ) . '</dt><dd>' . esc_html( $field['value'] ) . '</dd></div>';
		}
		return $out . '</dl>';
	}

	/**
	 * Star rating from Sidcraft Commerce reviews (orderable items only).
	 *
	 * @param Item $item Item.
	 * @return string
	 */
	public static function rating_html( Item $item ) {
		$product = $item->is_orderable() ? $item->get_product() : null;
		if ( ! $product || ! $product->get_review_count() ) {
			return '';
		}
		$unit = \SidcraftCommerce\Plugin::instance()->units->get( 'sidccofo-product-rating' );
		return $unit ? $unit->render(
			array(
				'product_id'   => $product->get_id(),
				'show_count'   => true,
				'clickable'    => false,
				'hide_if_none' => true,
			)
		) : '';
	}

	/**
	 * Add-to-cart form for an orderable item (Sidcraft Commerce handles the
	 * size choice, the cart and checkout).
	 *
	 * @param Item $item Item.
	 * @return string
	 */
	public static function order_html( Item $item ) {
		$product = $item->is_orderable() ? $item->get_product() : null;
		$unit    = \SidcraftCommerce\Plugin::instance()->units->get( 'sidccofo-add-to-cart' );
		if ( ! $product || ! $unit ) {
			return '';
		}
		return '<div class="sidrest-order">' . $unit->render(
			array(
				'product_id'    => $product->get_id(),
				'show_quantity' => false,
				'button_text'   => label( 'add' ),
				'full_width'    => false,
			)
		) . '</div>';
	}
}
