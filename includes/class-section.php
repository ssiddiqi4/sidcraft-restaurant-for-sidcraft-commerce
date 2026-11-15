<?php
/**
 * A menu section ("Appetizers", "Kid’s Menu"): a title, an optional note and
 * an ordered list of items. A section can appear in several menus.
 *
 * Meta: _sidrest_note (string), _sidrest_items (int[] in display order).
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Section
 */
class Section {

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
	 * Load a section (in the current language).
	 *
	 * @param int|\WP_Post $id Post or ID.
	 * @return Section|null
	 */
	public static function get( $id ) {
		$post = get_post( $id instanceof \WP_Post ? $id->ID : tid( (int) $id, Post_Types::SECTION ) );
		return $post && Post_Types::SECTION === $post->post_type ? new static( $post ) : null;
	}

	/**
	 * The first published section (used when a block or Unit has none chosen yet).
	 *
	 * @return Section|null
	 */
	public static function first() {
		$ids = get_posts(
			array(
				'post_type'      => Post_Types::SECTION,
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		return $ids ? self::get( $ids[0] ) : null;
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
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return get_the_title( $this->post );
	}

	/**
	 * Note shown under the title.
	 *
	 * @return string
	 */
	public function get_note() {
		return (string) get_post_meta( $this->post->ID, '_sidrest_note', true );
	}

	/**
	 * Item IDs in order (as stored, untranslated).
	 *
	 * @return int[]
	 */
	public function get_item_ids() {
		return array_values( array_filter( array_map( 'intval', (array) get_post_meta( $this->post->ID, '_sidrest_items', true ) ) ) );
	}

	/**
	 * Published items in order.
	 *
	 * @return Item[]
	 */
	public function get_items() {
		$out = array();
		$ids = $this->get_item_ids();
		prime_posts( $ids, Post_Types::ITEM );
		foreach ( $ids as $id ) {
			$item = Item::get( $id );
			if ( $item && $item->is_visible() ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * Is it published?
	 *
	 * @return bool
	 */
	public function is_visible() {
		return 'publish' === $this->post->post_status;
	}

	/**
	 * Save the item order.
	 *
	 * @param int[] $ids Item IDs.
	 */
	public function set_item_ids( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		update_post_meta( $this->post->ID, '_sidrest_items', $ids );
	}
}
