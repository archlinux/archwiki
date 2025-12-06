<?php

namespace Wikimedia\RemexHtml\DOM;

use DOMElement;
use DOMNode;

interface DOMFormatter {
	/**
	 * Recursively format a DOMNode.
	 *
	 * @param DOMNode $node The node to format
	 * @return string
	 */
	public function formatDOMNode( $node );

	/**
	 * Non-recursively format a DOMElement.
	 *
	 * @param DOMElement $element The element to format
	 * @param string $contents The formatted contents of the element
	 */
	public function formatDOMElement( $element, $contents );
}
