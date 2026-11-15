<?php
/**
 * Opening hours: weekly hours (one or more ranges a day, past midnight
 * allowed), closed dates and a "pause online ordering" switch.
 *
 * Settings: hours (weekday 0–6 => "11:00-14:30, 17:00-22:00"), holidays (one
 * date per line, Y-m-d), ordering_paused, closed_mode (later|refuse).
 * Nothing is limited while no hours are entered.
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant\Service;

use function SidcraftRestaurant\setting;

defined( 'ABSPATH' ) || exit;

/**
 * Class Hours
 */
class Hours {

	/**
	 * Are opening hours set at all?
	 *
	 * @return bool
	 */
	public static function configured() {
		foreach ( (array) setting( 'hours', array() ) as $text ) {
			if ( self::parse( (string) $text ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * "11:00-14:30, 17:00-2:00" → [ [ 660, 870 ], [ 1020, 1560 ] ] (minutes from midnight; an end past midnight is over 1440).
	 *
	 * @param string $text Ranges.
	 * @return int[][]
	 */
	public static function parse( $text ) {
		$out = array();
		foreach ( preg_split( '/[,;\n]+/', (string) $text ) as $range ) {
			if ( ! preg_match( '/^\s*(\d{1,2})[:.]?(\d{2})?\s*[-–]\s*(\d{1,2})[:.]?(\d{2})?\s*$/u', $range, $m ) ) {
				continue;
			}
			$from = min( 24, (int) $m[1] ) * 60 + min( 59, (int) ( $m[2] ?? 0 ) );
			$to   = min( 24, (int) $m[3] ) * 60 + min( 59, (int) ( $m[4] ?? 0 ) );
			if ( $to <= $from ) {
				$to += 1440; // Closes after midnight.
			}
			$out[] = array( $from, $to );
		}
		return $out;
	}

	/**
	 * Closed dates (Y-m-d).
	 *
	 * @return string[]
	 */
	public static function holidays() {
		preg_match_all( '/\d{4}-\d{2}-\d{2}/', (string) setting( 'holidays', '' ), $m );
		return array_values( array_unique( $m[0] ) );
	}

	/**
	 * Open intervals [ start, end ] (Unix times) from the day before $from for $days days.
	 *
	 * @param int $from Unix time.
	 * @param int $days Days to cover.
	 * @return int[][]
	 */
	public static function intervals( $from, $days = 8 ) {
		static $memo = array();
		$key = (int) floor( $from / 60 ) . ':' . $days;
		if ( isset( $memo[ $key ] ) ) {
			return $memo[ $key ];
		}
		$tz       = wp_timezone();
		$hours    = (array) setting( 'hours', array() );
		$closed   = self::holidays();
		$start    = ( new \DateTimeImmutable( '@' . (int) $from ) )->setTimezone( $tz )->setTime( 0, 0 )->modify( '-1 day' );
		$out      = array();
		for ( $i = 0; $i <= $days + 1; $i++ ) {
			$day  = $start->modify( '+' . $i . ' day' );
			$date = $day->format( 'Y-m-d' );
			if ( in_array( $date, $closed, true ) ) {
				continue;
			}
			foreach ( self::parse( (string) ( $hours[ (int) $day->format( 'w' ) ] ?? '' ) ) as $range ) {
				$out[] = array(
					$day->modify( '+' . $range[0] . ' minutes' )->getTimestamp(),
					$day->modify( '+' . $range[1] . ' minutes' )->getTimestamp(),
				);
			}
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $a[0] <=> $b[0];
			}
		);
		$memo[ $key ] = $out;
		return $out;
	}

	/**
	 * Open at this moment (by the hours; ignores the pause switch)?
	 *
	 * @param int|null $time Unix time (default now).
	 * @return bool
	 */
	public static function open_at( $time = null ) {
		$time = null === $time ? time() : (int) $time;
		if ( ! self::configured() ) {
			return true;
		}
		foreach ( self::intervals( $time, 1 ) as $range ) {
			if ( $time >= $range[0] && $time < $range[1] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Taking orders for right now (open and not paused)?
	 *
	 * @return bool
	 */
	public static function taking_orders_now() {
		return ! setting( 'ordering_paused', 0 ) && self::open_at();
	}

	/**
	 * Next opening time after $time, or 0 within the next week.
	 *
	 * @param int|null $time Unix time.
	 * @return int
	 */
	public static function next_open( $time = null ) {
		$time = null === $time ? time() : (int) $time;
		foreach ( self::intervals( $time, 8 ) as $range ) {
			if ( $range[0] > $time ) {
				return $range[0];
			}
		}
		return 0;
	}

	/**
	 * The interval open at $time (or null).
	 *
	 * @param int $time Unix time.
	 * @return int[]|null
	 */
	public static function interval_at( $time ) {
		foreach ( self::intervals( $time, 1 ) as $range ) {
			if ( $time >= $range[0] && $time < $range[1] ) {
				return $range;
			}
		}
		return null;
	}

	/**
	 * "Today 17:00", "Tomorrow 11:30", "Friday 11:30".
	 *
	 * @param int $time Unix time.
	 * @return string
	 */
	public static function day_time( $time ) {
		$tz    = wp_timezone();
		$day   = ( new \DateTimeImmutable( '@' . (int) $time ) )->setTimezone( $tz )->format( 'Y-m-d' );
		$today = ( new \DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' );
		$tomor = ( new \DateTimeImmutable( 'tomorrow', $tz ) )->format( 'Y-m-d' );
		$clock = wp_date( (string) get_option( 'time_format', 'g:i a' ), (int) $time );
		if ( $day === $today ) {
			/* translators: %s: time. */
			return sprintf( __( 'today at %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $clock );
		}
		if ( $day === $tomor ) {
			/* translators: %s: time. */
			return sprintf( __( 'tomorrow at %s', 'sidcraft-restaurant-for-sidcraft-commerce' ), $clock );
		}
		/* translators: 1: weekday, 2: time. */
		return sprintf( __( '%1$s at %2$s', 'sidcraft-restaurant-for-sidcraft-commerce' ), wp_date( 'l', (int) $time ), $clock );
	}

	/**
	 * Data for the menu's "open / closed" line, worked out in the browser so
	 * cached pages stay right: the open intervals of the coming week and the
	 * messages.
	 *
	 * @return array|null Null when no hours are set.
	 */
	public static function for_script() {
		if ( ! self::configured() || ! setting( 'hours_banner', 1 ) ) {
			return null;
		}
		$now = time();
		$out = array();
		foreach ( self::intervals( $now, 7 ) as $range ) {
			if ( $range[1] > $now ) {
				$out[] = array( $range[0], $range[1], self::day_time( $range[0] ) );
			}
		}
		return array(
			'open'    => $out,
			'paused'  => (bool) setting( 'ordering_paused', 0 ),
			/* translators: %s: "today at 5:00 pm". */
			'closed'  => __( 'We’re closed right now. We open %s.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'later'   => 'later' === setting( 'closed_mode', 'later' ) ? __( 'You can still order now for later.', 'sidcraft-restaurant-for-sidcraft-commerce' ) : '',
			'closing' => __( 'Open now', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'pausedT' => __( 'We’re not taking online orders right now.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'shut'    => __( 'We’re closed right now.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		);
	}

	/**
	 * The week's hours as rows for a "Opening hours" list: [ weekday name, text ].
	 *
	 * @return array[]
	 */
	public static function week_rows() {
		global $wp_locale;
		$hours = (array) setting( 'hours', array() );
		$start = (int) get_option( 'start_of_week', 1 );
		$rows  = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$d      = ( $start + $i ) % 7;
			$ranges = array();
			foreach ( self::parse( (string) ( $hours[ $d ] ?? '' ) ) as $r ) {
				$ranges[] = self::clock( $r[0] ) . '–' . self::clock( $r[1] );
			}
			$rows[] = array( $wp_locale->get_weekday( $d ), $ranges ? implode( ', ', $ranges ) : __( 'Closed', 'sidcraft-restaurant-for-sidcraft-commerce' ) );
		}
		return $rows;
	}

	/**
	 * Minutes from midnight as a clock time in the site's format.
	 *
	 * @param int $minutes Minutes (may pass 1440).
	 * @return string
	 */
	public static function clock( $minutes ) {
		$minutes = (int) $minutes % 1440;
		return gmdate( (string) get_option( 'time_format', 'g:i a' ), $minutes * 60 );
	}
}
