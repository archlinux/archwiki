<?php
/**
 * @license GPL-2.0-or-later
 * @file
 * @author Antoine Musso "<hashar at free dot fr>"
 */

namespace Wikimedia;

use InvalidArgumentException;

/**
 * Play with IP addresses and IP ranges.
 */
class IPUtils {

	/**
	 * An IPv4 address is made of 4 bytes from x00 to xFF which is d0 to d255
	 */
	public const RE_IP_BYTE = '(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|0?[0-9]?[0-9])';

	private const RE_IP_ADD = self::RE_IP_BYTE . '\.' . self::RE_IP_BYTE . '\.'
		. self::RE_IP_BYTE . '\.' . self::RE_IP_BYTE;
	/**
	 * An IPv4 range is an IP address and a prefix (d0 to d32)
	 */
	private const RE_IP_PREFIX = '(3[0-2]|[12][0-9]|[0-9])';

	private const RE_IP_RANGE = '(' . self::RE_IP_ADD . '\/' . self::RE_IP_PREFIX . '|'
		. self::RE_IP_ADD . ' ?\- ?' . self::RE_IP_ADD . ')';

	/**
	 * An IPv6 address is made up of 8 words (each x0000 to xFFFF).
	 * However, the "::" abbreviation can be used on consecutive x0000 words.
	 */
	private const RE_IPV6_WORD = '([0-9A-Fa-f]{1,4})';
	/**
	 * An IPv6 range is an IP address and a prefix (d0 to d128)
	 */
	private const RE_IPV6_PREFIX = '(12[0-8]|1[01][0-9]|[1-9][0-9]|[0-9])';

	private const RE_IPV6_ADD =
		'(?:' .
			// starts with "::" (including "::")
			':(?::|(?::' . self::RE_IPV6_WORD . '){1,7})' .
		'|' .
			// ends with "::" (except "::")
			self::RE_IPV6_WORD . '(?::' . self::RE_IPV6_WORD . '){0,6}::' .
		'|' .
			// contains one "::" in the middle (the ^ makes the test fail if none found)
			self::RE_IPV6_WORD . '(?::((?(-1)|:))?' . self::RE_IPV6_WORD . '){1,6}(?(-2)|^)' .
		'|' .
			// contains no "::"
			self::RE_IPV6_WORD . '(?::' . self::RE_IPV6_WORD . '){7}' .
		')';
	/**
	 * An IPv6 range is an IP address and a prefix (d1 to d128)
	 */
	private const RE_IPV6_RANGE = '(' . self::RE_IPV6_ADD . '\/' . self::RE_IPV6_PREFIX
		. '|' . self::RE_IPV6_ADD . ' ?\- ?' . self::RE_IPV6_ADD . ')';
	/**
	 * For IPv6 canonicalization (NOT for strict validation; these are quite lax!)
	 */
	public const RE_IPV6_GAP = ':(?:0+:)*(?::(?:0+:)*)?';

	private const RE_IPV6_V4_PREFIX = '0*' . self::RE_IPV6_GAP . '(?:ffff:)?';

	/**
	 * Maximum number of IP addresses that can be retrieved from a given range.
	 */
	private const MAXIMUM_IPS_FROM_RANGE = 2 ** 16;

	/**
	 * Determine if a string is a valid IP address or network (CIDR prefix).
	 *
	 * SIIT IPv4-translated addresses are rejected.
	 *
	 * @note canonicalize() tries to convert translated addresses to IPv4.
	 *
	 * @param string $ip Possible IP address
	 * @return bool
	 */
	public static function isIPAddress( $ip ) {
		return self::isIPv4( $ip ) || self::isIPv6( $ip );
	}

	/**
	 * Given a string, determine if it is a valid IP address in IPv6 only.
	 *
	 * @note Unlike isValid(), this looks for networks too.
	 *
	 * @param string $ip Possible IP address
	 * @return bool
	 */
	public static function isIPv6( $ip ) {
		return (bool)preg_match( '/^' . self::RE_IPV6_ADD . '(?:\/' . self::RE_IPV6_PREFIX . ')?$/', $ip );
	}

