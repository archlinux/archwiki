<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Utils;

use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Wt2Html\Wt2HtmlDOMProcessor;

/**
 * Class for helping us traverse the DOM.
 *
 * This class currently does a pre-order depth-first traversal.
 * See {@link DOMPostOrder} for post-order traversal.
 */
class DOMTraverser implements Wt2HtmlDOMProcessor {
	/**
	 * List of handlers to call on each node. Each handler is an array with the following fields:
	 * - action: a callable to call
	 * - nodeName: if set, only call it on nodes with this name
	 * @var array<array{action:callable,nodeName:string}>
	 * @see addHandler()
	 */
	private $handlers = [];

	/**
	 * @var bool
	 */
	private $traverseWithTplInfo;

	/**
	 * @param bool $traverseWithTplInfo
	 */
	public function __construct( bool $traverseWithTplInfo = false ) {
		$this->traverseWithTplInfo = $traverseWithTplInfo;
	}

	/**
	 * Add a handler to the DOM traverser.
	 *
	 * @param ?string $nodeName An optional node name filter
	 * @param callable $action A callback, called on each node we traverse that matches nodeName.
	 *   Will be called with the following parameters:
	 *   - Node $node: the node being processed
	 *   - Env $env: the parser environment
	 *   - DTState $state: State.
	 *   Return value: Node|null|true.
	 *   - true: proceed normally
	 *   - Node: traversal will continue on the new node (further handlers will not be called
	 *     on the current node); after processing it and its siblings, it will continue with the
	 *     next sibling of the closest ancestor which has one.
	 *   - null: like the Node case, except there is no new node to process before continuing.
	 */
	public function addHandler( ?string $nodeName, callable $action ): void {
		$this->handlers[] = [
			'action' => $action,
			'nodeName' => $nodeName,
		];
	}

	/**
	 * @param Node $node
	 * @param Env $env
	 * @param DTState|null $state
	 * @return bool|mixed
	 */
	private function callHandlers( Node $node, Env $env, ?DTState $state ) {
		$name = DOMCompat::nodeName( $node );
		foreach ( $this->handlers as $handler ) {
			if ( $handler['nodeName'] === null || $handler['nodeName'] === $name ) {
				$result = call_user_func( $handler['action'], $node, $env, $state );
				if ( $result !== true ) {
					// Abort processing for this node
					return $result;
				}
			}
		}
		return true;
	}

	/**
	 * Traverse the DOM and fire the handlers that are registered.
	 *
	 * Handlers can return
	 * - the next node to process: aborts processing for current node (ie. no further handlers are
	 *   called) and continues processing on returned node. Essentially, that node and its siblings
	 *   replace the current node and its siblings for the purposes of the traversal; after they
	 *   are fully processed, the algorithm moves back to the parent of $workNode to look for
	 *   the next sibling.
	 * - `null`: same as above, except it continues from the next sibling of the parent (or if
	 *   that does not exist, the next sibling of the grandparent etc). This is so that returning
	 *   `$workNode->nextSibling` works even when workNode is a last child of its parent.
	 * - `true`: continues regular processing on current node.
	 *
	 * @param Env $env
	 * @param Node $workNode The starting node for the traversal.
	 *   The traversal could go beyond the subtree rooted at $workNode if
	 *   the handlers called during traversal return an arbitrary node elsewhere
	 *   in the DOM in which case the traversal scope can be pretty much the whole
	 *   DOM that $workNode is present in. This behavior would be confusing but
	 *   there is nothing in the traversal code to prevent that.
	 * @param DTState|null $state
	 */
	public function traverse( Env $env, Node $workNode, ?DTState $state = null ) {
		$this->traverseInternal( true, $env, $workNode, $state );
	}

	/**
	 * @param bool $isRootNode
	 * @param Env $env
	 * @param Node $workNode
	 * @param DTState|null $state
	 */
	private function traverseInternal(
		bool $isRootNode, Env $env, Node $workNode, ?DTState $state
	) {
		while ( $workNode !== null ) {
			if ( $this->traverseWithTplInfo && $workNode instanceof Element ) {
				// Identify the first template/extension node.
				// You'd think the !tplInfo check isn't necessary since
				// we don't have nested transclusions, however, you can
				// get extensions in transclusions.
				if (
					!( $state->tplInfo ?? null ) && WTUtils::isFirstEncapsulationWrapperNode( $workNode )
					// Ensure this isn't just a meta marker, since we might
					// not be traversing after encapsulation.  Note that the
					// valid data-mw assertion is the same test as used in
					// cleanup.
					&& ( !WTUtils::isTplMarkerMeta( $workNode ) || DOMDataUtils::validDataMw( $workNode ) )
					// Encapsulation info on sections should not be used to
					// traverse with since it's designed to be dropped and
					// may have expanded ranges.
					&& !WTUtils::isParsoidSectionTag( $workNode )
				) {
					$about = $workNode->getAttribute( 'about' ) ?? '';
					$aboutSiblings = WTUtils::getAboutSiblings( $workNode, $about );
					$state->tplInfo = (object)[
						'first' => $workNode,
						'last' => end( $aboutSiblings ),
						'clear' => false,
					];
				}
			}

			// Call the handlers on this workNode
			if ( $workNode instanceof DocumentFragment ) {
				$possibleNext = true;
			} else {
				$possibleNext = $this->callHandlers( $workNode, $env, $state );
			}

			// We may have walked passed the last about sibling or want to
			// ignore the template info in future processing.
			// In any case, it's up to the handler returning a possible next
			// to figure out.
			if ( $this->traverseWithTplInfo && ( $state->tplInfo->clear ?? false ) ) {
				$state->tplInfo = null;
			}

			if ( $possibleNext === true ) {
				// The 'continue processing' case
				if ( $workNode->hasChildNodes() ) {
					$this->traverseInternal(
						false, $env, $workNode->firstChild, $state
					);
				}
				if ( $isRootNode ) {
					// Confine the traverse to the tree rooted as the root node.
					// `$workNode->nextSibling` would take us outside that.
					$possibleNext = null;
				} else {
					$possibleNext = $workNode->nextSibling;
				}
			} elseif ( $isRootNode && $possibleNext !== $workNode ) {
				$isRootNode = false;
			}

			// Clear the template info after reaching the last about sibling.
			if (
				$this->traverseWithTplInfo &&
				( ( $state->tplInfo->last ?? null ) === $workNode )
			) {
				$state->tplInfo = null;
			}

			$workNode = $possibleNext;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function run(
		Env $env, Node $workNode, array $options = [], bool $atTopLevel = false
	): void {
		$state = new DTState( $options, $atTopLevel );
		$this->traverse( $env, $workNode, $state );
	}
}
