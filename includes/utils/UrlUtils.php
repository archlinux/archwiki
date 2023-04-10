<?php

namespace MediaWiki\Utils;

use BadMethodCallException;
use Exception;
use InvalidArgumentException;
use MediaWiki\MainConfigSchema;
use MWDebug;

/**
 * A service to expand, parse, and otherwise manipulate URLs.
 *
 * @since 1.39
 * @newable
 */
class UrlUtils {
	public const SERVER = 'server';
	public const CANONICAL_SERVER = 'canonicalServer';
	public const INTERNAL_SERVER = 'internalServer';
	public const FALLBACK_PROTOCOL = 'fallbackProtocol';
	public const HTTPS_PORT = 'httpsPort';
	public const VALID_PROTOCOLS = 'validProtocols';

	/** @var ?string */
	private $server = null;

	/** @var ?string */
	private $canonicalServer = null;

	/** @var ?string */
	private $internalServer = null;
	/** @var string */
	private $fallbackProtocol = 'http';

	/** @var int */
	private $httpsPort = 443;

	/** @var array */
	private $validProtocols = MainConfigSchema::UrlProtocols['default'];

	/** @var ?string */
	private $validProtocolsCache = null;

	/** @var ?string */
	private $validAbsoluteProtocolsCache = null;

	/**
	 * @stable to call
	 * @param array $options All keys are optional, but if you omit SERVER then calling expand()
	 *   (and getServer(), expandIRI(), and matchesDomainList()) will throw. Recognized keys:
	 *     * self::SERVER: The protocol and server portion of the URLs to expand, with no other parts
	 *       (port, path, etc.). Example: 'https://example.com'. Protocol-relative URLs are
	 *       allowed.
	 *     * self::CANONICAL_SERVER: If SERVER is protocol-relative, this can be set to a
	 *       fully-qualified version for use when PROTO_CANONICAL is passed to expand(). Defaults
	 *       to SERVER, with 'http:' prepended if SERVER is protocol-relative.
	 *     * self::INTERNAL_SERVER: An alternative to SERVER that's used when PROTO_INTERNAL is
	 *       passed to expand(). It's intended for sites that have a different server name exposed
	 *       to CDNs. Defaults to SERVER.
	 *     * self::FALLBACK_PROTOCOL: Used by expand() when no $defaultProto parameter is provided.
	 *       Defaults to 'http'. The instance created by ServiceWiring sets this to 'https' if the
	 *       current request is detected to be via HTTPS, and 'http' otherwise.
	 *     * self::HTTPS_PORT: Defaults to 443. Used when a protocol-relative URL is expanded to
	 *       https.
	 *     * self::VALID_PROTOCOLS: An array of recognized URL protocols. The default can be found
	 *       in MainConfigSchema::UrlProtocols['default'].
	 */
	public function __construct( array $options = [] ) {
		foreach ( $options as $key => $value ) {
			switch ( $key ) {
				case self::SERVER:
				case self::CANONICAL_SERVER:
				case self::INTERNAL_SERVER:
				case self::FALLBACK_PROTOCOL:
				case self::HTTPS_PORT:
				case self::VALID_PROTOCOLS:
					$this->$key = $value;
					break;

				default:
					throw new InvalidArgumentException( "Unrecognized option \"$key\"" );
			}
		}

		if ( $this->server !== null ) {
			if ( $this->canonicalServer === null || $this->canonicalServer === false ) {
				$this->canonicalServer = $this->expand( $this->server, PROTO_HTTP );
			}
			if ( $this->internalServer === null || $this->internalServer === false ) {
				$this->internalServer = $this->server;
			}
		}
	}

