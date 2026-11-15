<?php
/**
 * What can be ordered when:
 *
 *  - "Sold out today": an item marked sold out stays on the menu, greyed
 *    out, and can't be ordered until the next day (item meta _sidrest_sold_out:
 *    the last day it is sold out, Y-m-d).
 *  - Times of day for menus and sections (meta _sidrest_times: days[], from,
 *    to; empty = always), e.g. Breakfast 7:00–11:00, Lunch on weekdays. An
 *    item is available while at least one section it is in is (and that
 *    section's menu, when it has times).
 *
 * The menu page shows both through data attributes worked out again in the
 * browser, so cached pages stay right; the cart and checkout check them on
 * the server.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Service;

use SidcraftCommerce\Cart\Cart;
use SidcraftRestaurant\Item;
use SidcraftRestaurant\Ordering;
use SidcraftRestaurant\Post_Types;
use function SidcraftRestaurant\label;

defined( 'ABSPATH' ) || exit;

/**
 * Class Availability
 */
class Availability {

	const SOLD_OUT = '_sidrest_sold_out';

	const TIMES = '_sidrest_times';

	/**
	 * Attach hooks.
	 */
	public function hooks() {
		add_filter( 'sidcraft_commerce_is_purchasable', array( $this, 'purchasable' ), 20, 2 );
		add_filter( 'sidcraft_commerce_checkout_errors', array( $this, 'checkout_errors' ), 30, 2 );
		add_action( 'admin_post_sidrest_sold_out', array( $this, 'toggle_from_list' ) );
		add_action( 'save_post_' . Post_Types::SECTION, array( __CLASS__, 'forget' ) );
		add_action( 'save_post_' . Post_Types::MENU, array( __CLASS__, 'forget' ) );
		add_action( 'deleted_post', array( $this, 'forget_deleted' ), 10, 2 );
	}

	/*
	------------------------------------------------------------------
	 * Sold out today
	 * ----------------------------------------------------------------
	 */

	/**
	 * Is the item sold out today?
	 *
	 * @param int $item_id Item.
	 * @return bool
	 */
	public static function sold_out( $item_id ) {
		$until = (string) get_post_meta( (int) $item_id, self::SOLD_OUT, true );
		return '' !== $until && wp_date( 'Y-m-d' ) <= $until;
	}

	/**
	 * End of the sold-out period (Unix time), or 0.
	 *
	 * @param int $item_id Item.
	 * @return int
	 */
	public static function sold_out_until( $item_id ) {
		$until = (string) get_post_meta( (int) $item_id, self::SOLD_OUT, true );
		if ( '' === $until || wp_date( 'Y-m-d' ) > $until ) {
			return 0;
		}
		$day = date_create_immutable( $until . ' 23:59:59', wp_timezone() );
		return $day ? $day->getTimestamp() + 1 : 0;
	}

	/**
	 * Mark sold out for today (or back on sale).
	 *
	 * @param int  $item_id Item.
	 * @param bool $on      Sold out.
	 */
	public static function set_sold_out( $item_id, $on ) {
		if ( $on ) {
			update_post_meta( (int) $item_id, self::SOLD_OUT, wp_date( 'Y-m-d' ) );
		} else {
			delete_post_meta( (int) $item_id, self::SOLD_OUT );
		}
		// Menus are cached pages: their browser check catches up; the store's grids by product version.
		if ( class_exists( '\SidcraftCommerce\Data\Product' ) ) {
			\SidcraftCommerce\Data\Product::bump_query_version();
		}
		/**
		 * Fires when an item is marked sold out or back on sale.
		 *
		 * @param int  $item_id Item.
		 * @param bool $on      Sold out.
		 */
		do_action( 'sidrest_sold_out_changed', (int) $item_id, (bool) $on );
	}

