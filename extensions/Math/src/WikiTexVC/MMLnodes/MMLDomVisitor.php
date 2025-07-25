<?php
namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

use DOMDocument;
use DOMElement;
use DOMNode;

class MMLDomVisitor implements MMLVisitor {
	private DOMDocument $dom;
	/** @var DOMNode[] */
	private array $elementStack = [];

	public function __construct() {
		$this->dom = new DOMDocument( '1.0', 'UTF-8' );
		$this->dom->formatOutput = true;
		$this->dom->preserveWhiteSpace = false;
		$this->elementStack[] = $this->dom;
	}

	/**
	 * Visit an MMLbase node and process it.
	 * @param MMLbase $node Node to visit
	 * @throws \DOMException
	 */
	public function visit( MMLbase $node ): void {
		$element = $this->createElement( $node );
		end( $this->elementStack )->appendChild( $element );
		if ( $node instanceof MMLleaf ) {
			$textNode = $this->dom->createTextNode( $node->getText() );
			$element->appendChild( $textNode );
			return;
		}
		$this->elementStack[] = $element;
		foreach ( $node->getChildren() as $child ) {
			if ( $child !== null ) {
				// implicitly calls visit
				$child->accept( $this );
			}
		}
		array_pop( $this->elementStack );
	}

	/**
	 * Get HTML from current node
	 * @return string
	 */
	public function getHTML(): string {
		return trim( $this->dom->saveHTML() );
	}

	/**
	 * Create DOMElement from MMLbase node
	 * @param MMLbase $node
	 * @return DOMElement
	 * @throws \DOMException
	 */
	private function createElement( MMLbase $node ): DOMElement {
		$element = $this->dom->createElement( $node->getName() );
		foreach ( $node->getAttributes() as $name => $value ) {
			$element->setAttribute( $name, htmlspecialchars( $value, ENT_QUOTES ) );
		}
		return $element;
	}
}