	/**
	 * Expand a potentially local URL to a fully-qualified URL.
	 *
	 * The meaning of the PROTO_* constants is as follows:
	 * PROTO_HTTP: Output a URL starting with http://
	 * PROTO_HTTPS: Output a URL starting with https://
	 * PROTO_RELATIVE: Output a URL starting with // (protocol-relative URL)
	 * PROTO_FALLBACK: Output a URL starting with the FALLBACK_PROTOCOL option
	 * PROTO_CURRENT: Legacy alias for PROTO_FALLBACK
	 * PROTO_CANONICAL: For URLs without a domain, like /w/index.php, use CANONICAL_SERVER.  For
	 *   protocol-relative URLs, use the protocol of CANONICAL_SERVER
	 * PROTO_INTERNAL: Like PROTO_CANONICAL, but uses INTERNAL_SERVER instead of CANONICAL_SERVER
	 *
	 * @todo this won't work with current-path-relative URLs like "subdir/foo.html", etc.
	 *
	 * @throws BadMethodCallException if no server was passed to the constructor
	 * @param string $url Either fully-qualified or a local path + query
	 * @param string|int|null $defaultProto One of the PROTO_* constants. Determines the
	 *    protocol to use if $url or SERVER is protocol-relative
	 * @return ?string Fully-qualified URL, current-path-relative URL or null if
	 *    no valid URL can be constructed
	 */
	public function expand( string $url, $defaultProto = PROTO_FALLBACK ): ?string {
		if ( $defaultProto === PROTO_CANONICAL ) {
			$serverUrl = $this->canonicalServer;
		} elseif ( $defaultProto === PROTO_INTERNAL ) {
			$serverUrl = $this->internalServer;
		} else {
			$serverUrl = $this->server;
			if ( $defaultProto === PROTO_FALLBACK ) {
				$defaultProto = $this->fallbackProtocol . '://';
			}
		}

		if ( substr( $url, 0, 1 ) === '/' ) {
			if ( $serverUrl === null ) {
				throw new BadMethodCallException( 'Cannot call expand() if the appropriate ' .
					'SERVER/CANONICAL_SERVER/INTERNAL_SERVER option was not passed to the ' .
					'constructor' );
			}

			// Analyze $serverUrl to obtain its protocol
			$bits = $this->parse( $serverUrl );
			$serverProto = $bits && $bits['scheme'] != '' ? $bits['scheme'] . '://' : null;

			if ( $defaultProto === PROTO_CANONICAL || $defaultProto === PROTO_INTERNAL ) {
				// Fall back to HTTP in the ridiculous case that CanonicalServer or InternalServer
				// doesn't have a protocol
				$defaultProto = $serverProto ?? PROTO_HTTP;
			}

			// @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal T308355
			$defaultProtoWithoutSlashes = $defaultProto === PROTO_FALLBACK ? '' : substr( $defaultProto, 0, -2 );

			if ( substr( $url, 0, 2 ) == '//' ) {
				$url = $defaultProtoWithoutSlashes . $url;
			} else {
				// If $serverUrl is protocol-relative, prepend $defaultProtoWithoutSlashes,
				// otherwise leave it alone.
				if ( $serverProto ) {
					$url = $serverUrl . $url;
				} else {
					// If an HTTPS URL is synthesized from a protocol-relative Server, allow the
					// user to override the port number (T67184)
					if ( $defaultProto === PROTO_HTTPS && $this->httpsPort != 443 ) {
						if ( isset( $bits['port'] ) ) {
							throw new Exception(
								'A protocol-relative server may not contain a port number' );
						}
						$url = "$defaultProtoWithoutSlashes$serverUrl:{$this->httpsPort}$url";
					} else {
						$url = "$defaultProtoWithoutSlashes$serverUrl$url";
					}
				}
			}
		}

		$bits = $this->parse( $url );

		if ( $bits && isset( $bits['path'] ) ) {
			$bits['path'] = $this->removeDotSegments( $bits['path'] );
			return $this->assemble( $bits );
		} elseif ( $bits ) {
			# No path to expand
			return $url;
		} elseif ( substr( $url, 0, 1 ) != '/' ) {
			# URL is a relative path
			return $this->removeDotSegments( $url );
		}

		# Expanded URL is not valid.
		return null;
	}

	/**
	 * Get the wiki's "server", i.e. the protocol and host part of the URL, with a
	 * protocol specified using a PROTO_* constant as in expand()
	 *
	 * @throws BadMethodCallException if no server was passed to the constructor
	 * @param string|int|null $proto One of the PROTO_* constants.
	 * @return ?string The URL, or null on failure
	 */
	public function getServer( $proto ): ?string {
		$url = $this->expand( '/', $proto );
		if ( $url === null ) {
			return null;
		}
		return substr( $url, 0, -1 );
	}