	/**
	 * The Menu items list's "Sold out today" link.
	 */
	public function toggle_from_list() {
		$id = isset( $_GET['item'] ) ? absint( $_GET['item'] ) : 0;
		check_admin_referer( 'sidrest_sold_out_' . $id );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || Post_Types::ITEM !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'sidcraft-restaurant-for-sidcraft-commerce' ), 403 );
		}
		self::set_sold_out( $id, ! self::sold_out( $id ) );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . Post_Types::ITEM ) );
		exit;
	}

	/*
	------------------------------------------------------------------
	 * Times of day
	 * ----------------------------------------------------------------
	 */

	/**
	 * Times of a menu or section: [ days[], from, to ] or null (always).
	 *
	 * @param int $post_id Menu or section.
	 * @return array|null
	 */
	public static function times( $post_id ) {
		$t = get_post_meta( (int) $post_id, self::TIMES, true );
		if ( ! is_array( $t ) ) {
			return null;
		}
		$days = array_values( array_unique( array_map( 'intval', (array) ( $t['days'] ?? array() ) ) ) );
		$from = (string) ( $t['from'] ?? '' );
		$to   = (string) ( $t['to'] ?? '' );
		if ( ! $days && ( '' === $from || '' === $to ) ) {
			return null;
		}
		return array(
			'days' => $days,
			'from' => $from,
			'to'   => $to,
		);
	}

	/**
	 * Is a times rule open at $time?
	 *
	 * @param array|null $t    Rule.
	 * @param int        $time Unix time.
	 * @return bool
	 */
	public static function rule_open( $t, $time ) {
		if ( ! $t ) {
			return true;
		}
		$day   = (int) wp_date( 'w', $time );
		$clock = wp_date( 'H:i', $time );
		$on    = static function ( $d ) use ( $t ) {
			return ! $t['days'] || in_array( $d, $t['days'], true );
		};
		if ( '' === $t['from'] || '' === $t['to'] ) {
			return $on( $day );
		}
		if ( $t['from'] <= $t['to'] ) {
			return $on( $day ) && $clock >= $t['from'] && $clock < $t['to'];
		}
		// Past midnight: before "to" belongs to the evening before.
		if ( $clock >= $t['from'] ) {
			return $on( $day );
		}
		return $clock < $t['to'] && $on( ( $day + 6 ) % 7 );
	}

	/**
	 * Open intervals of a rule for the coming days (for the browser).
	 *
	 * @param array $t    Rule.
	 * @param int   $from Unix time.
	 * @param int   $days Days.
	 * @return int[][]
	 */
	public static function rule_intervals( array $t, $from, $days = 7 ) {
		$tz    = wp_timezone();
		$start = ( new \DateTimeImmutable( '@' . (int) $from ) )->setTimezone( $tz )->setTime( 0, 0 )->modify( '-1 day' );
		$parse = static function ( $hm ) {
			$p = explode( ':', (string) $hm );
			return (int) $p[0] * 60 + (int) ( $p[1] ?? 0 );
		};
		$a   = '' !== $t['from'] ? $parse( $t['from'] ) : 0;
		$b   = '' !== $t['to'] ? $parse( $t['to'] ) : 1440;
		$b   = $b <= $a ? $b + 1440 : $b;
		$out = array();
		for ( $i = 0; $i <= $days + 1; $i++ ) {
			$day = $start->modify( '+' . $i . ' day' );
			if ( $t['days'] && ! in_array( (int) $day->format( 'w' ), $t['days'], true ) ) {
				continue;
			}
			$out[] = array( $day->modify( '+' . $a . ' minutes' )->getTimestamp(), $day->modify( '+' . $b . ' minutes' )->getTimestamp() );
		}
		return $out;
	}

	/**
	 * "Available 7:00 am–11:00 am, Mon–Fri" for a rule.
	 *
	 * @param array|null $t Rule.
	 * @return string
	 */
	public static function rule_label( $t ) {
		if ( ! $t ) {
			return '';
		}
		global $wp_locale;
		$parts = array();
		if ( '' !== $t['from'] && '' !== $t['to'] ) {
			$fmt     = (string) get_option( 'time_format', 'g:i a' );
			$parts[] = gmdate( $fmt, strtotime( '1970-01-01 ' . $t['from'] . ' UTC' ) ) . '–' . gmdate( $fmt, strtotime( '1970-01-01 ' . $t['to'] . ' UTC' ) );
		}
		if ( $t['days'] && count( $t['days'] ) < 7 ) {
			$names = array();
			foreach ( $t['days'] as $d ) {
				$names[] = $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $d ) );
			}
			$parts[] = implode( ', ', $names );
		}
		/* translators: %s: times and days. */
		return sprintf( label( 'available_times' ), implode( ', ', $parts ) );
	}

	/** Option holding the map (autoloaded; small: restricted items only). */
	const MAP = 'sidrest_availability_map';

	/**
	 * The map for this request.
	 *
	 * @var array|null
	 */
	protected static $map = null;

	/**
	 * Items with serving times, and the rules of the places they appear:
	 * item id => [ [ section rule, [ menu rules ] ], … ]. Items that appear
	 * anywhere without times are always served and left out, so a site
	 * without serving times stores an empty map and reads nothing else.
	 *
	 * @return array
	 */
	protected static function map() {
		if ( null !== self::$map ) {
			return self::$map;
		}
		$stored = get_option( self::MAP, null );
		if ( is_array( $stored ) ) {
			self::$map = $stored;
			return self::$map;
		}
		self::$map = self::build_map();
		update_option( self::MAP, self::$map, true );
		return self::$map;
	}

	/**
	 * Work the map out from the published menus and sections.
	 *
	 * @return array
	 */
	protected static function build_map() {
		$query    = array(
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);
		$sections = get_posts( array( 'post_type' => Post_Types::SECTION ) + $query );
		$menus    = get_posts( array( 'post_type' => Post_Types::MENU ) + $query );
		update_meta_cache( 'post', array_merge( $sections, $menus ) );
		$menu_rules = array();
		foreach ( $menus as $menu_id ) {
			$rule = self::times( $menu_id );
			foreach ( array_map( 'intval', (array) get_post_meta( $menu_id, '_sidrest_sections', true ) ) as $sid ) {
				$menu_rules[ $sid ][] = $rule;
			}
		}
		$places = array();
		$always = array();
		foreach ( $sections as $sid ) {
			$rule  = self::times( $sid );
			$in    = $menu_rules[ $sid ] ?? array();
			$free  = ! $rule && ( ! $in || in_array( null, $in, true ) ); // Served whenever the restaurant is.
			foreach ( array_map( 'intval', (array) get_post_meta( $sid, '_sidrest_items', true ) ) as $item_id ) {
				if ( $free ) {
					$always[ $item_id ] = true;
				} else {
					$places[ $item_id ][] = array( $rule, $in );
				}
			}
		}
		return array_diff_key( $places, $always );
	}

	/**
	 * Drop the stored map (a menu or section changed).
	 */
	public static function forget() {
		self::$map = null;
		delete_option( self::MAP );
	}

	/**
	 * A menu or section was deleted.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    Post.
	 */
	public function forget_deleted( $post_id, $post = null ) {
		if ( $post instanceof \WP_Post && in_array( $post->post_type, array( Post_Types::SECTION, Post_Types::MENU ), true ) ) {
			self::forget();
		}
	}

	/**
	 * Is the item served at $time (by the times of its menus and sections)?
	 *
	 * @param int $item_id Item.
	 * @param int $time    Unix time.
	 * @return bool
	 */
	public static function served_at( $item_id, $time ) {
		$places = self::map()[ (int) $item_id ] ?? array();
		if ( ! $places ) {
			return true;
		}
		foreach ( $places as $place ) {
			if ( ! self::rule_open( $place[0], $time ) ) {
				continue;
			}
			if ( ! $place[1] ) {
				return true;
			}
			foreach ( $place[1] as $menu_rule ) {
				if ( self::rule_open( $menu_rule, $time ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Served at any time customers can order for (now, or a slot ahead)?
	 *
	 * @param int $item_id Item.
	 * @return bool
	 */
	public static function served_soon( $item_id ) {
		static $memo = array(); // Asked for every price and button on a page.
		$item_id = (int) $item_id;
		if ( isset( $memo[ $item_id ] ) ) {
			return $memo[ $item_id ];
		}
		$memo[ $item_id ] = self::served_at( $item_id, time() );
		if ( ! $memo[ $item_id ] && Hours::configured() && \SidcraftRestaurant\setting( 'order_ahead', 1 ) ) {
			foreach ( Schedule::slots( null, false ) as $slot ) {
				if ( self::served_at( $item_id, $slot[0] ) ) {
					$memo[ $item_id ] = true;
					break;
				}
			}
		}
		return $memo[ $item_id ];
	}

	/*
	------------------------------------------------------------------
	 * Store checks
	 * ----------------------------------------------------------------
	 */

	/**
	 * Sold-out items, and items not served at any orderable time, can't be bought.
	 *
	 * @param bool                                $ok      Purchasable.
	 * @param \SidcraftCommerce\Data\Product|null $product Product.
	 * @return bool
	 */
	public function purchasable( $ok, $product ) {
		if ( ! $ok || ! $product ) {
			return $ok;
		}
		$item = Ordering::item_for( $product );
		if ( ! $item ) {
			return $ok;
		}
		return ! self::sold_out( $item->get_id() ) && self::served_soon( $item->get_id() ) && Deals::available( $item->get_id() );
	}

	/**
	 * At checkout: each item must be served at the time it is wanted.
	 *
	 * @param array $errors  field => message.
	 * @param array $billing Checkout fields.
	 * @return array
	 */
	public function checkout_errors( $errors, $billing ) {
		$when = (string) ( $billing[ Schedule::FIELD ] ?? 'asap' );
		$time = time();
		if ( '' !== $when && 'asap' !== $when ) {
			$date = date_create_immutable( $when, wp_timezone() );
			$time = $date ? $date->getTimestamp() : $time;
		}
		foreach ( Cart::instance()->get_items() as $line ) {
			$item = Ordering::item_for( (int) $line['product_id'] );
			if ( $item && ! self::served_at( $item->get_id(), $time ) ) {
				/* translators: %s: item name. */
				$errors[ Schedule::FIELD ] = sprintf( __( '%s isn’t served at the time you chose. Choose another time or remove it.', 'sidcraft-restaurant-for-sidcraft-commerce' ), $item->get_name() );
				break;
			}
		}
		return $errors;
	}

	/**
	 * Data attributes for an item card: sold-out end and serving times.
	 *
	 * @param Item $item Item.
	 * @return string Attributes (escaped), starting with a space.
	 */
	public static function item_attrs( Item $item ) {
		$out   = '';
		$until = self::sold_out_until( $item->get_id() );
		if ( $until ) {
			$out .= ' data-sold-out-until="' . esc_attr( (string) $until ) . '"';
		}
		return $out;
	}

	/**
	 * Data attributes for a menu or section with times.
	 *
	 * @param int $post_id Menu or section.
	 * @return string
	 */
	public static function block_attrs( $post_id ) {
		$t = self::times( $post_id );
		if ( ! $t ) {
			return '';
		}
		return ' data-sidrest-times="' . esc_attr( (string) wp_json_encode( self::rule_intervals( $t, time() ) ) ) . '" data-sidrest-times-label="' . esc_attr( self::rule_label( $t ) ) . '"';
	}
}
