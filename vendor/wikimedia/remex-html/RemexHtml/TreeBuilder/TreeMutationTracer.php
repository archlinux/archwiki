<?php

namespace RemexHtml\TreeBuilder;
use RemexHtml\Tokenizer\Attributes;

/**
 * This is a debugging helper class which calls the supplied callback function
 * each time there is a TreeHandler event, giving a descriptive message. It
 * then forwards the event through to the supplied handler.
 */
class TreeMutationTracer implements TreeHandler {

	/**
	 * Constructor.
	 *
	 * @param TreeHandler $handler The next pipeline stage
	 * @param callable $callback The message output function
	 * @param integer $verbosity Set to non-zero to call dump() on the handler
	 *   before and after each event.
	 */
	public function __construct( TreeHandler $handler, callable $callback, $verbosity = 0 ) {
		$this->handler = $handler;
		$this->callback = $callback;
		$this->verbosity = $verbosity;
	}

	/**
	 * Send a message
	 */
	private function trace( $msg ) {
		call_user_func( $this->callback, "[Tree] $msg" );
	}

	/**
	 * Get a debug tag for an element or null
	 *
	 * @param Element|null $element
	 */
	private function getDebugTag( $element ) {
		return $element ? $element->getDebugTag() : '';
	}

	/**
	 * Get a short excerpt of some text
	 *
	 * @param string $text
	 * @return string
	 */
	private function excerpt( $text ) {
		if ( strlen( $text ) > 20 ) {
			$text = substr( $text, 0, 20 ) . '...';
		}
		return str_replace( "\n", "\\n", $text );
	}

	/**
	 * Get a readable version of the TreeBuilder preposition constants
	 */
	private function getPrepositionName( $prep ) {
		$names = [
			TreeBuilder::BEFORE => 'before',
			TreeBuilder::UNDER => 'under',
			TreeBuilder::ROOT => 'under root'
		];
		return isset( $names[$prep] ) ? $names[$prep] : '???';
	}

	/**
	 * A helper called before the underlying handler is called.
	 */
	private function before() {
		if ( $this->verbosity > 0 ) {
			$this->trace( "Before: " . $this->handler->dump() . "\n" );
		}
	}

	/**
	 * A helper called after the underlying handler is called.
	 */
	private function after() {
		if ( $this->verbosity > 0 ) {
			$this->trace( "After:  " . $this->handler->dump() . "\n" );
		}
	}

	public function startDocument( $fns, $fn ) {
		$this->trace( "startDocument" );
		call_user_func_array( [ $this->handler, __FUNCTION__ ], func_get_args() );
	}

	public function endDocument( $pos ) {
		$this->trace( "endDocument $pos" );
		call_user_func_array( [ $this->handler, __FUNCTION__ ], func_get_args() );
	}

	public function characters( $preposition, $refNode, $text, $start, $length,
		$sourceStart, $sourceLength
	) {
		$excerpt = $this->excerpt( substr( $text, $start, $length ) );
		$prepName = $this->getPrepositionName( $preposition );
		$refTag = $this->getDebugTag( $refNode );

		$this->trace( "characters \"$excerpt\", $prepName $refTag, start=$sourceStart" );
		$this->before();
		call_user_func_array( [ $this->handler, __FUNCTION__ ], func_get_args() );
		$this->after();
	}

	public function insertElement( $preposition, $refNode, Element $element, $void,
		$sourceStart, $sourceLength
	) {
		$prepName = $this->getPrepositionName( $preposition );
		$refTag = $this->getDebugTag( $refNode );
		$elementTag = $this->getDebugTag( $element );
		$voidMsg = $void ? 'void' : '';
		$this->trace( "insert $elementTag $voidMsg, $prepName $refTag, start=$sourceStart" );
		$this->before();
		call_user_func_array( [ $this->handler, __FUNCTION__ ], func_get_args() );
		$this->after();
	}

	public function endTag( Element $element, $sourceStart, $sourceLength ) {
		$elementTag = $this->getDebugTag( $element );
		$this->trace( "end $elementTag, start=$sourceStart" );
		$this->before();
		call_user_func_array( [ $this->handler, __FUNCTION__ ], func_get_args() );
		$this->after();
	}

	public function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
		$quirksTypes = [
			TreeBuilder::QUIRKS => 'quirks',
			TreeBuilder::NO_QUIRKS => 'no-quirks',
			TreeBuilder::LIMITED_QUIRKS => 'limited-quirks'
		];
		$quirksMsg = $quirksTypes[$quirks];
		$this->trace( "doctype $name, public=\"$public\", system=\"$system\", " .
			"$quirksMsg, start=$sourceStart" );
		$this->before();
		call_user_func_array( [ $this->handler, __FUNCTION__ ], func_get_args() );
		$this->after();
	}

	public function comment( $preposition, $refNode, $text, $sourceStart, $sourceLength ) {
		$prepName = $this->getPrepositionName( $preposition );
		$refTag = $this->getDebugTag( $refNode );
		$excerpt = $this->excerpt( $text );

		$this->trace( "comment \"$excerpt\", $prepName $refTag, start=$sourceStart" );
		$this->before();
		call_user_func_array( [ $this->handler, __FUNCTION__ ], func_get_args() );
		$this->after();
	}

	public function error( $text, $pos ) {
		$this->trace( "error \"$text\", start=$pos" );
		call_user_func_array( [ $this->handler, __FUNCTION__ ], func_get_args() );
	}

	public function mergeAttributes( Element $element, Attributes $attrs, $sourceStart ) {
		$elementTag = $this->getDebugTag( $element );
		$this->trace( "merge $elementTag, start=$sourceStart" );
		$this->before();
		call_user_func_array( [ $this->handler, __FUNCTION__ ], func_get_args() );
	}

	public function removeNode( Element $element, $sourceStart ) {
		$elementTag = $this->getDebugTag( $element );
		$this->trace( "remove $elementTag, start=$sourceStart" );
		$this->before();
		call_user_func_array( [ $this->handler, __FUNCTION__ ], func_get_args() );
		$this->after();
	}

	public function reparentChildren( Element $element, Element $newParent, $sourceStart ) {
		$elementTag = $this->getDebugTag( $element );
		$newParentTag = $this->getDebugTag( $newParent );
		$this->trace( "reparent children of $elementTag under $newParentTag, start=$sourceStart" );
		$this->before();
		call_user_func_array( [ $this->handler, __FUNCTION__ ], func_get_args() );
		$this->after();
	}
}
