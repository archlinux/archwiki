<?php

namespace MediaWiki\Extension\TemplateStyles;

/**
 * @file
 * @license GPL-2.0-or-later
 */

use Wikimedia\CSS\Grammar\TokenMatcher;
use Wikimedia\CSS\Grammar\UrlMatcher;
use Wikimedia\CSS\Objects\Token;

/**
 * Extend the standard factory for TemplateStyles-specific matchers
 */
class TemplateStylesMatcherFactory extends \Wikimedia\CSS\Grammar\MatcherFactory {

	private array $fileNames = [];

	/**
	 * @param array<string,string[]> $allowedDomains See $wgTemplateStylesAllowedUrls
	 */
	public function __construct(
		private readonly array $allowedDomains,
	) {
	}

	/**
	 * Check a URL for safety
	 * @param string $type
	 * @param string $url
	 * @return bool
	 */
	protected function checkUrl( $type, $url ) {
		// Undo unnecessary percent encoding
		$url = preg_replace_callback( '/%[2-7][0-9A-Fa-f]/', static function ( $m ) {
			$char = urldecode( $m[0] );
			/** @phan-suppress-next-line PhanParamSuspiciousOrder */
			return str_contains( '"#%<>[\]^`{|}/?&=+;', $char ) ? $m[0] : $char;
		}, $url );

		// Don't allow unescaped \ or /../ in the non-query part of the URL
		$tmp = preg_replace( '<[#?].*$>', '', $url );
		if ( str_contains( $tmp, '\\' ) || preg_match( '<(?:^|/|%2[fF])\.+(?:/|%2[fF]|$)>', $tmp ) ) {
			return false;
		}

		// Check if it is allowed
		$regexes = $this->allowedDomains[$type] ?? [];
		foreach ( $regexes as $regex ) {
			$m = [];
			if ( preg_match( $regex, $url, $m ) ) {
				if ( isset( $m['filename'] ) && $m['filename'] !== '' ) {
					$this->fileNames[] = rawurldecode( $m['filename'] );
				}
				return true;
			}
		}

		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function urlstring( $type ) {
		$key = __METHOD__ . ':' . $type;
		if ( !isset( $this->cache[$key] ) ) {
			$this->cache[$key] = new TokenMatcher( Token::T_STRING, function ( Token $t ) use ( $type ) {
				return $this->checkUrl( $type, $t->value() );
			} );
		}
		return $this->cache[$key];
	}

	/**
	 * @inheritDoc
	 */
	public function url( $type ) {
		$key = __METHOD__ . ':' . $type;
		if ( !isset( $this->cache[$key] ) ) {
			$this->cache[$key] = new UrlMatcher( function ( $url, $modifiers ) use ( $type ) {
				return !$modifiers && $this->checkUrl( $type, $url );
			} );
		}
		return $this->cache[$key];
	}

	/**
	 * Clear list of captured file names from urls
	 */
	public function clearFileNames() {
		$this->fileNames = [];
	}

	/**
	 * Get a list of filenames captured from used URLs
	 *
	 * This corresponds to filename named group in the URL regex
	 * @return array
	 */
	public function getFileNames() {
		return $this->fileNames;
	}
}
