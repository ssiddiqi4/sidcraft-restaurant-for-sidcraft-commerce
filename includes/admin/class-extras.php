<?php
/**
 * Admin boxes for 1.2.0: on items "Sold out today", allergens and meal
 * deal groups; on menus and sections the times they are served; and a
 * "Sold out" column with a one-click switch in the Menu items list.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Admin;

use SidcraftRestaurant\Post_Types;
use SidcraftRestaurant\Service\Allergens;
use SidcraftRestaurant\Service\Availability;
use SidcraftRestaurant\Service\Deals;

defined( 'ABSPATH' ) || exit;

/**
 * Class Extras
 */
class Extras {

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes_' . Post_Types::ITEM, array( $this, 'item_boxes' ) );
		add_action( 'add_meta_boxes_' . Post_Types::MENU, array( $this, 'times_box' ) );
		add_action( 'add_meta_boxes_' . Post_Types::SECTION, array( $this, 'times_box' ) );
		add_action( 'save_post_' . Post_Types::ITEM, array( $this, 'save_item' ), 15, 2 );
		add_action( 'save_post_' . Post_Types::MENU, array( $this, 'save_times' ), 15 );
		add_action( 'save_post_' . Post_Types::SECTION, array( $this, 'save_times' ), 15 );
		add_filter( 'manage_' . Post_Types::ITEM . '_posts_columns', array( $this, 'columns' ), 20 );
		add_action( 'manage_' . Post_Types::ITEM . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	/**
	 * Item boxes.
	 */
	public function item_boxes() {
		add_meta_box( 'sidrest-item-today', __( 'Today', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_today' ), Post_Types::ITEM, 'side', 'high' );
		if ( Allergens::enabled() ) {
			add_meta_box( 'sidrest-item-allergens', __( 'Allergens', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_allergens' ), Post_Types::ITEM, 'normal', 'default' );
		}
		add_meta_box( 'sidrest-item-deal', __( 'Meal deal', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_deal' ), Post_Types::ITEM, 'normal', 'default' );
	}

	/**
	 * "Sold out today".
	 *
	 * @param \WP_Post $post Item.
	 */
	public function render_today( $post ) {
		wp_nonce_field( 'sidrest_extras', '_sidrest_extras_nonce' );
		echo '<p><label><input type="checkbox" name="sidrest_sold_out" value="1" ' . checked( Availability::sold_out( $post->ID ), true, false ) . '> ' . esc_html__( 'Sold out today', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</label></p>';
		echo '<p class="description">' . esc_html__( 'Stays on the menu, greyed out, and can’t be ordered until tomorrow. Also on the kitchen screen and in the Menu items list.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
	}

	/**
	 * Allergen checkboxes.
	 *
	 * @param \WP_Post $post Item.
	 */
	public function render_allergens( $post ) {
		$on = Allergens::of( $post->ID );
		echo '<fieldset class="sidrest-checks"><legend>' . esc_html__( 'This dish contains', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</legend>';
		foreach ( Allergens::all() as $slug => $name ) {
			echo '<label><input type="checkbox" name="sidrest_allergens[]" value="' . esc_attr( $slug ) . '" ' . checked( in_array( $slug, $on, true ), true, false ) . '> ' . esc_html( $name ) . '</label>';
		}
		echo '</fieldset><p class="description">' . esc_html__( 'Shown on menus and the item page, used by the allergen filter, and printed on kitchen tickets. Keep it accurate: guests rely on it.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
	}

	/**
	 * Meal deal groups.
	 *
	 * @param \WP_Post $post Item.
	 */
	public function render_deal( $post ) {
		$groups  = Deals::groups( $post->ID );
		$choices = Deals::item_choices( $post->ID );
		echo '<p class="description">' . esc_html__( 'Make this item a meal deal: its price buys one choice from each group (Main, Side, Drink…). Customers choose when they order online, and the kitchen ticket shows what they picked.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		echo '<div class="sidrest-repeater" data-sidrest-repeater><table class="widefat sidrest-repeater__table"><thead><tr><th>' . esc_html__( 'Group', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th>' . esc_html__( 'Items to choose from (Ctrl/⌘ to pick several)', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th class="sidrest-repeater__tools"></th></tr></thead><tbody data-rows>';
		foreach ( $groups as $i => $group ) {
			self::deal_row( $i, $group, $choices );
		}
		echo '</tbody></table><template data-template>';
		self::deal_row(
			'__i__',
			array(
				'label' => '',
				'items' => array(),
			),
			$choices
		);
		echo '</template><p><button type="button" class="button" data-add-row>' . esc_html__( 'Add a group', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</button></p></div>';
	}

	/**
	 * One deal group row.
	 *
	 * @param int|string $i       Index.
	 * @param array      $group   label, items.
	 * @param array      $choices id => title.
	 */
	protected static function deal_row( $i, array $group, array $choices ) {
		echo '<tr data-row><td><input type="text" class="widefat" name="sidrest_deal[' . esc_attr( (string) $i ) . '][label]" value="' . esc_attr( $group['label'] ) . '" placeholder="' . esc_attr__( 'e.g. Side', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '"></td>';
		echo '<td><select multiple size="5" class="widefat" name="sidrest_deal[' . esc_attr( (string) $i ) . '][items][]" aria-label="' . esc_attr__( 'Items to choose from', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '">';
		foreach ( $choices as $id => $title ) {
			printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $id, selected( in_array( (int) $id, $group['items'], true ), true, false ), esc_html( $title ) );
		}
		echo '</select></td><td class="sidrest-repeater__tools"><button type="button" class="button-link sidrest-picker__remove" data-remove-row aria-label="' . esc_attr__( 'Remove', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '">×</button></td></tr>';
	}

	/**
	 * Save the item boxes.
	 *
	 * @param int      $post_id Item.
	 * @param \WP_Post $post    Item.
	 */
	public function save_item( $post_id, $post ) {
		unset( $post );
		if ( ! self::can_save( $post_id ) ) {
			return;
		}
		$sold = ! empty( $_POST['sidrest_sold_out'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in can_save().
		if ( $sold !== Availability::sold_out( $post_id ) ) {
			Availability::set_sold_out( $post_id, $sold );
		}
		if ( Allergens::enabled() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in can_save().
			Allergens::save( $post_id, isset( $_POST['sidrest_allergens'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sidrest_allergens'] ) ) : array() );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Checked in can_save(); sanitized in Deals::save().
		Deals::save( $post_id, isset( $_POST['sidrest_deal'] ) && is_array( $_POST['sidrest_deal'] ) ? wp_unslash( $_POST['sidrest_deal'] ) : array() );
	}

	/**
	 * Nonce, autosave and capability.
	 *
	 * @param int $post_id Post.
	 * @return bool
	 */
	protected static function can_save( $post_id ) {
		if ( ! isset( $_POST['_sidrest_extras_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_sidrest_extras_nonce'] ) ), 'sidrest_extras' ) ) {
			return false;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * "Served" box on menus and sections.
	 *
	 * @param \WP_Post $post Menu or section.
	 */
	public function times_box( $post ) {
		add_meta_box( 'sidrest-times', __( 'Served', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( $this, 'render_times' ), $post->post_type, 'side', 'default' );
	}

	/**
	 * Days and times.
	 *
	 * @param \WP_Post $post Menu or section.
	 */
	public function render_times( $post ) {
		global $wp_locale;
		wp_nonce_field( 'sidrest_extras', '_sidrest_extras_nonce' );
		$t    = Availability::times( $post->ID );
		$days = $t ? $t['days'] : array();
		echo '<p class="description">' . esc_html( Post_Types::MENU === $post->post_type ? __( 'Only order from this menu at these times (e.g. Breakfast 07:00–11:00). Empty: always.', 'sidcraft-restaurant-for-sidcraft-commerce' ) : __( 'Only order from this section at these times (e.g. Lunch on weekdays). Empty: always.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) . '</p>';
		echo '<p><label>' . esc_html__( 'From', 'sidcraft-restaurant-for-sidcraft-commerce' ) . ' <input type="time" name="sidrest_times[from]" value="' . esc_attr( $t ? $t['from'] : '' ) . '"></label> ';
		echo '<label>' . esc_html__( 'until', 'sidcraft-restaurant-for-sidcraft-commerce' ) . ' <input type="time" name="sidrest_times[to]" value="' . esc_attr( $t ? $t['to'] : '' ) . '"></label></p>';
		echo '<fieldset class="sidrest-checks sidrest-checks--days"><legend>' . esc_html__( 'On these days (none = every day)', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</legend>';
		for ( $d = 0; $d < 7; $d++ ) {
			echo '<label><input type="checkbox" name="sidrest_times[days][]" value="' . esc_attr( (string) $d ) . '" ' . checked( in_array( $d, $days, true ), true, false ) . '> ' . esc_html( $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $d ) ) ) . '</label>';
		}
		echo '</fieldset><p class="description">' . esc_html__( 'Outside these times the dishes stay on the menu, greyed out with when they are served, and can only be ordered for a time when they are.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
	}

	/**
	 * Save the times.
	 *
	 * @param int $post_id Menu or section.
	 */
	public function save_times( $post_id ) {
		if ( ! isset( $_POST['_sidrest_extras_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_sidrest_extras_nonce'] ) ), 'sidrest_extras' ) || ! self::can_save( $post_id ) ) {
			return;
		}
		$raw  = isset( $_POST['sidrest_times'] ) && is_array( $_POST['sidrest_times'] ) ? wp_unslash( $_POST['sidrest_times'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is checked below (days as integers 0–6, times as HH:MM).
		$time = static function ( $v ) {
			return preg_match( '/^\d{2}:\d{2}$/', (string) $v ) ? (string) $v : '';
		};
		$t = array(
			'days' => array_values( array_intersect( range( 0, 6 ), array_map( 'intval', (array) ( $raw['days'] ?? array() ) ) ) ),
			'from' => $time( $raw['from'] ?? '' ),
			'to'   => $time( $raw['to'] ?? '' ),
		);
		if ( ! $t['days'] && ( '' === $t['from'] || '' === $t['to'] ) ) {
			delete_post_meta( $post_id, Availability::TIMES );
		} else {
			update_post_meta( $post_id, Availability::TIMES, $t );
		}
		Availability::forget();
	}

	/**
	 * "Today" column.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public function columns( $cols ) {
		$date = $cols['date'] ?? null;
		unset( $cols['date'] );
		$cols['sidrest_today'] = __( 'Today', 'sidcraft-restaurant-for-sidcraft-commerce' );
		if ( $date ) {
			$cols['date'] = $date;
		}
		return $cols;
	}

	/**
	 * Sold out / on sale, with a switch.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Item.
	 */
	public function column( $col, $post_id ) {
		if ( 'sidrest_today' !== $col ) {
			return;
		}
		$sold = Availability::sold_out( $post_id );
		$url  = wp_nonce_url( admin_url( 'admin-post.php?action=sidrest_sold_out&item=' . (int) $post_id ), 'sidrest_sold_out_' . (int) $post_id );
		echo '<span class="sidrest-today ' . ( $sold ? 'is-sold-out' : 'is-on' ) . '">' . esc_html( $sold ? __( 'Sold out', 'sidcraft-restaurant-for-sidcraft-commerce' ) : __( 'Available', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) . '</span><br>';
		echo '<a href="' . esc_url( $url ) . '">' . esc_html( $sold ? __( 'Back on sale', 'sidcraft-restaurant-for-sidcraft-commerce' ) : __( 'Sold out today', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) . '</a>';
	}
}
