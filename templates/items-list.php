<?php
/**
 * Short list of menu items (widgets, sidebars, footers, the Menu Items Unit).
 *
 * Override by copying to: yourtheme/sidcraft-restaurant/items-list.php
 *
 * @package SidcraftRestaurant
 * @version 0.1.0
 *
 * @var \SidcraftRestaurant\Item[] $items Items.
 * @var array                      $args  show_images, show_prices, show_description.
 */

defined( 'ABSPATH' ) || exit;

use SidcraftRestaurant\Renderer;
use function SidcraftRestaurant\setting;
?>
<ul class="sidrest sidrest-list" style="<?php echo esc_attr( \SidcraftRestaurant\Frontend::css_vars() ); ?>">
	<?php foreach ( $items as $sidrest_item ) : ?>
		<?php
		$sidrest_link  = setting( 'item_pages', 1 ) ? $sidrest_item->get_url() : '';
		$sidrest_image = $args['show_images'] ? $sidrest_item->get_image( 'thumbnail' ) : '';
		?>
		<li class="sidrest-list__item<?php echo $sidrest_image ? ' has-image' : ''; ?>">
			<?php if ( $sidrest_image ) : ?>
				<span class="sidrest-list__media"><?php echo $sidrest_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?></span>
			<?php endif; ?>
			<span class="sidrest-list__body">
				<span class="sidrest-list__head">
					<?php if ( $sidrest_link ) : ?>
						<a class="sidrest-list__name" href="<?php echo esc_url( $sidrest_link ); ?>"><?php echo esc_html( $sidrest_item->get_name() ); ?></a>
					<?php else : ?>
						<span class="sidrest-list__name"><?php echo esc_html( $sidrest_item->get_name() ); ?></span>
					<?php endif; ?>
					<?php if ( $args['show_prices'] ) : ?>
						<?php echo Renderer::prices_html( $sidrest_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
					<?php endif; ?>
				</span>
				<?php if ( $args['show_description'] && '' !== $sidrest_item->get_description() ) : ?>
					<span class="sidrest-list__desc"><?php echo esc_html( $sidrest_item->get_description() ); ?></span>
				<?php endif; ?>
			</span>
		</li>
	<?php endforeach; ?>
</ul>
