<?php
/**
 * Online ordering through Sidcraft Commerce.
 *
 * Ticking "Order online" on a menu item keeps a matching Commerce product in
 * step with it (name, description, photo, price). The item stays the single
 * source of truth: its prices (with any running promotion) become the
 * product's price, and several prices become a required size choice, so the
 * cart, checkout, emails and orders all work as for any other product.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

use SidcraftCommerce\Data\Product;

defined( 'ABSPATH' ) || exit;

/**
 * Class Ordering
 */
class Ordering {

	/** Meta on the Commerce product pointing back at the menu item. */
	const LINK = '_sidrest_item_id';

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_action( 'save_post_' . Post_Types::ITEM, array( $this, 'on_save' ), 20, 2 );
		add_action( 'transition_post_status', array( $this, 'on_status' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'on_delete' ) );
		add_filter( 'sidcraft_commerce_product_price', array( $this, 'price' ), 10, 2 );
		add_filter( 'sidcraft_commerce_product_options', array( $this, 'options' ), 10, 2 );
		add_filter( 'sidcraft_commerce_product_grid_query_args', array( $this, 'hide_from_grids' ) );
		add_action( 'template_redirect', array( $this, 'redirect_product' ) );
	}

	/**
	 * The menu item behind a Commerce product.
	 *
	 * @param Product|int $product Product or ID.
	 * @return Item|null
	 */
	public static function item_for( $product ) {
		$id      = $product instanceof Product ? $product->get_id() : (int) $product;
		$item_id = (int) get_post_meta( $id, self::LINK, true );
		$post    = $item_id ? get_post( $item_id ) : null;
		return $post && Post_Types::ITEM === $post->post_type ? new Item( $post ) : null;
	}

	/**
	 * After an item is saved: create, update or retire its product.
	 *
	 * @param int      $post_id Item ID.
	 * @param \WP_Post $post    Item post.
	 */
	public function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		self::sync( $post_id );
	}

	/**
	 * Keep the product's visibility with the item's (published, draft, trash).
	 *
	 * @param string   $new_status New.
	 * @param string   $old_status Old.
	 * @param \WP_Post $post       Post.
	 */
	public function on_status( $new_status, $old_status, $post ) {
		if ( Post_Types::ITEM === $post->post_type && $new_status !== $old_status && 'auto-draft' !== $new_status ) {
			self::sync( $post->ID );
		}
	}

	/**
	 * An item deleted for good: its product goes to draft (past orders keep it).
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_delete( $post_id ) {
		if ( Post_Types::ITEM !== get_post_type( $post_id ) ) {
			return;
		}
		$product_id = (int) get_post_meta( $post_id, '_sidrest_product_id', true );
		if ( $product_id && 'draft' !== get_post_status( $product_id ) ) {
			wp_update_post(
				array(
					'ID'          => $product_id,
					'post_status' => 'draft',
				)
			);
		}
	}

	/**
	 * Bring an item's product in line with the item.
	 *
	 * @param int $item_id Item ID.
	 * @return int Product ID (0 when not orderable).
	 */
	public static function sync( $item_id ) {
		$post = get_post( $item_id );
		if ( ! $post || Post_Types::ITEM !== $post->post_type ) {
			return 0;
		}
		$item       = new Item( $post );
		$product_id = (int) $item->meta( 'product_id' );
		if ( $product_id && Product::get( $product_id ) === null ) {
			$product_id = 0; // Deleted in the store.
		}
		$prices = $item->get_prices();
		$wanted = $item->meta( 'orderable' ) && $prices;

		if ( ! $wanted ) {
			if ( $product_id && 'draft' !== get_post_status( $product_id ) ) {
				wp_update_post(
					array(
						'ID'          => $product_id,
						'post_status' => 'draft',
					)
				);
			}
			return 0;
		}

		$status = 'publish' === $post->post_status && '' === $post->post_password ? 'publish' : 'draft';
		$data   = array(
			'post_type'    => \SidcraftCommerce\Data\Post_Types::PRODUCT,
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_status'  => $status,
		);
		if ( $product_id ) {
			$data['ID'] = $product_id;
			wp_update_post( $data );
		} else {
			$product_id = (int) wp_insert_post( $data );
			if ( ! $product_id ) {
				return 0;
			}
			update_post_meta( $item_id, '_sidrest_product_id', $product_id );
		}

		$regular = min( wp_list_pluck( $prices, 'price' ) );
		update_post_meta( $product_id, self::LINK, $item_id );
		update_post_meta( $product_id, '_sidccofo_regular_price', (string) $regular );
		update_post_meta( $product_id, '_sidccofo_sale_price', '' );
		update_post_meta( $product_id, '_sidccofo_manage_stock', 0 );
		update_post_meta( $product_id, '_sidccofo_stock_status', 'instock' );
		update_post_meta( $product_id, '_sidccofo_gift_card', 0 );

		$thumb = $item->get_image_id();
		if ( $thumb ) {
			set_post_thumbnail( $product_id, $thumb );
		} else {
			delete_post_thumbnail( $product_id );
		}
		Product::sync_derived_meta( $product_id );

		/**
		 * Fires after a menu item's Commerce product was updated.
		 *
		 * @param int  $product_id Product ID.
		 * @param Item $item       Item.
		 */
		do_action( 'sidrest_product_synced', $product_id, $item );
		return $product_id;
	}

	/**
	 * Linked products cost what the item costs now (promotions included).
	 *
	 * @param float   $price   Price.
	 * @param Product $product Product.
	 * @return float
	 */
	public function price( $price, $product ) {
		$item = $product instanceof Product ? self::item_for( $product ) : null;
		if ( ! $item ) {
			return $price;
		}
		$min = $item->get_min_price();
		return null === $min ? $price : $min;
	}

	/**
	 * Several prices ("Glass / Bottle", "Small / Large") become a required
	 * choice, priced as the difference from the lowest price.
	 *
	 * @param array[] $groups  Option groups.
	 * @param Product $product Product.
	 * @return array[]
	 */
	public function options( $groups, $product ) {
		$item = $product instanceof Product ? self::item_for( $product ) : null;
		if ( ! $item ) {
			return $groups;
		}
		$rows = $item->get_current_prices();
		if ( count( $rows ) < 2 ) {
			return $groups;
		}
		$base    = min( wp_list_pluck( $rows, 'price' ) );
		$choices = array();
		foreach ( $rows as $i => $row ) {
			$choices[] = array(
				'id'    => 'p' . $i,
				/* translators: %d: price row number. */
				'label' => '' !== $row['label'] ? $row['label'] : sprintf( __( 'Option %d', 'sidcraft-restaurant-for-sidcraft-commerce' ), $i + 1 ),
				'price' => max( 0, round( $row['price'] - $base, 2 ) ),
			);
		}
		array_unshift(
			$groups,
			array(
				'id'       => 'sidrest-size',
				'label'    => label( 'choose' ),
				'type'     => 'radio',
				'required' => true,
				'choices'  => $choices,
			)
		);
		return $groups;
	}

	/**
	 * Keep menu-item products out of the store's product grids (they are
	 * ordered from the menu).
	 *
	 * @param array $args WP_Query args.
	 * @return array
	 */
	public function hide_from_grids( $args ) {
		if ( ! setting( 'hide_from_shop', 1 ) ) {
			return $args;
		}
		$args['meta_query']   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Existence check on an indexed key.
		$args['meta_query'][] = array(
			'key'     => self::LINK,
			'compare' => 'NOT EXISTS',
		);
		return $args;
	}

	/**
	 * Visiting a menu item's product page goes to the menu item page instead.
	 */
	public function redirect_product() {
		if ( ! is_singular( \SidcraftCommerce\Data\Post_Types::PRODUCT ) || ! setting( 'item_pages', 1 ) ) {
			return;
		}
		$item = self::item_for( get_queried_object_id() );
		if ( $item && $item->is_visible() ) {
			wp_safe_redirect( $item->get_url(), 301 );
			exit;
		}
	}
}