	/**
	 * Get the canonical server, i.e. the canonical protocol and host part of
	 * the wiki's URL.
	 * @return string
	 */
	public function getCanonicalServer(): string {
		// @phan-suppress-next-line PhanTypeMismatchReturnNullable -- throw if unconfigured
		return $this->canonicalServer;
	}

	/**
	 * This function will reassemble a URL parsed with parse().  This is useful if you need to edit
	 * part of a URL and put it back together.
	 *
	 * This is the basic structure used (brackets contain keys for $urlParts):
	 * [scheme][delimiter][user]:[pass]@[host]:[port][path]?[query]#[fragment]
	 *
	 * @todo Need to integrate this into expand() (see T34168)
	 *
	 * @param array $urlParts URL parts, as output from parse()
	 * @return string URL assembled from its component parts
	 */
	public function assemble( array $urlParts ): string {
		$result = '';

		if ( isset( $urlParts['delimiter'] ) ) {
			if ( isset( $urlParts['scheme'] ) ) {
				$result .= $urlParts['scheme'];
			}

			$result .= $urlParts['delimiter'];
		}

		if ( isset( $urlParts['host'] ) ) {
			if ( isset( $urlParts['user'] ) ) {
				$result .= $urlParts['user'];
				if ( isset( $urlParts['pass'] ) ) {
					$result .= ':' . $urlParts['pass'];
				}
				$result .= '@';
			}

			$result .= $urlParts['host'];

			if ( isset( $urlParts['port'] ) ) {
				$result .= ':' . $urlParts['port'];
			}
		}

		if ( isset( $urlParts['path'] ) ) {
			$result .= $urlParts['path'];
		}

		if ( isset( $urlParts['query'] ) && $urlParts['query'] !== '' ) {
			$result .= '?' . $urlParts['query'];
		}

		if ( isset( $urlParts['fragment'] ) ) {
			$result .= '#' . $urlParts['fragment'];
		}

		return $result;
	}

	/**
	 * Remove all dot-segments in the provided URL path.  For example, '/a/./b/../c/' becomes
	 * '/a/c/'.  For details on the algorithm, please see RFC3986 section 5.2.4.
	 *
	 * @todo Need to integrate this into expand() (see T34168)
	 *
	 * @param string $urlPath URL path, potentially containing dot-segments
	 * @return string URL path with all dot-segments removed
	 */
	public function removeDotSegments( string $urlPath ): string {
		$output = '';
		$inputOffset = 0;
		$inputLength = strlen( $urlPath );

		while ( $inputOffset < $inputLength ) {
			$prefixLengthOne = substr( $urlPath, $inputOffset, 1 );
			$prefixLengthTwo = substr( $urlPath, $inputOffset, 2 );
			$prefixLengthThree = substr( $urlPath, $inputOffset, 3 );
			$prefixLengthFour = substr( $urlPath, $inputOffset, 4 );
			$trimOutput = false;

			if ( $prefixLengthTwo == './' ) {
				# Step A, remove leading "./"
				$inputOffset += 2;
			} elseif ( $prefixLengthThree == '../' ) {
				# Step A, remove leading "../"
				$inputOffset += 3;
			} elseif ( ( $prefixLengthTwo == '/.' ) && ( $inputOffset + 2 == $inputLength ) ) {
				# Step B, replace leading "/.$" with "/"
				$inputOffset += 1;
				$urlPath[$inputOffset] = '/';
			} elseif ( $prefixLengthThree == '/./' ) {
				# Step B, replace leading "/./" with "/"
				$inputOffset += 2;
			} elseif ( $prefixLengthThree == '/..' && ( $inputOffset + 3 == $inputLength ) ) {
				# Step C, replace leading "/..$" with "/" and
				# remove last path component in output
				$inputOffset += 2;
				$urlPath[$inputOffset] = '/';
				$trimOutput = true;
			} elseif ( $prefixLengthFour == '/../' ) {
				# Step C, replace leading "/../" with "/" and
				# remove last path component in output
				$inputOffset += 3;
				$trimOutput = true;
			} elseif ( ( $prefixLengthOne == '.' ) && ( $inputOffset + 1 == $inputLength ) ) {
				# Step D, remove "^.$"
				$inputOffset += 1;
			} elseif ( ( $prefixLengthTwo == '..' ) && ( $inputOffset + 2 == $inputLength ) ) {
				# Step D, remove "^..$"
				$inputOffset += 2;
			} else {
				# Step E, move leading path segment to output
				if ( $prefixLengthOne == '/' ) {
					$slashPos = strpos( $urlPath, '/', $inputOffset + 1 );
				} else {
					$slashPos = strpos( $urlPath, '/', $inputOffset );
				}
				if ( $slashPos === false ) {
					$output .= substr( $urlPath, $inputOffset );
					$inputOffset = $inputLength;
				} else {
					$output .= substr( $urlPath, $inputOffset, $slashPos - $inputOffset );
					$inputOffset += $slashPos - $inputOffset;
				}
			}

			if ( $trimOutput ) {
				$slashPos = strrpos( $output, '/' );
				if ( $slashPos === false ) {
					$output = '';
				} else {
					$output = substr( $output, 0, $slashPos );
				}
			}
		}

		return $output;
	}