	/**
	 * Given a string, determine if it is a valid IP address in IPv4 only.
	 *
	 * @note Unlike isValid(), this looks for networks too.
	 *
	 * @param string $ip Possible IP address
	 * @return bool
	 */
	public static function isIPv4( $ip ) {
		return (bool)preg_match( '/^' . self::RE_IP_ADD . '(?:\/' . self::RE_IP_PREFIX . ')?$/', $ip );
	}

	/**
	 * Validate an IP address. Ranges are NOT considered valid.
	 *
	 * SIIT IPv4-translated addresses are rejected.
	 *
	 * @note canonicalize() tries to convert translated addresses to IPv4.
	 *
	 * @param string $ip
	 * @return bool True if it is valid
	 */
	public static function isValid( $ip ) {
		// Test IPv4 before IPv6 as it's more common.
		return self::isValidIPv4( $ip ) || self::isValidIPv6( $ip );
	}

	/**
	 * Validate an IPv4 address. Ranges are NOT considered valid.
	 *
	 * @param string $ip
	 * @return bool True if it is valid
	 */
	public static function isValidIPv4( $ip ) {
		return (bool)preg_match( '/^' . self::RE_IP_ADD . '$/', $ip );
	}

	/**
	 * Validate an IPv6 address. Ranges are NOT considered valid.
	 *
	 * SIIT IPv4-translated addresses are rejected.
	 *
	 * @note canonicalize() tries to convert translated addresses to IPv4.
	 *
	 * @param string $ip
	 * @return bool True if it is valid
	 */
	public static function isValidIPv6( $ip ) {
		return (bool)preg_match( '/^' . self::RE_IPV6_ADD . '$/', $ip );
	}

	/**
	 * Validate an IPv4 range or single address.
	 *
	 * @param string $ipRange Single address, or CIDR range like "198.18.0.0/16", or
	 *  explicit range like "198.18.0.0-198.18.255.255".
	 * @return bool True if input is valid
	 */
	private static function isValidIPv4Range( $ipRange ) {
		return (bool)preg_match( '/^' . self::RE_IP_RANGE . '$/', $ipRange );
	}

	/**
	 * Validate an IPv6 range or single address.
	 *
	 * @param string $ipRange Single address, or CIDR range like "2001:db8:1:2::/64", or
	 *  explicit range like "2001:DB8:85A3:0:0:8A2E:370:7334-2001:DB8:85A3:8A2E:370:7334:0:0".
	 * @return bool True if input is valid
	 */
	private static function isValidIPv6Range( $ipRange ) {
		return (bool)preg_match( '/^' . self::RE_IPV6_RANGE . '$/', $ipRange );
	}

	/**
	 * Validate an IPv4 or IPv6 range or single address.
	 *
	 * This accepts:
	 * - CIDR range like "198.18.0.0/16" or "2001:db8:1:2::/64"
	 * - explicit range like "198.18.0.0-198.18.255.255" or
	 *   "2001:DB8:85A3:0:0:8A2E:370:7334-2001:DB8:85A3:8A2E:370:7334:0:0"
	 * - single address
	 *
	 * Note that SIIT IPv4-translated addresses are rejected.
	 *
	 * @note canonicalize() tries to convert translated addresses to IPv4.
	 *
	 * @param string $ipRange
	 * @return bool True if it is valid
	 */
	public static function isValidRange( $ipRange ) {
		// Optimization: Check IPv4 before IPv6 as it's more common.
		return self::isValidIPv4Range( $ipRange ) || self::isValidIPv6Range( $ipRange );
	}

	/**
	 * Convert an IPv4 address into a verbose, uppercase, normalized form.
	 * IPv4 addresses have leading zeros, in each octet, removed.
	 *
	 * @param string $ip IPv4 address in quad or octet form (CIDR or not).
	 * @return string|null
	 */
	private static function sanitizeIPv4( $ip ) {
		$ip = trim( $ip );
		if ( $ip === '' ) {
			return null;
		}
		// Remove leading 0's from an octet representation of IPv4 address
		return preg_replace( '!(?:^|(?<=\.))0+(?=[1-9]|0[./]|0$)!', '', $ip );
	}

