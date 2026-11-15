<?php
/**
 * Menus, sections, menu items and item categories.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Post_Types
 */
class Post_Types {

	const MENU     = 'sidrest_menu';
	const SECTION  = 'sidrest_section';
	const ITEM     = 'sidrest_item';
	const CATEGORY = 'sidrest_item_cat';

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ), 5 );
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );
	}

	/**
	 * Register everything.
	 */
	public function register() {
		$cap = manage_cap();

		register_post_type(
			self::MENU,
			array(
				'labels'       => array(
					'name'          => __( 'Menus', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'singular_name' => __( 'Menu', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'menu_name'     => __( 'Restaurant', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'all_items'     => __( 'Menus', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'add_new'       => __( 'Add New Menu', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'add_new_item'  => __( 'Add menu', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'edit_item'     => __( 'Edit menu', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'view_item'     => __( 'View menu', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'search_items'  => __( 'Search menus', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'not_found'     => __( 'No menus yet.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'menu_icon'    => 'dashicons-food',
				'menu_position' => 57,
				'show_in_rest' => true,
				'rewrite'      => array(
					'slug'       => 'restaurant-menu',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'revisions' ),
				'capability_type' => 'post',
				'map_meta_cap' => true,
			)
		);

		register_post_type(
			self::SECTION,
			array(
				'labels'       => array(
					'name'          => __( 'Menu sections', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'singular_name' => __( 'Menu section', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'all_items'     => __( 'Sections', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'add_new'       => __( 'Add New Section', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'add_new_item'  => __( 'Add section', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'edit_item'     => __( 'Edit section', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'search_items'  => __( 'Search sections', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'not_found'     => __( 'No sections yet.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=' . self::MENU,
				'show_in_rest' => true,
				'supports'     => array( 'title' ),
			)
		);

		register_post_type(
			self::ITEM,
			array(
				'labels'       => array(
					'name'          => __( 'Menu items', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'singular_name' => __( 'Menu item', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'all_items'     => __( 'Menu items', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'add_new'       => __( 'Add New Item', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'add_new_item'  => __( 'Add menu item', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'edit_item'     => __( 'Edit menu item', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'view_item'     => __( 'View menu item', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'search_items'  => __( 'Search menu items', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'not_found'     => __( 'No menu items yet.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'featured_image' => __( 'Item photo', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'set_featured_image' => __( 'Set item photo', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'show_in_menu' => 'edit.php?post_type=' . self::MENU,
				'show_in_rest' => true,
				'rewrite'      => array(
					'slug'       => 'menu-item',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			)
		);

		register_taxonomy(
			self::CATEGORY,
			self::ITEM,
			array(
				'labels'            => array(
					'name'          => __( 'Item categories', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'singular_name' => __( 'Item category', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'menu_name'     => __( 'Categories', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'add_new_item'  => __( 'Add category', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'search_items'  => __( 'Search categories', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'menu-category' ),
				'capabilities'      => array(
					'manage_terms' => $cap,
					'edit_terms'   => $cap,
					'delete_terms' => $cap,
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Flush rewrite rules once after activation.
	 */
	public function maybe_flush() {
		// Kept stored (autoloaded, 0 when done): reading a missing option queries
		// the database on every request.
		$flag = get_option( 'sidrest_flush_rewrites' );
		if ( $flag ) {
			update_option( 'sidrest_flush_rewrites', 0, true );
			flush_rewrite_rules();
		} elseif ( false === $flag ) {
			add_option( 'sidrest_flush_rewrites', 0, '', true );
		}
	}
}
