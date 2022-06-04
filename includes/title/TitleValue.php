<?php
/**
 * Representation of a page title within MediaWiki.
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
 * @author Daniel Kinzler
 */
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageReference;
use Wikimedia\Assert\Assert;
use Wikimedia\Assert\ParameterAssertionException;
use Wikimedia\Assert\ParameterTypeException;

/**
 * Represents a page (or page fragment) title within MediaWiki.
 *
 * @note In contrast to Title, this is designed to be a plain value object. That is,
 * it is immutable, does not use global state, and causes no side effects.
 *
 * @newable
 *
 * @see https://www.mediawiki.org/wiki/Requests_for_comment/TitleValue
 * @since 1.23
 */
class TitleValue implements LinkTarget {

	/** @var int */
	private $namespace;

	/** @var string */
	private $dbkey;

	/** @var string */
	private $fragment;

	/** @var string */
	private $interwiki;

	/**
	 * Text form including namespace/interwiki, initialised on demand
	 *
	 * Only public to share cache with TitleFormatter
	 *
	 * @internal
	 * @var string
	 */
	public $prefixedText = null;

	/**
	 * Constructs a TitleValue, or returns null if the parameters are not valid.
	 *
	 * @note This does not perform any normalization, and only basic validation.
	 * For full normalization and validation, use TitleParser::makeTitleValueSafe().
	 *
	 * @param int $namespace The namespace ID. This is not validated.
	 * @param string $title The page title in either DBkey or text form. No normalization is applied
	 *   beyond underscore/space conversion.
	 * @param string $fragment The fragment title. Use '' to represent the whole page.
	 *   No validation or normalization is applied.
	 * @param string $interwiki The interwiki component.
	 *   No validation or normalization is applied.
	 * @return TitleValue|null
	 * @throws InvalidArgumentException
	 */
	public static function tryNew( $namespace, $title, $fragment = '', $interwiki = '' ) {
		if ( !is_int( $namespace ) ) {
			throw new ParameterTypeException( '$namespace', 'int' );
		}

		try {
			return new static( $namespace, $title, $fragment, $interwiki );
		} catch ( ParameterAssertionException $ex ) {
			return null;
		}
	}

	/**
	 * Create a TitleValue from a local PageReference.
	 *
	 * @note The PageReference may belong to another wiki. In that case, the resulting TitleValue
	 *       is also logically bound to that other wiki. No attempt is made to map the
	 *       PageReference wiki ID to an interwiki prefix for the TitleValue.
	 *
	 * @since 1.36
	 * @param PageReference $page
	 * @return TitleValue
	 */
	public static function newFromPage( PageReference $page ): TitleValue {
		return new TitleValue( $page->getNamespace(), $page->getDBkey() );
	}

	/**
	 * Casts a PageReference to a LinkTarget.
	 *
	 * If $page is null, null is returned.
	 * If $page is also an instance of LinkTarget, $page is returned unchanged.
	 *
	 * @see newFromPage()
	 * @since 1.37
	 * @param PageReference|null $page
	 * @return LinkTarget|null
	 */
	public static function castPageToLinkTarget( ?PageReference $page ): ?LinkTarget {
		if ( !$page || $page instanceof LinkTarget ) {
			return $page;
		}

		return self::newFromPage( $page );
	}

	/**
	 * Construct a TitleValue.
	 *
	 * @note TitleValue expects a valid namespace and name; typically, a TitleValue is constructed
	 * either from a database entry, or by a TitleParser. For constructing a TitleValue from user
	 * input or external sources, use a TitleParser.
	 *
	 * @stable to call
	 * @param int $namespace The namespace ID. This is not validated.
	 * @param string $title The page title in either DBkey or text form. No normalization is applied
	 *   beyond underscore/space conversion.
	 * @param string $fragment The fragment title. Use '' to represent the whole page.
	 *   No validation or normalization is applied.
	 * @param string $interwiki The interwiki component.
	 *   No validation or normalization is applied.
	 * @throws InvalidArgumentException
	 */
	public function __construct( $namespace, $title, $fragment = '', $interwiki = '' ) {
		self::assertValidSpec( $namespace, $title, $fragment, $interwiki );

		$this->namespace = $namespace;
		$this->dbkey = strtr( $title, ' ', '_' );
		$this->fragment = $fragment;
		$this->interwiki = $interwiki;
	}

