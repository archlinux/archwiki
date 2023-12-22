<?php
/**
 * Dumps the conversion tables from CrhExceptions.php
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
 *
 * @file
 * @ingroup MaintenanceLanguage
 */

require_once __DIR__ . '/Maintenance.php';
require_once __DIR__ . '/BadRegexException.php';
require_once __DIR__ . '/BadEscapeException.php';

use Wikimedia\Assert\Assert;

/**
 * Dumps the conversion exceptions table from CrhExceptions.php
 * as foma-style regular expressions.  This is used to generate
 * large portions of fst/crh-exceptions.foma
 *
 * @ingroup MaintenanceLanguage
 */
class DumpCrh extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Dump crh exceptions' );
	}

	/**
	 * Does not write to the DB.
	 * @inheritDoc
	 */
	public function getDbType() {
		return Maintenance::DB_NONE;
	}

	/**
	 * Helper function for ad-hoc regex parser.
	 * @param string $str The input string
	 * @return string The next character
	 */
	private static function peek( string $str ): string {
		if ( $str == '' ) {
			return '';
		}
		return mb_substr( $str, 0, 1 );
	}

	/**
	 * Helper function for ad-hoc regex parser.
	 * @param string &$str The input string
	 * @param string|null $c The character expected, or null for any.
	 * @return string The next character
	 */
	private static function eat( string &$str, ?string $c = null ): string {
		Assert::invariant( $c === null || self::peek( $str ) === $c, "Ate something unexpected." );
		$str = mb_substr( $str, 1 );
		return self::peek( $str );
	}

	/**
	 * Ad-hoc regex parser to translate from PHP regexes to foma regexes.
	 * This does character classes.
	 * @param string &$str The input string (PHP regex)
	 * @return string The translated regexp (FOMA regex)
	 */
	private static function translateRegexClass( string &$str ): string {
		$peek = self::peek( $str );
		$not = false;
		$result = '';
		if ( $peek === '^' ) {
			$not = true;
			$peek = self::eat( $str, '^' );
		}
		while ( $peek !== ']' ) {
			$first = mb_ord( self::translateRegexChar( $str, false ) );
			$peek = self::peek( $str );
			if ( $peek !== '-' ) {
				$last = $first;
			} else {
				self::eat( $str, '-' );
				$last = mb_ord( self::translateRegexChar( $str, false ) );
				$peek = self::peek( $str );
			}
			for ( $i = $first; $i <= $last; $i++ ) {
				if ( $result !== '' ) {
					$result .= "|";
				}
				$result .= "{" . mb_chr( $i ) . "}";
			}
		}
		if ( $not ) {
			return "\\[$result]";
		}
		return $result;
	}

	/**
	 * Ad-hoc regex parser to translate from PHP regexes to foma regexes.
	 * This does individual characters (including backslash escapes)
	 * @param string &$str The input string (PHP regex)
	 * @param bool $escape Whether escape sequences are permitted
	 *    (aka, escape sequences not permitted in character classes)
	 * @return string The translated regexp (FOMA regex)
	 */
	private static function translateRegexChar( string &$str, bool $escape = true ): string {
		$peek = self::peek( $str );
		if ( $peek == '\\' ) {
			$c = self::eat( $str, '\\' );
			self::eat( $str, $c );
			# XXX check that $c is a reasonable escape sequence, not
			# something special like \w \b etc.
			if ( $c === '.' ) {
				return $escape ? '{.}' : $c;
			} elseif ( $c === 'b' && $escape ) {
				return '[br|.#.]';
			} else {
				throw new BadEscapeException( $c );
			}
		} else {
			self::eat( $str );
			return $escape ? ( '{' . $peek . '}' ) : $peek;
		}
	}

	/**
	 * Ad-hoc regex parser to translate from PHP regexes to foma regexes.
	 * This does "base" expressions (parenthesized subexpressions,
	 * character classes, or individual characters).
	 * @param string &$str The input string (PHP regex)
	 * @return string The translated regexp (FOMA regex)
	 */
	private static function translateRegexBase( string &$str ): string {
		$peek = self::peek( $str );
		if ( $peek == '(' ) {
			self::eat( $str, '(' );
			$r = self::translateRegex( $str );
			self::eat( $str, ')' );
			return "[ $r ]";
		} elseif ( $peek == '[' ) {
			self::eat( $str, '[' );
			$r = self::translateRegexClass( $str );
			self::eat( $str, ']' );
			return "[$r]";
		} else {
			# XXX figure out if this needs to be escaped further
			return self::translateRegexChar( $str, true );
		}
	}

	/**
	 * Ad-hoc regex parser to translate from PHP regexes to foma regexes.
	 * This does "factor" expressions (a base expression followed by
	 * a multiplicity operator such as * + ?).
	 * @param string &$str The input string (PHP regex)
	 * @return string The translated regexp (FOMA regex)
	 */
	private static function translateRegexFactor( string &$str ): string {
		$base = self::translateRegexBase( $str );
		$peek = self::peek( $str );
		while ( $peek == '*' || $peek == '+' || $peek == '?' ) {
			self::eat( $str, $peek );
			if ( $peek == '?' ) {
				$base = "(" . $base . ")";
			} else {
				$base .= $peek;
			}
			$peek = self::peek( $str );
		}
		return $base;
	}

	/**
	 * Ad-hoc regex parser to translate from PHP regexes to foma regexes.
	 * This does "term" expressions (a list of factors).
	 * @param string &$str The input string (PHP regex)
	 * @param bool $noParens Whether to stop parsing as soon as an open paren
	 *   is seen.
	 * @return string The translated regexp (FOMA regex)
	 */
	private static function translateRegexTerm( string &$str, bool $noParens = false ): string {
		$factor = '';
		$peek = self::peek( $str );
		while ( $peek != '' && $peek != ')' && $peek != '|' ) {
			if ( $noParens && $peek === '(' ) {
				break;
			}
			$nextFactor = self::translateRegexFactor( $str );
			if ( $factor != '' ) {
				$factor .= " ";
			}
			$factor .= $nextFactor;
			$peek = self::peek( $str );
		}
		return $factor;
	}

	/**
	 * Ad-hoc regex parser to translate from PHP regexes to foma regexes.
	 * This does "term" expressions separated by '|'.
	 * @param string &$str The input string (PHP regex)
	 * @param bool $noParens Whether to stop parsing as soon as an open paren
	 *   is seen.
	 * @return string The translated regexp (FOMA regex)
	 */
	private static function translateRegex( string &$str, bool $noParens = false ): string {
		$term = self::translateRegexTerm( $str, $noParens );
		if ( self::peek( $str ) == '|' ) {
			self::eat( $str, '|' );
			$term2 = self::translateRegex( $str, $noParens );
			return $term . " | " . $term2;
		}
		return $term;
	}

	/**
	 * Translate from PHP regexes to foma regexes.
	 * @param string $s The input string (PHP regex)
	 * @return string The translated regexp (FOMA regex)
	 */
	public static function translate( string $s ): string {
		return self::translateRegex( $s );
	}

	/**
	 * Handle translation of a regex with a parenthesized subexpression.
	 * @param string $str
	 * @return string[]
	 */
	private static function translateSplit( string $str ): array {
		$r = [ '' ];
		$c = self::peek( $str );
		while ( $c !== '' ) {
			if ( $c === '(' ) {
				$r[] = self::translateRegexBase( $str );
				$r[] = '';
			} else {
				if ( $r[count( $r ) - 1] != '' ) {
					$r[count( $r ) - 1] .= " ";
				}
				$r[count( $r ) - 1] .= self::translateRegex( $str, true );
			}
			$c = self::peek( $str );
		}
		return $r;
	}

	/**
	 * Split replacement string around dollar expression.
	 * @param string $str
	 * @return string[]
	 */
	private static function dollarSplit( string $str ): array {
		$i = mb_ord( '1' );
		$r = [ '' ];
		$c = self::peek( $str );
		while ( $c !== '' ) {
			if ( $c === '$' ) {
				$c = self::eat( $str, '$' );
				Assert::invariant( $c === mb_chr( $i ), "replacements out of order" );
				$i++;
				$r[] = '_';
				$r[] = '';
			} else {
				$r[count( $r ) - 1] .= $c;
			}
			$c = self::eat( $str );
		}
		return $r;
	}

	/**
	 * Emit a foma-style definition to stdout with the given name based on
	 * the replacement array, as would be provided to strtr.
	 * (Note that strtr does "longest possible" replacement.)
	 * @param string $name The name of the definition in the foma output
	 * @param array<string,string> $mapArray An array whose keys are
	 *   strings and whose values are the desired replacement strings.
	 */
	public function emitFomaRepl( string $name, array $mapArray ): void {
		$first = true;
		echo( "define $name [\n" );
		foreach ( $mapArray as $from => $to ) {
			if ( !$first ) {
				echo( " ,,\n" );
			}
			echo( "  {" . $from . "} @-> {" . $to . "}" );
			$first = false;
		}
		echo( "\n];\n" );
	}

	/**
	 * Emit a foma-style definition to stdout with the given name based on
	 * the regex replacement array, as would be provided to preg_replace()
	 * with array arguments, except that the array is provides as key-value
	 * mappings from regexp to replacement (preg_replace takes two parallel
	 * arrays).
	 * (Note that preg_replace doesn't do "longest possible" replacement;
	 * instead it processes the regexps strictly in order.)
	 * @param string $name The name of the definition in the foma output
	 * @param array<string,string> $patArray An array whose keys are
	 *   regexps and whose values are the desired replacement strings.
	 */
	public function emitFomaRegex( string $name, array $patArray ): void {
		# break up large arrays to avoid segfaults in foma (!)
		if ( count( $patArray ) > 100 ) {
			$r = [];
			foreach ( array_chunk( $patArray, 100, true ) as $chunk ) {
				$n = $name . "'" . ( count( $r ) + 1 );
				$r[] = "$n(br)";
				self::emitFomaRegex( $n, $chunk );
			}
			echo( "define $name(br) " . implode( ' .o. ', $r ) . ";\n" );
			return;
		}
		$first = true;
		echo( "define $name(br) [\n" );
		foreach ( $patArray as $from => $to ) {
			$from = preg_replace( '/^\/|\/u$/', '', $from, 2, $cnt );
			Assert::invariant( $cnt === 2, "missing regex delimiters: $from $cnt" );
			$from = preg_replace( '/^\\\\b/', '', $from, 1, $startAnchor );
			$from = preg_replace( '/\\\\b$/', '', $from, 1, $endAnchor );
			if ( $first ) {
				$first = false;
			} else {
				echo( " .o.\n" );
			}
			if ( preg_match( '/[$]\d/', $to ) ) {
				try {
					$from = $this->translateSplit( $from );
				} catch ( BadRegexException $ex ) {
					echo( "# SKIPPING $from -> $to\n" );
					$first = true;
					continue;
				}
				$to = self::dollarSplit( $to );
				# Convert identical parts to context
				for ( $i = 0; $i < count( $from ); $i += 2 ) {
					if ( $from[$i] == ( '{' . $to[$i] . '}' ) ) {
						array_splice( $from, $i + 1, 0, [ '' ] );
						array_splice( $from, $i, 0, [ '' ] );
						array_splice( $to, $i, 1, [ '', '_', '' ] );
					}
				}
				for ( $i = 0; $i < count( $from ); $i += 2 ) {
					if ( $to[$i] !== '' || $from[$i] !== '' ) {
						break;
					}
				}
				Assert::invariant(
					$i < count( $from ) && $i < count( $to ),
					"Can't find replace string"
				);
				$f = $from[$i] ?: '[..]';
				$t = $to[$i] ? ( '{' . $to[$i] . '}' ) : '0';
				echo( "  [ $f -> $t ||" );
				if ( $startAnchor ) {
					echo( " [.#.|br]" );
				}
				for ( $j = 0; $j < count( $from ); $j += 2 ) {
					if ( $j === $i ) {
						echo( ' _' );
					} else {
						Assert::invariant( $from[$j] === '', "Bad from part" );
						Assert::invariant( $to[$j] === '', "Bad to part" );
					}
					if ( $j + 1 < count( $from ) ) {
						echo( ' ' );
						echo( $from[$j + 1] );
					}
				}
				if ( $endAnchor ) {
					echo( " [br|.#.]" );
				}
				echo( ' ]' );
				continue;
			}
			if ( preg_match( '/[\\[\\(\\*\\+\\?\\\\]/u', $from ) ) {
				try {
					$r = $this->translate( $from );
				} catch ( BadRegexException $ex ) {
					echo( "# SKIPPING $from -> $to\n" );
					$first = true;
					continue;
				}
				echo( '  [ [' . $r . '] @-> {' . $to . '}' );
			} else {
				echo( '  [ {' . $from . '} @-> {' . $to . '}' );
			}
			if ( $startAnchor || $endAnchor ) {
				echo( ' || ' );
				if ( $startAnchor ) {
					echo( "[.#.|br] " );
				}
				echo( '_' );
				if ( $endAnchor ) {
					echo( " [br|.#.]" );
				}
			}
			echo( ' ]' );
		}
		echo( "\n];\n" );
	}

	/** @inheritDoc */
	public function execute() {
		$crh = Language::factory( 'crh' );
		$converter = $crh->getConverter();

		$this->emitFomaRepl( "CRH'LATN'EXCEPTIONS", $converter->mCyrl2LatnExceptions );
		$this->emitFomaRepl( "CRH'CYRL'EXCEPTIONS", $converter->mLatn2CyrlExceptions );
		# regular expressions
		$this->emitFomaRegex( "CRH'LATN'PATTERNS", $converter->mCyrl2LatnPatterns );
		$this->emitFomaRegex( "CRH'CYRL'PATTERNS", $converter->mLatn2CyrlPatterns );
	}
}

$maintClass = DumpCrh::class;
require_once RUN_MAINTENANCE_IF_MAIN;