	/**
	 * Returns a regular expression of recognized URL protocols
	 *
	 * @return string
	 */
	public function validProtocols(): string {
		if ( $this->validProtocolsCache !== null ) {
			return $this->validProtocolsCache; // @codeCoverageIgnore
		}
		$this->validProtocolsCache = $this->validProtocolsInternal( true );
		return $this->validProtocolsCache;
	}

	/**
	 * Like validProtocols(), but excludes '//' from the protocol list. Use this if you need a
	 * regex that matches all URL protocols but does not match protocol-relative URLs
	 *
	 * @return string
	 */
	public function validAbsoluteProtocols(): string {
		if ( $this->validAbsoluteProtocolsCache !== null ) {
			return $this->validAbsoluteProtocolsCache; // @codeCoverageIgnore
		}
		$this->validAbsoluteProtocolsCache = $this->validProtocolsInternal( false );
		return $this->validAbsoluteProtocolsCache;
	}

	/**
	 * Returns a regular expression of URL protocols
	 *
	 * @param bool $includeProtocolRelative If false, remove '//' from the returned protocol list.
	 * @return string
	 */
	private function validProtocolsInternal( bool $includeProtocolRelative ): string {
		if ( !is_array( $this->validProtocols ) ) {
			MWDebug::deprecated( '$wgUrlProtocols that is not an array', '1.39' );
			return (string)$this->validProtocols;
		}

		$protocols = [];
		foreach ( $this->validProtocols as $protocol ) {
			// Filter out '//' if !$includeProtocolRelative
			if ( $includeProtocolRelative || $protocol !== '//' ) {
				$protocols[] = preg_quote( $protocol, '/' );
			}
		}

		return implode( '|', $protocols );
	}

