<?php
/**
 * Shared base for the restaurant Units.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Units;

use SidcraftRestaurant\Post_Types;
use function SidcraftRestaurant\styles;

defined( 'ABSPATH' ) || exit;

/**
 * Class Base_Unit
 */
abstract class Base_Unit extends \SidcraftCommerce\Abstracts\Unit {

	/**
	 * False while only control types and defaults are needed (block attributes):
	 * pickers then skip loading their menu, section, item and category lists.
	 *
	 * @var bool
	 */
	protected static $choices = true;

	/**
	 * Controls without the picker lists. Block registration runs on every
	 * request and reads only each control's type and default.
	 *
	 * @param \SidcraftCommerce\Abstracts\Unit $unit Unit.
	 * @return array
	 */
	public static function controls_without_choices( $unit ) {
		$before          = self::$choices;
		self::$choices   = false;
		try {
			return $unit->get_controls();
		} finally {
			self::$choices = $before;
		}
	}

	/** {@inheritDoc} */
	public function get_keywords() {
		return array( 'restaurant', 'menu', 'food', 'drink' );
	}

	/** {@inheritDoc} */
	public function get_assets() {
		return array(
			'styles'  => array( 'sidrest-menu' ),
			'scripts' => array( 'sidrest-menu' ),
		);
	}

	/**
	 * Choices for a post picker: '' => first label, then id => title.
	 *
	 * @param string $type  Post type.
	 * @param string $first Label for the empty choice.
	 * @return array
	 */
	protected static function post_options( $type, $first ) {
		static $cache = array();
		if ( ! self::$choices ) {
			return array( '' => $first );
		}
		if ( ! isset( $cache[ $type ] ) ) {
			$cached = get_transient( 'sidrest_opts_' . $type );
			if ( ! is_array( $cached ) ) {
				$cached = array();
				$posts  = get_posts(
					array(
						'post_type'              => $type,
						'post_status'            => array( 'publish', 'draft', 'private' ),
						'posts_per_page'         => 300, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Builder picker, cached.
						'orderby'                => 'title',
						'order'                  => 'ASC',
						'no_found_rows'          => true,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
						'suppress_filters'       => false,
					)
				);
				foreach ( $posts as $post ) {
					$cached[ (string) $post->ID ] = $post->post_title . ' (#' . $post->ID . ')';
				}
				set_transient( 'sidrest_opts_' . $type, $cached, DAY_IN_SECONDS );
			}
			$cache[ $type ] = $cached;
		}
		return array( '' => $first ) + $cache[ $type ];
	}

	/**
	 * Forget cached picker choices when menus, sections or items change.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function flush_options( $post_id ) {
		$type = get_post_type( $post_id );
		if ( in_array( $type, array( Post_Types::MENU, Post_Types::SECTION, Post_Types::ITEM ), true ) ) {
			delete_transient( 'sidrest_opts_' . $type );
		}
	}

	/**
	 * Style choices with "menu default" first.
	 *
	 * @param string $first Label for the default.
	 * @return array
	 */
	protected static function style_options( $first ) {
		return array( '' => $first ) + styles();
	}

	/**
	 * Show / hide / default choices.
	 *
	 * @return array
	 */
	protected static function toggle_options() {
		return array(
			''     => __( 'Default', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'show' => __( 'Show', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'hide' => __( 'Hide', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		);
	}

	/**
	 * Read an ID setting (shortcodes may use id="…" as well).
	 *
	 * @param array  $settings Settings.
	 * @param string $key      Key.
	 * @return int
	 */
	protected static function id( array $settings, $key ) {
		return absint( $settings[ $key ] ?? 0 );
	}

	/**
	 * Post type helper for subclasses.
	 *
	 * @return string
	 */
	protected static function menu_type() {
		return Post_Types::MENU;
	}
}
