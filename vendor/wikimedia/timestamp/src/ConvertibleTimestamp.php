<?php
/**
 * Timestamp
 *
 * Copyright (C) 2012 Tyler Romeo <tylerromeo@gmail.com>
 *
 * @license GPL-2.0-or-later
 * @file
 * @author Tyler Romeo <tylerromeo@gmail.com>
 */

namespace Wikimedia\Timestamp;

use DateInterval;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use ValueError;

/**
 * Library for creating, parsing, and converting timestamps.
 */
class ConvertibleTimestamp {
	/**
	 * Standard gmdate() formats for the different timestamp types.
	 */
	private function formatFor( TimestampFormat $f ): string {
		return match ( $f ) {
			TimestampFormat::UNIX => 'U',
			TimestampFormat::MW => 'YmdHis',
			TimestampFormat::DB => 'Y-m-d H:i:s',
			TimestampFormat::ISO_8601 => 'Y-m-d\TH:i:s\Z',
			TimestampFormat::ISO_8601_BASIC => 'Ymd\THis\Z',
			// This shouldn't ever be used, but is included for completeness
			TimestampFormat::EXIF => 'Y:m:d H:i:s',
			TimestampFormat::RFC2822 => 'D, d M Y H:i:s',
			// Was 'd-M-y h.i.s A' . ' +00:00' before r51500
			TimestampFormat::ORACLE => 'd-m-Y H:i:s.u',
			// Formerly 'Y-m-d H:i:s' . ' GMT'
			TimestampFormat::POSTGRES => 'Y-m-d H:i:s+00',
			TimestampFormat::UNIX_MICRO => 'U.u',
		};
	}