	/**
	 * parse_url() work-alike, but non-broken.  Differences:
	 *
	 * 1) Handles protocols that don't use :// (e.g., mailto: and news:, as well as
	 *    protocol-relative URLs) correctly.
	 * 2) Adds a "delimiter" element to the array (see (2)).
	 * 3) Verifies that the protocol is on the UrlProtocols allowed list.
	 * 4) Rejects some invalid URLs that parse_url doesn't, e.g. the empty string or URLs starting
	 *    with a line feed character.
	 *
	 * @param string $url A URL to parse
	 * @return ?string[] Bits of the URL in an associative array, or null on failure.
	 *   Possible fields:
	 *   - scheme: URI scheme (protocol), e.g. 'http', 'mailto'. Lowercase, always present, but can
	 *       be an empty string for protocol-relative URLs.
	 *   - delimiter: either '://', ':' or '//'. Always present.
	 *   - host: domain name / IP. Always present, but could be an empty string, e.g. for file: URLs.
	 *   - port: port number. Will be missing when port is not explicitly specified.
	 *   - user: user name, e.g. for HTTP Basic auth URLs such as http://user:pass@example.com/
	 *       Missing when there is no username.
	 *   - pass: password, same as above.
	 *   - path: path including the leading /. Will be missing when empty (e.g. 'http://example.com')
	 *   - query: query string (as a string; see wfCgiToArray() for parsing it), can be missing.
	 *   - fragment: the part after #, can be missing.
	 */
	public function parse( string $url ): ?array {
		// Protocol-relative URLs are handled really badly by parse_url(). It's so bad that the
		// easiest way to handle them is to just prepend 'http:' and strip the protocol out later.
		$wasRelative = substr( $url, 0, 2 ) == '//';
		if ( $wasRelative ) {
			$url = "http:$url";
		}
		$bits = parse_url( $url );
		// parse_url() returns an array without scheme for some invalid URLs, e.g.
		// parse_url("%0Ahttp://example.com") == [ 'host' => '%0Ahttp', 'path' => 'example.com' ]
		if ( !$bits || !isset( $bits['scheme'] ) ) {
			return null;
		}

		// parse_url() incorrectly handles schemes case-sensitively. Convert it to lowercase.
		$bits['scheme'] = strtolower( $bits['scheme'] );

		// most of the protocols are followed by ://, but mailto: and sometimes news: not, check for it
		if ( in_array( $bits['scheme'] . '://', $this->validProtocols ) ) {
			$bits['delimiter'] = '://';
		} elseif ( in_array( $bits['scheme'] . ':', $this->validProtocols ) ) {
			$bits['delimiter'] = ':';
			// parse_url detects for news: and mailto: the host part of an url as path
			// We have to correct this wrong detection
			if ( isset( $bits['path'] ) ) {
				$bits['host'] = $bits['path'];
				$bits['path'] = '';
			}
		} else {
			return null;
		}

		// Provide an empty host for, e.g., file:/// urls (see T30627)
		if ( !isset( $bits['host'] ) ) {
			$bits['host'] = '';

			// See T47069
			if ( isset( $bits['path'] ) ) {
				/* parse_url loses the third / for file:///c:/ urls (but not on variants) */
				if ( substr( $bits['path'], 0, 1 ) !== '/' ) {
					$bits['path'] = '/' . $bits['path'];
				}
			} else {
				$bits['path'] = '';
			}
		}

		// If the URL was protocol-relative, fix scheme and delimiter
		if ( $wasRelative ) {
			$bits['scheme'] = '';
			$bits['delimiter'] = '//';
		}
		return $bits;
	}

	/**
	 * Take a URL, make sure it's expanded to fully qualified, and replace any encoded non-ASCII
	 * Unicode characters with their UTF-8 original forms for more compact display and legibility
	 * for local audiences.
	 *
	 * @todo handle punycode domains too
	 *
	 * @throws BadMethodCallException if no server was passed to the constructor
	 * @param string $url
	 * @return ?string
	 */
	public function expandIRI( string $url ): ?string {
		$expanded = $this->expand( $url );
		if ( $expanded === null ) {
			return null;
		}
		return preg_replace_callback(
			'/((?:%[89A-F][0-9A-F])+)/i',
			static function ( array $matches ) {
				return urldecode( $matches[1] );
			},
			$expanded
		);
	}

	/**
	 * Check whether a given URL has a domain that occurs in a given set of domains
	 *
	 * @throws BadMethodCallException if no server was passed to the constructor
	 * @param string $url
	 * @param array $domains Array of domains (strings)
	 * @return bool True if the host part of $url ends in one of the strings in $domains
	 */
	public function matchesDomainList( string $url, array $domains ): bool {
		$bits = $this->parse( $url );
		if ( is_array( $bits ) && isset( $bits['host'] ) ) {
			$host = '.' . $bits['host'];
			foreach ( $domains as $domain ) {
				$domain = '.' . $domain;
				if ( substr( $host, -strlen( $domain ) ) === $domain ) {
					return true;
				}
			}
		}
		return false;
	}
}