	/**
	 * Convert an IPv6 address into a verbose, uppercase, normalized form.
	 * IPv6 addresses are expanded to 8 words.
	 *
	 * @param string $ip IP address in quad or octet form (CIDR or not).
	 * @return string|null
	 */
	private static function sanitizeIPv6( $ip ) {
		$ip = trim( $ip );
		if ( $ip === '' ) {
			return null;
		}
		// Remove any whitespaces, convert characters to uppercase
		$ip = strtoupper( $ip );
		// Expand zero abbreviations
		$abbrevPos = strpos( $ip, '::' );
		if ( $abbrevPos !== false ) {
			// We know this is valid IPv6. Find the last index of the
			// address before any CIDR number (e.g. "a:b:c::/24").
			$CIDRStart = strpos( $ip, "/" );
			$addressEnd = ( $CIDRStart !== false )
				? $CIDRStart - 1
				: strlen( $ip ) - 1;
			// If the '::' is at the beginning...
			if ( $abbrevPos === 0 ) {
				$repeat = '0:';
				// for the address '::'
				$extra = $ip === '::' ? '0' : '';
				// 7+2 (due to '::')
				$pad = 9;
			// If the '::' is at the end...
			} elseif ( $abbrevPos === $addressEnd - 1 ) {
				$repeat = ':0';
				$extra = '';
				// 7+2 (due to '::')
				$pad = 9;
			// If the '::' is in the middle...
			} else {
				$repeat = ':0';
				$extra = ':';
				// 6+2 (due to '::')
				$pad = 8;
			}
			$ip = str_replace( '::',
				str_repeat( $repeat, $pad - substr_count( $ip, ':' ) ) . $extra,
				$ip
			);
		}
		// Remove leading zeros from each bloc as needed
		return preg_replace( '/(^|:)0+(' . self::RE_IPV6_WORD . ')/', '$1$2', $ip );
	}

	/**
	 * Convert an IP into a verbose, uppercase, normalized form.
	 * Both IPv4 and IPv6 addresses are trimmed. Additionally,
	 * IPv6 addresses in octet notation are expanded to 8 words;
	 * IPv4 addresses have leading zeros, in each octet, removed.
	 *
	 * @param string $ip IP address in quad or octet form (CIDR or not).
	 * @return string|null
	 */
	public static function sanitizeIP( $ip ) {
		if ( self::isIPv4( $ip ) ) {
			return self::sanitizeIPv4( $ip );
		}
		if ( self::isIPv6( $ip ) ) {
			return self::sanitizeIPv6( $ip );
		}
		// Compat: Returns null if empty/whitespace, or original (trimmed)
		// input if not a valid IP.
		$ip = trim( $ip );
		if ( $ip === '' ) {
			return null;
		}
		return $ip;
	}

	/**
	 * Prettify an IP for display to end users.
	 *
	 * This will make it more compact and lower-case.
	 *
	 * @param string $ip
	 * @return string|null
	 */
	public static function prettifyIP( $ip ) {
		// normalize (removes '::')
		$ip = self::sanitizeIP( $ip );
		if ( $ip === null ) {
			return null;
		}
		if ( self::isIPv6( $ip ) ) {
			// Split IP into an address and a CIDR
			if ( str_contains( $ip, '/' ) ) {
				[ $ip, $cidr ] = explode( '/', $ip, 2 );
			} else {
				[ $ip, $cidr ] = [ $ip, '' ];
			}
			// Get the largest slice of words with multiple zeros
			$offset = 0;
			$longest = $longestPos = false;
			while ( preg_match(
				'!(?:^|:)0(?::0)+(?:$|:)!', $ip, $m, PREG_OFFSET_CAPTURE, $offset
			) ) {
				// full match
				[ $match, $pos ] = $m[0];
				if ( strlen( (string)$match ) > strlen( (string)$longest ) ) {
					$longest = $match;
					$longestPos = $pos;
				}
				// advance
				$offset = $pos + strlen( $match );
			}
			if ( $longest !== false ) {
				// Replace this portion of the string with the '::' abbreviation
				$ip = substr_replace( $ip, '::', $longestPos, strlen( $longest ) );
			}
			// Add any CIDR back on
			if ( $cidr !== '' ) {
				$ip = "{$ip}/{$cidr}";
			}
			// Convert to lower case to make it more readable
			$ip = strtolower( $ip );
		}

		return $ip;
	}

