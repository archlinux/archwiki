<?php

namespace Wikimedia\RemexHtml\DOM;

use Wikimedia\RemexHtml\HTMLData;
use Wikimedia\RemexHtml\TreeBuilder\Element;
use Wikimedia\RemexHtml\TreeBuilder\TreeBuilder;

/**
 * A TreeHandler which constructs a DOMDocumentFragment.
 *
 * Note that this class permits third-party `DOMImplementation`s
 * (documents other than `\DOMDocument`, nodes other than `\DOMNode`,
 * etc) and so no enforced PHP type hints are used which name these
 * classes directly.  For the sake of static type checking, the
 * types *in comments* are given as if the standard PHP `\DOM*`
 * classes are being used but at runtime everything is duck-typed.
 */
class DOMFragmentBuilder extends DOMBuilder {
	/** @var \DOMDocumentFragment */
	private $documentFragment;

	/**
	 * @param \DOMDocument $ownerDocument
	 * @param array $options See options for DOMBuilder::__construct
	 */
	public function __construct( $ownerDocument, $options = [] ) {
		$options += [
			'domImplementation' => $ownerDocument->implementation,
			// @phan-suppress-next-line PhanRedundantCondition (could be a different implementation)
			'suppressHtmlNamespace' => $ownerDocument->implementation
				instanceof \DOMImplementation,
		];
		parent::__construct( $options );
		$this->documentFragment = $ownerDocument->createDocumentFragment();
	}

	/** @inheritDoc */
	public function getFragment() {
		return $this->documentFragment;
	}

	/** @inheritDoc */
	protected function createDocument(
		?string $doctypeName = null,
		?string $public = null,
		?string $system = null
	) {
		$doc = $this->documentFragment->ownerDocument;
		// phan thinks $doc is nullable
		'@phan-var \DOMDocument $doc';
		return $doc;
	}

	/** @inheritDoc */
	protected function createNode( Element $element ) {
		if ( $element->namespace === HTMLData::NS_HTML &&
			 $element->name === 'html' ) {
			// Use the DocumentFragment instead of the <html> documentElement
			$element->userData = $this->documentFragment;
			return $this->documentFragment;
		}
		return parent::createNode( $element );
	}

	/** @inheritDoc */
	protected function insertNode( $preposition, $refElement, $node ) {
		if ( $preposition === TreeBuilder::ROOT ) {
			// We don't need to insert the DocumentFragment into anything.
			return;
		}
		parent::insertNode( $preposition, $refElement, $node );
	}
}
