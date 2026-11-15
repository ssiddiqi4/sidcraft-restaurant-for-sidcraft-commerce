<?php
/**
 * A menu (or a single section): toolbar, section sidebar, sections, dietary
 * key and footer.
 *
 * Override by copying to: yourtheme/sidcraft-restaurant/menu.php
 *
 * @package SidcraftRestaurant
 * @version 1.2.0
 *
 * @var \SidcraftRestaurant\Menu|null $menu       Menu (null for a single section).
 * @var array[]                       $sections   Each: section (Section), items (Item[]).
 * @var array                         $args       style, sidebar, toolbar, show_title, show_images, columns, heading.
 * @var string                        $uid        Unique ID prefix for this instance.
 * @var array                         $categories slug => name of categories used.
 * @var string[]                      $diets      Dietary icons used.
 * @var array                         $allergens  slug => name of allergens used (filter).
 * @var \SidcraftRestaurant\Renderer  $renderer   Renderer.
 */

defined( 'ABSPATH' ) || exit;

use SidcraftRestaurant\Dietary;
use function SidcraftRestaurant\label;
use function SidcraftRestaurant\setting;

if ( ! $sections ) {
	return;
}

$sidrest_style   = $args['style'];
$sidrest_sidebar = $args['sidebar'] && count( $sections ) > 1;
$allergens       = isset( $allergens ) ? (array) $allergens : array();
$sidrest_toolbar = $args['toolbar'] && ( setting( 'enable_search', 1 ) || $categories || ( setting( 'enable_dietary', 1 ) && $diets ) || setting( 'enable_sort', 1 ) || $allergens );
$sidrest_heading = $args['heading'];
$sidrest_item_h  = 'h2' === $sidrest_heading ? 'h3' : 'h4';
$sidrest_classes = array( 'sidrest', 'sidrest--style-' . $sidrest_style );
if ( $sidrest_sidebar ) {
	$sidrest_classes[] = 'sidrest--has-nav';
}
$sidrest_order = 0;
?>
<div id="<?php echo esc_attr( $uid ); ?>" class="<?php echo esc_attr( implode( ' ', $sidrest_classes ) ); ?>" style="<?php echo esc_attr( \SidcraftRestaurant\Frontend::css_vars() ); ?>" data-sidrest-menu<?php echo $menu ? \SidcraftRestaurant\Service\Availability::block_attrs( $menu->get_id() ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in block_attrs(). ?>>
	<?php if ( $menu && $args['show_title'] ) : ?>
		<<?php echo esc_html( $sidrest_heading ); ?> class="sidrest__title"><?php echo esc_html( $menu->get_title() ); ?></<?php echo esc_html( $sidrest_heading ); ?>>
	<?php endif; ?>

	<p class="sidrest__open" data-sidrest-open-status role="status" hidden></p>
	<p class="sidrest__table" data-sidrest-table-status role="status" hidden></p>
	<p class="sidrest__served" data-sidrest-served hidden></p>

	<?php
	/**
	 * Fires at the top of a menu, under its title (delivery platform links use this).
	 *
	 * @param \SidcraftRestaurant\Menu|null $menu Menu.
	 * @param array                         $args Display options.
	 */
	do_action( 'sidrest_menu_top', $menu, $args );
	?>

	<?php if ( 'ordering' === $sidrest_style ) : ?>
		<div class="sidrest__cartbar">
			<?php
			$sidrest_mini = \SidcraftCommerce\Plugin::instance()->units->get( 'sidccofo-mini-cart' );
			echo $sidrest_mini ? $sidrest_mini->render( array( 'show_total' => true, 'label' => label( 'view_order' ) ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the Unit.
			?>
		</div>
	<?php endif; ?>

	<?php if ( $sidrest_toolbar ) : ?>
		<div class="sidrest__toolbar">
			<?php if ( setting( 'enable_search', 1 ) ) : ?>
				<div class="sidrest__search">
					<label class="sidrest-sr" for="<?php echo esc_attr( $uid ); ?>-search"><?php echo esc_html( label( 'search' ) ); ?></label>
					<input type="search" id="<?php echo esc_attr( $uid ); ?>-search" placeholder="<?php echo esc_attr( label( 'search' ) ); ?>" autocomplete="off" data-sidrest-search>
				</div>
			<?php endif; ?>
			<?php if ( setting( 'enable_sort', 1 ) ) : ?>
				<div class="sidrest__sort">
					<label for="<?php echo esc_attr( $uid ); ?>-sort"><?php echo esc_html( label( 'sort' ) ); ?></label>
					<select id="<?php echo esc_attr( $uid ); ?>-sort" data-sidrest-sort>
						<option value="menu"><?php echo esc_html( label( 'sort_menu' ) ); ?></option>
						<option value="name-asc"><?php echo esc_html( label( 'sort_name_asc' ) ); ?></option>
						<option value="name-desc"><?php echo esc_html( label( 'sort_name_desc' ) ); ?></option>
						<option value="price-asc"><?php echo esc_html( label( 'sort_price_asc' ) ); ?></option>
						<option value="price-desc"><?php echo esc_html( label( 'sort_price_dsc' ) ); ?></option>
					</select>
				</div>
			<?php endif; ?>
			<?php if ( $categories ) : ?>
				<div class="sidrest__chips" role="group" aria-label="<?php echo esc_attr( label( 'categories' ) ); ?>">
					<button type="button" class="sidrest-chip" aria-pressed="true" data-sidrest-cat=""><?php echo esc_html( label( 'all' ) ); ?></button>
					<?php foreach ( $categories as $sidrest_slug => $sidrest_name ) : ?>
						<button type="button" class="sidrest-chip" aria-pressed="false" data-sidrest-cat="<?php echo esc_attr( $sidrest_slug ); ?>"><?php echo esc_html( $sidrest_name ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( setting( 'enable_dietary', 1 ) && $diets ) : ?>
				<div class="sidrest__chips sidrest__chips--diet" role="group" aria-label="<?php echo esc_attr( label( 'dietary' ) ); ?>">
					<?php $sidrest_all_diets = Dietary::all(); ?>
					<?php foreach ( $diets as $sidrest_diet ) : ?>
						<button type="button" class="sidrest-chip sidrest-chip--diet" aria-pressed="false" data-sidrest-diet="<?php echo esc_attr( $sidrest_diet ); ?>">
							<?php echo Dietary::icon( $sidrest_diet ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Dietary::icon(). ?>
							<span><?php echo esc_html( $sidrest_all_diets[ $sidrest_diet ][0] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( $allergens ) : ?>
				<div class="sidrest__chips sidrest__chips--allergens" role="group" aria-label="<?php echo esc_attr( label( 'avoid' ) ); ?>">
					<span class="sidrest__chips-label"><?php echo esc_html( label( 'avoid' ) ); ?>:</span>
					<?php foreach ( $allergens as $sidrest_slug => $sidrest_name ) : ?>
						<button type="button" class="sidrest-chip sidrest-chip--allergen" aria-pressed="false" data-sidrest-allergen="<?php echo esc_attr( $sidrest_slug ); ?>"><?php echo esc_html( $sidrest_name ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<p class="sidrest__status" role="status" aria-live="polite" data-sidrest-status></p>
	<?php endif; ?>

	<div class="sidrest__layout">
		<?php if ( $sidrest_sidebar ) : ?>
			<nav class="sidrest__nav" aria-label="<?php echo esc_attr( label( 'sections' ) ); ?>">
				<ul>
					<?php foreach ( $sections as $sidrest_block ) : ?>
						<li><a href="#<?php echo esc_attr( $uid . '-s' . $sidrest_block['section']->get_id() ); ?>" data-sidrest-nav="<?php echo esc_attr( $uid . '-s' . $sidrest_block['section']->get_id() ); ?>"><?php echo esc_html( $sidrest_block['section']->get_title() ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>

		<div class="sidrest__sections">
			<?php foreach ( $sections as $sidrest_block ) : ?>
				<?php $sidrest_section = $sidrest_block['section']; ?>
				<section class="sidrest-section" id="<?php echo esc_attr( $uid . '-s' . $sidrest_section->get_id() ); ?>" data-sidrest-section<?php echo \SidcraftRestaurant\Service\Availability::block_attrs( $sidrest_section->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in block_attrs(). ?>>
					<<?php echo esc_html( $sidrest_heading ); ?> class="sidrest-section__title"><?php echo esc_html( $sidrest_section->get_title() ); ?></<?php echo esc_html( $sidrest_heading ); ?>>
					<p class="sidrest-section__served" data-sidrest-served hidden></p>
					<?php if ( '' !== $sidrest_section->get_note() ) : ?>
						<p class="sidrest-section__note"><?php echo esc_html( $sidrest_section->get_note() ); ?></p>
					<?php endif; ?>
					<div class="sidrest-items sidrest-items--<?php echo esc_attr( $sidrest_style ); ?> sidrest-items--cols-<?php echo esc_attr( $args['columns'] ); ?>" data-sidrest-items>
						<?php
						foreach ( $sidrest_block['items'] as $sidrest_item ) {
							echo $renderer->item( $sidrest_item, array_merge( $args, array( 'heading' => $sidrest_item_h ) ), $sidrest_order++ ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in item.php.
						}
						?>
					</div>
				</section>
			<?php endforeach; ?>
			<div class="sidrest__empty" data-sidrest-empty hidden>
				<p><?php echo esc_html( label( 'no_results' ) ); ?></p>
				<button type="button" class="sidrest-chip" data-sidrest-clear><?php echo esc_html( label( 'clear' ) ); ?></button>
			</div>
		</div>
	</div>

	<?php if ( $diets && setting( 'show_legend', 1 ) ) : ?>
		<?php $sidrest_all_diets = Dietary::all(); ?>
		<div class="sidrest__legend">
			<span class="sidrest__legend-title"><?php echo esc_html( label( 'legend' ) ); ?>:</span>
			<?php foreach ( $diets as $sidrest_diet ) : ?>
				<span class="sidrest__legend-item"><?php echo Dietary::icon( $sidrest_diet ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Dietary::icon(). ?> <?php echo esc_html( $sidrest_all_diets[ $sidrest_diet ][0] ); ?></span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $menu && '' !== $menu->get_footer() ) : ?>
		<footer class="sidrest__footer"><?php echo wp_kses_post( wpautop( $menu->get_footer() ) ); ?></footer>
	<?php endif; ?>
</div>
