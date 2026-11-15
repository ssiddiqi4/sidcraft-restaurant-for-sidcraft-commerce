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
			'delivery' => __( 'Delivery', 'sidcraft-restaurant-for-sidcraft-commerce' ),
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
					default:
						$out[ $key ] = sanitize_text_field( (string) $value );
				}
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
		if ( isset( $fields[ $tab ] ) ) {
			echo '<table class="form-table" role="presentation"><tbody>';
			foreach ( $fields[ $tab ] as $key => $def ) {
				$this->field( $key, $def );
			}
			echo '</tbody></table>';
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
			default:
				echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
		if ( ! empty( $def[2] ) ) {
			echo '<p class="description">' . esc_html( $def[2] ) . '</p>';
		}
		echo '</td></tr>';
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
			default:
				echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
		if ( ! empty( $def[2] ) ) {
			echo '<p class="description">' . esc_html( $def[2] ) . '</p>';
		}
		echo '</td></tr>';
	}
}
