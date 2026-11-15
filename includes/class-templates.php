<?php
/**
 * Template loader. Themes override any file in templates/ by copying it to
 * yourtheme/sidcraft-restaurant/; add-ons can point elsewhere with the
 * sidrest_locate_template filter.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Templates
 */
class Templates {

	/**
	 * Template paths found this request, keyed by theme-relative path.
	 *
	 * @var array<string,string>
	 */
	protected $found = array();

	/**
	 * Find a template: child theme, parent theme, then the plugin.
	 *
	 * @param string $name Relative path, e.g. 'item.php'.
	 * @return string
	 */
	public function locate( $name ) {
		$name = ltrim( str_replace( '..', '', (string) $name ), '/' );
		$dir  = trailingslashit( (string) apply_filters( 'sidrest_template_dir', 'sidcraft-restaurant' ) );
		// A menu renders item.php once per item: check the theme for an override once.
		$key = $dir . $name;
		if ( ! isset( $this->found[ $key ] ) ) {
			$found = locate_template( array( $key ) );
			if ( ! $found ) {
				$found = SIDREST_PATH . 'templates/' . $name;
			}
			$this->found[ $key ] = $found;
		}
		$found = $this->found[ $key ];

		/**
		 * Filter a template's path.
		 *
		 * @param string $found Path.
		 * @param string $name  Template name.
		 */
		return (string) apply_filters( 'sidrest_locate_template', $found, $name );
	}

	/**
	 * Render a template to a string.
	 *
	 * @param string $name Template name.
	 * @param array  $args Variables for the template.
	 * @return string
	 */
	public function render( $name, array $args = array() ) {
		$file = $this->locate( $name );
		if ( ! is_readable( $file ) ) {
			return '';
		}

		/**
		 * Filter the variables passed to a template.
		 *
		 * @param array  $args Variables.
		 * @param string $name Template name.
		 */
		$args = (array) apply_filters( 'sidrest_template_args', $args, $name );

		ob_start();
		( static function ( $sidrest_template_file, $sidrest_template_vars ) {
			extract( $sidrest_template_vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variables, documented in each template.
			include $sidrest_template_file;
		} )( $file, $args );
		return (string) ob_get_clean();
	}
}
