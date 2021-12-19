<?php
/**
 * Copyright (C) 2021 Kunal Mehta <legoktm@debian.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

namespace MediaWiki\SyntaxHighlight;

use MediaWiki\MediaWikiServices;
use Shellbox\Command\BoxedCommand;

/**
 * Wrapper around the `pygmentize` command
 */
class Pygmentize {

	/**
	 * If no pygmentize is configured, use bundled
	 *
	 * @return bool
	 */
	public static function useBundled(): bool {
		global $wgPygmentizePath;
		return $wgPygmentizePath === false;
	}

	/**
	 * Get a real path to pygmentize
	 *
	 * @return string
	 */
	private static function getPath(): string {
		global $wgPygmentizePath;

		// If $wgPygmentizePath is unset, use the bundled copy.
		if ( $wgPygmentizePath === false ) {
			return __DIR__ . '/../pygments/pygmentize';
		}

		return $wgPygmentizePath;
	}

	/**
	 * Get the version of pygments
	 *
	 * @return string
	 */
	public static function getVersion(): string {
		static $version;
		if ( $version !== null ) {
			return $version;
		}
		if ( self::useBundled() ) {
			$version = self::getBundledVersion();
			return $version;
		}

		$cache = MediaWikiServices::getInstance()->getLocalServerObjectCache();
		$version = $cache->getWithSetCallback(
			$cache->makeGlobalKey( 'pygmentize-version' ),
			$cache::TTL_HOUR,
			function () {
				$result = self::boxedCommand()
					->params( self::getPath(), '-V' )
					->includeStderr()
					->execute();
				self::recordShellout( 'version' );

				$output = $result->getStdout();
				if ( $result->getExitCode() != 0 ||
					!preg_match( '/^Pygments version (\S+),/', $output, $matches )
				) {
					throw new PygmentsException( $output );
				}

				return $matches[1];
			}
		);

		return $version;
	}

	/**
	 * Get the version of bundled pygments
	 *
	 * @return string
	 */
	private static function getBundledVersion(): string {
		return trim( file_get_contents( __DIR__ . '/../pygments/VERSION' ) );
	}

	/**
	 * Get the pygments generated CSS (cached)
	 *
	 * Note: if using bundled, the CSS is already available
	 * in modules/pygments.generated.css.
	 *
	 * @return string
	 */
	public static function getGeneratedCSS(): string {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		return $cache->getWithSetCallback(
			$cache->makeGlobalKey( 'pygmentize-css', self::getVersion() ),
			$cache::TTL_WEEK,
			[ __CLASS__, 'fetchGeneratedCSS' ]
		);
	}

	/**
	 * Shell out to get generated CSS from pygments
	 *
	 * @internal Only public for updateCSS.php
	 * @return string
	 */
	public static function fetchGeneratedCSS(): string {
		$result = self::boxedCommand()
			->params(
				self::getPath(), '-f', 'html',
				'-S', 'default', '-a', '.mw-highlight' )
			->includeStderr()
			->execute();
		self::recordShellout( 'generated_css' );
		$output = $result->getStdout();
		if ( $result->getExitCode() != 0 ) {
			throw new PygmentsException( $output );
		}
		return $output;
	}

	/**
	 * Get the list of supported lexers by pygments (cached)
	 *
	 * @return array
	 */
	public static function getLexers(): array {
		if ( self::useBundled() ) {
			return require __DIR__ . '/../SyntaxHighlight.lexers.php';
		}

		$cache = MediaWikiServices::getInstance()->getLocalServerObjectCache();
		return $cache->getWithSetCallback(
			$cache->makeGlobalKey( 'pygmentize-lexers', self::getVersion() ),
			$cache::TTL_DAY,
			[ __CLASS__, 'fetchLexers' ]
		);
	}

	/**
	 * Shell out to get supported lexers by pygments
	 *
	 * @internal Only public for updateLexerList.php
	 * @return array
	 */
	public static function fetchLexers(): array {
		$result = self::boxedCommand()
			->params( self::getPath(), '-L', 'lexer' )
			->includeStderr()
			->execute();
		self::recordShellout( 'fetch_lexers' );
		$output = $result->getStdout();
		if ( $result->getExitCode() != 0 ) {
			throw new PygmentsException( $output );
		}

		// Post-process the output, ideally pygments would output this in a
		// machine-readable format (https://github.com/pygments/pygments/issues/1437)
		$output = $result->getStdout();
		$lexers = [];
		foreach ( explode( "\n", $output ) as $line ) {
			if ( substr( $line, 0, 1 ) === '*' ) {
				$newLexers = explode( ', ', trim( $line, "* :\n" ) );

				// Skip internal, unnamed lexers
				if ( $newLexers[0] !== '' ) {
					$lexers = array_merge( $lexers, $newLexers );
				}
			}
		}
		$lexers = array_unique( $lexers );
		sort( $lexers );
		$data = [];
		foreach ( $lexers as $lexer ) {
			$data[$lexer] = true;
		}

		return $data;
	}

	/**
	 * Actually highlight some text
	 *
	 * @param string $lexer Lexer name
	 * @param string $code Code to highlight
	 * @param array $options Options to pass to pygments
	 * @return string
	 */
	public static function highlight( $lexer, $code, array $options ): string {
		$optionPairs = [];
		foreach ( $options as $k => $v ) {
			$optionPairs[] = "{$k}={$v}";
		}
		$result = self::boxedCommand()
			->params(
				self::getPath(),
				'-l', $lexer,
				'-f', 'html',
				'-O', implode( ',', $optionPairs ),
				'file'
			)
			->inputFileFromString( 'file', $code )
			->execute();
		self::recordShellout( 'highlight' );

		$output = $result->getStdout();
		if ( $result->getExitCode() != 0 ) {
			throw new PygmentsException( $output );
		}
		return $output;
	}

	private static function boxedCommand(): BoxedCommand {
		return MediaWikiServices::getInstance()->getShellCommandFactory()
			->createBoxed( 'syntaxhighlight' )
			->disableNetwork()
			->firejailDefaultSeccomp()
			->routeName( 'syntaxhighlight-pygments' );
	}

	/**
	 * Track how often we do each type of shellout in statsd
	 *
	 * @param string $type Type of shellout
	 */
	private static function recordShellout( $type ) {
		$statsd = MediaWikiServices::getInstance()->getStatsdDataFactory();
		$statsd->increment( "syntaxhighlight_shell.$type" );
	}
}