	/**
	 * Assert that the given parameters could be used to construct a TitleValue object.
	 *
	 * Performs basic syntax and consistency checks. Does not perform full validation,
	 * use TitleParser::makeTitleValueSafe() for that.
	 *
	 * @param int $namespace
	 * @param string $title
	 * @param string $fragment
	 * @param string $interwiki
	 * @throws InvalidArgumentException if the combination of parameters is not valid for
	 *  constructing a TitleValue.
	 */
	public static function assertValidSpec( $namespace, $title, $fragment = '', $interwiki = '' ) {
		if ( !is_int( $namespace ) ) {
			throw new ParameterTypeException( '$namespace', 'int' );
		}
		if ( !is_string( $title ) ) {
			throw new ParameterTypeException( '$title', 'string' );
		}
		if ( !is_string( $fragment ) ) {
			throw new ParameterTypeException( '$fragment', 'string' );
		}
		if ( !is_string( $interwiki ) ) {
			throw new ParameterTypeException( '$interwiki', 'string' );
		}

		Assert::parameter( !preg_match( '/^[_ ]|[\r\n\t]|[_ ]$/', $title ), '$title',
			"invalid name '$title'" );

		// NOTE: As of MW 1.34, [[#]] is rendered as a valid link, pointing to the empty
		// page title, effectively leading to the wiki's main page. This means that a completely
		// empty TitleValue has to be considered valid, for consistency with Title.
		// Also note that [[#foo]] is a valid on-page section links, and that [[acme:#foo]] is
		// a valid interwiki link.
		Assert::parameter(
			$title !== '' || $namespace === NS_MAIN,
			'$title',
			'should not be empty unless namespace is main'
		);
	}

	public function getNamespace(): int {
		return $this->namespace;
	}

	public function inNamespace( int $ns ): bool {
		return $this->namespace == $ns;
	}

	public function getFragment(): string {
		return $this->fragment;
	}

	public function hasFragment(): bool {
		return $this->fragment !== '';
	}

	public function getDBkey(): string {
		return $this->dbkey;
	}

	public function getText(): string {
		return str_replace( '_', ' ', $this->dbkey );
	}

	public function createFragmentTarget( string $fragment ): self {
		return new TitleValue(
			$this->namespace,
			$this->dbkey,
			$fragment,
			$this->interwiki
		);
	}

	public function isExternal(): bool {
		return $this->interwiki !== '';
	}

	public function getInterwiki(): string {
		return $this->interwiki;
	}

	/**
	 * Returns a string representation of the title, for logging. This is purely informative
	 * and must not be used programmatically. Use the appropriate TitleFormatter to generate
	 * the correct string representation for a given use.
	 *
	 * @since 1.23
	 * @return string
	 */
	public function __toString(): string {
		$name = $this->namespace . ':' . $this->dbkey;

		if ( $this->fragment !== '' ) {
			$name .= '#' . $this->fragment;
		}

		if ( $this->interwiki !== '' ) {
			$name = $this->interwiki . ':' . $name;
		}

		return $name;
	}

	public function isSameLinkAs( LinkTarget $other ): bool {
		// NOTE: keep in sync with Title::isSameLinkAs()!
		return ( $other->getInterwiki() === $this->getInterwiki() )
			&& ( $other->getDBkey() === $this->getDBkey() )
			&& ( $other->getNamespace() === $this->getNamespace() )
			&& ( $other->getFragment() === $this->getFragment() );
	}
}
