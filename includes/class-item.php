<?php
/**
 * A menu item: name, description, photo, one or more prices, promotional
 * pricing, dietary icons, badges, custom fields, suppliers, related items and
 * an optional link to a Sidcraft Commerce product for online ordering.
 *
 * Meta (all prefixed _sidrest_):
 *   prices     [ [ label, price ], … ]   first row is the main price
 *   promo      [ enabled, type (percent|amount), value, label, start, end, days[], from, to ]
 *   dietary    string[]
 *   badges     string[]
 *   cf         custom field key => value
 *   suppliers  [ [ name, address, note ], … ]
 *   related    int[] (empty = automatic)
 *   orderable  0|1, product_id int
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Item
 */
class Item {

	/**
	 * Post.
	 *
	 * @var \WP_Post
	 */
	protected $post;

	/**
	 * Constructor.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function __construct( \WP_Post $post ) {
		$this->post = $post;
	}

	/**
	 * Load an item (translated to the current language when WPML/Polylang run).
	 *
	 * @param int|\WP_Post $id Post or ID.
	 * @return Item|null
	 */
	public static function get( $id ) {
		$post = get_post( $id instanceof \WP_Post ? $id->ID : tid( (int) $id, Post_Types::ITEM ) );
		return $post && Post_Types::ITEM === $post->post_type ? new static( $post ) : null;
	}

	/**
	 * Meta value.
	 *
	 * @param string $key Key without prefix.
	 * @return mixed
	 */
	public function meta( $key ) {
		return get_post_meta( $this->post->ID, '_sidrest_' . $key, true );
	}

	/**
	 * ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return (int) $this->post->ID;
	}

	/**
	 * Post object.
	 *
	 * @return \WP_Post
	 */
	public function get_post() {
		return $this->post;
	}

	/**
	 * Visible on the front end?
	 *
	 * @return bool
	 */
	public function is_visible() {
		return 'publish' === $this->post->post_status && '' === $this->post->post_password;
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return get_the_title( $this->post );
	}

