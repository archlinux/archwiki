<?php

namespace MediaWiki\OutputTransform\Stages;

use MediaWiki\Message\Message;
use MediaWiki\OutputTransform\ContentDOMTransformStage;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use Wikimedia\Bcp47Code\Bcp47Code;
use Wikimedia\Bcp47Code\Bcp47CodeValue;
use Wikimedia\Parsoid\DOM\Document;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\NodeData\I18nInfo;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMTraverser;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * Resolves the mw:I18n and mw:LocalizedAttrs to their localised forms
 * @internal
 */
class ParsoidLocalization extends ContentDOMTransformStage {

	public function transformDOM(
		Document $doc, ParserOutput $po, ?ParserOptions $popts, array &$options
	): Document {
		$poLang = $po->getLanguage();
		if ( $poLang == null ) {
			$this->logger->warning( 'Localization pass started on ParserOutput without defined language',
				[
					'pass' => 'Localization',
				] );
			return $doc;
		}
		// TODO this traversal will need to also traverse rich attributes
		$traverser = new DOMTraverser( false, false );
		$traverser->addHandler( null, function ( $node ) use ( $po, $doc, $poLang ) {
			if ( $node instanceof Element ) {
				return $this->localizeElement( $node, $poLang, $doc );
			}
			return true;
		} );
		$traverser->traverse( null, $doc );
		return $doc;
	}

	public function shouldRun( ParserOutput $po, ?ParserOptions $popts, array $options = [] ): bool {
		return ( $options['isParsoidContent'] ?? false );
	}

	/**
	 * @return bool|Element
	 */
	private function localizeElement( Element $node, Bcp47Code $lang, Document $doc ) {
		if ( DOMUtils::hasTypeOf( $node, 'mw:LocalizedAttrs' ) ) {
			$i18nNames = DOMDataUtils::getDataAttrI18nNames( $node );
			if ( count( $i18nNames ) === 0 ) {
				$this->logger->warning( 'node with mw:LocalizedAttrs typeof does not contain localisation data',
					[
						'pass' => 'Localization',
						'node' => $node,
					] );
			}
			foreach ( $i18nNames as $name ) {
				$i18n = DOMDataUtils::getDataAttrI18n( $node, $name );
				if ( $i18n === null ) {
					$this->logger->warning( 'null localization element for attribute ' . $name, [
							'pass' => 'Localization',
							'node' => DOMCompat::getOuterHTML( $node ),
						] );
					continue;
				}
				$frag = $this->localizeI18n( $i18n, $lang, $doc, true );
				$node->setAttribute( $name, $frag->textContent );
			}
		}

		if (
			( $node->tagName === 'span' || $node->tagName === 'div' )
			&& DOMUtils::hasTypeOf( $node, 'mw:I18n' )
		) {
			$i18n = DOMDataUtils::getDataNodeI18n( $node );
			if ( $i18n !== null ) {
				$frag = $this->localizeI18n( $i18n, $lang, $doc, $node->tagName === 'span' );
				$node->appendChild( $frag );
			} else {
				$this->logger->warning( 'element with mw:I18n typeof does not contain i18n data', [
					'pass' => 'Localization',
					'node' => DOMCompat::getOuterHTML( $node ),
				] );
			}
		}
		return true;
	}

	private function localizeI18n( I18nInfo $i18n, Bcp47Code $poLang, Document $doc, bool $inline ): DocumentFragment {
		$msg = Message::newFromKey( $i18n->key, ...( $i18n->params ?? [] ) );
		if ( $i18n->lang === I18nInfo::PAGE_LANG ) {
			$msg = $msg->inLanguage( $poLang );
		} elseif ( $i18n->lang === I18nInfo::USER_LANG ) {
			$msg = $msg->inUserLanguage();
		} else {
			$msg = $msg->inLanguage( new Bcp47CodeValue( $i18n->lang ) );
		}
		$txt = $inline ? $msg->parse() : $msg->parseAsBlock();
		return DOMUtils::parseHTMLToFragment( $doc, $txt );
	}
}
