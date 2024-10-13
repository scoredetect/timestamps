<?php
/**
 * SDCOM_Timestamps CLI Utility
 *
 * @since 1.8.0
 * @package SDCOM_Timestamps
 */

namespace SDCOM_Timestamps\Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Utility class for WP CLI commands.
 */
class Utility {

	/**
	 * Internal timer.
	 *
	 * @var float
	 */
	protected static $time_start = null;

	/**
	 * Stops the timer.
	 *
	 * @param int $precision The number of digits from the right of the decimal to display. Default 3.
	 * @return float Time spent so far
	 */
	public static function timer_stop( $precision = 3 ) {
		$diff = microtime( true ) - self::$time_start;
		return (float) number_format( (float) $diff, $precision );
	}

	/**
	 * Starts the timer.
	 *
	 * @return true
	 */
	public static function timer_start() {
		self::$time_start = microtime( true );
		return true;
	}

	/**
	 * Given a timestamp in microseconds, returns it in the given format.
	 *
	 * @param float  $microtime Unix timestamp in ms
	 * @param string $format    Desired format
	 * @return string
	 */
	public static function timer_format( $microtime, $format = 'H:i:s.u' ) {
		$microtime_date = \DateTime::createFromFormat( 'U.u', number_format( (float) $microtime, 3, '.', '' ) );
		return $microtime_date->format( $format );
	}
}
