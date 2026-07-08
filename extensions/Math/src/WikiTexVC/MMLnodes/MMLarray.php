<?php

namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

class MMLarray extends MMLbase {

	/**
	 * Constructor for array wrapper of MMLbase (and string) elements
	 *
	 * @param MMLbase|string|null ...$children MMLbase child elements ...$children
	 */
	public function __construct( ...$children ) {
		parent::__construct( '', '', [], ...$children );
	}

	public function __toString(): string {
		return implode( '', $this->children );
	}

	/**
	 * An array behaves differently as it's not a real MathML element.
	 * Is empty if all of its children are empty (null, empty string, or empty MMLbase element).
	 */
	public function isEmpty(): bool {
		$empty = true;
		foreach ( $this->children as $child ) {
			if ( $child === null ||
				( is_string( $child ) && trim( $child ) === '' ) ||
				( $child instanceof MMLbase && $child->isEmpty() ) ) {
				continue;
			} else {
				$empty = false;
				break;
			}
		}
		return $empty;
	}

	public function getTextContent(): string {
		$tmp = '';
		foreach ( $this->getChildren() as $child ) {
			if ( $child instanceof MMLbase ) {
				$tmp .= $child->getTextContent();
			} elseif ( is_string( $child ) ) {
				$tmp .= $child;
			}
		}
		return $tmp;
	}
}
