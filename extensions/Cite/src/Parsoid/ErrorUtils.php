<?php
declare( strict_types = 1 );

namespace Cite\Parsoid;

use Wikimedia\Message\MessageValue;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Ext\DOMDataUtils;
use Wikimedia\Parsoid\Ext\DOMUtils;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\NodeData\DataMwError;
use Wikimedia\Parsoid\Utils\DOMCompat;

/**
 * @license GPL-2.0-or-later
 */
class ErrorUtils {

	private ParsoidExtensionAPI $extApi;

	public function __construct( ParsoidExtensionAPI $extApi ) {
		$this->extApi = $extApi;
	}

	/**
	 * Tracks a list of errors and stores it as part of the DataMw structure in the DOM element.
	 *
	 * @param Element $node
	 * @param DataMwError[] $errs
	 */
	public static function addErrorsToNode( Element $node, array $errs ): void {
		// Nothing to add
		if ( !$errs ) {
			return;
		}

		DOMUtils::addTypeOf( $node, 'mw:Error' );
		$dmw = DOMDataUtils::getDataMw( $node );
		$dmw->errors = array_merge( $dmw->errors ?? [], $errs );
	}

	/**
	 * Traverse into all the embedded content and mark up the refs in there
	 * that have errors that weren't known before the content was serialized.
	 *
	 * Some errors are only known at the time when we're inserting the
	 * references lists, at which point, embedded content has already been
	 * serialized and stored, so we no longer have live access to it.  We
	 * therefore map about ids to errors for a ref at that time, and then do
	 * one final walk of the dom to peak into all the embedded content and
	 * mark up the errors where necessary.
	 */
	public function addEmbeddedErrors( ReferencesData $refsData, Node $node ): void {
		// Either nothing to add or nothing to add to; stop recursing deeper
		if ( !$refsData->embeddedErrors || !$node->hasChildNodes() ) {
			return;
		}

		$processEmbeddedErrors = function ( DocumentFragment $domFragment ) use ( $refsData ) {
			$this->addEmbeddedErrors( $refsData, $domFragment );
			return true;
		};

		$child = $node->firstChild;
		while ( $child ) {
			$nextChild = $child->nextSibling;
			if ( $child instanceof Element ) {
				$this->extApi->processAttributeEmbeddedDom( $child, $processEmbeddedErrors );
				if ( DOMUtils::hasTypeOf( $child, 'mw:Extension/ref' ) ) {
					$about = DOMCompat::getAttribute( $child, 'about' );
					'@phan-var string $about';
					$errs = $refsData->embeddedErrors[$about] ?? [];
					self::addErrorsToNode( $child, $errs );
				}

				// Recursion
				$this->addEmbeddedErrors( $refsData, $child );
			}
			$child = $nextChild;
		}
	}

	/**
	 * Adds classes and lead on an existing Parsoid rendering of an error message, sets the tracking
	 * category and returns the completed fragment
	 *
	 * @param MessageValue|DataMwError $error
	 * @return DocumentFragment
	 */
	public function renderParsoidError( object $error ): DocumentFragment {
		if ( $error instanceof DataMwError ) {
			$error = new MessageValue( $error->key, $error->params );
		}

		$this->extApi->addTrackingCategory( 'cite-tracking-category-cite-error' );

		$fragment = $this->extApi->createInterfaceI18nFragment( 'cite_error', [ $error ] );
		$fragSpan = DOMCompat::getFirstElementChild( $fragment );
		DOMUtils::addAttributes( $fragSpan, [ 'class' => 'error mw-ext-cite-error' ] );
		return $fragment;
	}
}