	/**
	 * Given a host/port string, like one might find in the host part of a URL
	 * per RFC 2732, split the hostname part and the port part and return an
	 * array with an element for each. If there is no port part, the array will
	 * have false in place of the port. If the string was invalid in some way,
	 * false is returned.
	 *
	 * This was easy with IPv4 and was generally done in an ad-hoc way, but
	 * with IPv6 it's somewhat more complicated due to the need to parse the
	 * square brackets and colons.
	 *
	 * A bare IPv6 address is accepted despite the lack of square brackets.
	 *
	 * @param string $both The string with the host (or IPv4/IPv6 address) and port
	 * @return array|false Array normally, false on certain failures
	 */
	public static function splitHostAndPort( $both ) {
		if ( str_starts_with( $both, '[' ) ) {
			if ( preg_match( '/^\[(' . self::RE_IPV6_ADD . ')\](?::(?P<port>\d+))?$/', $both, $m ) ) {
				if ( isset( $m['port'] ) ) {
					return [ $m[1], intval( $m['port'] ) ];
				}

				return [ $m[1], false ];
			}

			// Square bracket found but no IPv6
			return false;
		}
		$numColons = substr_count( $both, ':' );
		if ( $numColons >= 2 ) {
			// Is it a bare IPv6 address?
			if ( preg_match( '/^' . self::RE_IPV6_ADD . '$/', $both ) ) {
				return [ $both, false ];
			}

			// Not valid IPv6, but too many colons for anything else
			return false;
		}
		if ( $numColons >= 1 ) {
			// Host:port?
			$bits = explode( ':', $both );
			if ( preg_match( '/^\d+/', $bits[1] ) ) {
				return [ $bits[0], intval( $bits[1] ) ];
			}

			// Not a valid port
			return false;
		}

		// Plain hostname
		return [ $both, false ];
	}

	/**
	 * Given a host name and a port, combine them into a host/port string like
	 * you might find in a URL. If the host contains a colon, wrap it in square
	 * brackets like in RFC 2732. If the port matches the default port, omit
	 * the port specification
	 *
	 * @param string $host
	 * @param int $port
	 * @param bool|int $defaultPort
	 * @return string
	 */
	public static function combineHostAndPort( $host, $port, $defaultPort = false ) {
		if ( str_contains( $host, ':' ) ) {
			$host = "[$host]";
		}
		if ( $defaultPort !== false && $port === $defaultPort ) {
			return $host;
		}

		return "$host:$port";
	}

	/**
	 * Convert an IPv4 or IPv6 hexadecimal representation back to readable format
	 *
	 * @param string $hex Number, with "v6-" prefix if it is IPv6
	 * @return string Quad-dotted (IPv4) or octet notation (IPv6)
	 */
	public static function formatHex( $hex ) {
		if ( str_starts_with( $hex, 'v6-' ) ) {
			// IPv6
			return self::hexToOctet( substr( $hex, 3 ) );
		}

		// IPv4
		return self::hexToQuad( $hex );
	}

	/**
	 * Converts a hexadecimal number to an IPv6 address in octet notation
	 *
	 * @param string $ip_hex Pure hex (no v6- prefix)
	 * @return string (of format a:b:c:d:e:f:g:h)
	 */
	public static function hexToOctet( $ip_hex ) {
		// Pad hex to 32 chars (128 bits)
		$ip_hex = str_pad( strtoupper( $ip_hex ), 32, '0', STR_PAD_LEFT );
		// Separate into 8 words a 4 bytes
		$ip_oct = [];
		foreach ( str_split( $ip_hex, 4 ) as $o ) {
			// NO leading zeroes
			$ip_oct[] = ltrim( $o, '0' ) ?: '0';
		}
		return implode( ':', $ip_oct );
	}

	/**
	 * Converts a hexadecimal number to an IPv4 address in quad-dotted notation
	 *
	 * @param string $ip_hex Pure hex
	 * @return string (of format a.b.c.d)
	 */
	public static function hexToQuad( $ip_hex ) {
		// Pad hex to 8 chars (32 bits)
		$ip_hex = str_pad( $ip_hex, 8, '0', STR_PAD_LEFT );
		// Separate into four quads
		$ip_quads = [];
		foreach ( str_split( $ip_hex, 2 ) as $q ) {
			$ip_quads[] = hexdec( $q );
		}

		return implode( '.', $ip_quads );
	}

