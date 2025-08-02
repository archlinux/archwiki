<?php

namespace Wikimedia\RemexHtml\Serializer;

use Wikimedia\RemexHtml\Tokenizer\Attributes;
use Wikimedia\RemexHtml\TreeBuilder\Element;
use Wikimedia\RemexHtml\TreeBuilder\TraceFormatter;

class SerializerWithTracer extends Serializer {
	/** @var callable|null */
	private $traceCallback;
	/** @var int */
	private $verbosity;

	/**
	 * @param Formatter $formatter
	 * @param callable|null $errorCallback
	 * @param callable|null $traceCallback
	 * @param int $verbosity
	 */
	public function __construct( Formatter $formatter, $errorCallback = null, $traceCallback = null,
		$verbosity = 0
	) {
		$this->traceCallback = $traceCallback;
		$this->verbosity = $verbosity;
		parent::__construct( $formatter, $errorCallback );
	}

	private function handle( string $funcName, array $args ) {
		$this->trace( TraceFormatter::$funcName( ...$args ) );
		parent::$funcName( ...$args );
		if ( $this->verbosity > 0 && $funcName !== 'endDocument' ) {
			$this->trace( "Dump after $funcName: " . $this->dump() );
		}
	}

	private function trace( string $msg ) {
		( $this->traceCallback )( "[Serializer] $msg" );
	}

	/** @inheritDoc */
	public function startDocument( $fragmentNamespace, $fragmentName ) {
		$this->handle( __FUNCTION__, func_get_args() );
	}

	/** @inheritDoc */
	public function endDocument( $pos ) {
		if ( count( $this->nodes ) ) {
			$nodeTags = '';
			foreach ( $this->nodes as $node ) {
				if ( $nodeTags !== '' ) {
					$nodeTags .= ', ';
				}
				$nodeTags .= $node->getDebugTag();
			}
			$this->trace( "endDocument: unclosed elements: $nodeTags" );
		} else {
			$this->trace( "endDocument: no unclosed elements" );
		}

		$this->handle( __FUNCTION__, func_get_args() );
	}

	/** @inheritDoc */
	public function characters( $preposition, $refElement, $text, $start, $length,
		$sourceStart, $sourceLength
	) {
		$this->handle( __FUNCTION__, func_get_args() );
	}

	/** @inheritDoc */
	public function insertElement( $preposition, $refElement, Element $element, $void,
		$sourceStart, $sourceLength
	) {
		$this->handle( __FUNCTION__, func_get_args() );
	}

	/** @inheritDoc */
	public function endTag( Element $element, $sourceStart, $sourceLength ) {
		$this->handle( __FUNCTION__, func_get_args() );
	}

	/** @inheritDoc */
	public function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
		$this->handle( __FUNCTION__, func_get_args() );
	}

	/** @inheritDoc */
	public function comment( $preposition, $refElement, $text, $sourceStart, $sourceLength ) {
		$this->handle( __FUNCTION__, func_get_args() );
	}

	/** @inheritDoc */
	public function error( $text, $pos ) {
		$this->handle( __FUNCTION__, func_get_args() );
	}

	/** @inheritDoc */
	public function mergeAttributes( Element $element, Attributes $attrs, $sourceStart ) {
		$this->handle( __FUNCTION__, func_get_args() );
	}

	/** @inheritDoc */
	public function removeNode( Element $element, $sourceStart ) {
		$this->handle( __FUNCTION__, func_get_args() );
	}

	/** @inheritDoc */
	public function reparentChildren( Element $element, Element $newParent, $sourceStart ) {
		$this->handle( __FUNCTION__, func_get_args() );
	}
}