	/**
	 * Regexes for setTimestamp(). Named capture groups correspond to format codes for
	 * DateTime::createFromFormat(). Unnamed groups are ignored.
	 */
	private const REGEXES = [
		// 'TS_DB' => subset of TS_ISO_8601 (with no 'T')
		'TS_MW' => '/^(?<Y>\d{4})(?<m>\d\d)(?<d>\d\d)(?<H>\d\d)(?<i>\d\d)(?<s>\d\d)$/D',
		'TS_UNIX' => '/^(?<U>-?\d{1,13})$/D',
		'TS_ISO_8601' =>
			'/^(?<Y>\d{4})-(?<m>\d{2})-(?<d>\d{2})[T ]' .
				'(?<H>\d{2}):(?<i>\d{2}):(?<s>\d{2})(?:[.,](?<u>\d{1,6}))?' .
				'(?<O>Z|[+\-]\d{2}(?::?\d{2})?)?$/',
		'TS_ISO_8601_BASIC' =>
			'/^(?<Y>\d{4})(?<m>\d{2})(?<d>\d{2})T(?<H>\d{2})(?<i>\d{2})(?<s>\d{2})(?:[.,](?<u>\d{1,6}))?' .
				'(?<O>Z|[+\-]\d{2}(?::?\d{2})?)?$/',
		'TS_UNIX_MICRO' => '/^(?<U>-?\d{1,13})\.(?<u>\d{1,6})$/D',
		'TS_ORACLE' =>
			'/^(?<d>\d{2})-(?<m>\d{2})-(?<Y>\d{4}) (?<H>\d{2}):(?<i>\d{2}):(?<s>\d{2})\.(?<u>\d{6})$/',
		// TS_POSTGRES is almost redundant to TS_ISO_8601 (with no 'T'), but accepts a space in place of
		// a `+` before the timezone.
		'TS_POSTGRES' =>
			'/^(?<Y>\d{4})-(?<m>\d\d)-(?<d>\d\d) (?<H>\d\d):(?<i>\d\d):(?<s>\d\d)(?:\.(?<u>\d{1,6}))?' .
				'(?<O>[\+\- ]\d\d)$/',
		'old TS_POSTGRES' =>
			'/^(?<Y>\d{4})-(?<m>\d\d)-(?<d>\d\d) (?<H>\d\d):(?<i>\d\d):(?<s>\d\d)(?:\.(?<u>\d{1,6}))? GMT$/',
		'TS_EXIF' => '/^(?<Y>\d{4}):(?<m>\d\d):(?<d>\d\d) (?<H>\d\d):(?<i>\d\d):(?<s>\d\d)$/D',

		'TS_RFC2822' =>
			# Day of the week
			'/^[ \t\r\n]*(?:(?<D>[A-Z][a-z]{2}),[ \t\r\n]*)?' .
			# dd Mon yyyy
			'(?<d>\d\d?)[ \t\r\n]+(?<M>[A-Z][a-z]{2})[ \t\r\n]+(?<Y>\d{2,})' .
			# hh:mm:ss
			'[ \t\r\n]+(?<H>\d\d)[ \t\r\n]*:[ \t\r\n]*(?<i>\d\d)[ \t\r\n]*:[ \t\r\n]*(?<s>\d\d)' .
			# zone, optional for hysterical raisins
			'(?:[ \t\r\n]+(?<O>[+-]\d{4}|UT|GMT|[ECMP][SD]T|[A-IK-Za-ik-z]))?' .
			# optional trailing comment
			# See http://www.squid-cache.org/mail-archive/squid-users/200307/0122.html / r77171
			'(?:[ \t\r\n]*;|$)/S',

		'TS_RFC850' =>
			'/^(?<D>[A-Z][a-z]{5,8}), (?<d>\d\d)-(?<M>[A-Z][a-z]{2})-(?<y>\d{2}) ' .
			'(?<H>\d\d):(?<i>\d\d):(?<s>\d\d)' .
			# timezone optional for hysterical raisins. RFC just says "worldwide time zone abbreviations".
			# https://en.wikipedia.org/wiki/List_of_time_zone_abbreviations lists strings of up to 5
			# uppercase letters. PHP 7.2's DateTimeZone::listAbbreviations() lists strings of up to 4
			# letters.
			'(?: (?<O>[+\-]\d{2}(?::?\d{2})?|[A-Z]{1,5}))?$/',

		'asctime' => '/^(?<D>[A-Z][a-z]{2}) (?<M>[A-Z][a-z]{2}) +(?<d>\d{1,2}) ' .
			'(?<H>\d\d):(?<i>\d\d):(?<s>\d\d) (?<Y>\d{4})\s*$/',
	];

	/**
	 * @var callback|null
	 * @see setFakeTime()
	 */
	protected static $fakeTimeCallback = null;

	/**
	 * Get the current time in the same form that PHP's built-in time() function uses.
	 *
	 * This is used by now() through setTimestamp( false ) instead of the built in time() function.
	 * The output of this method can be overwritten for testing purposes by calling setFakeTime().
	 *
	 * @return int UNIX epoch
	 */
	public static function time() {
		return static::$fakeTimeCallback ? (int)( static::$fakeTimeCallback )() : \time();
	}

	/**
	 * Get the value of a monotonic clock in nanoseconds.
	 *
	 * This is equivalent to calling PHP's built-in `hrtime(true)`. Repeated calls to
	 * hrtime() are guruanteed to be equal to or higher than the previous call. It is
	 * designed to measure time between two calls, and is not affected by clock
	 * drift correction, and thus does not represent any kind of date or timestamp.
	 *
	 * The output of this method can be controlled for testing purposes by calling
	 * setFakeTime() with any value. In that case, hrtime() will always increment
	 * its arbitrary starting amount by 1 millisecond every time the function is
	 * called.
	 *
	 * @see https://www.php.net/hrtime
	 * @return int|float Monotonic nanoseconds since an arbitrary starting point
	 */
	public static function hrtime() {
		static $fakeNanoMono = 41_999_000_000;

		if ( static::$fakeTimeCallback ) {
			// Use a fake start. We ignore the actual fake time because:
			// 1. Using it would make it easy to mistakenly use it as a timestamp.
			// 2. Multiplying time() by 1e9 for nanoseconds since 1970 is larger than
			//    fits in float precision, thus causing all kinds of accuracy problems.
			//
			// We add 1 millisecond each time this method is called, so that it is always
			// increasing, and ensures `b - a` gives a deterministic end result that can
			// be strictly asserted in a unit test.
			$fakeNanoMono += 1_000_000;
			return $fakeNanoMono;
		} else {
			return hrtime( true );
		}
	}

