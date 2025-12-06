<?php
namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;

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
		if ( $node instanceof MMLarray ) {
			if ( end( $this->elementStack ) === $this->dom ) {
				throw new InvalidArgumentException( 'MMLarray cannot be a root element' );
			}
			$this->handleChildren( $node );
			return;
		}
		$element = $this->createElement( $node );
		end( $this->elementStack )->appendChild( $element );
		if ( $node instanceof MMLleaf ) {
			$textNode = $this->dom->createTextNode( $node->getText() );
			$element->appendChild( $textNode );
			return;
		}
		$this->elementStack[] = $element;
		$this->handleChildren( $node );
		array_pop( $this->elementStack );
	}

	/**
	 * Add child elements of MMLbase to DOM
	 * @param MMLbase $node
	 * @return void
	 */
	private function handleChildren( MMLbase $node ): void {
		foreach ( $node->getChildren() as $child ) {
			if ( $child === null || $child === '' ) {
				continue;
			}
			if ( $child instanceof MMLbase ) {
				$child->accept( $this );
			} else {
				// use LIBXML_PARSEHUGE if the XML is too big
				if ( strlen( $child ) >= 1792 ) { // smallest XML with depth 256: len(<x></x>)*256
					$tempDoc = new DOMDocument( '1.0', 'UTF-8' );
					$tempDoc->loadXML(
						'<root>' . preg_replace(
							'/&(#(?:x[0-9A-Fa-f]+|\d+);)/',
							'&amp;$1',
							$child . '</root>'
					), LIBXML_PARSEHUGE | LIBXML_COMPACT | LIBXML_NOEMPTYTAG );
					// Import nodes into main document
					$fragment = $this->dom->createDocumentFragment();
					foreach ( $tempDoc->documentElement->childNodes as $childNode ) {
						$fragment->appendChild( $this->dom->importNode( $childNode, true ) );
					}
				} else {
					// Parse string as XML fragment
					$fragment = $this->dom->createDocumentFragment();
					// appendXML automatically converts escaped unicode to unicode
					$fragment->appendXML( preg_replace(
						'/&(#(?:x[0-9A-Fa-f]+|\d+);)/',
						'&amp;$1',
						$child
					) );
				}
				// Only append if fragment has content
				if ( $fragment->hasChildNodes() ) {
					end( $this->elementStack )->appendChild( $fragment );
				}
			}
		}
	}

	public function getHTML(): string {
		// DOM converts escaped Unicode chars like &#x338; to &amp;#x338;. This will revert the change.
		return preg_replace( '/&amp;#(x[0-9A-Fa-f]+|\d+);/',
			'&#$1;',
			$this->dom->saveHTML( $this->dom->documentElement )
		);
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
			$element->setAttribute( strtolower( $name ), $value ?? "" );
		}
		return $element;
	}
}