	/**
	 * Determine if an IP address really is an IP address, and if it is public,
	 * i.e. not RFC 1918 or similar
	 *
	 * @param string $ip
	 * @return bool
	 */
	public static function isPublic( $ip ) {
		static $privateSet = null;
		if ( !$privateSet ) {
			$privateSet = new IPSet( [
				// RFC 1918 (private)
				'10.0.0.0/8',
				// RFC 1918 (private)
				'172.16.0.0/12',
				// RFC 1918 (private)
				'192.168.0.0/16',
				// this network
				'0.0.0.0/8',
				// loopback
				'127.0.0.0/8',
				// RFC 4193 (local)
				'fc00::/7',
				// loopback
				'0:0:0:0:0:0:0:1',
				// link-local
				'169.254.0.0/16',
				// link-local
				'fe80::/10',
			] );
		}
		return !$privateSet->match( $ip );
	}

	/**
	 * Return a zero-padded upper case hexadecimal representation of an IPv6
	 * address.
	 *
	 * To separate the ranges, the return value from this function for an IPv6
	 * address will be prefixed with "v6-", a non-hexadecimal string which
	 * sorts after the IPv4 addresses.
	 *
	 * @param string $ip Quad dotted/octet IPv6 address.
	 * @return string|bool False on failure
	 */
	private static function toHex6( $ip ) {
		$ip = self::sanitizeIPv6( $ip );
		if ( $ip === null ) {
			return false;
		}
		return 'v6-' . self::convertIPv6ToRawHex( $ip );
	}

	/**
	 * Return a zero-padded upper case hexadecimal representation of an IPv4
	 * address.
	 *
	 * @param string $ip Quad dotted/octet IPv4 address.
	 * @return string|bool False on failure
	 */
	private static function toHex4( $ip ) {
		// T62035/T97897: An IP with leading 0's fails in ip2long sometimes (e.g. *.08),
		// also double/triple 0 needs to be changed to just a single 0 for ip2long.
		$ip = self::sanitizeIPv4( $ip );
		if ( $ip === null ) {
			return false;
		}
		$n = ip2long( $ip );
		if ( $n < 0 ) {
			// We don't run code coverage on a 32-bit OS or Windows, so this will never be exercised
			// @codeCoverageIgnoreStart
			$n += 2 ** 32;
			// On 32-bit platforms (and on Windows), 2^32 does not fit into an int,
			// so $n becomes a float. We convert it to string instead.
			if ( is_float( $n ) ) {
				$n = (string)$n;
			}
			// @codeCoverageIgnoreEnd
		}
		// Floating points can handle the conversion; faster than \Wikimedia\base_convert()
		return strtoupper( str_pad( base_convert( $n, 10, 16 ), 8, '0', STR_PAD_LEFT ) );
	}

	/**
	 * Return a zero-padded upper case hexadecimal representation of an IP address.
	 *
	 * Hexadecimal addresses are used because they can easily be extended to
	 * IPv6 support. To separate the ranges, the return value from this
	 * function for an IPv6 address will be prefixed with "v6-", a non-
	 * hexadecimal string which sorts after the IPv4 addresses.
	 *
	 * @param string $ip Quad dotted/octet IP address.
	 * @return string|bool False on failure
	 */
	public static function toHex( $ip ) {
		if ( self::isIPv6( $ip ) ) {
			return self::toHex6( $ip );
		} elseif ( self::isIPv4( $ip ) ) {
			return self::toHex4( $ip );
		}
		return false;
	}

	/**
	 * Given an IPv6 address in octet notation, returns a pure hex string.
	 *
	 * @param string $ip Octet ipv6 IP address.
	 * @return string Pure hex (uppercase)
	 */
	private static function convertIPv6ToRawHex( $ip ) {
		$r_ip = '';
		foreach ( explode( ':', $ip ) as $v ) {
			$r_ip .= str_pad( $v, 4, '0', STR_PAD_LEFT );
		}

		return $r_ip;
	}