	/**
	 * Set a fake time value or clock callback.
	 *
	 * @param callable|ConvertibleTimestamp|string|int|false $fakeTime a fixed time given as a string,
	 *   or as a number representing seconds since the UNIX epoch; or a callback that returns an int.
	 *   or false to disable fake time and go back to real time.
	 * @param int|float $step The number of seconds by which to increment the clock each time
	 *   the time() method is called. Must not be smaller than zero.
	 *   Ignored if $fakeTime is a callback or false.
	 *
	 * @return callable|null the previous fake time callback, if any.
	 *
	 * @phan-param callable():int|ConvertibleTimestamp|string|int|false $fakeTime
	 */
	public static function setFakeTime( $fakeTime, $step = 0 ) {
		if ( $fakeTime instanceof ConvertibleTimestamp ) {
			$fakeTime = (int)$fakeTime->getTimestamp();
		}

		if ( is_string( $fakeTime ) ) {
			$fakeTime = (int)static::convert( TimestampFormat::UNIX, $fakeTime );
		}

		if ( is_int( $fakeTime ) ) {
			$clock = $fakeTime;
			$fakeTime = static function () use ( &$clock, $step ) {
				$t = $clock;
				$clock += $step;
				return (int)$t;
			};
		}

		if ( $fakeTime && !is_callable( $fakeTime ) ) {
			throw new InvalidArgumentException( 'Bad fake time' );
		}

		$old = static::$fakeTimeCallback;
		static::$fakeTimeCallback = $fakeTime ?: null;
		return $old;
	}

	/**
	 * Get a PSR-20 clock that respects setFakeTime()
	 */
	public static function getClock(): ClockInterface {
		return new Clock();
	}

	/**
	 * The actual timestamp being wrapped (DateTime object).
	 * @var DateTime
	 */
	public $timestamp;

	/**
	 * Make a new timestamp and set it to the specified time,
	 * or the current time if unspecified.
	 *
	 * @param string|int|float|null|false|DateTimeInterface $timestamp Timestamp to set.
	 *   If any falsy value is provided, the timestamp uses the current time instead.
	 * @throws TimestampException
	 */
	public function __construct( $timestamp = false ) {
		if ( $timestamp instanceof DateTimeInterface ) {
			$this->timestamp = $timestamp instanceof DateTime
				? $timestamp
				: DateTime::createFromInterface( $timestamp );
		} else {
			$this->setTimestamp( $timestamp );
		}
	}

