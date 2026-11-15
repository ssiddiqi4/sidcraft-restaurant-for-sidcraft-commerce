<?php
/**
 * Restaurant → QR codes: make a QR code for a menu (or any page), download it
 * as PNG or SVG, or print table cards. Everything is drawn in the browser.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Admin;

use SidcraftRestaurant\Post_Types;
use function SidcraftRestaurant\label;
use function SidcraftRestaurant\manage_cap;

defined( 'ABSPATH' ) || exit;

/**
 * Class Qr
 */
class Qr {

	const PAGE = 'sidrest-qr';

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 15 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Submenu.
	 */
	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Post_Types::MENU,
			__( 'Menu QR codes', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			__( 'QR codes', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'edit_posts',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Scripts.
	 *
	 * @param string $hook Screen hook.
	 */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_script( 'sidrest-qr' );
		wp_localize_script(
			'sidrest-qr',
			'sidrestQr',
			array(
				'filename' => 'menu-qr-code',
				'print'    => __( 'Print', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			)
		);
	}

	/**
	 * The page.
	 */
	public function render() {
		$targets = array();
		foreach ( get_posts(
			array(
				'post_type'      => Post_Types::MENU,
				'post_status'    => 'publish',
				'posts_per_page' => 100, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin list.
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		) as $menu ) {
			$targets[ get_permalink( $menu ) ] = sprintf( /* translators: %s: menu name. */ __( 'Menu: %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $menu->post_title );
		}
		foreach ( get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin list.
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		) as $page ) {
			$targets[ get_permalink( $page ) ] = sprintf( /* translators: %s: page title. */ __( 'Page: %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $page->post_title );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preselect only.
		$preselect = isset( $_GET['menu'] ) ? get_permalink( absint( $_GET['menu'] ) ) : '';
		?>
		<div class="wrap sidrest-qr-page">
			<h1><?php esc_html_e( 'Menu QR codes', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></h1>
			<p><?php esc_html_e( 'Put a QR code on each table: guests scan it and your menu opens on their phone. Change the menu any time — the code keeps working.', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></p>
			<div class="sidrest-qr-tool" data-sidrest-qr-tool>
				<div class="sidrest-qr-tool__form">
					<p><label for="sidrest-qr-target"><strong><?php esc_html_e( 'Opens', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></strong></label><br>
						<select id="sidrest-qr-target" data-qr-target class="regular-text">
							<?php foreach ( $targets as $url => $label ) : ?>
								<option value="<?php echo esc_attr( $url ); ?>" <?php selected( $preselect, $url ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
							<option value=""><?php esc_html_e( 'Another link…', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></option>
						</select></p>
					<p data-qr-custom-wrap hidden><label for="sidrest-qr-url"><?php esc_html_e( 'Link', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label><br>
						<input type="url" id="sidrest-qr-url" class="regular-text" data-qr-url placeholder="https://"></p>
					<p><label for="sidrest-qr-size"><?php esc_html_e( 'Size (pixels)', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label><br>
						<input type="number" id="sidrest-qr-size" data-qr-size value="512" min="128" max="2048" step="32" class="small-text"></p>
					<p><label for="sidrest-qr-color"><?php esc_html_e( 'Colour', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
						<input type="color" id="sidrest-qr-color" data-qr-color value="#111111">
						<label for="sidrest-qr-bg"><?php esc_html_e( 'Background', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
						<input type="color" id="sidrest-qr-bg" data-qr-bg value="#ffffff"></p>
					<p><label for="sidrest-qr-caption"><?php esc_html_e( 'Caption for table cards', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label><br>
						<input type="text" id="sidrest-qr-caption" class="regular-text" data-qr-caption value="<?php echo esc_attr( label( 'scan' ) ); ?>"></p>
					<p class="sidrest-qr-tool__buttons">
						<button type="button" class="button button-primary" data-qr-png><?php esc_html_e( 'Download PNG', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></button>
						<button type="button" class="button" data-qr-svg><?php esc_html_e( 'Download SVG', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></button>
						<button type="button" class="button" data-qr-print><?php esc_html_e( 'Print table cards', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'You can also place a QR code on any page with the Menu QR Code block, Unit or [sidrest_qr] shortcode.', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></p>
				</div>
				<div class="sidrest-qr-tool__preview">
					<div data-qr-preview class="sidrest-qr-tool__code"></div>
					<p data-qr-link class="sidrest-qr-tool__link"></p>
				</div>
			</div>
			<?php if ( \SidcraftRestaurant\Service\Dine_In::enabled() ) : ?>
				<?php
				$sidrest_tables = array();
				foreach ( \SidcraftRestaurant\Service\Dine_In::tables() as $sidrest_table ) {
					$sidrest_tables[] = array(
						'name' => $sidrest_table,
						'k'    => \SidcraftRestaurant\Service\Dine_In::sign( $sidrest_table ),
					);
				}
				?>
				<h2><?php esc_html_e( 'Table QR codes', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></h2>
				<p><?php esc_html_e( 'One code per table: guests who scan it order from the menu chosen above, and their order is marked for that table. Print them and put each on its table.', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></p>
				<?php /* translators: %s: table name. */ ?>
				<div class="sidrest-qr-tables" data-qr-tables="<?php echo esc_attr( (string) wp_json_encode( $sidrest_tables ) ); ?>" data-qr-table-label="<?php echo esc_attr__( 'Table %s', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?>"></div>
				<p><button type="button" class="button button-primary" data-qr-tables-print><?php esc_html_e( 'Print all table cards', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></button></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
