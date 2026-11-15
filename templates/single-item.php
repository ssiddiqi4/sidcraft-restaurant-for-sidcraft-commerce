<?php
/**
 * Extra content on a menu item's own page, around the description written
 * in the editor: prices, badges, icons, ordering, details, suppliers with
 * maps, reviews and related items.
 *
 * Override by copying to: yourtheme/sidcraft-restaurant/single-item.php
 *
 * @package SidcraftRestaurant
 * @version 1.1
 *
 * @var \SidcraftRestaurant\Item $item    Item.
 * @var string                   $content The description (already filtered).
 */

defined( 'ABSPATH' ) || exit;

use SidcraftRestaurant\Renderer;
use function SidcraftRestaurant\label;
use function SidcraftRestaurant\setting;

$sidrest_suppliers = $item->get_suppliers();
$sidrest_related   = $item->get_related( 4 );
$sidrest_product   = $item->is_orderable() ? $item->get_product() : null;
?>
<div class="sidrest sidrest-single" style="<?php echo esc_attr( \SidcraftRestaurant\Frontend::css_vars() ); ?>">
	<div class="sidrest-single__summary">
		<?php echo Renderer::badges_html( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
		<?php echo Renderer::prices_html( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
		<?php echo Renderer::dietary_html( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
		<?php echo Renderer::rating_html( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the Unit. ?>
		<?php echo Renderer::order_html( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the Unit. ?>
		<?php
		/**
		 * Fires at the end of an item page's summary (delivery platform links use this).
		 *
		 * @param \SidcraftRestaurant\Item $item Item.
		 */
		do_action( 'sidrest_single_item_summary', $item );
		?>
	</div>

	<div class="sidrest-single__content"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content output. ?></div>

	<?php $sidrest_fields = Renderer::fields_html( $item, false ); ?>
	<?php if ( '' !== $sidrest_fields ) : ?>
		<section class="sidrest-single__block">
			<h2><?php echo esc_html( label( 'details' ) ); ?></h2>
			<?php echo $sidrest_fields; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Renderer. ?>
		</section>
	<?php endif; ?>

	<?php if ( $sidrest_suppliers ) : ?>
		<section class="sidrest-single__block sidrest-suppliers">
			<h2><?php echo esc_html( label( 'suppliers' ) ); ?></h2>
			<ul>
				<?php foreach ( $sidrest_suppliers as $sidrest_s ) : ?>
					<li class="sidrest-supplier">
						<strong><?php echo esc_html( $sidrest_s['name'] ); ?></strong>
						<?php if ( '' !== $sidrest_s['address'] ) : ?>
							<span class="sidrest-supplier__address"><?php echo esc_html( $sidrest_s['address'] ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $sidrest_s['note'] ) : ?>
							<p><?php echo esc_html( $sidrest_s['note'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $sidrest_s['address'] && setting( 'supplier_maps', 1 ) ) : ?>
							<iframe class="sidrest-supplier__map" title="<?php echo esc_attr( $sidrest_s['name'] ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="<?php echo esc_url( 'https://maps.google.com/maps?output=embed&z=12&q=' . rawurlencode( $sidrest_s['address'] ) ); ?>"></iframe>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( $sidrest_product ) : ?>
		<?php $sidrest_reviews = \SidcraftCommerce\Plugin::instance()->units->get( 'sidccofo-product-reviews' ); ?>
		<?php if ( $sidrest_reviews ) : ?>
			<section class="sidrest-single__block sidrest-single__reviews">
				<?php echo $sidrest_reviews->render( array( 'product_id' => $sidrest_product->get_id() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the Unit. ?>
			</section>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $sidrest_related ) : ?>
		<section class="sidrest-single__block">
			<h2><?php echo esc_html( label( 'related' ) ); ?></h2>
			<div class="sidrest-items sidrest-items--grid sidrest-items--cols-4">
				<?php
				foreach ( $sidrest_related as $sidrest_i => $sidrest_rel ) {
					$sidrest_card = \SidcraftRestaurant\Plugin::instance()->renderer->item(
						$sidrest_rel,
						array(
							'style'       => 'grid',
							'show_images' => true,
							'heading'     => 'h3',
						),
						$sidrest_i
					);
					echo $sidrest_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in item.php.
				}
				?>
			</div>
		</section>
	<?php endif; ?>
</div>