	/**
	 * Set the timestamp to the specified time, or the current time if unspecified.
	 *
	 * Parse the given timestamp into either a DateTime object or a Unix timestamp,
	 * and then store it.
	 *
	 * @param string|int|float|null|false $ts Timestamp to store.
	 *   If any falsy value is provided, the timestamp uses the current time instead.
	 * @throws TimestampException
	 */
	public function setTimestamp( $ts = false ) {
		$format = null;
		$strtime = '';

		// We want to catch 0, '', null... but not date strings starting with a letter.
		if ( !$ts || $ts === "\0\0\0\0\0\0\0\0\0\0\0\0\0\0" ) {
			$strtime = (string)self::time();
			$format = 'U';
		} else {
			foreach ( self::REGEXES as $name => $regex ) {
				if ( !preg_match( $regex, $ts, $m ) ) {
					continue;
				}

				// Apply RFC 2626 § 11.2 rules for fixing a 2-digit year.
				// We apply by year as written, without regard for
				// offset within the year or timezone of the input date.
				if ( isset( $m['y'] ) ) {
					$pivot = (int)gmdate( 'Y', static::time() ) + 50;
					$m['Y'] = $pivot - ( $pivot % 100 ) + (int)$m['y'];
					if ( $m['Y'] > $pivot ) {
						$m['Y'] -= 100;
					}
					unset( $m['y'] );
				}

				// TS_POSTGRES's match for 'O' can begin with a space, which PHP doesn't accept
				if ( $name === 'TS_POSTGRES' && isset( $m['O'] ) && $m['O'][0] === ' ' ) {
					$m['O'][0] = '+';
				}

				if ( $name === 'TS_RFC2822' ) {
					// RFC 2822 rules for two- and three-digit years
					if ( $m['Y'] < 1000 ) {
						$m['Y'] += $m['Y'] < 50 ? 2000 : 1900;
					}

					// TS_RFC2822 timezone fixups
					if ( isset( $m['O'] ) ) {
						// obs-zone value not recognized by PHP
						if ( $m['O'] === 'UT' ) {
							$m['O'] = 'UTC';
						}

						// RFC 2822 says all these should be treated as +0000 due to an error in RFC 822
						if ( strlen( $m['O'] ) === 1 ) {
							$m['O'] = '+0000';
						}
					}
				}

				if ( $name === 'TS_UNIX_MICRO' && $m['U'] < 0 && $m['u'] > 0 ) {
					// createFromFormat()'s componentwise behavior is counterintuitive in this case, "-1.2" gets
					// interpreted as "-1 seconds + 200000 microseconds = -0.8 seconds" rather than as a decimal
					// "-1.2 seconds" like we want. So correct the values to match the componentwise
					// interpretation.
					$m['U']--;
					$m['u'] = 1000000 - (int)str_pad( $m['u'], 6, '0' );
				}

				$formatFiltered = [];
				$strtimeFiltered = [];
				foreach ( $m as $k => $v ) {
					if ( !is_int( $k ) && $v !== '' ) {
						$formatFiltered[] = $k;
						$strtimeFiltered[] = $v;
					}
				}
				$format = implode( ' ', $formatFiltered );
				$strtime = implode( ' ', $strtimeFiltered );

				break;
			}
		}

		if ( $format === null ) {
			throw new TimestampException( __METHOD__ . ": Invalid timestamp - $ts" );
		}

		try {
			$final = DateTime::createFromFormat( "!$format", $strtime, new DateTimeZone( 'UTC' ) );
		} catch ( ValueError $e ) {
			throw new TimestampException( __METHOD__ . ': Invalid timestamp format.', $e->getCode(), $e );
		}

		if ( $final === false ) {
			throw new TimestampException( __METHOD__ . ': Invalid timestamp format.' );
		}

		$this->timestamp = $final;
	}

	/**
	 * Converts any timestamp to the given string format.
	 * This is identical to `( new ConvertibleTimestamp() )->getTimestamp()`,
	 * except it returns false instead of throwing an exception.
	 *
	 * @param TimestampFormat|int $style Constant Output format for timestamp
	 * @param string|int|float|null|false|DateTime $ts Timestamp
	 * @return string|false Formatted timestamp or false on failure
	 */
	public static function convert( $style, $ts ) {
		try {
			$ct = new static( $ts );
			return $ct->getTimestamp( $style );
		} catch ( TimestampException ) {
			return false;
		}
	}

	/**
	 * Get the current time in the given format
	 *
	 * @param TimestampFormat|int $style Constant Output format for timestamp
	 * @return string
	 */
	public static function now( $style = TimestampFormat::MW ) {
		return static::convert( $style, false );
	}