	/**
	 * Convert a network specification in CIDR notation
	 * to an integer network and a number of bits
	 *
	 * @param string $range IP with CIDR prefix
	 * @return array [int|string, int]
	 */
	public static function parseCIDR( $range ) {
		if ( self::isIPv6( $range ) ) {
			return self::parseCIDR6( $range );
		} else {
			return self::parseCIDR4( $range );
		}
	}

	/**
	 * Given a string range in a number of formats,
	 * return the start and end of the range in hexadecimal.
	 *
	 * Formats are:
	 *     1.2.3.4/24          CIDR
	 *     1.2.3.4 - 1.2.3.5   Explicit range
	 *     1.2.3.4             Single IP
	 *
	 *     2001:0db8:85a3::7344/96                       CIDR
	 *     2001:0db8:85a3::7344 - 2001:0db8:85a3::7344   Explicit range
	 *     2001:0db8:85a3::7344                          Single IP
	 * @param string $range IP range
	 * @return array{string,string}|array{false,false} If the start or end of the range
	 * is invalid, then array `[false, false]` is returned
	 */
	public static function parseRange( $range ) {
		if ( str_contains( $range, '/' ) ) {
			// CIDR notation
			if ( self::isIPv6( $range ) ) {
				return self::parseRange6( $range );
			}
			[ $network, $bits ] = self::parseCIDR4( $range );
			if ( $network === false ) {
				$start = $end = false;
			} else {
				$start = sprintf( '%08X', $network );
				$end = sprintf( '%08X', $network + 2 ** ( 32 - $bits ) - 1 );
			}
		} elseif ( str_contains( $range, '-' ) ) {
			// Explicit range
			[ $start, $end ] = array_map( 'trim', explode( '-', $range, 2 ) );
			if ( self::isIPv6( $start ) && self::isIPv6( $end ) ) {
				return self::parseRange6( $range );
			}
			if ( self::isIPv4( $start ) && self::isIPv4( $end ) ) {
				$start = self::toHex4( $start );
				$end = self::toHex4( $end );
				if ( $start > $end ) {
					$start = $end = false;
				}
			} else {
				$start = $end = false;
			}
		} else {
			// Single IP
			$start = $end = self::toHex( $range );
		}
		if ( $start === false || $end === false ) {
			return [ false, false ];
		}

		return [ $start, $end ];
	}

	/**
	 * Convert a network specification in IPv4 CIDR notation to an
	 * integer network and a number of bits
	 *
	 * @param string $range
	 *
	 * @return array{int,int}|array{false,false}
	 */
	private static function parseCIDR4( $range ) {
		$parts = explode( '/', $range, 2 );
		if ( count( $parts ) !== 2 ) {
			return [ false, false ];
		}
		[ $network, $bits ] = $parts;
		$network = ip2long( $network );
		if ( $network !== false && is_numeric( $bits ) && $bits >= 0 && $bits <= 32 ) {
			$network &= ~( ( 1 << ( 32 - (int)$bits ) ) - 1 );
			// Convert to unsigned
			if ( $network < 0 ) {
				$network += 2 ** 32;
			}
		} else {
			$network = false;
			$bits = false;
		}

		return [ $network, $bits ];
	}

	/**
	 * Convert a network specification in IPv6 CIDR notation to an
	 * integer network and a number of bits
	 *
	 * @param string $range
	 *
	 * @return array{string,int}|array{false,false}
	 */
	private static function parseCIDR6( $range ) {
		// Explode into <expanded IP,range>
		$parts = explode( '/', self::sanitizeIPv6( $range ), 2 );
		if ( count( $parts ) !== 2 ) {
			return [ false, false ];
		}
		[ $network, $bits ] = $parts;
		$network = self::convertIPv6ToRawHex( $network );
		if ( is_numeric( $bits ) && $bits >= 0 && $bits <= 128 ) {
			// Native 32-bit functions WON'T work here!!!
			// Convert to a padded binary number
			$network = \Wikimedia\base_convert( $network, 16, 2, 128 );
			// Truncate the last (128-$bits) bits and replace them with zeros
			$network = str_pad( substr( $network, 0, (int)$bits ), 128, '0', STR_PAD_RIGHT );
			// Convert back to an integer
			$network = \Wikimedia\base_convert( $network, 2, 10 );
		} else {
			$network = false;
			$bits = false;
		}

		return [ $network, (int)$bits ];
	}

