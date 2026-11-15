<?php
/**
 * One menu item card. The style (classic, grid, list, compact, luxe,
 * ordering) changes the layout through CSS classes on the list.
 *
 * Override by copying to: yourtheme/sidcraft-restaurant/item.php
 * Keep the data-* attributes: search, filters and sorting use them.
 *
 * @package SidcraftRestaurant
 * @version 0.1.0
 *
 * @var \SidcraftRestaurant\Item $item  Item.
 * @var array                    $args  style, show_images, heading.
 * @var int                      $order Position in the menu.
 */

defined( 'ABSPATH' ) || exit;

use SidcraftRestaurant\Renderer;
use function SidcraftRestaurant\setting;

$sidrest_style   = $args['style'];
$sidrest_image   = ( $args['show_images'] && 'luxe' !== $sidrest_style && 'classic' !== $sidrest_style && 'compact' !== $sidrest_style ) ? $item->get_image( 'grid' === $sidrest_style || 'ordering' === $sidrest_style ? 'medium_large' : 'thumbnail' ) : '';
$sidrest_link    = setting( 'item_pages', 1 ) ? $item->get_url() : '';
$sidrest_h       = in_array( $args['heading'] ?? 'h3', array( 'h2', 'h3', 'h4' ), true ) ? $args['heading'] : 'h3';
$sidrest_min     = $item->get_min_price();
$sidrest_cats    = implode( ' ', wp_list_pluck( $item->get_categories(), 'slug' ) );
$sidrest_desc    = $item->get_description();
$sidrest_classes = array( 'sidrest-item' );
if ( $sidrest_image ) {
	$sidrest_classes[] = 'has-image';
}
if ( $item->promo_active() ) {
	$sidrest_classes[] = 'is-promo';
}
?>
<article class="<?php echo esc_attr( implode( ' ', $sidrest_classes ) ); ?>" data-sidrest-item data-order="<?php echo esc_attr( $order ); ?>" data-name="<?php echo esc_attr( mb_strtolower( $item->get_name() ) ); ?>" data-price="<?php echo esc_attr( null === $sidrest_min ? '' : $sidrest_min ); ?>" data-cats="<?php echo esc_attr( $sidrest_cats ); ?>" data-diet="<?php echo esc_attr( implode( ' ', $item->get_dietary() ) ); ?>" data-search="<?php echo esc_attr( mb_strtolower( $item->get_name() . ' ' . $sidrest_desc ) ); ?>">
	<?php if ( $sidrest_image ) : ?>
		<div class="sidrest-item__media">
			<?php if ( $sidrest_link ) : ?>
				<a href="<?php echo esc_url( $sidrest_link ); ?>" tabindex="-1" aria-hidden="true"><?php echo $sidrest_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?></a>
			<?php else : ?>
				<?php echo $sidrest_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?>
			<?php endif; ?>
			<?php echo Renderer::badges_html( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
		</div>
	<?php endif; ?>
	<div class="sidrest-item__body">
		<div class="sidrest-item__head">
			<<?php echo esc_html( $sidrest_h ); ?> class="sidrest-item__name">
				<?php if ( $sidrest_link ) : ?>
					<a href="<?php echo esc_url( $sidrest_link ); ?>"><?php echo esc_html( $item->get_name() ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $item->get_name() ); ?>
				<?php endif; ?>
			</<?php echo esc_html( $sidrest_h ); ?>>
			<?php echo Renderer::dietary_html( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
			<span class="sidrest-item__leader" aria-hidden="true"></span>
			<?php echo Renderer::prices_html( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
		</div>
		<?php if ( ! $sidrest_image ) : ?>
			<?php echo Renderer::badges_html( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
		<?php endif; ?>
		<?php if ( '' !== $sidrest_desc ) : ?>
			<p class="sidrest-item__desc"><?php echo esc_html( $sidrest_desc ); ?></p>
		<?php endif; ?>
		<?php echo Renderer::fields_html( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
		<?php echo Renderer::rating_html( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the Unit. ?>
		<?php if ( 'ordering' === $sidrest_style ) : ?>
			<?php echo Renderer::order_html( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the Unit. ?>
		<?php endif; ?>
	</div>
</article>