	/**
	 * Get the timestamp represented by this object in a certain form.
	 *
	 * Convert the internal timestamp to the specified format and then
	 * return it.
	 *
	 * @param TimestampFormat|int $style Constant Output format for timestamp
	 * @throws TimestampException
	 * @return string The formatted timestamp
	 */
	public function getTimestamp( $style = TimestampFormat::UNIX ) {
		if ( is_int( $style ) ) {
			$style = TimestampFormat::tryFrom( $style );
		}
		if ( !( $style instanceof TimestampFormat ) ) {
			throw new InvalidArgumentException( __METHOD__ . ': Illegal timestamp output type.' );
		}

		// All our formats are in UTC, so make sure to use that timezone
		$timestamp = clone $this->timestamp;
		$timestamp->setTimezone( new DateTimeZone( 'UTC' ) );

		if ( $style === TimestampFormat::UNIX_MICRO ) {
			$seconds = (int)$timestamp->format( 'U' );
			$microseconds = (int)$timestamp->format( 'u' );
			if ( $seconds < 0 && $microseconds > 0 ) {
				// Adjust components to properly create a decimal number for TS_UNIX_MICRO and negative
				// timestamps. See the comment in setTimestamp() for details.
				$seconds++;
				$microseconds = 1000000 - $microseconds;
			}
			return sprintf( "%d.%06d", $seconds, $microseconds );
		}

		$output = $timestamp->format( self::formatFor( $style ) );

		if ( $style == TimestampFormat::RFC2822 ) {
			$output .= ' GMT';
		}

		if ( $style == TimestampFormat::MW && strlen( $output ) !== 14 ) {
			throw new TimestampException( __METHOD__ . ': The timestamp cannot be represented in ' .
				'the specified format' );
		}

		return $output;
	}

	/**
	 * @return string
	 * @throws TimestampException
	 */
	public function __toString() {
		return $this->getTimestamp();
	}

	/**
	 * Calculate the difference between two ConvertibleTimestamp objects.
	 *
	 * @param ConvertibleTimestamp $relativeTo Base time to calculate difference from
	 * @return DateInterval The DateInterval object representing the
	 *   difference between the two dates
	 */
	public function diff( ConvertibleTimestamp $relativeTo ) {
		return $this->timestamp->diff( $relativeTo->timestamp );
	}

	/**
	 * Add an interval to the timestamp.
	 * @param DateInterval|string $interval DateInterval or DateInterval specification (such as "P2D")
	 * @return $this
	 * @throws TimestampException
	 */
	public function add( $interval ) {
		if ( is_string( $interval ) ) {
			try {
				$interval = new DateInterval( $interval );
			} catch ( Exception $e ) {
				throw new TimestampException( __METHOD__ . ': Invalid interval.', $e->getCode(), $e );
			}
		}
		$this->timestamp->add( $interval );
		return $this;
	}

	/**
	 * Subtract an interval from the timestamp.
	 * @param DateInterval|string $interval DateInterval or DateInterval specification (such as "P2D")
	 * @return $this
	 * @throws TimestampException
	 */
	public function sub( $interval ) {
		if ( is_string( $interval ) ) {
			try {
				$interval = new DateInterval( $interval );
			} catch ( Exception $e ) {
				throw new TimestampException( __METHOD__ . ': Invalid interval.', $e->getCode(), $e );
			}
		}
		$this->timestamp->sub( $interval );
		return $this;
	}

	/**
	 * Set the timezone of this timestamp to the specified timezone.
	 *
	 * @param string $timezone Timezone to set
	 * @throws TimestampException
	 */
	public function setTimezone( $timezone ) {
		try {
			$this->timestamp->setTimezone( new DateTimeZone( $timezone ) );
		} catch ( Exception $e ) {
			throw new TimestampException( __METHOD__ . ': Invalid timezone.', $e->getCode(), $e );
		}
	}

	/**
	 * Get the timezone of this timestamp.
	 *
	 * @return DateTimeZone The timezone
	 */
	public function getTimezone() {
		return $this->timestamp->getTimezone();
	}

	/**
	 * Format the timestamp in a given format.
	 *
	 * @param string $format Pattern to format in
	 * @return string The formatted timestamp
	 */
	public function format( $format ) {
		return $this->timestamp->format( $format );
	}
}
