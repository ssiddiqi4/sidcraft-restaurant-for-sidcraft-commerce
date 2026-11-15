<?php
/**
 * Table booking form.
 *
 * Override by copying to: yourtheme/sidcraft-restaurant/booking.php
 * Keep the data-* attributes: the booking script uses them.
 *
 * @package SidcraftRestaurant
 * @version 1.2.0
 *
 * @var string $title     Heading ('' = none).
 * @var int    $max_party Largest party bookable online.
 * @var string $min_date  First bookable date (Y-m-d).
 * @var string $max_date  Last bookable date (Y-m-d).
 */

defined( 'ABSPATH' ) || exit;

$sidrest_uid = 'sidrest-book-' . wp_unique_id();
?>
<form class="sidrest-booking" data-sidrest-booking novalidate style="<?php echo esc_attr( \SidcraftRestaurant\Frontend::css_vars() ); ?>">
	<?php if ( '' !== $title ) : ?>
		<h3 class="sidrest-booking__title"><?php echo esc_html( $title ); ?></h3>
	<?php endif; ?>
	<div class="sidrest-booking__row">
		<p class="sidrest-booking__field">
			<label for="<?php echo esc_attr( $sidrest_uid ); ?>-date"><?php esc_html_e( 'Date', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
			<input type="date" id="<?php echo esc_attr( $sidrest_uid ); ?>-date" name="date" min="<?php echo esc_attr( $min_date ); ?>" max="<?php echo esc_attr( $max_date ); ?>" value="<?php echo esc_attr( $min_date ); ?>" required>
		</p>
		<p class="sidrest-booking__field">
			<label for="<?php echo esc_attr( $sidrest_uid ); ?>-party"><?php esc_html_e( 'Guests', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
			<select id="<?php echo esc_attr( $sidrest_uid ); ?>-party" name="party">
				<?php for ( $sidrest_n = 1; $sidrest_n <= $max_party; $sidrest_n++ ) : ?>
					<option value="<?php echo esc_attr( $sidrest_n ); ?>" <?php selected( 2, $sidrest_n ); ?>><?php echo esc_html( $sidrest_n ); ?></option>
				<?php endfor; ?>
			</select>
		</p>
	</div>
	<fieldset class="sidrest-booking__times">
		<legend><?php esc_html_e( 'Time', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></legend>
		<div class="sidrest-booking__slots" data-sidrest-slots role="radiogroup" aria-live="polite"></div>
		<p class="sidrest-booking__hint" data-sidrest-slots-hint></p>
	</fieldset>
	<div class="sidrest-booking__row">
		<p class="sidrest-booking__field">
			<label for="<?php echo esc_attr( $sidrest_uid ); ?>-name"><?php esc_html_e( 'Name', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $sidrest_uid ); ?>-name" name="name" autocomplete="name" required>
		</p>
		<p class="sidrest-booking__field">
			<label for="<?php echo esc_attr( $sidrest_uid ); ?>-phone"><?php esc_html_e( 'Phone', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
			<input type="tel" id="<?php echo esc_attr( $sidrest_uid ); ?>-phone" name="phone" autocomplete="tel" required>
		</p>
	</div>
	<p class="sidrest-booking__field">
		<label for="<?php echo esc_attr( $sidrest_uid ); ?>-email"><?php esc_html_e( 'Email', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
		<input type="email" id="<?php echo esc_attr( $sidrest_uid ); ?>-email" name="email" autocomplete="email" required>
	</p>
	<p class="sidrest-booking__field">
		<label for="<?php echo esc_attr( $sidrest_uid ); ?>-notes"><?php esc_html_e( 'Anything we should know? (optional)', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
		<textarea id="<?php echo esc_attr( $sidrest_uid ); ?>-notes" name="notes" rows="2" maxlength="500"></textarea>
	</p>
	<p class="sidrest-booking__hp" aria-hidden="true"><label><?php esc_html_e( 'Website', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
	<p><button type="submit" class="sidrest-btn"><?php echo esc_html( \SidcraftRestaurant\label( 'book_table' ) ); ?></button></p>
	<p class="sidrest-booking__message" data-sidrest-booking-message role="status" aria-live="polite"></p>
</form>
