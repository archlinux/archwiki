<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Indicator;

use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Ext\DiffDOMUtils;
use Wikimedia\Parsoid\Ext\DOMDataUtils;
use Wikimedia\Parsoid\Ext\DOMUtils;
use Wikimedia\Parsoid\Ext\ExtensionModule;
use Wikimedia\Parsoid\Ext\ExtensionTagHandler;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Utils\DOMCompat;

/**
 * Implements the php parser's `indicator` hook natively.
 */
class Indicator extends ExtensionTagHandler implements ExtensionModule {
	/** @inheritDoc */
	public function getConfig(): array {
		return [
			'name' => 'Indicator',
			'tags' => [
				[
					'name' => 'indicator',
					'handler' => self::class,
					'options' => [
						'wt2html' => [
							'embedsDomInAttributes' => true,
							'customizesDataMw' => true,
						],
						'outputHasCoreMwDomSpecMarkup' => true
					],
				]
			],
		];
	}

	/** @inheritDoc */
	public function processAttributeEmbeddedDom(
		ParsoidExtensionAPI $extApi, Element $elt, callable $proc
	): void {
		$dmw = DOMDataUtils::getDataMw( $elt );
		if ( isset( $dmw->html ) ) {
			$dom = $extApi->htmlToDom( $dmw->html );
			$ret = $proc( $dom );
			if ( $ret ) {
				$dmw->html = $extApi->domToHtml( $dom, true, true );
			}
		}
	}

	/** @inheritDoc */
	public function sourceToDom(
		ParsoidExtensionAPI $extApi, string $content, array $args
	): DocumentFragment {
		$dataMw = $extApi->extTag->getDefaultDataMw();
		$kvArgs = $extApi->extArgsToArray( $args );
		$name = $kvArgs['name'] ?? '';
		if ( trim( $name ) === '' ) {
			$out = $extApi->pushError( 'invalid-indicator-name' );
			DOMDataUtils::setDataMw( $out->firstChild, $dataMw );
			return $out;
		}

		// Convert indicator wikitext to DOM
		$domFragment = $extApi->extTagToDOM( [] /* No args to apply */, $content, [
			'parseOpts' => [ 'extTag' => 'indicator' ],
		] );

		// Strip an outer paragraph if it is the sole paragraph without additional attributes
		$content = DiffDOMUtils::firstNonSepChild( $domFragment );
		if ( $content &&
			DOMCompat::nodeName( $content ) === 'p' &&
			DiffDOMUtils::nextNonSepSibling( $content ) === null &&
			$content instanceof Element && // Needed to mollify Phan
			DOMDataUtils::noAttrs( $content )
		) {
			DOMUtils::migrateChildren( $content, $domFragment, $content->nextSibling );
			$domFragment->removeChild( $content );
		}

		// We should embed this directly as a DocumentFragment once
		// T348161 lands.
		$dataMw->html = $extApi->domToHtml( $domFragment, true );

		// Use a meta tag whose data-mw we will stuff this HTML into later.
		// NOTE: Till T214994 is resolved, this HTML will not get processed
		// by all the top-level DOM passes that may need to process this (ex: linting)
		$meta = $domFragment->ownerDocument->createElement( 'meta' );

		DOMDataUtils::setDataMw( $meta, $dataMw );

		DOMCompat::replaceChildren( $domFragment, $meta );
		return $domFragment;
	}
}
