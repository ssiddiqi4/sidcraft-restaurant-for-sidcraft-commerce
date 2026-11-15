<?php
/**
 * Meal deals: an item whose price buys one choice from each of its groups
 * ("Burger + side + drink for 12": Main from 3 burgers, Side from fries or
 * salad, Drink from 4 drinks). Meta _sidrest_deal: [ [ label, items[] ], … ].
 *
 * When ordered online each group becomes a required choice in the cart
 * (through Sidcraft Commerce's item options), so the order, the emails and
 * the kitchen ticket show what was picked. Items sold out today drop out of
 * the choices.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Service;

use SidcraftRestaurant\Item;
use SidcraftRestaurant\Ordering;
use SidcraftRestaurant\Post_Types;
use function SidcraftRestaurant\label;

defined( 'ABSPATH' ) || exit;

/**
 * Class Deals
 */
class Deals {

	const META = '_sidrest_deal';

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		// After the size choice (Ordering::options at 10).
		add_filter( 'sidcraft_commerce_product_options', array( $this, 'options' ), 11, 2 );
	}

	/**
	 * Groups of an item: [ label, items (int[]) ].
	 *
	 * @param int $item_id Item.
	 * @return array[]
	 */
	public static function groups( $item_id ) {
		$out = array();
		foreach ( (array) get_post_meta( (int) $item_id, self::META, true ) as $row ) {
			$items = array_values( array_filter( array_map( 'intval', (array) ( $row['items'] ?? array() ) ) ) );
			$label = trim( (string) ( $row['label'] ?? '' ) );
			if ( '' !== $label && $items ) {
				$out[] = array(
					'label' => $label,
					'items' => $items,
				);
			}
		}
		return $out;
	}

	/**
	 * Save groups from the item screen.
	 *
	 * @param int   $item_id Item.
	 * @param array $rows    Posted rows.
	 */
	public static function save( $item_id, array $rows ) {
		$out = array();
		foreach ( $rows as $row ) {
			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			$items = array_values( array_diff( array_unique( array_filter( array_map( 'absint', (array) ( $row['items'] ?? array() ) ) ) ), array( (int) $item_id ) ) );
			if ( '' !== $label && $items ) {
				$out[] = array(
					'label' => $label,
					'items' => $items,
				);
			}
		}
		update_post_meta( (int) $item_id, self::META, $out );
	}

	/**
	 * "Main: Cheeseburger, Veggie burger / Side: Fries, Salad" for menus.
	 *
	 * @param int $item_id Item.
	 * @return string HTML.
	 */
	public static function html( $item_id ) {
		$groups = self::groups( $item_id );
		if ( ! $groups ) {
			return '';
		}
		_prime_post_caches( array_merge( ...array_column( $groups, 'items' ) ), false, false );
		$out = '<ul class="sidrest-item__deal">';
		foreach ( $groups as $group ) {
			$names = array();
			foreach ( $group['items'] as $id ) {
				if ( 'publish' === get_post_status( $id ) ) {
					$names[] = get_the_title( $id );
				}
			}
			$out .= '<li><strong>' . esc_html( $group['label'] ) . ':</strong> ' . esc_html( implode( ', ', $names ) ) . '</li>';
		}
		return $out . '</ul>';
	}

	/**
	 * Items of a group that can be chosen now (published, not sold out).
	 *
	 * @param int[] $ids Items.
	 * @return int[]
	 */
	public static function on_offer( array $ids ) {
		_prime_post_caches( $ids, false, true );
		return array_values(
			array_filter(
				$ids,
				static function ( $id ) {
					return 'publish' === get_post_status( $id ) && ! Availability::sold_out( $id );
				}
			)
		);
	}

	/**
	 * Can the deal be put together now: something left in every group?
	 *
	 * @param int $item_id Item.
	 * @return bool
	 */
	public static function available( $item_id ) {
		foreach ( self::groups( $item_id ) as $group ) {
			if ( ! self::on_offer( $group['items'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * A deal's groups become required choices.
	 *
	 * @param array[] $groups  Option groups.
	 * @param mixed   $product Product.
	 * @return array[]
	 */
	public function options( $groups, $product ) {
		$item = $product instanceof \SidcraftCommerce\Data\Product ? Ordering::item_for( $product ) : null;
		if ( ! $item ) {
			return $groups;
		}
		foreach ( self::groups( $item->get_id() ) as $i => $group ) {
			$choices = array();
			foreach ( self::on_offer( $group['items'] ) as $id ) {
				$choices[] = array(
					'id'    => 'i' . $id,
					'label' => get_the_title( $id ),
					'price' => 0,
				);
			}
			if ( ! $choices ) {
				continue; // The deal itself can't be bought then (see available()).
			}
			$groups[] = array(
				'id'       => 'sidrest-deal-' . $i,
				/* translators: %s: group name, e.g. "Side". */
				'label'    => sprintf( label( 'deal_choose' ), mb_strtolower( $group['label'] ) ),
				'type'     => count( $choices ) > 6 ? 'select' : 'radio',
				'required' => true,
				'choices'  => $choices,
			);
		}
		return $groups;
	}

	/**
	 * Items to pick from on the item screen: id => title.
	 *
	 * @param int $exclude Item being edited.
	 * @return array
	 */
	public static function item_choices( $exclude ) {
		$out = array();
		foreach ( get_posts(
			array(
				'post_type'              => Post_Types::ITEM,
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'posts_per_page'         => 500,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		) as $post ) {
			if ( (int) $post->ID !== (int) $exclude ) {
				$out[ $post->ID ] = get_the_title( $post );
			}
		}
		return $out;
	}
}
