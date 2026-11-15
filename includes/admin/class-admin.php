<?php
/**
 * Admin screens: menu manager, section and item editors, list columns, and
 * "Design a page in Sidcraft Page Builder".
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Admin;

use SidcraftRestaurant\Dietary;
use SidcraftRestaurant\Item;
use SidcraftRestaurant\Menu;
use SidcraftRestaurant\Post_Types;
use SidcraftRestaurant\Section;
use function SidcraftRestaurant\badge_options;
use function SidcraftRestaurant\manage_cap;
use function SidcraftRestaurant\parse_price;
use function SidcraftRestaurant\price;
use function SidcraftRestaurant\setting;
use function SidcraftRestaurant\styles;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin
 */
class Admin {

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'add_meta_boxes_' . Post_Types::MENU, array( $this, 'menu_boxes' ) );
		add_action( 'add_meta_boxes_' . Post_Types::SECTION, array( $this, 'section_boxes' ) );
		add_action( 'add_meta_boxes_' . Post_Types::ITEM, array( $this, 'item_boxes' ) );
		add_action( 'save_post_' . Post_Types::MENU, array( $this, 'save_menu' ), 10, 2 );
		add_action( 'save_post_' . Post_Types::SECTION, array( $this, 'save_section' ), 10, 2 );
		add_action( 'save_post_' . Post_Types::ITEM, array( $this, 'save_item' ), 10, 2 );
		add_action( 'admin_post_sidrest_create_page', array( $this, 'create_page' ) );
		add_filter( 'manage_' . Post_Types::ITEM . '_posts_columns', array( $this, 'item_columns' ) );
		add_action( 'manage_' . Post_Types::ITEM . '_posts_custom_column', array( $this, 'item_column' ), 10, 2 );
		add_filter( 'manage_' . Post_Types::MENU . '_posts_columns', array( $this, 'menu_columns' ) );
		add_action( 'manage_' . Post_Types::MENU . '_posts_custom_column', array( $this, 'menu_column' ), 10, 2 );
		add_filter( 'manage_' . Post_Types::SECTION . '_posts_columns', array( $this, 'section_columns' ) );
		add_action( 'manage_' . Post_Types::SECTION . '_posts_custom_column', array( $this, 'section_column' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'classic_screens' ), 10, 2 );
	}

	/**
	 * Menus and sections are managed in their meta boxes: use the classic
	 * screen, which gives the menu manager the full width.
	 *
	 * @param bool   $use       Use the block editor.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function classic_screens( $use, $post_type ) {
		return in_array( $post_type, array( Post_Types::MENU, Post_Types::SECTION ), true ) ? false : $use;
	}

	/**
	 * Categories submenu (the item type lives under Restaurant, so WordPress
	 * does not add it by itself).
	 */
	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Post_Types::MENU,
			__( 'Item categories', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			__( 'Categories', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			manage_cap(),
			'edit-tags.php?taxonomy=' . Post_Types::CATEGORY . '&post_type=' . Post_Types::ITEM
		);
	}

	/**
	 * Admin CSS/JS on our screens.
	 *
	 * @param string $hook Screen hook.
	 */
	public function assets( $hook ) {
		$screen = get_current_screen();
		$ours   = $screen && ( in_array( $screen->post_type, array( Post_Types::MENU, Post_Types::SECTION, Post_Types::ITEM ), true ) || false !== strpos( (string) $hook, 'sidrest' ) );
		if ( ! $ours ) {
			return;
		}
		wp_enqueue_style( 'sidrest-admin', SIDREST_URL . 'assets/css/sidrest-admin.css', array(), SIDREST_VERSION );
		wp_enqueue_script( 'sidrest-admin', SIDREST_URL . 'assets/js/sidrest-admin.js', array(), SIDREST_VERSION, true );
		wp_localize_script(
			'sidrest-admin',
			'sidrestAdmin',
			array(
				'up'     => __( 'Move up', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'down'   => __( 'Move down', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'remove' => __( 'Remove', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'saveTo' => __( 'Save the menu to arrange this section’s items here.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			)
		);
	}

	/**
	 * Title placeholders.
	 *
	 * @param string   $text Text.
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public function title_placeholder( $text, $post ) {
		$map = array(
			Post_Types::MENU    => __( 'Menu name, e.g. Dinner', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			Post_Types::SECTION => __( 'Section name, e.g. Appetizers', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			Post_Types::ITEM    => __( 'Dish or drink name', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		);
		return $map[ $post->post_type ] ?? $text;
	}

	/*
	------------------------------------------------------------------
	 * Sortable picker (shared by menus, sections and related items)
	 * ----------------------------------------------------------------
	 */

	/**
	 * All posts of a type for a picker: id => title.
	 *
	 * @param string $type Post type.
	 * @return array
	 */
	protected static function all_posts( $type ) {
		$out   = array();
		$posts = get_posts(
			array(
				'post_type'              => $type,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'         => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin picker.
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		foreach ( $posts as $post ) {
			$out[ $post->ID ] = ( '' !== $post->post_title ? $post->post_title : __( '(no title)', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) . ( 'publish' === $post->post_status ? '' : ' — ' . get_post_status_object( $post->post_status )->label );
		}
		return $out;
	}

	/**
	 * Render a sortable picker.
	 *
	 * @param string   $name     Input name (without []).
	 * @param int[]    $selected Selected IDs in order.
	 * @param array    $choices  id => label.
	 * @param string   $add      "Add" placeholder.
	 * @param callable $extra    Optional: function( $id ) returning extra HTML inside each row.
	 */
	protected static function picker( $name, array $selected, array $choices, $add, $extra = null ) {
		echo '<div class="sidrest-picker" data-sidrest-picker data-name="' . esc_attr( $name ) . '[]">';
		echo '<ul class="sidrest-picker__list" data-list>';
		foreach ( $selected as $id ) {
			if ( ! isset( $choices[ $id ] ) ) {
				continue;
			}
			self::picker_row( $name, $id, $choices[ $id ], $extra ? (string) call_user_func( $extra, $id ) : '' );
		}
		echo '</ul>';
		echo '<p class="sidrest-picker__empty" data-empty' . ( $selected ? ' hidden' : '' ) . '>' . esc_html__( 'Nothing here yet.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		echo '<div class="sidrest-picker__add"><select data-add-select aria-label="' . esc_attr( $add ) . '"><option value="">' . esc_html( $add ) . '</option>';
		foreach ( $choices as $id => $label ) {
			printf( '<option value="%1$d">%2$s</option>', (int) $id, esc_html( $label ) );
		}
		echo '</select> <button type="button" class="button" data-add>' . esc_html__( 'Add', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</button></div>';
		echo '<template data-row>';
		self::picker_row( $name, 0, '', '' );
		echo '</template>';
		echo '</div>';
	}

	/**
	 * One picker row.
	 *
	 * @param string $name  Input name.
	 * @param int    $id    ID.
	 * @param string $label Label.
	 * @param string $extra Extra HTML (already escaped).
	 */
	protected static function picker_row( $name, $id, $label, $extra ) {
		$edit = $id ? get_edit_post_link( $id ) : '';
		echo '<li class="sidrest-picker__row" draggable="true" data-id="' . esc_attr( $id ) . '">';
		echo '<div class="sidrest-picker__main"><span class="sidrest-picker__handle" aria-hidden="true">⋮⋮</span>';
		echo '<span class="sidrest-picker__label" data-label>' . ( $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $label ) . '</a>' : esc_html( $label ) ) . '</span>';
		echo '<span class="sidrest-picker__tools"><button type="button" class="button-link" data-up aria-label="' . esc_attr__( 'Move up', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '">↑</button><button type="button" class="button-link" data-down aria-label="' . esc_attr__( 'Move down', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '">↓</button><button type="button" class="button-link sidrest-picker__remove" data-remove aria-label="' . esc_attr__( 'Remove', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '">×</button></span>';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $id ) . '" data-value></div>';
		echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts by the caller.
		echo '</li>';
	}

	/**
	 * Posted ID list.
	 *
	 * @param string $key Field name.
	 * @return int[]
	 */
	protected static function posted_ids( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callers verify the nonce first.
		return isset( $_POST[ $key ] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST[ $key ] ) ) ) ) ) : array();
	}

	/**
	 * Autosave, revision and capability checks for a save (the nonce is
	 * verified in each save method).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	protected static function can_save( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id );
	}

	/*
	------------------------------------------------------------------
	 * Menus
	 * ----------------------------------------------------------------
	 */

	/**
	 * Menu boxes.
	 */
	public function menu_boxes() {
		add_meta_box( 'sidrest-menu-manager', __( 'Sections and items', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_menu_manager' ), Post_Types::MENU, 'normal', 'high' );
		add_meta_box( 'sidrest-menu-footer', __( 'Menu footer', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_menu_footer' ), Post_Types::MENU, 'normal' );
		add_meta_box( 'sidrest-menu-display', __( 'Display', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_menu_display' ), Post_Types::MENU, 'side' );
		add_meta_box( 'sidrest-menu-use', __( 'Show this menu', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_menu_use' ), Post_Types::MENU, 'side' );
	}

	/**
	 * Sections (with their items) in order, plus new sections.
	 *
	 * @param \WP_Post $post Menu.
	 */
	public function render_menu_manager( $post ) {
		wp_nonce_field( 'sidrest_menu', '_sidrest_menu_nonce' );
		$menu  = new Menu( $post );
		$items = self::all_posts( Post_Types::ITEM );
		echo '<p class="description">' . esc_html__( 'Add sections, drag them into order, and arrange the items inside each one. Changes to a section’s items apply everywhere that section is used.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		self::picker(
			'sidrest_sections',
			$menu->get_section_ids(),
			self::all_posts( Post_Types::SECTION ),
			__( 'Add an existing section…', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			static function ( $section_id ) use ( $items ) {
				$section = get_post( $section_id );
				if ( ! $section ) {
					return '';
				}
				ob_start();
				echo '<details class="sidrest-picker__nested"><summary>' . esc_html(
					sprintf(
						/* translators: %d: number of items. */
						_n( '%d item', '%d items', count( ( new Section( $section ) )->get_item_ids() ), 'sidcraft-restaurant-for-sidcraft-commerce' ),
						count( ( new Section( $section ) )->get_item_ids() )
					)
				) . '</summary>';
				echo '<input type="hidden" name="sidrest_section_items_present[' . esc_attr( $section_id ) . ']" value="1">';
				self::picker( 'sidrest_section_items[' . $section_id . ']', ( new Section( $section ) )->get_item_ids(), $items, __( 'Add an item…', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
				echo '</details>';
				return (string) ob_get_clean();
			}
		);
		echo '<div class="sidrest-new"><label for="sidrest-new-section">' . esc_html__( 'New sections (one per line)', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</label>';
		echo '<textarea id="sidrest-new-section" name="sidrest_new_sections" rows="2" class="large-text" placeholder="' . esc_attr__( "Appetizers\nMain Course\nDessert", 'sidcraft-restaurant-for-sidcraft-commerce' ) . '"></textarea>';
		echo '<p class="description">' . esc_html__( 'They are created and added to the end of this menu when you save.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p></div>';
	}

	/**
	 * Footer note.
	 *
	 * @param \WP_Post $post Menu.
	 */
	public function render_menu_footer( $post ) {
		echo '<textarea name="sidrest_footer" rows="3" class="large-text">' . esc_textarea( ( new Menu( $post ) )->get_footer() ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Shown under the menu: allergy notes, service charge, legal disclaimers. Basic HTML allowed.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
	}

	/**
	 * Display options.
	 *
	 * @param \WP_Post $post Menu.
	 */
	public function render_menu_display( $post ) {
		$saved = (array) get_post_meta( $post->ID, '_sidrest_display', true );
		echo '<p><label for="sidrest-style"><strong>' . esc_html__( 'Style', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</strong></label><br><select id="sidrest-style" name="sidrest_display[style]" class="widefat"><option value="">' . esc_html__( 'Site default', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</option>';
		foreach ( styles() as $slug => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $slug ), selected( $saved['style'] ?? '', $slug, false ), esc_html( $label ) );
		}
		echo '</select></p>';
		foreach ( array(
			'sidebar' => __( 'Sections sidebar', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'toolbar' => __( 'Search, filters and sorting', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		) as $key => $label ) {
			$value = (string) ( $saved[ $key ] ?? '' );
			echo '<p><label for="sidrest-' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><br><select id="sidrest-' . esc_attr( $key ) . '" name="sidrest_display[' . esc_attr( $key ) . ']" class="widefat">';
			foreach ( array(
				''  => __( 'Site default', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'1' => __( 'Show', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'0' => __( 'Hide', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			) as $v => $t ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $v ), selected( $value, (string) $v, false ), esc_html( $t ) );
			}
			echo '</select></p>';
		}
	}

	/**
	 * How to show the menu: its page, shortcode, Sidcraft page, QR code.
	 *
	 * @param \WP_Post $post Menu.
	 */
	public function render_menu_use( $post ) {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Save the menu first.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
			return;
		}
		echo '<p><strong>' . esc_html__( 'Sidcraft Page Builder', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</strong><br>' . esc_html__( 'Create a page with this menu and open it in Sidcraft Page Builder to design around it.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=sidrest_create_page&menu=' . $post->ID ), 'sidrest_create_page_' . $post->ID );
		echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Design a page in Sidcraft Page Builder', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</a></p>';
		echo '<p><strong>' . esc_html__( 'Shortcode', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</strong><br><code class="sidrest-copy">[sidrest_menu menu="' . esc_html( $post->ID ) . '"]</code></p>';
		echo '<p><strong>' . esc_html__( 'Block', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</strong><br>' . esc_html__( 'Restaurant Menu (Restaurant category), or the Restaurant patterns.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		if ( 'publish' === $post->post_status ) {
			echo '<p><strong>' . esc_html__( 'Menu page', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</strong><br><a href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener">' . esc_html( get_permalink( $post ) ) . '</a></p>';
			echo '<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=' . Post_Types::MENU . '&page=sidrest-qr&menu=' . $post->ID ) ) . '">' . esc_html__( 'Make a QR code', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</a></p>';
		}
	}

	/**
	 * Save a menu: section order, each section's item order, new sections,
	 * footer and display options.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save_menu( $post_id, $post ) {
		if ( ! isset( $_POST['_sidrest_menu_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_sidrest_menu_nonce'] ) ), 'sidrest_menu' ) || ! self::can_save( $post_id ) ) {
			return;
		}
		$menu     = new Menu( $post );
		$sections = self::posted_ids( 'sidrest_sections' );

		// New sections typed in the box.
		$new = isset( $_POST['sidrest_new_sections'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sidrest_new_sections'] ) ) : '';
		foreach ( array_filter( array_map( 'trim', explode( "\n", $new ) ) ) as $title ) {
			$id = wp_insert_post(
				array(
					'post_type'   => Post_Types::SECTION,
					'post_title'  => $title,
					'post_status' => 'publish',
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$sections[] = (int) $id;
			}
		}
		$menu->set_section_ids( $sections );

		// Item order inside sections that were shown in the manager.
		$present = isset( $_POST['sidrest_section_items_present'] ) ? array_keys( array_map( 'absint', (array) wp_unslash( $_POST['sidrest_section_items_present'] ) ) ) : array();
		$present = array_map( 'absint', $present );
		$posted  = isset( $_POST['sidrest_section_items'] ) ? (array) wp_unslash( $_POST['sidrest_section_items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value goes through absint() below.
		foreach ( $present as $section_id ) {
			$section = Section::get( $section_id );
			if ( $section && current_user_can( 'edit_post', $section_id ) ) {
				$section->set_item_ids( array_map( 'absint', (array) ( $posted[ $section_id ] ?? array() ) ) );
			}
		}

		$footer = isset( $_POST['sidrest_footer'] ) ? wp_kses_post( wp_unslash( $_POST['sidrest_footer'] ) ) : '';
		update_post_meta( $post_id, '_sidrest_footer', $footer );

		$display = isset( $_POST['sidrest_display'] ) ? (array) wp_unslash( $_POST['sidrest_display'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is checked below.
		$style   = sanitize_key( $display['style'] ?? '' );
		update_post_meta(
			$post_id,
			'_sidrest_display',
			array(
				'style'   => array_key_exists( $style, styles() ) ? $style : '',
				'sidebar' => in_array( $display['sidebar'] ?? '', array( '0', '1' ), true ) ? $display['sidebar'] : '',
				'toolbar' => in_array( $display['toolbar'] ?? '', array( '0', '1' ), true ) ? $display['toolbar'] : '',
			)
		);
	}

	/**
	 * Create a page holding this menu and open it in Sidcraft Page Builder.
	 */
	public function create_page() {
		$menu_id = isset( $_GET['menu'] ) ? absint( $_GET['menu'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce (checked next) includes this ID.
		check_admin_referer( 'sidrest_create_page_' . $menu_id );
		$menu = $menu_id ? Menu::get( $menu_id ) : null;
		if ( ! $menu || ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You cannot create pages.', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
		}
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => $menu->get_title(),
				'post_content' => '[sidrest_menu menu="' . $menu->get_id() . '"]',
			),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			wp_die( esc_html( $page_id->get_error_message() ) );
		}
		$builder = class_exists( '\SidcraftPageBuilder\Document\DocumentManager' ) && function_exists( 'sidcraft_page_builder_supports_post_type' ) && sidcraft_page_builder_supports_post_type( 'page' );
		if ( $builder ) {
			\SidcraftPageBuilder\Document\DocumentManager::save(
				$page_id,
				array(
					'version'  => '2.8',
					'settings' => array( 'template' => 'default' ),
					'root'     => array(
						array(
							'id'       => 'sidrest' . wp_generate_password( 6, false, false ),
							'type'     => 'heading',
							'settings' => array(
								'text' => $menu->get_title(),
								'tag'  => 'h1',
							),
						),
						array(
							'id'       => 'sidrest' . wp_generate_password( 6, false, false ),
							'type'     => 'sidrest-menu',
							'settings' => array( 'menu' => (string) $menu->get_id() ),
						),
					),
				)
			);
			wp_safe_redirect( admin_url( 'admin.php?page=sidcraft-page-builder&post_id=' . $page_id ) );
		} else {
			wp_safe_redirect( get_edit_post_link( $page_id, 'url' ) );
		}
		exit;
	}

	/*
	------------------------------------------------------------------
	 * Sections
	 * ----------------------------------------------------------------
	 */

	/**
	 * Section boxes.
	 */
	public function section_boxes() {
		add_meta_box( 'sidrest-section-items', __( 'Items in this section', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_section_items' ), Post_Types::SECTION, 'normal', 'high' );
		add_meta_box( 'sidrest-section-note', __( 'Section note', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_section_note' ), Post_Types::SECTION, 'normal' );
		add_meta_box( 'sidrest-section-menus', __( 'Used in menus', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_section_menus' ), Post_Types::SECTION, 'side' );
	}

	/**
	 * Items picker.
	 *
	 * @param \WP_Post $post Section.
	 */
	public function render_section_items( $post ) {
		wp_nonce_field( 'sidrest_section', '_sidrest_section_nonce' );
		self::picker( 'sidrest_items', ( new Section( $post ) )->get_item_ids(), self::all_posts( Post_Types::ITEM ), __( 'Add an item…', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
	}

	/**
	 * Note.
	 *
	 * @param \WP_Post $post Section.
	 */
	public function render_section_note( $post ) {
		echo '<input type="text" name="sidrest_note" class="large-text" value="' . esc_attr( ( new Section( $post ) )->get_note() ) . '" placeholder="' . esc_attr__( 'e.g. All items in this section come with salad or fries.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '">';
	}

	/**
	 * Menus using this section.
	 *
	 * @param \WP_Post $post Section.
	 */
	public function render_section_menus( $post ) {
		$menus = self::menus_with_section( $post->ID );
		if ( ! $menus ) {
			echo '<p>' . esc_html__( 'Not in any menu yet. Add it from a menu’s “Sections and items” box.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
			return;
		}
		echo '<ul>';
		foreach ( $menus as $menu ) {
			echo '<li><a href="' . esc_url( get_edit_post_link( $menu->ID ) ) . '">' . esc_html( $menu->post_title ) . '</a></li>';
		}
		echo '</ul>';
	}

	/**
	 * Menus that contain a section.
	 *
	 * @param int $section_id Section ID.
	 * @return \WP_Post[]
	 */
	protected static function menus_with_section( $section_id ) {
		$out = array();
		foreach ( get_posts(
			array(
				'post_type'      => Post_Types::MENU,
				'post_status'    => 'any',
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin info box.
				'no_found_rows'  => true,
			)
		) as $menu ) {
			if ( in_array( (int) $section_id, ( new Menu( $menu ) )->get_section_ids(), true ) ) {
				$out[] = $menu;
			}
		}
		return $out;
	}

	/**
	 * Save a section.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save_section( $post_id, $post ) {
		if ( ! isset( $_POST['_sidrest_section_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_sidrest_section_nonce'] ) ), 'sidrest_section' ) || ! self::can_save( $post_id ) ) {
			return;
		}
		( new Section( $post ) )->set_item_ids( self::posted_ids( 'sidrest_items' ) );
		update_post_meta( $post_id, '_sidrest_note', isset( $_POST['sidrest_note'] ) ? sanitize_text_field( wp_unslash( $_POST['sidrest_note'] ) ) : '' );
	}

	/*
	------------------------------------------------------------------
	 * Items
	 * ----------------------------------------------------------------
	 */

	/**
	 * Item boxes.
	 */
	public function item_boxes() {
		add_meta_box( 'sidrest-item-prices', __( 'Prices', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_item_prices' ), Post_Types::ITEM, 'normal', 'high' );
		add_meta_box( 'sidrest-item-labels', __( 'Dietary icons and badges', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_item_labels' ), Post_Types::ITEM, 'normal', 'high' );
		add_meta_box( 'sidrest-item-promo', __( 'Promotion', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_item_promo' ), Post_Types::ITEM, 'normal' );
		add_meta_box( 'sidrest-item-fields', __( 'Details', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_item_fields' ), Post_Types::ITEM, 'normal' );
		add_meta_box( 'sidrest-item-suppliers', __( 'Suppliers', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_item_suppliers' ), Post_Types::ITEM, 'normal' );
		add_meta_box( 'sidrest-item-related', __( 'Related items', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_item_related' ), Post_Types::ITEM, 'normal' );
		add_meta_box( 'sidrest-item-order', __( 'Online ordering', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_item_order' ), Post_Types::ITEM, 'side' );
		add_meta_box( 'sidrest-item-sections', __( 'In sections', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_item_sections' ), Post_Types::ITEM, 'side' );
	}

	/**
	 * Prices repeater.
	 *
	 * @param \WP_Post $post Item.
	 */
	public function render_item_prices( $post ) {
		wp_nonce_field( 'sidrest_item', '_sidrest_item_nonce' );
		$rows = ( new Item( $post ) )->get_prices();
		if ( ! $rows ) {
			$rows = array(
				array(
					'label' => '',
					'price' => '',
				),
			);
		}
		$sym = trim( \SidcraftCommerce\currency_symbol() );
		echo '<p class="description">' . esc_html__( 'One price, or several (Small / Large, Glass / Bottle). Give each a name when there is more than one.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		echo '<div class="sidrest-repeater" data-sidrest-repeater><table class="widefat sidrest-repeater__table"><thead><tr><th>' . esc_html__( 'Name (optional)', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th>' . esc_html( sprintf( /* translators: %s: currency symbol. */ __( 'Price (%s)', 'sidcraft-restaurant-for-sidcraft-commerce' ), $sym ) ) . '</th><th class="sidrest-repeater__tools"><span class="screen-reader-text">' . esc_html__( 'Actions', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</span></th></tr></thead><tbody data-rows>';
		foreach ( $rows as $i => $row ) {
			self::price_row( $i, $row );
		}
		echo '</tbody></table><template data-template>';
		self::price_row(
			'__i__',
			array(
				'label' => '',
				'price' => '',
			)
		);
		echo '</template><p><button type="button" class="button" data-add-row>' . esc_html__( 'Add a price', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</button></p></div>';
	}

	/**
	 * One price row.
	 *
	 * @param int|string $i   Index.
	 * @param array      $row label, price.
	 */
	protected static function price_row( $i, array $row ) {
		$price = '' === (string) $row['price'] ? '' : (string) ( 0 + $row['price'] );
		echo '<tr data-row><td><input type="text" class="widefat" name="sidrest_prices[' . esc_attr( $i ) . '][label]" value="' . esc_attr( $row['label'] ) . '" placeholder="' . esc_attr__( 'e.g. Glass', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '"></td>';
		echo '<td><input type="text" inputmode="decimal" class="widefat" name="sidrest_prices[' . esc_attr( $i ) . '][price]" value="' . esc_attr( $price ) . '" placeholder="0.00"></td>';
		echo '<td class="sidrest-repeater__tools"><button type="button" class="button-link sidrest-picker__remove" data-remove-row aria-label="' . esc_attr__( 'Remove', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '">×</button></td></tr>';
	}

	/**
	 * Dietary icons and badges.
	 *
	 * @param \WP_Post $post Item.
	 */
	public function render_item_labels( $post ) {
		$item = new Item( $post );
		$on   = (array) $item->meta( 'dietary' );
		echo '<fieldset class="sidrest-checks"><legend>' . esc_html__( 'Dietary and ethical', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</legend>';
		foreach ( Dietary::enabled() as $slug => $def ) {
			echo '<label><input type="checkbox" name="sidrest_dietary[]" value="' . esc_attr( $slug ) . '" ' . checked( in_array( $slug, $on, true ), true, false ) . '> ' . Dietary::icon( $slug ) . ' ' . esc_html( $def[0] ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Dietary::icon().
		}
		echo '</fieldset>';
		$badges = (array) $item->meta( 'badges' );
		$active = (array) setting( 'badges', array() );
		echo '<fieldset class="sidrest-checks"><legend>' . esc_html__( 'Badges', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</legend>';
		foreach ( badge_options() as $slug => $label ) {
			if ( in_array( $slug, $active, true ) ) {
				echo '<label><input type="checkbox" name="sidrest_badges[]" value="' . esc_attr( $slug ) . '" ' . checked( in_array( $slug, $badges, true ), true, false ) . '> <span class="sidrest-badge sidrest-badge--' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</span></label>';
			}
		}
		echo '</fieldset><p class="description">' . esc_html__( 'A “Sale” badge appears by itself while a promotion is running.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
	}

	/**
	 * Promotion.
	 *
	 * @param \WP_Post $post Item.
	 */
	public function render_item_promo( $post ) {
		$p    = ( new Item( $post ) )->get_promo();
		$days = array_map( 'intval', (array) $p['days'] );
		global $wp_locale;
		?>
		<p><label><input type="checkbox" name="sidrest_promo[enabled]" value="1" <?php checked( ! empty( $p['enabled'] ) ); ?>> <?php esc_html_e( 'Run a promotion on this item', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label></p>
		<div class="sidrest-grid">
			<p><label for="sidrest-promo-type"><?php esc_html_e( 'Discount', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
				<span class="sidrest-inline"><input type="text" inputmode="decimal" id="sidrest-promo-value" name="sidrest_promo[value]" value="<?php echo esc_attr( $p['value'] ? 0 + $p['value'] : '' ); ?>" size="6" aria-label="<?php esc_attr_e( 'Discount amount', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?>">
				<select id="sidrest-promo-type" name="sidrest_promo[type]">
					<option value="percent" <?php selected( $p['type'], 'percent' ); ?>><?php esc_html_e( '% off', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></option>
					<option value="amount" <?php selected( $p['type'], 'amount' ); ?>><?php echo esc_html( sprintf( /* translators: %s: currency symbol. */ __( '%s off', 'sidcraft-restaurant-for-sidcraft-commerce' ), trim( \SidcraftCommerce\currency_symbol() ) ) ); ?></option>
				</select></span></p>
			<p><label for="sidrest-promo-label"><?php esc_html_e( 'Badge text', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
				<input type="text" id="sidrest-promo-label" name="sidrest_promo[label]" value="<?php echo esc_attr( $p['label'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Happy hour', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?>"></p>
			<p><label for="sidrest-promo-start"><?php esc_html_e( 'From date', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
				<input type="date" id="sidrest-promo-start" name="sidrest_promo[start]" value="<?php echo esc_attr( $p['start'] ); ?>"></p>
			<p><label for="sidrest-promo-end"><?php esc_html_e( 'Until date', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
				<input type="date" id="sidrest-promo-end" name="sidrest_promo[end]" value="<?php echo esc_attr( $p['end'] ); ?>"></p>
			<p><label for="sidrest-promo-from"><?php esc_html_e( 'Daily from', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
				<input type="time" id="sidrest-promo-from" name="sidrest_promo[from]" value="<?php echo esc_attr( $p['from'] ); ?>"></p>
			<p><label for="sidrest-promo-to"><?php esc_html_e( 'Daily until', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
				<input type="time" id="sidrest-promo-to" name="sidrest_promo[to]" value="<?php echo esc_attr( $p['to'] ); ?>"></p>
		</div>
		<fieldset class="sidrest-checks sidrest-checks--days"><legend><?php esc_html_e( 'On these days (none = every day)', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></legend>
			<?php for ( $d = 0; $d < 7; $d++ ) : ?>
				<label><input type="checkbox" name="sidrest_promo[days][]" value="<?php echo esc_attr( $d ); ?>" <?php checked( in_array( $d, $days, true ) ); ?>> <?php echo esc_html( $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $d ) ) ); ?></label>
			<?php endfor; ?>
		</fieldset>
		<p class="description"><?php esc_html_e( 'Applies to every price of this item, on the menu and when ordering online. Leave dates and times empty to run it until you switch it off.', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></p>
		<?php
	}

	/**
	 * Custom fields defined in the settings.
	 *
	 * @param \WP_Post $post Item.
	 */
	public function render_item_fields( $post ) {
		$fields = (array) setting( 'custom_fields', array() );
		if ( ! $fields ) {
			echo '<p>' . wp_kses_post( sprintf( /* translators: %s: settings link. */ __( 'Add your own fields (calories, allergens, wine region…) under <a href="%s">Restaurant → Settings → Custom fields</a>.', 'sidcraft-restaurant-for-sidcraft-commerce' ), esc_url( admin_url( 'edit.php?post_type=' . Post_Types::MENU . '&page=sidrest-settings&tab=fields' ) ) ) ) . '</p>';
			return;
		}
		$values = (array) ( new Item( $post ) )->meta( 'cf' );
		echo '<div class="sidrest-grid">';
		foreach ( $fields as $field ) {
			$key = (string) ( $field['key'] ?? '' );
			if ( '' === $key ) {
				continue;
			}
			echo '<p><label for="sidrest-cf-' . esc_attr( $key ) . '">' . esc_html( $field['label'] ?? $key ) . '</label><input type="text" class="widefat" id="sidrest-cf-' . esc_attr( $key ) . '" name="sidrest_cf[' . esc_attr( $key ) . ']" value="' . esc_attr( $values[ $key ] ?? '' ) . '"></p>';
		}
		echo '</div>';
	}

	/**
	 * Suppliers repeater.
	 *
	 * @param \WP_Post $post Item.
	 */
	public function render_item_suppliers( $post ) {
		$rows = ( new Item( $post ) )->get_suppliers();
		echo '<p class="description">' . esc_html__( 'Local farms, producers or ethical sourcing programmes. With an address, the item page shows a map.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		echo '<div class="sidrest-repeater" data-sidrest-repeater><table class="widefat sidrest-repeater__table"><thead><tr><th>' . esc_html__( 'Name', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th>' . esc_html__( 'Address', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th>' . esc_html__( 'Note', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th class="sidrest-repeater__tools"></th></tr></thead><tbody data-rows>';
		foreach ( $rows as $i => $row ) {
			self::supplier_row( $i, $row );
		}
		echo '</tbody></table><template data-template>';
		self::supplier_row(
			'__i__',
			array(
				'name'    => '',
				'address' => '',
				'note'    => '',
			)
		);
		echo '</template><p><button type="button" class="button" data-add-row>' . esc_html__( 'Add a supplier', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</button></p></div>';
	}

	/**
	 * One supplier row.
	 *
	 * @param int|string $i   Index.
	 * @param array      $row name, address, note.
	 */
	protected static function supplier_row( $i, array $row ) {
		echo '<tr data-row>';
		foreach ( array( 'name', 'address', 'note' ) as $key ) {
			echo '<td><input type="text" class="widefat" name="sidrest_suppliers[' . esc_attr( $i ) . '][' . esc_attr( $key ) . ']" value="' . esc_attr( $row[ $key ] ) . '"></td>';
		}
		echo '<td class="sidrest-repeater__tools"><button type="button" class="button-link sidrest-picker__remove" data-remove-row aria-label="' . esc_attr__( 'Remove', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '">×</button></td></tr>';
	}

	/**
	 * Related items picker.
	 *
	 * @param \WP_Post $post Item.
	 */
	public function render_item_related( $post ) {
		$choices = self::all_posts( Post_Types::ITEM );
		unset( $choices[ $post->ID ] );
		echo '<p class="description">' . esc_html__( 'Shown on the item’s page. Leave empty to suggest items from the same category.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		self::picker( 'sidrest_related', array_map( 'intval', (array) ( new Item( $post ) )->meta( 'related' ) ), $choices, __( 'Add an item…', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
	}

	/**
	 * Online ordering switch.
	 *
	 * @param \WP_Post $post Item.
	 */
	public function render_item_order( $post ) {
		$item = new Item( $post );
		echo '<p><label><input type="checkbox" name="sidrest_orderable" value="1" ' . checked( (bool) $item->meta( 'orderable' ), true, false ) . '> ' . esc_html__( 'Customers can order this online', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</label></p>';
		echo '<p class="description">' . esc_html__( 'Adds an “Add” button in the Ordering style and on the item page. Payment, cart and checkout come from Sidcraft Commerce; several prices become a choice.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		$product = $item->get_product();
		if ( $product && $item->meta( 'orderable' ) ) {
			echo '<p><a href="' . esc_url( get_edit_post_link( $product->get_id() ) ) . '">' . esc_html__( 'Linked store product', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</a> · ' . esc_html( price( (float) $product->get_price() ) ) . '</p>';
		}
	}

	/**
	 * Which sections the item is in.
	 *
	 * @param \WP_Post $post Item.
	 */
	public function render_item_sections( $post ) {
		$sections = self::all_posts( Post_Types::SECTION );
		if ( ! $sections ) {
			echo '<p>' . esc_html__( 'No sections yet.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . ' <a href="' . esc_url( admin_url( 'post-new.php?post_type=' . Post_Types::SECTION ) ) . '">' . esc_html__( 'Add one', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</a></p>';
			return;
		}
		echo '<input type="hidden" name="sidrest_sections_present" value="1"><div class="sidrest-scroll">';
		foreach ( $sections as $id => $label ) {
			$section = Section::get( $id );
			$in      = $section && in_array( (int) $post->ID, $section->get_item_ids(), true );
			echo '<label class="sidrest-block-check"><input type="checkbox" name="sidrest_in_sections[]" value="' . esc_attr( $id ) . '" ' . checked( $in, true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div><p class="description">' . esc_html__( 'New sections get the item at the end; reorder in the section or menu.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
	}

	/**
	 * Save an item.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save_item( $post_id, $post ) {
		if ( ! isset( $_POST['_sidrest_item_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_sidrest_item_nonce'] ) ), 'sidrest_item' ) || ! self::can_save( $post_id ) ) {
			return;
		}

		// Prices.
		$prices = array();
		foreach ( isset( $_POST['sidrest_prices'] ) ? (array) wp_unslash( $_POST['sidrest_prices'] ) : array() as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field below.
			$amount = parse_price( is_array( $row ) ? ( $row['price'] ?? '' ) : '' );
			if ( '' !== $amount ) {
				$prices[] = array(
					'label' => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
					'price' => $amount,
				);
			}
		}
		update_post_meta( $post_id, '_sidrest_prices', $prices );

		// Icons and badges.
		$diets = isset( $_POST['sidrest_dietary'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sidrest_dietary'] ) ) : array();
		update_post_meta( $post_id, '_sidrest_dietary', array_values( array_intersect( $diets, array_keys( Dietary::all() ) ) ) );
		$badges = isset( $_POST['sidrest_badges'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sidrest_badges'] ) ) : array();
		update_post_meta( $post_id, '_sidrest_badges', array_values( array_intersect( $badges, array_keys( badge_options() ) ) ) );

		// Promotion.
		$raw   = isset( $_POST['sidrest_promo'] ) ? (array) wp_unslash( $_POST['sidrest_promo'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field below.
		$date  = static function ( $v ) {
			$v = sanitize_text_field( (string) $v );
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		};
		$time  = static function ( $v ) {
			$v = sanitize_text_field( (string) $v );
			return preg_match( '/^\d{2}:\d{2}$/', $v ) ? $v : '';
		};
		$value = (float) parse_price( $raw['value'] ?? '' );
		$type  = 'amount' === ( $raw['type'] ?? '' ) ? 'amount' : 'percent';
		update_post_meta(
			$post_id,
			'_sidrest_promo',
			array(
				'enabled' => empty( $raw['enabled'] ) ? 0 : 1,
				'type'    => $type,
				'value'   => 'percent' === $type ? min( 100, $value ) : $value,
				'label'   => sanitize_text_field( (string) ( $raw['label'] ?? '' ) ),
				'start'   => $date( $raw['start'] ?? '' ),
				'end'     => $date( $raw['end'] ?? '' ),
				'days'    => array_values( array_intersect( array_map( 'intval', (array) ( $raw['days'] ?? array() ) ), range( 0, 6 ) ) ),
				'from'    => $time( $raw['from'] ?? '' ),
				'to'      => $time( $raw['to'] ?? '' ),
			)
		);

		// Custom fields.
		$cf = array();
		foreach ( isset( $_POST['sidrest_cf'] ) ? (array) wp_unslash( $_POST['sidrest_cf'] ) : array() as $key => $val ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
			$key = sanitize_key( (string) $key );
			if ( '' !== $key && is_scalar( $val ) ) {
				$cf[ $key ] = sanitize_text_field( (string) $val );
			}
		}
		update_post_meta( $post_id, '_sidrest_cf', $cf );

		// Suppliers.
		$suppliers = array();
		foreach ( isset( $_POST['sidrest_suppliers'] ) ? (array) wp_unslash( $_POST['sidrest_suppliers'] ) : array() as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field below.
			$name = is_array( $row ) ? sanitize_text_field( (string) ( $row['name'] ?? '' ) ) : '';
			if ( '' !== $name ) {
				$suppliers[] = array(
					'name'    => $name,
					'address' => sanitize_text_field( (string) ( $row['address'] ?? '' ) ),
					'note'    => sanitize_text_field( (string) ( $row['note'] ?? '' ) ),
				);
			}
		}
		update_post_meta( $post_id, '_sidrest_suppliers', $suppliers );

		update_post_meta( $post_id, '_sidrest_related', array_values( array_diff( self::posted_ids( 'sidrest_related' ), array( $post_id ) ) ) );
		update_post_meta( $post_id, '_sidrest_orderable', empty( $_POST['sidrest_orderable'] ) ? 0 : 1 );

		// Sections.
		if ( ! empty( $_POST['sidrest_sections_present'] ) ) {
			$wanted = self::posted_ids( 'sidrest_in_sections' );
			foreach ( array_keys( self::all_posts( Post_Types::SECTION ) ) as $section_id ) {
				$section = Section::get( $section_id );
				if ( ! $section || ! current_user_can( 'edit_post', $section_id ) ) {
					continue;
				}
				$ids = $section->get_item_ids();
				$in  = in_array( (int) $post_id, $ids, true );
				if ( in_array( (int) $section_id, $wanted, true ) && ! $in ) {
					$ids[] = (int) $post_id;
					$section->set_item_ids( $ids );
				} elseif ( ! in_array( (int) $section_id, $wanted, true ) && $in ) {
					$section->set_item_ids( array_diff( $ids, array( (int) $post_id ) ) );
				}
			}
		}
	}

	/*
	------------------------------------------------------------------
	 * List columns
	 * ----------------------------------------------------------------
	 */

	/**
	 * Item columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public function item_columns( $cols ) {
		$out = array();
		foreach ( $cols as $key => $label ) {
			if ( 'title' === $key ) {
				$out['sidrest_photo'] = '<span class="screen-reader-text">' . esc_html__( 'Photo', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</span>';
			}
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['sidrest_price'] = __( 'Price', 'sidcraft-restaurant-for-sidcraft-commerce' );
				$out['sidrest_order'] = __( 'Online', 'sidcraft-restaurant-for-sidcraft-commerce' );
			}
		}
		return $out;
	}

	/**
	 * Item column cell.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post.
	 */
	public function item_column( $col, $post_id ) {
		$item = Item::get( $post_id );
		if ( ! $item ) {
			return;
		}
		if ( 'sidrest_photo' === $col ) {
			echo $item->get_image( array( 40, 40 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image().
		} elseif ( 'sidrest_price' === $col ) {
			echo \SidcraftRestaurant\Renderer::prices_html( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer.
		} elseif ( 'sidrest_order' === $col ) {
			echo $item->meta( 'orderable' ) ? '<span class="dashicons dashicons-cart" title="' . esc_attr__( 'Orderable online', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '"></span>' : '—';
		}
	}

	/**
	 * Menu columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public function menu_columns( $cols ) {
		$date = $cols['date'] ?? null;
		unset( $cols['date'] );
		$cols['sidrest_sections']  = __( 'Sections', 'sidcraft-restaurant-for-sidcraft-commerce' );
		$cols['sidrest_shortcode'] = __( 'Shortcode', 'sidcraft-restaurant-for-sidcraft-commerce' );
		if ( $date ) {
			$cols['date'] = $date;
		}
		return $cols;
	}

	/**
	 * Menu column cell.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post.
	 */
	public function menu_column( $col, $post_id ) {
		$menu = Menu::get( $post_id );
		if ( ! $menu ) {
			return;
		}
		if ( 'sidrest_sections' === $col ) {
			echo esc_html( implode( ', ', array_map( static fn( $s ) => $s->get_title(), $menu->get_sections() ) ) );
		} elseif ( 'sidrest_shortcode' === $col ) {
			echo '<code>[sidrest_menu menu="' . esc_html( $post_id ) . '"]</code>';
		}
	}

	/**
	 * Section columns.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public function section_columns( $cols ) {
		$date = $cols['date'] ?? null;
		unset( $cols['date'] );
		$cols['sidrest_items'] = __( 'Items', 'sidcraft-restaurant-for-sidcraft-commerce' );
		$cols['sidrest_menus'] = __( 'Menus', 'sidcraft-restaurant-for-sidcraft-commerce' );
		if ( $date ) {
			$cols['date'] = $date;
		}
		return $cols;
	}

	/**
	 * Section column cell.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post.
	 */
	public function section_column( $col, $post_id ) {
		if ( 'sidrest_items' === $col ) {
			$section = Section::get( $post_id );
			echo esc_html( (string) ( $section ? count( $section->get_item_ids() ) : 0 ) );
		} elseif ( 'sidrest_menus' === $col ) {
			echo esc_html( implode( ', ', wp_list_pluck( self::menus_with_section( $post_id ), 'post_title' ) ) );
		}
	}
}