	/**
	 * Short description for the menu: the excerpt, else the start of the content.
	 *
	 * @return string Plain text.
	 */
	public function get_description() {
		$text = '' !== trim( $this->post->post_excerpt ) ? $this->post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $this->post->post_content ) ), 30, '…' );
		return trim( wp_strip_all_tags( $text ) );
	}

	/**
	 * Item page URL.
	 *
	 * @return string
	 */
	public function get_url() {
		return (string) get_permalink( $this->post );
	}

	/**
	 * Photo ID.
	 *
	 * @return int
	 */
	public function get_image_id() {
		return (int) get_post_thumbnail_id( $this->post );
	}

	/**
	 * Photo HTML.
	 *
	 * @param string $size Image size.
	 * @return string
	 */
	public function get_image( $size = 'medium_large' ) {
		$id = $this->get_image_id();
		return $id ? wp_get_attachment_image(
			$id,
			$size,
			false,
			array(
				'class'   => 'sidrest-item__img',
				'loading' => 'lazy',
				'alt'     => $this->get_name(),
			)
		) : '';
	}

	/*
	------------------------------------------------------------------
	 * Prices
	 * ----------------------------------------------------------------
	 */

	/**
	 * Price rows as entered.
	 *
	 * @return array[] Each: label, price (float).
	 */
	public function get_prices() {
		$rows = array();
		foreach ( (array) $this->meta( 'prices' ) as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['price'] ) || '' === (string) $row['price'] ) {
				continue;
			}
			$rows[] = array(
				'label' => (string) ( $row['label'] ?? '' ),
				'price' => (float) $row['price'],
			);
		}
		return $rows;
	}

	/**
	 * Price rows with the promotion applied (when one is running now).
	 *
	 * @return array[] Each: label, price (now), regular (as entered).
	 */
	public function get_current_prices() {
		$active = $this->promo_active();
		$out    = array();
		foreach ( $this->get_prices() as $row ) {
			$out[] = array(
				'label'   => $row['label'],
				'regular' => $row['price'],
				'price'   => $active ? $this->apply_promo( $row['price'] ) : $row['price'],
			);
		}
		return $out;
	}

	/**
	 * Lowest current price (for sorting and ordering), or null when unpriced.
	 *
	 * @return float|null
	 */
	public function get_min_price() {
		$prices = wp_list_pluck( $this->get_current_prices(), 'price' );
		return $prices ? (float) min( $prices ) : null;
	}

	/**
	 * Promotion settings.
	 *
	 * @return array
	 */
	public function get_promo() {
		$promo = (array) $this->meta( 'promo' );
		return array_merge(
			array(
				'enabled' => 0,
				'type'    => 'percent',
				'value'   => 0,
				'label'   => '',
				'start'   => '',
				'end'     => '',
				'days'    => array(),
				'from'    => '',
				'to'      => '',
			),
			$promo
		);
	}

	/**
	 * Is the promotion running right now (dates, weekdays and daily hours)?
	 *
	 * @param int|null $now Timestamp (default: now).
	 * @return bool
	 */
	public function promo_active( $now = null ) {
		$p = $this->get_promo();
		if ( empty( $p['enabled'] ) || (float) $p['value'] <= 0 ) {
			return false;
		}
		$now   = null === $now ? time() : (int) $now;
		$date  = wp_date( 'Y-m-d', $now );
		$time  = wp_date( 'H:i', $now );
		$day   = (int) wp_date( 'w', $now );
		$ok    = true;
		$ok    = $ok && ( '' === $p['start'] || $date >= $p['start'] );
		$ok    = $ok && ( '' === $p['end'] || $date <= $p['end'] );
		$ok    = $ok && ( empty( $p['days'] ) || in_array( $day, array_map( 'intval', (array) $p['days'] ), true ) );
		if ( $ok && '' !== $p['from'] && '' !== $p['to'] ) {
			$ok = $p['from'] <= $p['to'] ? ( $time >= $p['from'] && $time < $p['to'] ) : ( $time >= $p['from'] || $time < $p['to'] );
		}

		/**
		 * Filter whether an item's promotion is running.
		 *
		 * @param bool $ok   Running.
		 * @param Item $item Item.
		 */
		return (bool) apply_filters( 'sidrest_promo_active', $ok, $this );
	}

	/**
	 * A price with the promotion applied.
	 *
	 * @param float $price Regular price.
	 * @return float
	 */
	public function apply_promo( $price ) {
		$p     = $this->get_promo();
		$value = (float) $p['value'];
		$new   = 'amount' === $p['type'] ? $price - $value : $price * ( 1 - min( 100, $value ) / 100 );
		return round( max( 0, $new ), 2 );
	}

	/**
	 * Label for the running promotion ("Happy hour"), or "Sale".
	 *
	 * @return string
	 */
	public function get_promo_label() {
		$p = $this->get_promo();
		return '' !== trim( (string) $p['label'] ) ? (string) $p['label'] : label( 'badge_sale' );
	}

	/*
	------------------------------------------------------------------
	 * Icons, badges, fields
	 * ----------------------------------------------------------------
	 */

	/**
	 * Dietary icon slugs (only those switched on in the settings).
	 *
	 * @return string[]
	 */
	public function get_dietary() {
		return array_values( array_intersect( (array) $this->meta( 'dietary' ), array_keys( Dietary::enabled() ) ) );
	}

	/**
	 * Badges: slug => label, plus the promotion's label while it runs.
	 *
	 * @return array
	 */
	public function get_badges() {
		$on      = (array) setting( 'badges', array() );
		$options = badge_options();
		$out     = array();
		foreach ( (array) $this->meta( 'badges' ) as $slug ) {
			if ( isset( $options[ $slug ] ) && in_array( $slug, $on, true ) ) {
				$out[ $slug ] = $options[ $slug ];
			}
		}
		if ( $this->promo_active() ) {
			$out['sale'] = $this->get_promo_label();
		}
		return $out;
	}

	/**
	 * Custom field values that have a definition in the settings.
	 *
	 * @param bool $menu_only Only fields marked "show on menu".
	 * @return array[] Each: key, label, value.
	 */
	public function get_custom_fields( $menu_only = false ) {
		$values = (array) $this->meta( 'cf' );
		$out    = array();
		foreach ( (array) setting( 'custom_fields', array() ) as $field ) {
			$key = (string) ( $field['key'] ?? '' );
			if ( '' === $key || ! isset( $values[ $key ] ) || '' === trim( (string) $values[ $key ] ) ) {
				continue;
			}
			if ( $menu_only && empty( $field['on_menu'] ) ) {
				continue;
			}
			$out[] = array(
				'key'   => $key,
				'label' => (string) ( $field['label'] ?? $key ),
				'value' => (string) $values[ $key ],
			);
		}
		return $out;
	}

	/**
	 * Suppliers.
	 *
	 * @return array[] Each: name, address, note.
	 */
	public function get_suppliers() {
		$out = array();
		foreach ( (array) $this->meta( 'suppliers' ) as $row ) {
			if ( is_array( $row ) && '' !== trim( (string) ( $row['name'] ?? '' ) ) ) {
				$out[] = array(
					'name'    => (string) $row['name'],
					'address' => (string) ( $row['address'] ?? '' ),
					'note'    => (string) ( $row['note'] ?? '' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Category terms.
	 *
	 * @return \WP_Term[]
	 */
	public function get_categories() {
		$terms = get_the_terms( $this->post, Post_Types::CATEGORY );
		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Related items: chosen ones, else others from the same category.
	 *
	 * @param int $limit Max.
	 * @return Item[]
	 */
	public function get_related( $limit = 4 ) {
		$ids = array_filter( array_map( 'intval', (array) $this->meta( 'related' ) ) );
		if ( ! $ids ) {
			$cats = wp_list_pluck( $this->get_categories(), 'term_id' );
			if ( $cats ) {
				$ids = get_posts(
					array(
						'post_type'      => Post_Types::ITEM,
						'posts_per_page' => $limit + 1, // One spare: this item is skipped below.
						'fields'         => 'ids',
						'no_found_rows'  => true,
						'orderby'        => 'date',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Small, single-term lookup.
						'tax_query'      => array(
							array(
								'taxonomy' => Post_Types::CATEGORY,
								'terms'    => $cats,
							),
						),
					)
				);
			}
		}
		$out = array();
		foreach ( $ids as $id ) {
			$item = self::get( $id );
			if ( $item && $item->is_visible() && $item->get_id() !== $this->get_id() ) {
				$out[] = $item;
			}
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/*
	------------------------------------------------------------------
	 * Ordering
	 * ----------------------------------------------------------------
	 */

	/**
	 * Can it be ordered online?
	 *
	 * @return bool
	 */
	public function is_orderable() {
		return (bool) $this->meta( 'orderable' ) && $this->get_product();
	}

	/**
	 * The linked Sidcraft Commerce product.
	 *
	 * @return \SidcraftCommerce\Data\Product|null
	 */
	public function get_product() {
		$id = (int) $this->meta( 'product_id' );
		return $id ? \SidcraftCommerce\Data\Product::get( $id ) : null;
	}
}
