<?php
/**
 * Gutenberg blocks: Restaurant Menu, Menu Section, Menu Item, Menu Items List
 * and Menu QR Code. Each is rendered on the server by the matching Unit, so
 * blocks, Sidcraft Page Builder Units and shortcodes always look the same.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Blocks
 */
class Blocks {

	/**
	 * Block name => Unit short name.
	 */
	const BLOCKS = array(
		'sidrest/menu'    => 'menu',
		'sidrest/section' => 'section',
		'sidrest/item'    => 'item',
		'sidrest/items'   => 'items',
		'sidrest/qr'      => 'qr',
	);

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_schema' ) );
		add_filter( 'block_categories_all', array( $this, 'category' ) );
	}

	/**
	 * Block inserter category.
	 *
	 * @param array $categories Categories.
	 * @return array
	 */
	public function category( $categories ) {
		array_unshift(
			$categories,
			array(
				'slug'  => 'sidcraft-restaurant',
				'title' => __( 'Restaurant', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'icon'  => null,
			)
		);
		return $categories;
	}

	/**
	 * Attributes for a block from its Unit's controls.
	 *
	 * @param \SidcraftCommerce\Abstracts\Unit $unit Unit.
	 * @return array
	 */
	protected static function attributes( $unit ) {
		$attrs = array();
		foreach ( Units\Base_Unit::controls_without_choices( $unit ) as $key => $control ) {
			$type = $control['type'] ?? 'text';
			if ( 'number' === $type ) {
				$attrs[ $key ] = array(
					'type'    => 'number',
					'default' => (float) ( $control['default'] ?? 0 ),
				);
			} elseif ( 'switch' === $type ) {
				$attrs[ $key ] = array(
					'type'    => 'boolean',
					'default' => (bool) ( $control['default'] ?? false ),
				);
			} else {
				$attrs[ $key ] = array(
					'type'    => 'string',
					'default' => (string) ( $control['default'] ?? '' ),
				);
			}
		}
		$attrs['className'] = array(
			'type'    => 'string',
			'default' => '',
		);
		return $attrs;
	}

	/**
	 * Register the blocks and the editor script.
	 */
	public function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		wp_register_script(
			'sidrest-blocks',
			SIDREST_URL . 'assets/js/sidrest-blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
			SIDREST_VERSION,
			true
		);

		foreach ( self::BLOCKS as $block => $short ) {
			$unit = Units::get( $short );
			if ( ! $unit ) {
				continue;
			}
			register_block_type(
				$block,
				array(
					'api_version'     => 3,
					'title'           => $unit->get_title(),
					'category'        => 'sidcraft-restaurant',
					'attributes'      => self::attributes( $unit ),
					'editor_script'   => 'sidrest-blocks',
					'style'           => 'sidrest-menu',
					'script'          => 'qr' === $short ? 'sidrest-qr' : 'sidrest-menu',
					'supports'        => array(
						'align' => array( 'wide', 'full' ),
						'html'  => false,
					),
					'render_callback' => static function ( $attributes ) use ( $unit ) {
						$attributes = (array) $attributes;
						$html       = $unit->render( $attributes );
						$class      = trim( (string) ( $attributes['className'] ?? '' ) . ( ! empty( $attributes['align'] ) ? ' align' . sanitize_html_class( $attributes['align'] ) : '' ) );
						return '' === $class ? $html : '<div class="' . esc_attr( $class ) . '">' . $html . '</div>';
					},
				)
			);
		}
	}

	/**
	 * Block settings for the editor script: labels, help, picker lists. Only the
	 * block editor loads that script, so build this there, not on every request.
	 */
	public function editor_schema() {
		$schema = array();
		foreach ( self::BLOCKS as $block => $short ) {
			$unit = Units::get( $short );
			if ( ! $unit ) {
				continue;
			}
			$controls = array();
			foreach ( $unit->get_controls() as $key => $control ) {
				if ( in_array( $key, array( 'scheme', 'accent' ), true ) ) {
					continue; // Styling comes from Restaurant → Settings → Style.
				}
				$controls[ $key ] = array(
					'type'      => $control['type'] ?? 'text',
					'label'     => $control['label'] ?? $key,
					'help'      => $control['description'] ?? '',
					'options'   => isset( $control['options'] ) ? self::pairs( $control['options'] ) : array(),
					'min'       => $control['min'] ?? null,
					'max'       => $control['max'] ?? null,
					'condition' => $control['condition'] ?? null,
				);
			}
			$schema[ $block ] = array(
				'title'       => $unit->get_title(),
				'description' => self::descriptions()[ $short ],
				'icon'        => self::icons()[ $short ],
				'controls'    => $controls,
			);
		}
		wp_localize_script( 'sidrest-blocks', 'sidrestBlocks', array( 'blocks' => $schema ) );
	}

	/**
	 * Options as ordered [ value, label ] pairs (JS objects lose key order).
	 *
	 * @param array $options value => label.
	 * @return array[]
	 */
	protected static function pairs( array $options ) {
		$out = array();
		foreach ( $options as $value => $label ) {
			$out[] = array(
				'value' => (string) $value,
				'label' => (string) $label,
			);
		}
		return $out;
	}

	/**
	 * Inserter descriptions.
	 *
	 * @return array
	 */
	protected static function descriptions() {
		return array(
			'menu'    => __( 'A whole restaurant menu with its sections, filters and sidebar.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'section' => __( 'One menu section, such as Appetizers or Weekly Specials.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'item'    => __( 'A single dish or drink.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'items'   => __( 'A short list of menu items for sidebars and footers.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'qr'      => __( 'A QR code that opens your online menu.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		);
	}

	/**
	 * Dashicons for the inserter.
	 *
	 * @return array
	 */
	protected static function icons() {
		return array(
			'menu'    => 'food',
			'section' => 'list-view',
			'item'    => 'carrot',
			'items'   => 'star-filled',
			'qr'      => 'screenoptions',
		);
	}
}
