<?php

declare( strict_types = 1 );
// phpcs:disable MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic
// phpcs:disable MediaWiki.Commenting.FunctionComment.WrongStyle
// phpcs:disable MediaWiki.Commenting.PropertyDocumentation.MissingDocumentationPublic
// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
// phpcs:disable PSR2.Methods.MethodDeclaration.Underscore

namespace Wikimedia\Dodo;

/* TODO PORT:
* The Window is not a particularly relevant interface for our
* use case of building and manipulating a document quickly.
* We will, afaik, not need any of the interfaces provided here,
* since we are operating in a somewhat "headless" mode.
*
* Consider eliminating this, or stubbing it.
*/

/*
 use Dodo\interfaces\DOMImplementation;
 use Dodo\interfaces\EventTarget;

 use Dodo\interfaces\browser\Location;
 use Dodo\interfaces\browser\History;
 use Dodo\interfaces\browser\Console;
 use Dodo\interfaces\browser\NavigatorID;

 use Dodo\utils;

 require_once("interfaces/DOMImplementation.php");
 require_once("interfaces/EventTarget.php");

 require_once("interfaces/browser/Console.php");
 require_once("interfaces/browser/History.php");
 require_once("interfaces/browser/Location.php");
 require_once("interfaces/browser/NavigatorID.php");
*/

class Window extends EventTarget {
	public $document;
	public $console = null;
	public $history = null;
	public $navigator = null;

	/**
	 * @param Document|null $doc
	 */
	public function __construct( Document $doc = null ) {
		if ( $doc == null ) {
			$doc = new Document();
			$doc->_setContentType( 'text/html', true );
		}

		$this->document = $doc;
		/*
		  $this->document->_scripting_enabled = true;
		  $this->document->defaultView = $this;

		  if ( !$this->document->_address ) {
		  $this->document->_address = "about:blank";
		  }

		  // Instantiate sub-objects
		  $this->location = new Location( $this, $this->document->_address );
		  $this->console = new Console(); // not implemented
		  $this->history = new History(); // not implemented
		  $this->navigator = new NavigatorID();

		  // Self-referential properties; moved from prototype in port
		  $this->window = $this;
		  $this->self = $this;
		  $this->frames = $this;

		  // Self-referential properties for a top-level window
		  $this->parent = $this;
		  $this->top = $this;

		  // We don't support any other windows for now
		  $this->length = 0;              // no frames
		  $this->frameElement = null;     // not part of a frame
		  $this->opener = null;           // not opened by another window
		*/
	}

	public function _run( $code, $file ) {
		/* Original JS: */
		/*
		 * This was used only for the testharness, I think we
		 * will have another way to do this here.
		 */
		/*
		  if ($file) {
		  $code += '\n//@ sourceURL=' + $file;
		  }
		  with($this) eval($code);
		*/
	}

	/*
	 * The onload event handler.
	 * TODO: Need to support a bunch of other event types, too, and have
	 * them interoperate with document.body.
	 */
	public function onload( $handler = null ) {
		/*
		if ( $handler == null ) {
			// From the EventTarget parent class
			return $this->_getEventHandler( "load" );
		} else {
			return $this->_setEventHandler( "load", $handler );
		}
		*/
	}

	/* TODO: This is a completely broken implementation */
	public function getComputedStyle( $elt ) {
		return $elt->style;
	}
}

/* TODO: Make this work with Window properly */
//utils.expose(require('./WindowTimers'), Window);
//utils.expose(require('./impl'), Window);
