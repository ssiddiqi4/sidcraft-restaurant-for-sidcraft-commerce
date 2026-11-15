<?php
/**
 * Restaurant → Settings: General, Style, Labels, Icons & badges, Custom fields,
 * Delivery.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Admin;

use SidcraftRestaurant\Delivery\Delivery;
use SidcraftRestaurant\Delivery\Platforms;
use SidcraftRestaurant\Dietary;
use SidcraftRestaurant\Frontend;
use SidcraftRestaurant\Post_Types;
use function SidcraftRestaurant\all_settings;
use function SidcraftRestaurant\badge_options;
use function SidcraftRestaurant\default_labels;
use function SidcraftRestaurant\defaults;
use function SidcraftRestaurant\manage_cap;
use function SidcraftRestaurant\setting;
use function SidcraftRestaurant\styles;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 */
class Settings {

	const PAGE = 'sidrest-settings';

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'update_option_sidrest_settings', array( $this, 'flush' ) );
		add_action( 'add_option_sidrest_settings', array( $this, 'flush' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Drop the settings cache after saving.
	 */
	public function flush() {
		all_settings( true );
	}

	/**
	 * Colour pickers on the Style tab.
	 *
	 * @param string $hook Screen hook.
	 */
	public function assets( $hook ) {
		if ( false !== strpos( (string) $hook, self::PAGE ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
		}
	}

	/**
	 * Submenu.
	 */
	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Post_Types::MENU,
			__( 'Restaurant settings', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			__( 'Settings', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			manage_cap(),
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Register the option.
	 */
	public function register() {
		register_setting(
			'sidrest_settings',
			'sidrest_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Tabs.
	 *
	 * @return array
	 */
	protected function tabs() {
		return array(
			'general'  => __( 'General', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'style'    => __( 'Style', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'labels'   => __( 'Labels', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'icons'    => __( 'Icons & badges', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'fields'   => __( 'Custom fields', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'hours'    => __( 'Hours & ordering', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'delivery' => __( 'Delivery', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'dinein'   => __( 'Dine-in & tips', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'bookings' => __( 'Reservations', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		);
	}

	/**
	 * Field definitions per tab: key => [ label, type, help, choices ].
	 *
	 * @return array
	 */
	protected function fields() {
		$fonts = array();
		foreach ( Frontend::fonts() as $key => $font ) {
			$fonts[ $key ] = $font[0];
		}
		return array(
			'general' => array(
				'default_style'     => array( __( 'Default menu style', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'select', __( 'Each menu, block or Unit can choose its own.', 'sidcraft-restaurant-for-sidcraft-commerce' ), styles() ),
				'show_sidebar'      => array( __( 'Sections sidebar', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'A list of the menu’s sections for quick navigation (a scrolling bar on phones).', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'show_toolbar'      => array( __( 'Search, filters and sorting', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'Shown above menus by default. Choose which tools below.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'enable_search'     => array( __( 'Search box', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox' ),
				'enable_categories' => array( __( 'Filter by category', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox' ),
				'enable_dietary'    => array( __( 'Filter by dietary icon', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox' ),
				'enable_sort'       => array( __( 'Sort by name or price', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox' ),
				'show_images'       => array( __( 'Item photos', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'In the photo grid, photo list and ordering styles.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'show_menu_title'   => array( __( 'Show the menu’s title above it', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox' ),
				'show_legend'       => array( __( 'Dietary key under menus', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox' ),
				'item_pages'        => array( __( 'Item pages', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'Item names link to their own page with details, suppliers, reviews and related items.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'hide_from_shop'    => array( __( 'Keep orderable items out of store product grids', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'They are ordered from the menu instead.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'supplier_maps'     => array( __( 'Maps for suppliers', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'Shows a Google Map for each supplier address on item pages (loaded from Google when the page is viewed).', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'schema'            => array( __( 'Menu structured data', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'Adds schema.org Menu data so search engines recognise your menu.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'restaurant_name'   => array( __( 'Restaurant name (structured data)', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text', __( 'Optional. Describes the menu as your restaurant’s menu.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'cuisine'           => array( __( 'Cuisine (structured data)', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text', __( 'Optional, comma separated: Italian, Pizza.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'allergens'         => array( __( 'Allergens', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'Tick the 14 declarable allergens (gluten, milk, nuts…) on each item; menus and item pages list them and the kitchen ticket prints them.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'allergen_filter'   => array( __( 'Allergen filter', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( '“Hide dishes with…” buttons above menus.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
			),
			'hours'    => array(
				'ordering_paused' => array( __( 'Pause online ordering', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'Busy night or the kitchen is closed early: checkout refuses restaurant orders until you untick this.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'holidays'        => array( __( 'Closed on these dates', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'textarea', __( 'One date per line, e.g. 2026-12-25.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'closed_mode'     => array(
					__( 'While closed', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'select',
					'',
					array(
						'later'  => __( 'Take orders for later (when we’re open)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'refuse' => __( 'Don’t take orders', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					),
				),
				'hours_banner'    => array( __( '“We’re closed” line above menus', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'Says when you open next. Worked out in the visitor’s browser, so cached pages stay right.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'asap'            => array( __( 'Offer “as soon as possible”', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'Uses the preparation time under Delivery.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'order_ahead'     => array( __( 'Order ahead', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'Customers can pick a later pickup or delivery time.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'slot_minutes'    => array( __( 'Time slots every (minutes)', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'number', '', array( 5, 120 ) ),
				'slot_capacity'   => array( __( 'Orders per time slot', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'number', __( '0 = no limit. A full slot isn’t offered any more.', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 0, 500 ) ),
				'order_days'      => array( __( 'Days ahead customers can order for', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'number', __( '0 = today only.', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 0, 14 ) ),
				'kitchen_sound'   => array( __( 'Kitchen screen: sound for new orders', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox' ),
				'ready_email'     => array( __( 'Email customers when their order is ready', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'When the kitchen screen moves a pickup or table order to Ready.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'ticket_width'    => array( __( 'Printed ticket width (mm)', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'number', __( '80 or 58 for receipt printers.', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 40, 210 ) ),
			),
			'dinein'   => array(
				'dine_in'      => array( __( 'Ordering at the table', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'Each table gets its own QR code under Restaurant → QR codes. Guests who scan it order from their phone, marked for their table, with no delivery fee.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'tables'       => array( __( 'Tables', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'textarea', __( 'One name or number per line: 1, 2, Patio 3…', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'pay_at_table' => array( __( 'Offer “Pay at the table”', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'The order goes to the kitchen at once and is paid to the server. Guests can still pay online.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'tips'         => array( __( 'Tips at checkout', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox' ),
				'tip_presets'  => array( __( 'Tip percentages', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text', __( 'Comma separated, e.g. 10, 15, 20.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'tip_custom'   => array( __( 'Let customers enter an amount', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox' ),
				'tip_for'      => array(
					__( 'Ask for tips on', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'select',
					'',
					array(
						'all'    => __( 'Every restaurant order', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'dinein' => __( 'Table orders only', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					),
				),
			),
			'bookings' => array(
				'bookings'          => array( __( 'Online table booking', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'Add the Table Booking Unit or block, or [sidrest_booking], to a page. Bookings are listed under Restaurant → Reservations.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'booking_confirm'   => array(
					__( 'New bookings', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'select',
					'',
					array(
						'auto'   => __( 'Confirm automatically', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'manual' => __( 'Wait for me to confirm', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					),
				),
				'booking_capacity'  => array( __( 'Seats', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'number', __( 'Most guests booked at the same time (a booking keeps its table 90 minutes).', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 1, 1000 ) ),
				'booking_max_party' => array( __( 'Largest party online', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'number', __( 'Bigger groups call you.', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 1, 50 ) ),
				'booking_slot'      => array( __( 'Booking times every (minutes)', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'number', '', array( 15, 120 ) ),
				'booking_notice'    => array( __( 'Book at least (hours ahead)', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'number', '', array( 0, 72 ) ),
				'booking_days'      => array( __( 'Book up to (days ahead)', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'number', '', array( 1, 365 ) ),
				'booking_reminder'  => array( __( 'Reminder email the day before', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox' ),
			),
			'style'   => array(
				'accent'        => array( __( 'Accent colour', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'color', __( 'Buttons, active filters, section links.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'price_color'   => array( __( 'Price colour', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'color' ),
				'text'          => array( __( 'Text colour', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'color', __( 'Empty: the theme’s colours.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'muted'         => array( __( 'Secondary text colour', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'color' ),
				'background'    => array( __( 'Menu background', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'color' ),
				'card'          => array( __( 'Card background', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'color' ),
				'border_color'  => array( __( 'Border colour', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'color' ),
				'border_width'  => array( __( 'Border width (px)', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'number', '', array( 0, 6 ) ),
				'radius'        => array( __( 'Corner radius (px)', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'number', '', array( 0, 40 ) ),
				'gap'           => array( __( 'Space between items (px)', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'number', '', array( 4, 60 ) ),
				'max_width'     => array( __( 'Maximum menu width (px)', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'number', __( '0 = as wide as the page allows.', 'sidcraft-restaurant-for-sidcraft-commerce' ), array( 0, 2400 ) ),
				'sidebar_width' => array( __( 'Sidebar width (px)', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'number', '', array( 140, 400 ) ),
				'image_ratio'   => array(
					__( 'Photo shape', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'select',
					'',
					array(
						'1/1'  => __( 'Square', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'4/3'  => '4:3',
						'3/2'  => '3:2',
						'16/9' => '16:9',
					),
				),
				'heading_font'  => array( __( 'Heading font', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'select', '', $fonts ),
				'body_font'     => array( __( 'Text font', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'select', '', $fonts ),
			),
		);
	}

	/**
	 * Sanitize: merge the posted tab over the stored settings.
	 *
	 * @param mixed $input Posted values.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$stored = get_option( 'sidrest_settings', array() );
		$out    = is_array( $stored ) ? $stored : array();
		$tab    = isset( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : '';
		$fields = $this->fields();

		if ( isset( $fields[ $tab ] ) ) {
			foreach ( $fields[ $tab ] as $key => $def ) {
				$value = $input[ $key ] ?? '';
				switch ( $def[1] ) {
					case 'checkbox':
						$out[ $key ] = empty( $value ) ? 0 : 1;
						break;
					case 'select':
						$out[ $key ] = array_key_exists( (string) $value, $def[3] ) ? (string) $value : defaults()[ $key ];
						break;
					case 'color':
						$out[ $key ] = (string) sanitize_hex_color( (string) $value );
						break;
					case 'number':
						$out[ $key ] = max( $def[3][0], min( $def[3][1], (int) $value ) );
						break;
					case 'textarea':
						$out[ $key ] = sanitize_textarea_field( (string) $value );
						break;
					default:
						$out[ $key ] = sanitize_text_field( (string) $value );
				}
			}
			if ( 'hours' === $tab ) {
				$hours = array();
				for ( $d = 0; $d < 7; $d++ ) {
					$hours[ $d ] = sanitize_text_field( (string) ( $input['hours'][ $d ] ?? '' ) );
				}
				$out['hours'] = $hours;
			}
		} elseif ( 'labels' === $tab ) {
			$labels = array();
			foreach ( array_keys( default_labels() ) as $key ) {
				$labels[ $key ] = sanitize_text_field( (string) ( $input['labels'][ $key ] ?? '' ) );
			}
			$out['labels'] = array_filter( $labels, 'strlen' );
		} elseif ( 'icons' === $tab ) {
			$out['dietary'] = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $input['dietary'] ?? array() ) ), array_keys( Dietary::all() ) ) );
			$out['badges']  = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $input['badges'] ?? array() ) ), array_keys( badge_options() ) ) );
		} elseif ( 'fields' === $tab ) {
			$rows = array();
			foreach ( (array) ( $input['custom_fields'] ?? array() ) as $row ) {
				$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
				if ( '' === $label ) {
					continue;
				}
				$key = sanitize_key( (string) ( $row['key'] ?? '' ) );
				if ( '' === $key ) {
					$key = sanitize_key( sanitize_title( $label ) );
				}
				$rows[ $key ] = array(
					'key'     => $key,
					'label'   => $label,
					'on_menu' => empty( $row['on_menu'] ) ? 0 : 1,
				);
			}
			$out['custom_fields'] = array_values( $rows );
		} elseif ( 'delivery' === $tab ) {
			$out['delivery'] = $this->sanitize_delivery( (array) ( $input['delivery'] ?? array() ), (array) ( $out['delivery'] ?? array() ) );
		}
		return $out;
	}

	/**
	 * Settings page.
	 */
	public function render() {
		if ( ! current_user_can( manage_cap() ) ) {
			return;
		}
		$tabs = $this->tabs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab switch.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$tab = isset( $tabs[ $tab ] ) ? $tab : 'general';
		$url = admin_url( 'edit.php?post_type=' . Post_Types::MENU . '&page=' . self::PAGE );
		echo '<div class="wrap sidrest-settings"><h1>' . esc_html__( 'Restaurant settings', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</h1>';
		settings_errors();
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			printf( '<a href="%1$s" class="nav-tab%2$s">%3$s</a>', esc_url( add_query_arg( 'tab', $slug, $url ) ), $slug === $tab ? ' nav-tab-active' : '', esc_html( $label ) );
		}
		echo '</nav><form method="post" action="options.php">';
		settings_fields( 'sidrest_settings' );
		echo '<input type="hidden" name="sidrest_settings[_tab]" value="' . esc_attr( $tab ) . '">';

		$fields = $this->fields();
		if ( 'hours' === $tab ) {
			$this->render_hours();
		}
		if ( isset( $fields[ $tab ] ) ) {
			echo '<table class="form-table" role="presentation"><tbody>';
			foreach ( $fields[ $tab ] as $key => $def ) {
				$this->field( $key, $def );
			}
			echo '</tbody></table>';
			if ( 'dinein' === $tab ) {
				echo '<p class="description">' . wp_kses_post( sprintf( /* translators: %s: link. */ __( 'Print the table QR codes under <a href="%s">Restaurant → QR codes</a>.', 'sidcraft-restaurant-for-sidcraft-commerce' ), esc_url( admin_url( 'edit.php?post_type=' . Post_Types::MENU . '&page=sidrest-qr' ) ) ) ) . '</p>';
			}
		} elseif ( 'labels' === $tab ) {
			$this->render_labels();
		} elseif ( 'icons' === $tab ) {
			$this->render_icons();
		} elseif ( 'fields' === $tab ) {
			$this->render_custom_fields();
		} elseif ( 'delivery' === $tab ) {
			$this->render_delivery();
		}
		submit_button();
		echo '</form></div>';
		if ( 'style' === $tab ) {
			wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".sidrest-color").wpColorPicker();});' );
		}
	}

	/**
	 * One field row.
	 *
	 * @param string $key Key.
	 * @param array  $def [ label, type, help, choices ].
	 */
	protected function field( $key, array $def ) {
		$name  = 'sidrest_settings[' . $key . ']';
		$id    = 'sidrest-' . $key;
		$value = setting( $key );
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $def[0] ) . '</label></th><td>';
		switch ( $def[1] ) {
			case 'checkbox':
				echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $value ), true, false ) . '> ' . esc_html__( 'On', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</label>';
				break;
			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( $def[3] as $v => $t ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $v ), selected( (string) $value, (string) $v, false ), esc_html( $t ) );
				}
				echo '</select>';
				break;
			case 'color':
				echo '<input type="text" class="sidrest-color" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" placeholder="#rrggbb" data-default-color="' . esc_attr( (string) ( defaults()[ $key ] ?? '' ) ) . '">';
				break;
			case 'number':
				echo '<input type="number" class="small-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) (int) $value ) . '" min="' . esc_attr( $def[3][0] ) . '" max="' . esc_attr( $def[3][1] ) . '">';
				break;
			case 'textarea':
				echo '<textarea class="large-text" rows="4" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
				break;
			default:
				echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
		if ( ! empty( $def[2] ) ) {
			echo '<p class="description">' . esc_html( $def[2] ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Weekly opening hours grid (Hours tab).
	 */
	protected function render_hours() {
		global $wp_locale;
		$hours = (array) setting( 'hours', array() );
		$start = (int) get_option( 'start_of_week', 1 );
		echo '<h2>' . esc_html__( 'Opening hours', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'One or more ranges a day, e.g. 11:30-14:30, 17:00-22:30. A range may end after midnight (18:00-01:00). Leave a day empty when you are closed. With no hours at all, orders are taken at any time.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		for ( $i = 0; $i < 7; $i++ ) {
			$d  = ( $start + $i ) % 7;
			$id = 'sidrest-hours-' . $d;
			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $wp_locale->get_weekday( $d ) ) . '</label></th><td>';
			echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="sidrest_settings[hours][' . esc_attr( (string) $d ) . ']" value="' . esc_attr( (string) ( $hours[ $d ] ?? '' ) ) . '" placeholder="' . esc_attr__( 'Closed', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '">';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		/* translators: %s: time zone. */
		echo '<p class="description">' . esc_html( sprintf( __( 'Times are in the site’s time zone (%s, Settings → General).', 'sidcraft-restaurant-for-sidcraft-commerce' ), wp_timezone_string() ) ) . '</p>';
		echo '<h2>' . esc_html__( 'Ordering', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</h2>';
	}

	/**
	 * Labels tab.
	 */
	protected function render_labels() {
		$custom = (array) setting( 'labels', array() );
		echo '<p>' . esc_html__( 'Change any wording shown on your menus. Leave a field empty to keep the default.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p><table class="form-table" role="presentation"><tbody>';
		foreach ( default_labels() as $key => $default ) {
			echo '<tr><th scope="row"><label for="sidrest-label-' . esc_attr( $key ) . '">' . esc_html( $default ) . '</label></th><td><input type="text" class="regular-text" id="sidrest-label-' . esc_attr( $key ) . '" name="sidrest_settings[labels][' . esc_attr( $key ) . ']" value="' . esc_attr( $custom[ $key ] ?? '' ) . '" placeholder="' . esc_attr( $default ) . '"></td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Icons & badges tab.
	 */
	protected function render_icons() {
		$on = (array) setting( 'dietary', array() );
		echo '<h2>' . esc_html__( 'Dietary and ethical icons', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</h2><p>' . esc_html__( 'Icons offered on menu items and in the dietary filter.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p><div class="sidrest-checks sidrest-checks--grid">';
		foreach ( Dietary::all() as $slug => $def ) {
			echo '<label><input type="checkbox" name="sidrest_settings[dietary][]" value="' . esc_attr( $slug ) . '" ' . checked( in_array( $slug, $on, true ), true, false ) . '> ' . Dietary::icon( $slug ) . ' ' . esc_html( $def[0] ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Dietary::icon().
		}
		echo '</div>';
		$badges = (array) setting( 'badges', array() );
		echo '<h2>' . esc_html__( 'Badges', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</h2><p>' . esc_html__( 'Rename them on the Labels tab.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p><div class="sidrest-checks sidrest-checks--grid">';
		foreach ( badge_options() as $slug => $label ) {
			echo '<label><input type="checkbox" name="sidrest_settings[badges][]" value="' . esc_attr( $slug ) . '" ' . checked( in_array( $slug, $badges, true ), true, false ) . '> <span class="sidrest-badge sidrest-badge--' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</span></label>';
		}
		echo '</div>';
	}

	/**
	 * Custom fields tab.
	 */
	protected function render_custom_fields() {
		$rows   = (array) setting( 'custom_fields', array() );
		$rows[] = array(
			'key'     => '',
			'label'   => '',
			'on_menu' => 0,
		);
		echo '<p>' . esc_html__( 'Extra details for every menu item: calories, allergens, wine region, pairing… They appear on the item page, and on the menu when ticked.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		echo '<div class="sidrest-repeater" data-sidrest-repeater><table class="widefat sidrest-repeater__table"><thead><tr><th>' . esc_html__( 'Field name', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th>' . esc_html__( 'Key', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th>' . esc_html__( 'Show on menu', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th class="sidrest-repeater__tools"></th></tr></thead><tbody data-rows>';
		foreach ( array_values( $rows ) as $i => $row ) {
			$this->custom_field_row( $i, $row );
		}
		echo '</tbody></table><template data-template>';
		$this->custom_field_row(
			'__i__',
			array(
				'key'     => '',
				'label'   => '',
				'on_menu' => 0,
			)
		);
		echo '</template><p><button type="button" class="button" data-add-row>' . esc_html__( 'Add a field', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</button></p></div>';
	}

	/**
	 * One custom field row.
	 *
	 * @param int|string $i   Index.
	 * @param array      $row key, label, on_menu.
	 */
	protected function custom_field_row( $i, array $row ) {
		$base = 'sidrest_settings[custom_fields][' . $i . ']';
		echo '<tr data-row><td><input type="text" class="widefat" name="' . esc_attr( $base ) . '[label]" value="' . esc_attr( $row['label'] ) . '" placeholder="' . esc_attr__( 'e.g. Calories', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '"></td>';
		echo '<td><input type="text" class="widefat code" name="' . esc_attr( $base ) . '[key]" value="' . esc_attr( $row['key'] ) . '" placeholder="' . esc_attr__( 'auto', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '"></td>';
		echo '<td><input type="checkbox" name="' . esc_attr( $base ) . '[on_menu]" value="1" ' . checked( ! empty( $row['on_menu'] ), true, false ) . ' aria-label="' . esc_attr__( 'Show on menu', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '"></td>';
		echo '<td class="sidrest-repeater__tools"><button type="button" class="button-link sidrest-picker__remove" data-remove-row aria-label="' . esc_attr__( 'Remove', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '">×</button></td></tr>';
	}

	/*
	------------------------------------------------------------------
	 * Delivery tab
	 * ----------------------------------------------------------------
	 */

	/**
	 * Global delivery fields: key => [ label, type, help ].
	 *
	 * @return array
	 */
	protected function delivery_fields() {
		return array(
			'links'    => array(
				'links_on_menus' => array( __( 'Show the links above menus', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox' ),
				'links_on_items' => array( __( 'Show the links on menu item pages', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox' ),
				'links_heading'  => array( __( 'Heading', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text', __( 'Default: “Order for delivery on”.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
			),
			'checkout' => array(
				'fulfilment'    => array( __( 'Pickup or delivery at checkout', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'When the cart holds menu items, customers choose pickup or one of the delivery platforms ticked below. They pay with your Sidcraft Commerce payment gateways; the delivery fee is added as the shipping line.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'pickup'        => array( __( 'Offer pickup', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox' ),
				'min_order'     => array( __( 'Minimum order for delivery', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'money', __( 'Optional.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'postcodes'     => array( __( 'Delivery postcodes', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text', __( 'Optional, comma separated. End with * for a prefix: 02139, 0214*, SW1*. Empty: no restriction.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'prep_minutes'  => array( __( 'Preparation time (minutes)', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'int', __( 'Couriers are asked to arrive this long after the order is paid.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'auto_dispatch' => array( __( 'Request the courier automatically when the order is paid', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'checkbox', __( 'Off: staff press “Request courier” on the order.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
			),
			'store'    => array(
				'store_name'     => array( __( 'Restaurant name', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text', __( 'Shown to couriers. Default: the restaurant name on the General tab, else the site title.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'store_phone'    => array( __( 'Restaurant phone', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text', __( 'With country code, e.g. +16175551234.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'store_address'  => array( __( 'Street address', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text' ),
				'store_city'     => array( __( 'City', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text' ),
				'store_state'    => array( __( 'State / Province', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text' ),
				'store_postcode' => array( __( 'ZIP / Postal code', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text' ),
				'store_country'  => array( __( 'Country code', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text', __( 'Two letters, e.g. US, GB, IN.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'store_notes'    => array( __( 'Pickup instructions for couriers', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text' ),
			),
			'doordash' => array(
				'dd_developer_id' => array( __( 'Developer ID', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text' ),
				'dd_key_id'       => array( __( 'Key ID', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text' ),
				'dd_secret'       => array( __( 'Signing secret', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'secret' ),
			),
			'uber'     => array(
				'uber_customer_id' => array( __( 'Customer ID', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text' ),
				'uber_client_id'   => array( __( 'Client ID', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text' ),
				'uber_secret'      => array( __( 'Client secret', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'secret' ),
			),
			'zones'    => array(
				'geocoder'   => array(
					__( 'Find addresses with', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					'select',
					__( 'Only used when you add zones below. OpenStreetMap is free (fair use); Google needs a Geocoding API key.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					array(
						'osm'    => __( 'OpenStreetMap (Nominatim)', 'sidcraft-restaurant-for-sidcraft-commerce' ),
						'google' => __( 'Google Geocoding', 'sidcraft-restaurant-for-sidcraft-commerce' ),
					),
				),
				'google_key' => array( __( 'Google API key', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'secret' ),
				'store_lat'  => array( __( 'Restaurant latitude', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text', __( 'Optional, e.g. 51.5072. Empty: looked up from the address above.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'store_lng'  => array( __( 'Restaurant longitude', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'text', __( 'Optional, e.g. -0.1276.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
			),
			'webhook'  => array(
				'webhook_url'    => array( __( 'Webhook URL', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'url', __( 'Paid delivery orders for platforms set to “webhook” are posted here as JSON — for your own integration or an order hub that connects to the platform.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
				'webhook_secret' => array( __( 'Signing secret', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'secret', __( 'Optional. Each request then carries X-Sidrest-Signature: sha256=HMAC of the body.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
			),
		);
	}

	/**
	 * Sanitize the Delivery tab.
	 *
	 * @param array $input  Posted delivery values.
	 * @param array $stored Stored delivery values.
	 * @return array
	 */
	protected function sanitize_delivery( array $input, array $stored ) {
		$out = $stored;
		foreach ( $this->delivery_fields() as $group ) {
			foreach ( $group as $key => $def ) {
				$value = $input[ $key ] ?? '';
				switch ( $def[1] ) {
					case 'checkbox':
						$out[ $key ] = empty( $value ) ? 0 : 1;
						break;
					case 'money':
						$out[ $key ] = \SidcraftRestaurant\parse_price( $value );
						break;
					case 'int':
						$out[ $key ] = max( 0, min( 240, (int) $value ) );
						break;
					case 'url':
						$out[ $key ] = esc_url_raw( trim( (string) $value ), array( 'https', 'http' ) );
						break;
					case 'select':
						$out[ $key ] = array_key_exists( (string) $value, $def[3] ) ? (string) $value : (string) array_key_first( $def[3] );
						break;
					case 'secret':
						$value = trim( sanitize_text_field( (string) $value ) );
						if ( ! empty( $input[ $key . '_clear' ] ) ) {
							$out[ $key ] = '';
						} elseif ( '' !== $value ) {
							$out[ $key ] = $value; // Blank keeps the saved secret.
						}
						break;
					default:
						$out[ $key ] = sanitize_text_field( (string) $value );
				}
			}
		}
		foreach ( array( 'store_lat', 'store_lng' ) as $key ) {
			$out[ $key ] = is_numeric( str_replace( ',', '.', (string) ( $out[ $key ] ?? '' ) ) ) ? (string) (float) str_replace( ',', '.', (string) $out[ $key ] ) : '';
		}
		$zones = array();
		foreach ( (array) ( $input['zones'] ?? array() ) as $row ) {
			$km = (float) str_replace( ',', '.', (string) ( $row['km'] ?? '' ) );
			if ( $km > 0 ) {
				$zones[] = array(
					'km'  => (string) round( $km, 2 ),
					'fee' => \SidcraftRestaurant\parse_price( $row['fee'] ?? '' ),
					'min' => \SidcraftRestaurant\parse_price( $row['min'] ?? '' ),
				);
			}
		}
		$out['zones'] = $zones;
		if ( isset( $out['store_country'] ) ) {
			$out['store_country'] = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $out['store_country'] ), 0, 2 ) );
		}

		$rows = array();
		foreach ( array_keys( Platforms::all() ) as $slug ) {
			$row          = (array) ( $input['platforms'][ $slug ] ?? array() );
			$modes        = Platforms::modes( $slug );
			$dispatch     = sanitize_key( (string) ( $row['dispatch'] ?? '' ) );
			$rows[ $slug ] = array(
				'link'      => empty( $row['link'] ) ? 0 : 1,
				'url'       => esc_url_raw( trim( (string) ( $row['url'] ?? '' ) ), array( 'https', 'http' ) ),
				'checkout'  => empty( $row['checkout'] ) ? 0 : 1,
				'fee'       => \SidcraftRestaurant\parse_price( $row['fee'] ?? '' ),
				'free_over' => \SidcraftRestaurant\parse_price( $row['free_over'] ?? '' ),
				'dispatch'  => array_key_exists( $dispatch, $modes ) ? $dispatch : (string) array_key_first( $modes ),
			);
			if ( $rows[ $slug ]['link'] && '' === $rows[ $slug ]['url'] ) {
				add_settings_error(
					'sidrest_settings',
					'sidrest_link_' . $slug,
					/* translators: %s: platform. */
					sprintf( __( '%s: add your store page URL to show its link.', 'sidcraft-restaurant-for-sidcraft-commerce' ), Platforms::name( $slug ) ),
					'warning'
				);
			}
		}
		$out['platforms'] = $rows;
		return $out;
	}

	/**
	 * Delivery tab.
	 */
	protected function render_delivery() {
		$s        = Platforms::settings();
		$fields   = $this->delivery_fields();
		$commerce = admin_url( 'edit.php?post_type=' . \SidcraftCommerce\Data\Post_Types::PRODUCT . '&page=sidccofo-settings' );

		echo '<p>' . esc_html__( 'Use each platform in one or both ways:', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p><ol>';
		echo '<li>' . esc_html__( '“Order on …” links — buttons to your store page on the platform. The order and payment happen there.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</li>';
		echo '<li>' . esc_html__( 'Delivery from your own checkout — customers order on your site and pay with your Sidcraft Commerce payment gateways. When the order is paid, the courier is requested through the platform’s API (DoorDash, Uber Eats, Postmates), sent to your webhook, or booked by your staff.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</li></ol>';
		echo '<p class="description">' . sprintf(
			/* translators: %s: link to Store settings. */
			esc_html__( 'Payment gateways are set up in %s. Delivery at checkout needs “Collect shipping address” on there.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'<a href="' . esc_url( $commerce ) . '">' . esc_html__( 'Store → Settings', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</a>'
		) . '</p>';

		// Platforms table.
		echo '<h2>' . esc_html__( 'Platforms', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</h2>';
		echo '<table class="widefat striped sidrest-platforms"><thead><tr>';
		echo '<th>' . esc_html__( 'Platform', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th>' . esc_html__( '“Order on” link', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th>' . esc_html__( 'Delivery at your checkout', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th>' . esc_html__( 'Delivery fee', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th>' . esc_html__( 'Free delivery over', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th>' . esc_html__( 'Courier', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( Platforms::all() as $slug => $p ) {
			$row  = $s['platforms'][ $slug ];
			$base = 'sidrest_settings[delivery][platforms][' . $slug . ']';
			echo '<tr>';
			echo '<td><strong>' . esc_html( $p[0] ) . '</strong><br><span class="description">' . esc_html( $p[1] ) . '</span></td>';
			echo '<td><label><input type="checkbox" name="' . esc_attr( $base ) . '[link]" value="1" ' . checked( ! empty( $row['link'] ), true, false ) . '> ' . esc_html__( 'Show', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</label>';
			/* translators: %s: platform. */
			echo '<br><input type="url" class="regular-text" name="' . esc_attr( $base ) . '[url]" value="' . esc_attr( $row['url'] ) . '" placeholder="' . esc_attr( $p[3] ) . '" aria-label="' . esc_attr( sprintf( __( 'Your %s store page URL', 'sidcraft-restaurant-for-sidcraft-commerce' ), $p[0] ) ) . '"></td>';
			echo '<td><label><input type="checkbox" name="' . esc_attr( $base ) . '[checkout]" value="1" ' . checked( ! empty( $row['checkout'] ), true, false ) . '> ' . esc_html__( 'Offer', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</label></td>';
			echo '<td><input type="text" inputmode="decimal" class="small-text" name="' . esc_attr( $base ) . '[fee]" value="' . esc_attr( $row['fee'] ) . '" placeholder="0.00"></td>';
			echo '<td><input type="text" inputmode="decimal" class="small-text" name="' . esc_attr( $base ) . '[free_over]" value="' . esc_attr( $row['free_over'] ) . '" placeholder="—"></td>';
			echo '<td><select name="' . esc_attr( $base ) . '[dispatch]">';
			foreach ( Platforms::modes( $slug ) as $mode => $label ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $mode ), selected( $row['dispatch'], $mode, false ), esc_html( $label ) );
			}
			echo '</select></td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Place the links anywhere with the shortcode [sidrest_delivery], or [sidrest_delivery platforms="doordash,uber-eats" heading="Get it delivered"].', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';

		$titles = array(
			'links'    => array( __( '“Order on” links', 'sidcraft-restaurant-for-sidcraft-commerce' ), '' ),
			'checkout' => array( __( 'Pickup or delivery at your checkout', 'sidcraft-restaurant-for-sidcraft-commerce' ), '' ),
			'store'    => array( __( 'Pickup address for couriers', 'sidcraft-restaurant-for-sidcraft-commerce' ), '' ),
			'doordash' => array( __( 'DoorDash Drive API', 'sidcraft-restaurant-for-sidcraft-commerce' ), __( 'For DoorDash set to “Automatic”. Create access keys at developer.doordash.com; couriers are billed to your DoorDash account.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
			'uber'     => array( __( 'Uber Direct API (Uber Eats, Postmates)', 'sidcraft-restaurant-for-sidcraft-commerce' ), __( 'For Uber Eats or Postmates set to “Automatic”. Find these in the Uber Direct dashboard (direct.uber.com) under Developer; couriers are billed to your Uber account.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
			'zones'    => array( __( 'Delivery zones by distance', 'sidcraft-restaurant-for-sidcraft-commerce' ), __( 'Optional, instead of a postcode list: the customer’s address sets the delivery fee by how far it is from the restaurant. Farther than the last zone, delivery isn’t offered.', 'sidcraft-restaurant-for-sidcraft-commerce' ) ),
			'webhook'  => array( __( 'Webhook', 'sidcraft-restaurant-for-sidcraft-commerce' ), '' ),
		);
		foreach ( $fields as $group => $defs ) {
			echo '<h2>' . esc_html( $titles[ $group ][0] ) . '</h2>';
			if ( '' !== $titles[ $group ][1] ) {
				echo '<p class="description">' . esc_html( $titles[ $group ][1] ) . '</p>';
			}
			echo '<table class="form-table" role="presentation"><tbody>';
			foreach ( $defs as $key => $def ) {
				$this->delivery_field( $key, $def, $s[ $key ] ?? '' );
			}
			echo '</tbody></table>';
			if ( 'zones' === $group ) {
				$this->render_zones( (array) ( $s['zones'] ?? array() ) );
			}
		}

		echo '<h2>' . esc_html__( 'Courier status updates', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Add this URL as the webhook endpoint in the DoorDash Developer Portal, the Uber Direct dashboard, or your order hub. When the courier picks up or delivers, the order moves to “Out for delivery” and then “Completed”.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
		echo '<p><input type="text" class="large-text code" readonly value="' . esc_attr( Delivery::status_url() ) . '" onfocus="this.select()"></p>';
		echo '<p class="description">' . esc_html__( 'Keep it private: the key in the URL is what lets the update in.', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</p>';
	}

	/**
	 * One Delivery tab field.
	 *
	 * @param string $key   Key.
	 * @param array  $def   [ label, type, help ].
	 * @param mixed  $value Value.
	 */
	protected function delivery_field( $key, array $def, $value ) {
		$name = 'sidrest_settings[delivery][' . $key . ']';
		$id   = 'sidrest-d-' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $def[0] ) . '</label></th><td>';
		switch ( $def[1] ) {
			case 'checkbox':
				echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $value ), true, false ) . '> ' . esc_html__( 'On', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</label>';
				break;
			case 'secret':
				$set = '' !== (string) $value;
				echo '<input type="password" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="" autocomplete="new-password" placeholder="' . esc_attr( $set ? __( 'Saved — leave empty to keep', 'sidcraft-restaurant-for-sidcraft-commerce' ) : '' ) . '">';
				if ( $set ) {
					echo ' <label><input type="checkbox" name="' . esc_attr( 'sidrest_settings[delivery][' . $key . '_clear]' ) . '" value="1"> ' . esc_html__( 'Remove', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</label>';
				}
				break;
			case 'int':
				echo '<input type="number" class="small-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) (int) $value ) . '" min="0" max="240">';
				break;
			case 'money':
				echo '<input type="text" inputmode="decimal" class="small-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
				break;
			case 'url':
				echo '<input type="url" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" placeholder="https://">';
				break;
			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( $def[3] as $v => $t ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $v ), selected( (string) $value, (string) $v, false ), esc_html( $t ) );
				}
				echo '</select>';
				break;
			default:
				echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
		if ( ! empty( $def[2] ) ) {
			echo '<p class="description">' . esc_html( $def[2] ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Zones table (Delivery tab).
	 *
	 * @param array $zones Saved zones.
	 */
	protected function render_zones( array $zones ) {
		$sym  = trim( \SidcraftCommerce\currency_symbol() );
		$rows = array_values( $zones );
		echo '<div class="sidrest-repeater" data-sidrest-repeater><table class="widefat sidrest-repeater__table" style="max-width:640px"><thead><tr><th>' . esc_html__( 'Up to (km)', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</th><th>' . esc_html( sprintf( /* translators: %s: currency symbol. */ __( 'Fee (%s)', 'sidcraft-restaurant-for-sidcraft-commerce' ), $sym ) ) . '</th><th>' . esc_html( sprintf( /* translators: %s: currency symbol. */ __( 'Minimum order (%s)', 'sidcraft-restaurant-for-sidcraft-commerce' ), $sym ) ) . '</th><th class="sidrest-repeater__tools"></th></tr></thead><tbody data-rows>';
		foreach ( $rows as $i => $row ) {
			$this->zone_row( $i, $row );
		}
		echo '</tbody></table><template data-template>';
		$this->zone_row( '__i__', array() );
		echo '</template><p><button type="button" class="button" data-add-row>' . esc_html__( 'Add a zone', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '</button></p></div>';
	}

	/**
	 * One zone row.
	 *
	 * @param int|string $i   Index.
	 * @param array      $row km, fee, min.
	 */
	protected function zone_row( $i, array $row ) {
		$base = 'sidrest_settings[delivery][zones][' . $i . ']';
		echo '<tr data-row>';
		foreach ( array( 'km' => '3', 'fee' => '2.99', 'min' => '' ) as $key => $placeholder ) {
			echo '<td><input type="text" inputmode="decimal" class="small-text" name="' . esc_attr( $base . '[' . $key . ']' ) . '" value="' . esc_attr( (string) ( $row[ $key ] ?? '' ) ) . '" placeholder="' . esc_attr( $placeholder ) . '"></td>';
		}
		echo '<td class="sidrest-repeater__tools"><button type="button" class="button-link sidrest-picker__remove" data-remove-row aria-label="' . esc_attr__( 'Remove', 'sidcraft-restaurant-for-sidcraft-commerce' ) . '">×</button></td></tr>';
	}
}
