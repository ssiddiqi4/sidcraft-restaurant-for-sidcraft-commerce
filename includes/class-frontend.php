<?php
/**
 * Front end: assets, styling settings, and the extra content on menu and
 * menu item pages.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Frontend
 */
class Frontend {

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register_assets' ), 6 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
		add_filter( 'the_content', array( $this, 'single_content' ), 30 );
	}

	/**
	 * Register CSS and JS (enqueued only where a menu is shown).
	 */
	public function register_assets() {
		wp_register_style( 'sidrest-menu', SIDREST_URL . 'assets/css/sidrest-menu.css', array(), SIDREST_VERSION );
		wp_register_script(
			'sidrest-menu',
			SIDREST_URL . 'assets/js/sidrest-menu.js',
			array(),
			SIDREST_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'sidrest-menu',
			'sidrestMenu',
			array(
				/* translators: %d: number of menu items. */
				'count' => __( '%d items', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				/* translators: %d: number of menu items. */
				'one'   => __( '%d item', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			)
		);
		wp_register_script( 'sidrest-qrcode', SIDREST_URL . 'assets/js/vendor/qrcode.js', array(), '1.4.4', true );
		wp_register_script( 'sidrest-qr', SIDREST_URL . 'assets/js/sidrest-qr.js', array( 'sidrest-qrcode' ), SIDREST_VERSION, true );
	}

	/**
	 * Menu and menu item pages always need the assets.
	 */
	public function maybe_enqueue() {
		if ( is_singular( array( Post_Types::MENU, Post_Types::ITEM ) ) ) {
			wp_enqueue_style( 'sidrest-menu' );
			wp_enqueue_script( 'sidrest-menu' );
		}
	}

	/**
	 * Styling settings as CSS custom properties (inline style on each menu).
	 *
	 * @return string
	 */
	public static function css_vars() {
		static $css = null;
		if ( null !== $css ) {
			return $css;
		}
		$fonts = self::fonts();
		$vars  = array(
			'--sidrest-accent'       => sanitize_hex_color( (string) setting( 'accent' ) ),
			'--sidrest-text'         => sanitize_hex_color( (string) setting( 'text' ) ),
			'--sidrest-muted'        => sanitize_hex_color( (string) setting( 'muted' ) ),
			'--sidrest-bg'           => sanitize_hex_color( (string) setting( 'background' ) ),
			'--sidrest-card'         => sanitize_hex_color( (string) setting( 'card' ) ),
			'--sidrest-price'        => sanitize_hex_color( (string) setting( 'price_color' ) ),
			'--sidrest-border'       => sanitize_hex_color( (string) setting( 'border_color' ) ),
			'--sidrest-border-width' => absint( setting( 'border_width', 1 ) ) . 'px',
			'--sidrest-radius'       => absint( setting( 'radius', 12 ) ) . 'px',
			'--sidrest-gap'          => absint( setting( 'gap', 20 ) ) . 'px',
			'--sidrest-nav-width'    => max( 140, absint( setting( 'sidebar_width', 220 ) ) ) . 'px',
			'--sidrest-ratio'        => in_array( setting( 'image_ratio' ), array( '1/1', '4/3', '3/2', '16/9' ), true ) ? setting( 'image_ratio' ) : '4/3',
			'--sidrest-heading-font' => $fonts[ setting( 'heading_font', 'inherit' ) ][1] ?? '',
			'--sidrest-body-font'    => $fonts[ setting( 'body_font', 'inherit' ) ][1] ?? '',
		);
		$css = absint( setting( 'max_width', 0 ) ) ? 'max-width:' . absint( setting( 'max_width', 0 ) ) . 'px;margin-inline:auto;' : '';
		foreach ( $vars as $name => $value ) {
			if ( '' !== (string) $value && null !== $value ) {
				$css .= $name . ':' . $value . ';';
			}
		}
		return $css;
	}

	/**
	 * Font choices: key => [ label, CSS font-family ('' = inherit) ].
	 *
	 * @return array
	 */
	public static function fonts() {
		return (array) apply_filters(
			'sidrest_fonts',
			array(
				'inherit' => array( __( 'Same as the theme', 'sidcraft-restaurant-for-sidcraft-commerce' ), '' ),
				'serif'   => array( __( 'Classic serif', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'Georgia, "Times New Roman", serif' ),
				'elegant' => array( __( 'Elegant serif', 'sidcraft-restaurant-for-sidcraft-commerce' ), '"Didot", "Bodoni MT", "Playfair Display", Georgia, serif' ),
				'sans'    => array( __( 'Modern sans-serif', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif' ),
				'rounded' => array( __( 'Rounded', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'ui-rounded, "SF Pro Rounded", "Nunito", system-ui, sans-serif' ),
				'mono'    => array( __( 'Typewriter', 'sidcraft-restaurant-for-sidcraft-commerce' ), '"Courier New", Courier, ui-monospace, monospace' ),
			)
		);
	}

	/**
	 * Menu pages show the menu; menu item pages get prices, icons, ordering,
	 * details, suppliers, reviews and related items around the description.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function single_content( $content ) {
		if ( ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() ) {
			return $content;
		}
		if ( is_singular( Post_Types::MENU ) ) {
			$menu = Menu::get( get_the_ID() );
			return $menu ? Plugin::instance()->renderer->menu( $menu ) : $content;
		}
		if ( is_singular( Post_Types::ITEM ) ) {
			$item = Item::get( get_the_ID() );
			if ( $item ) {
				Schema::add_items( array( $item ) );
				return Plugin::instance()->templates->render(
					'single-item.php',
					array(
						'item'    => $item,
						'content' => $content,
					)
				);
			}
		}
		return $content;
	}
}
