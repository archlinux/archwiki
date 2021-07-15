<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Core;

use Composer\Semver\Semver;
use DOMDocument;
use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * PORT-FIXME: This is just a placeholder for data that was previously passed
 * to entrypoint in JavaScript.  Who will construct these objects and whether
 * this is the correct interface is yet to be determined.
 */
class PageBundle {
	/** @var string */
	public $html;

	/** @var ?array */
	public $parsoid;

	/** @var ?array */
	public $mw;

	/** @var ?string */
	public $version;

	/** @var ?array */
	public $headers;

	/** @var string|null */
	public $contentmodel;

	/**
	 * @param string $html
	 * @param ?array $parsoid
	 * @param ?array $mw
	 * @param ?string $version
	 * @param ?array $headers
	 * @param ?string $contentmodel
	 */
	public function __construct(
		string $html, ?array $parsoid = null, ?array $mw = null,
		?string $version = null, ?array $headers = null,
		?string $contentmodel = null
	) {
		$this->html = $html;
		$this->parsoid = $parsoid;
		$this->mw = $mw;
		$this->version = $version;
		$this->headers = $headers;
		$this->contentmodel = $contentmodel;
	}

	public function toHtml(): string {
		$doc = DOMUtils::parseHTML( $this->html );
		self::apply( $doc, $this );
		return ContentUtils::toXML( $doc );
	}

	/**
	 * Check if this pagebundle is valid.
	 * @param string $contentVersion Document content version to validate against.
	 * @param ?string &$errorMessage Error message will be returned here.
	 * @return bool
	 */
	public function validate(
		string $contentVersion, ?string &$errorMessage = null
	) {
		if ( !$this->parsoid || !isset( $this->parsoid['ids'] ) ) {
			$errorMessage = 'Invalid data-parsoid was provided.';
			return false;
		} elseif ( Semver::satisfies( $contentVersion, '^999.0.0' )
			&& ( !$this->mw || !isset( $this->mw['ids'] ) )
		) {
			$errorMessage = 'Invalid data-mw was provided.';
			return false;
		}
		return true;
	}

	/**
	 * @return array
	 */
	public function responseData() {
		$responseData = [
			'contentmodel' => $this->contentmodel ?? '',
			'html' => [
				'headers' => array_merge( [
					'content-type' => 'text/html; charset=utf-8; '
						. 'profile="https://www.mediawiki.org/wiki/Specs/HTML/'
						. $this->version . '"',
				], $this->headers ?? [] ),
				'body' => $this->html,
			],
			'data-parsoid' => [
				'headers' => [
					'content-type' => 'application/json; charset=utf-8; '
						. 'profile="https://www.mediawiki.org/wiki/Specs/data-parsoid/'
						. $this->version . '"',
				],
				'body' => $this->parsoid,
			],
		];
		if ( Semver::satisfies( $this->version, '^999.0.0' ) ) {
			$responseData['data-mw'] = [
				'headers' => [
					'content-type' => 'application/json; charset=utf-8; ' .
						'profile="https://www.mediawiki.org/wiki/Specs/data-mw/' .
						$this->version . '"',
				],
				'body' => $this->mw,
			];
		}
		return $responseData;
	}

	/**
	 * Applies the `data-*` attributes JSON structure to the document.
	 * Leaves `id` attributes behind -- they are used by citation code to
	 * extract `<ref>` body from the DOM.
	 *
	 * @param DOMDocument $doc doc
	 * @param PageBundle $pb page bundle
	 */
	public static function apply( DOMDocument $doc, PageBundle $pb ): void {
		DOMUtils::visitDOM(
			DOMCompat::getBody( $doc ),
			function ( DOMNode $node ) use ( &$pb ): void {
				if ( $node instanceof DOMElement ) {
					$id = $node->getAttribute( 'id' ) ?? '';
					if ( isset( $pb->parsoid['ids'][$id] ) ) {
						DOMDataUtils::setJSONAttribute(
							$node, 'data-parsoid', $pb->parsoid['ids'][$id]
						);
					}
					if ( isset( $pb->mw['ids'][$id] ) ) {
						// Only apply if it isn't already set.  This means
						// earlier applications of the pagebundle have higher
						// precedence, inline data being the highest.
						if ( !$node->hasAttribute( 'data-mw' ) ) {
							DOMDataUtils::setJSONAttribute(
								$node, 'data-mw', $pb->mw['ids'][$id]
							);
						}
					}
				}
			}
		);
	}

}
