<?php
/**
 * Uninstall: removes settings and cached data. Menus, sections and items are
 * kept unless SIDREST_REMOVE_ALL_DATA is true in wp-config.php.
 *
 * @package SidcraftRestaurant
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'sidrest_settings' );
delete_option( 'sidrest_flush_rewrites' );
delete_option( 'sidrest_courier_status_key' );
foreach ( array( 'sidrest_menu', 'sidrest_section', 'sidrest_item' ) as $sidrest_type ) {
	delete_transient( 'sidrest_opts_' . $sidrest_type );
}

if ( defined( 'SIDREST_REMOVE_ALL_DATA' ) && SIDREST_REMOVE_ALL_DATA ) {
	foreach ( array( 'sidrest_menu', 'sidrest_section', 'sidrest_item' ) as $sidrest_type ) {
		$sidrest_ids = get_posts(
			array(
				'post_type'      => $sidrest_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $sidrest_ids as $sidrest_id ) {
			wp_delete_post( $sidrest_id, true );
		}
	}
	$sidrest_terms = get_terms(
		array(
			'taxonomy'   => 'sidrest_item_cat',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	foreach ( is_array( $sidrest_terms ) ? $sidrest_terms : array() as $sidrest_term ) {
		wp_delete_term( $sidrest_term, 'sidrest_item_cat' );
	}
}
