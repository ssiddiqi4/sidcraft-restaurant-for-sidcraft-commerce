<?php
/**
 * A menu ("Dinner", "Wine"): an ordered list of sections, a footer note
 * (legal disclaimers, allergy notes) and display options.
 *
 * Meta: _sidrest_sections (int[]), _sidrest_footer (string),
 *       _sidrest_display (array: style, sidebar, toolbar).
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Menu
 */
class Menu {

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
	 * Load a menu (in the current language).
	 *
	 * @param int|\WP_Post $id Post or ID.
	 * @return Menu|null
	 */
	public static function get( $id ) {
		$post = get_post( $id instanceof \WP_Post ? $id->ID : tid( (int) $id, Post_Types::MENU ) );
		return $post && Post_Types::MENU === $post->post_type ? new static( $post ) : null;
	}

	/**
	 * The first published menu (blocks and patterns without a chosen menu).
	 *
	 * @return Menu|null
	 */
	public static function first() {
		$ids = get_posts(
			array(
				'post_type'      => Post_Types::MENU,
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
	 * Menu page URL.
	 *
	 * @return string
	 */
	public function get_url() {
		return (string) get_permalink( $this->post );
	}

	/**
	 * Is it published?
	 *
	 * @return bool
	 */
	public function is_visible() {
		return 'publish' === $this->post->post_status && '' === $this->post->post_password;
	}

	/**
	 * Section IDs in order (as stored).
	 *
	 * @return int[]
	 */
	public function get_section_ids() {
		return array_values( array_filter( array_map( 'intval', (array) get_post_meta( $this->post->ID, '_sidrest_sections', true ) ) ) );
	}

	/**
	 * Published sections in order.
	 *
	 * @return Section[]
	 */
	public function get_sections() {
		$out = array();
		$ids = $this->get_section_ids();
		prime_posts( $ids, Post_Types::SECTION );
		foreach ( $ids as $id ) {
			$section = Section::get( $id );
			if ( $section && $section->is_visible() ) {
				$out[] = $section;
			}
		}
		return $out;
	}

	/**
	 * Footer note.
	 *
	 * @return string
	 */
	public function get_footer() {
		return (string) get_post_meta( $this->post->ID, '_sidrest_footer', true );
	}

	/**
	 * Display options, with the site defaults filled in.
	 *
	 * @return array style, sidebar (bool), toolbar (bool).
	 */
	public function get_display() {
		$saved = (array) get_post_meta( $this->post->ID, '_sidrest_display', true );
		$style = isset( $saved['style'] ) && array_key_exists( $saved['style'], styles() ) ? $saved['style'] : (string) setting( 'default_style', 'classic' );
		return array(
			'style'   => $style,
			'sidebar' => isset( $saved['sidebar'] ) && '' !== $saved['sidebar'] ? (bool) $saved['sidebar'] : (bool) setting( 'show_sidebar', 1 ),
			'toolbar' => isset( $saved['toolbar'] ) && '' !== $saved['toolbar'] ? (bool) $saved['toolbar'] : (bool) setting( 'show_toolbar', 1 ),
		);
	}

	/**
	 * Save the section order.
	 *
	 * @param int[] $ids Section IDs.
	 */
	public function set_section_ids( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		update_post_meta( $this->post->ID, '_sidrest_sections', $ids );
	}
}