	/**
	 * Given a string range in a number of formats, return the
	 * start and end of the range in hexadecimal. For IPv6.
	 *
	 * Formats are:
	 *     2001:0db8:85a3::7344/96                       CIDR
	 *     2001:0db8:85a3::7344 - 2001:0db8:85a3::7344   Explicit range
	 *     2001:0db8:85a3::7344/96                       Single IP
	 *
	 * @param string $range
	 *
	 * @return array [string, string]|array [false, false] If the start or end of the range
	 * is invalid, then array [false, false] is returned
	 */
	private static function parseRange6( $range ) {
		$start = false;
		$end = false;

		if ( str_contains( $range, '/' ) ) {
			// CIDR notation...
			[ $network, $bits ] = self::parseCIDR6( $range );
			if ( $network !== false ) {
				$start = \Wikimedia\base_convert( $network, 10, 16, 32, false );
				// Turn network to binary (again)
				$end = \Wikimedia\base_convert( $network, 10, 2, 128 );
				// Truncate the last (128-$bits) bits and replace them with ones
				$end = str_pad( substr( $end, 0, $bits ), 128, '1', STR_PAD_RIGHT );
				// Convert to hex
				$end = \Wikimedia\base_convert( $end, 2, 16, 32, false );
				// see toHex() comment
				$start = "v6-$start";
				$end = "v6-$end";
			}
		} elseif ( str_contains( $range, '-' ) ) {
			// Explicit range notation...
			[ $start, $end ] = explode( '-', $range, 2 );
			$start = self::toHex6( $start );
			$end = self::toHex6( $end );
			if ( $start > $end ) {
				$start = $end = false;
			}
		}

		if ( $start === false || $end === false ) {
			return [ false, false ];
		}

		return [ $start, $end ];
	}

	/**
	 * Determine if a given IPv4/IPv6 address in hex format is in a given CIDR
	 * network
	 *
	 * @param string $hexIP The address (as hex) to check against the given range.
	 * @param string $range The range to check the given address against.
	 * @return bool Whether the given address is in the given range.
	 *
	 * @note This can return unexpected results for invalid arguments!
	 *       Make sure you pass a valid IP address and IP range.
	 */
	private static function isHexInRange( $hexIP, $range ) {
		[ $start, $end ] = self::parseRange( $range );

		return strcmp( $hexIP, $start ) >= 0 &&
			strcmp( $hexIP, $end ) <= 0;
	}

	/**
	 * Determine if a given IP address matches a given IP range or single address.
	 *
	 * @param string $addr The address to check against the given range.
	 * @param string $range A CIDR range, explicit range, or single address.
	 *  See IPUtils::isValidRange for examples.
	 * @return bool Whether the given address is in the given range.
	 *
	 * @note This can return unexpected results for invalid arguments!
	 *       Make sure you pass a valid IP address and IP range.
	 */
	public static function isInRange( $addr, $range ) {
		$hexIP = self::toHex( $addr );
		return self::isHexInRange( $hexIP, $range );
	}

	/**
	 * Determine if an IP address is in a list of IP ranges or addresses.
	 *
	 * @param string $ip The IP to check
	 * @param string[] $ranges The list to check against, where each value should be
	 *  a CIDR range, explicit range, or single address. See IPUtils::isValidRange for examples.
	 * @return bool True if the specified address belongs to the specified range, otherwise false.
	 */
	public static function isInRanges( $ip, $ranges ) {
		if ( self::isIPv6( $ip ) ) {
			$hexIP = self::toHex6( $ip );
			foreach ( $ranges as $range ) {
				if (
					( self::isValidIPv6( $range ) || self::isValidIPv6Range( $range ) ) &&
					self::isHexInRange( $hexIP, $range )
				) {
					return true;
				}
			}
		} elseif ( self::isIPv4( $ip ) ) {
			$hexIP = self::toHex4( $ip );
			foreach ( $ranges as $range ) {
				if (
					( self::isValidIPv4( $range ) || self::isValidIPv4Range( $range ) ) &&
					self::isHexInRange( $hexIP, $range )
				) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Convert some unusual representations of IPv4 addresses to their
	 * canonical dotted quad representation.
	 *
	 * This currently only checks a few IPV4-to-IPv6 related cases. More
	 * unusual representations may be added later.
	 *
	 * @param string $addr Something that might be an IP address
	 * @return string|null Valid IP address or null
	 * @return-taint none
	 */
	public static function canonicalize( $addr ) {
		// remove zone info (T37738)
		$addr = preg_replace( '/\%.*/', '', $addr );

		// If it's already a valid IPv4 address, nothing to do
		if ( self::isValidIPv4( $addr ) ) {
			return $addr;
		}

		// https://en.wikipedia.org/wiki/IPv6#IPv4-mapped_IPv6_addresses
		// Turn mapped addresses from:
		//  ::ce:ffff:1.2.3.4 to 1.2.3.4 (IPv4-mapped IPv6 addresses)
		//  ::1.2.3.4 to 1.2.3.4 (IPv4-compatible IPv6 address)
		// IPv4-compatible IPv6 addresses are now deprecated https://tools.ietf.org/html/rfc4291#section-2.5.5.1
		if ( preg_match( '/^' . self::RE_IPV6_V4_PREFIX . '(' . self::RE_IP_ADD . ')$/i', $addr, $m ) ) {
			return $m[1];
		}

		// Converts :ffff:1F to 255.255.0.31
		// Is this actually used/needed?
		if ( preg_match( '/^' . self::RE_IPV6_V4_PREFIX . self::RE_IPV6_WORD .
			':' . self::RE_IPV6_WORD . '$/i', $addr, $m )
		) {
			return long2ip( ( hexdec( $m[1] ) << 16 ) + hexdec( $m[2] ) );
		}

		// It's a valid IPv6 address that we haven't canonicalized, so return it
		if ( self::isValidIPv6( $addr ) ) {
			return $addr;
		}

		// Not a valid IP address
		return null;
	}

	/**
	 * Gets rid of unneeded numbers in quad-dotted/octet IP strings
	 * For example, 127.111.113.151/24 -> 127.111.113.0/24
	 * @param string $range IP address to normalize
	 * @return string
	 */
	public static function sanitizeRange( $range ) {
		[ , $bits ] = self::parseCIDR( $range );
		[ $start, ] = self::parseRange( $range );
		$start = self::formatHex( $start );
		if ( $bits === false ) {
			// input wasn't a range
			return $start;
		}

		return "$start/$bits";
	}

	/**
	 * Returns the subnet of a given IP
	 *
	 * @param string $ip
	 * @return string|false
	 */
	public static function getSubnet( $ip ) {
		$matches = [];
		$subnet = false;
		if ( self::isIPv6( $ip ) ) {
			$parts = self::parseRange( "$ip/64" );
			$subnet = $parts[0];
		} elseif ( preg_match( '/^' . self::RE_IP_ADD . '$/', $ip, $matches ) ) {
			// IPv4
			$subnet = "{$matches[1]}.{$matches[2]}.{$matches[3]}";
		}
		return $subnet;
	}

	/**
	 * Return all the addresses in a given range
	 *
	 * This currently does not support IPv6 ranges and is limited to /16 block (65535 addresses).
	 *
	 * @param string $range IP ranges to get the IPs within
	 * @return string[] Array of addresses in the range
	 * @throws InvalidArgumentException If input uses IPv6
	 * @throws InvalidArgumentException If the input range is too large
	 */
	public static function getIPsInRange( $range ): array {
		// No IPv6 for now.
		if ( self::isValidIPv6( $range ) || self::isValidIPv6Range( $range ) ) {
			throw new InvalidArgumentException( 'Cannot retrieve addresses for IPv6 range: ' . $range );
		}

		[ $start, $end ] = self::parseRange( $range );
		if ( $start === false || $start === $end ) {
			throw new InvalidArgumentException( 'Invalid range given: ' . $range );
		}

		if ( hexdec( $end ) - hexdec( $start ) > self::MAXIMUM_IPS_FROM_RANGE ) {
			throw new InvalidArgumentException( "Range {$range} is too large, it contains more than "
				. self::MAXIMUM_IPS_FROM_RANGE . ' addresses' );
		}

		$start = ip2long( self::formatHex( $start ) );
		$end = ip2long( self::formatHex( $end ) );

		return array_map( 'long2ip', range( $start, $end ) );
	}
}
