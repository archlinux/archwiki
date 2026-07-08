<?php

namespace MediaWiki\Extension\DiscussionTools;

use DOMException;
use RuntimeException;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\DOM\CharacterData;
use Wikimedia\Parsoid\DOM\Comment;
use Wikimedia\Parsoid\DOM\Document;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\DOM\DocumentType;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\DOM\ProcessingInstruction;
use Wikimedia\Parsoid\DOM\Text;

/**
 * ImmutableRange has a similar API to the DOM Range class.
 *
 * start/endContainer and offsets can be accessed, as can commonAncestorContainer
 * which is lazy evaluated.
 *
 * setStart and setEnd are still available but return a cloned range.
 *
 * @property bool $collapsed
 * @property Node $commonAncestorContainer
 * @property Node $endContainer
 * @property int $endOffset
 * @property Node $startContainer
 * @property int $startOffset
 */
class ImmutableRange {
	private ?Node $mCommonAncestorContainer = null;

	/**
	 * Find the common ancestor container of two nodes
	 *
	 * @return Node Common ancestor container
	 */
	private static function findCommonAncestorContainer( Node $a, Node $b ): Node {
		$ancestorsA = [];
		$ancestorsB = [];

		$parent = $a;
		do {
			// While walking up the parents of $a we found $b is a parent of $a or even identical
			if ( $parent === $b ) {
				return $b;
			}
			$ancestorsA[] = $parent;
		} while ( $parent = $parent->parentNode );

		$parent = $b;
		do {
			// While walking up the parents of $b we found $a is a parent of $b or even identical
			if ( $parent === $a ) {
				return $a;
			}
			$ancestorsB[] = $parent;
		} while ( $parent = $parent->parentNode );

		$node = null;
		// Start with the top-most (hopefully) identical root node, walk down, skip everything
		// that's identical, and stop at the first mismatch
		$indexA = count( $ancestorsA );
		$indexB = count( $ancestorsB );
		while ( $indexA-- && $indexB-- && $ancestorsA[$indexA] === $ancestorsB[$indexB] ) {
			// Remember the last match closest to $a and $b
			$node = $ancestorsA[$indexA];
		}

		if ( !$node ) {
			throw new DOMException( 'Nodes are not in the same document' );
		}

		return $node;
	}

	/**
	 * Get the root ancestor of a node
	 */
	private static function getRootNode( Node $node ): Node {
		while ( $node->parentNode ) {
			$node = $node->parentNode;
			'@phan-var Node $node';
		}

		return $node;
	}

	public function __construct(
		private Node $startNode,
		private int $startOffset,
		private Node $endNode,
		private int $endOffset,
	) {
	}

	/**
	 * @param string $field Field name
	 * @return mixed
	 */
	public function __get( string $field ) {
		switch ( $field ) {
			case 'collapsed':
				return $this->startNode === $this->endNode &&
					$this->startOffset === $this->endOffset;
			case 'commonAncestorContainer':
				if ( !$this->mCommonAncestorContainer ) {
					$this->mCommonAncestorContainer =
						static::findCommonAncestorContainer( $this->startNode, $this->endNode );
				}
				return $this->mCommonAncestorContainer;
			case 'endContainer':
				return $this->endNode;
			case 'endOffset':
				return $this->endOffset;
			case 'startContainer':
				return $this->startNode;
			case 'startOffset':
				return $this->startOffset;
			default:
				throw new RuntimeException( 'Invalid property: ' . $field );
		}
	}

	/**
	 * Clone range with a new start position
	 */
	public function setStart( Node $startNode, int $startOffset ): self {
		return $this->setStartOrEnd( 'start', $startNode, $startOffset );
	}

	/**
	 * Clone range with a new end position
	 */
	public function setEnd( Node $endNode, int $endOffset ): self {
		return $this->setStartOrEnd( 'end', $endNode, $endOffset );
	}

	/**
	 * Sets the start or end boundary point for the Range.
	 *
	 * Ported from https://github.com/TRowbotham/PHPDOM (MIT)
	 * @see https://dom.spec.whatwg.org/#concept-range-bp-set
	 *
	 * @param string $type Which boundary point should be set. Valid values are start or end.
	 * @param Node $node The Node that will become the boundary.
	 * @param int $offset The offset within the given Node that will be the boundary.
	 */
	private function setStartOrEnd( string $type, Node $node, int $offset ): self {
		if ( $node instanceof DocumentType ) {
			throw new DOMException();
		}

		switch ( $type ) {
			case 'start':
				$endContainer = $this->endNode;
				$endOffset = $this->endOffset;
				if (
					self::getRootNode( $this->startNode ) !== self::getRootNode( $node ) ||
					$this->computePosition(
						$node, $offset, $this->endNode, $this->endOffset
					) === 'after'
				) {
					$endContainer = $node;
					$endOffset = $offset;
				}

				return new self(
					$node, $offset, $endContainer, $endOffset
				);

			case 'end':
				$startContainer = $this->startNode;
				$startOffset = $this->startOffset;
				if (
					self::getRootNode( $this->startNode ) !== self::getRootNode( $node ) ||
					$this->computePosition(
						$node, $offset, $this->startNode, $this->startOffset
					) === 'before'
				) {
					$startContainer = $node;
					$startOffset = $offset;
				}

				return new self(
					$startContainer, $startOffset, $node, $offset
				);
		}
	}

	/**
	 * Returns true if only a portion of the Node is contained within the Range.
	 *
	 * Ported from https://github.com/TRowbotham/PHPDOM (MIT)
	 * @see https://dom.spec.whatwg.org/#partially-contained
	 *
	 * @param Node $node The Node to check against.
	 */
	private function isPartiallyContainedNode( Node $node ): bool {
		return CommentUtils::contains( $node, $this->startNode ) xor
			CommentUtils::contains( $node, $this->endNode );
	}

	/**
	 * Returns true if the entire Node is within the Range, otherwise false.
	 *
	 * Ported from https://github.com/TRowbotham/PHPDOM (MIT)
	 * @see https://dom.spec.whatwg.org/#contained
	 *
	 * @param Node $node The Node to check against.
	 */
	private function isFullyContainedNode( Node $node ): bool {
		return static::getRootNode( $node ) === static::getRootNode( $this->startNode )
			&& $this->computePosition( $node, 0, $this->startNode, $this->startOffset ) === 'after'
			&& $this->computePosition(
				// @phan-suppress-next-line PhanUndeclaredProperty
				$node, $node->length ?? $node->childNodes->length,
				$this->endNode, $this->endOffset
			) === 'before';
	}

	/**
	 * Extracts the content of the Range from the node tree and places it in a
	 * DocumentFragment.
	 *
	 * Ported from https://github.com/TRowbotham/PHPDOM (MIT)
	 * @see https://dom.spec.whatwg.org/#dom-range-extractcontents
	 */
	public function extractContents(): DocumentFragment {
		$fragment = $this->startNode->ownerDocument->createDocumentFragment();

		if (
			$this->startNode === $this->endNode
			&& $this->startOffset === $this->endOffset
		) {
			return $fragment;
		}

		$originalStartNode = $this->startNode;
		$originalStartOffset = $this->startOffset;
		$originalEndNode = $this->endNode;
		$originalEndOffset = $this->endOffset;

		if (
			$originalStartNode === $originalEndNode
			&& ( $originalStartNode instanceof Text
				|| $originalStartNode instanceof ProcessingInstruction
				|| $originalStartNode instanceof Comment )
		) {
			$clone = $originalStartNode->cloneNode();
			Assert::precondition( $clone instanceof CharacterData, 'TODO' );
			$clone->data = $originalStartNode->substringData(
				$originalStartOffset,
				$originalEndOffset - $originalStartOffset
			);
			$fragment->appendChild( $clone );
			$originalStartNode->replaceData(
				$originalStartOffset,
				$originalEndOffset - $originalStartOffset,
				''
			);

			return $fragment;
		}

		$commonAncestor = $this->commonAncestorContainer;
		// It should be impossible for common ancestor to be null here since both nodes should be
		// in the same tree.
		Assert::precondition( $commonAncestor !== null, 'TODO' );
		$firstPartiallyContainedChild = null;

		if ( !CommentUtils::contains( $originalStartNode, $originalEndNode ) ) {
			foreach ( $commonAncestor->childNodes as $node ) {
				if ( $this->isPartiallyContainedNode( $node ) ) {
					$firstPartiallyContainedChild = $node;

					break;
				}
			}
		}

		$lastPartiallyContainedChild = null;

		if ( !CommentUtils::contains( $originalEndNode, $originalStartNode ) ) {
			$node = $commonAncestor->lastChild;

			while ( $node ) {
				if ( $this->isPartiallyContainedNode( $node ) ) {
					$lastPartiallyContainedChild = $node;

					break;
				}

				$node = $node->previousSibling;
			}
		}

		$containedChildren = [];

		foreach ( $commonAncestor->childNodes as $childNode ) {
			if ( $this->isFullyContainedNode( $childNode ) ) {
				if ( $childNode instanceof DocumentType ) {
					throw new DOMException();
				}

				$containedChildren[] = $childNode;
			}
		}

		if ( CommentUtils::contains( $originalStartNode, $originalEndNode ) ) {
			$newNode = $originalStartNode;
			$newOffset = $originalStartOffset;
		} else {
			$referenceNode = $originalStartNode;
			$parent = $referenceNode->parentNode;

			while ( $parent && !CommentUtils::contains( $parent, $originalEndNode ) ) {
				$referenceNode = $parent;
				$parent = $referenceNode->parentNode;
			}

			// Note: If reference node’s parent is null, it would be the root of range, so would be an inclusive
			// ancestor of original end node, and we could not reach this point.
			Assert::precondition( $parent !== null, 'TODO' );
			$newNode = $parent;
			$newOffset = CommentUtils::childIndexOf( $referenceNode ) + 1;
		}

		if (
			$firstPartiallyContainedChild instanceof Text
			|| $firstPartiallyContainedChild instanceof ProcessingInstruction
			|| $firstPartiallyContainedChild instanceof Comment
		) {
			// Note: In this case, first partially contained child is original start node.
			Assert::precondition( $originalStartNode instanceof CharacterData, 'TODO' );
			$clone = $originalStartNode->cloneNode();
			Assert::precondition( $clone instanceof CharacterData, 'TODO' );
			$clone->data = $originalStartNode->substringData(
				$originalStartOffset,
				$originalStartNode->length - $originalStartOffset
			);
			$fragment->appendChild( $clone );
			$originalStartNode->replaceData(
				$originalStartOffset,
				$originalStartNode->length - $originalStartOffset,
				''
			);
		} elseif ( $firstPartiallyContainedChild ) {
			$clone = $firstPartiallyContainedChild->cloneNode();
			$fragment->appendChild( $clone );
			$subrange = clone $this;
			$subrange->startNode = $originalStartNode;
			$subrange->startOffset = $originalStartOffset;
			$subrange->endNode = $firstPartiallyContainedChild;
			$subrange->endOffset = count( $firstPartiallyContainedChild->childNodes );
			$subfragment = $subrange->extractContents();
			$clone->appendChild( $subfragment );
		}

		foreach ( $containedChildren as $child ) {
			$fragment->appendChild( $child );
		}

		if (
			$lastPartiallyContainedChild instanceof Text
			|| $lastPartiallyContainedChild instanceof ProcessingInstruction
			|| $lastPartiallyContainedChild instanceof Comment
		) {
			// Note: In this case, last partially contained child is original end node.
			Assert::precondition( $originalEndNode instanceof CharacterData, 'TODO' );
			$clone = $originalEndNode->cloneNode();
			Assert::precondition( $clone instanceof CharacterData, 'TODO' );
			$clone->data = $originalEndNode->substringData( 0, $originalEndOffset );
			$fragment->appendChild( $clone );
			$originalEndNode->replaceData( 0, $originalEndOffset, '' );
		} elseif ( $lastPartiallyContainedChild ) {
			$clone = $lastPartiallyContainedChild->cloneNode();
			$fragment->appendChild( $clone );
			$subrange = clone $this;
			$subrange->startNode = $lastPartiallyContainedChild;
			$subrange->startOffset = 0;
			$subrange->endNode = $originalEndNode;
			$subrange->endOffset = $originalEndOffset;
			$subfragment = $subrange->extractContents();
			$clone->appendChild( $subfragment );
		}

		$this->startNode = $newNode;
		$this->startOffset = $newOffset;
		$this->endNode = $newNode;
		$this->endOffset = $newOffset;

		return $fragment;
	}

	/**
	 * Ported from https://github.com/TRowbotham/PHPDOM (MIT)
	 * @see https://dom.spec.whatwg.org/#dom-range-clonecontents
	 */
	public function cloneContents(): DocumentFragment {
		$ownerDocument = $this->startNode->ownerDocument;
		$fragment = $ownerDocument->createDocumentFragment();

		if ( $this->startNode === $this->endNode
			&& $this->startOffset === $this->endOffset
		) {
			return $fragment;
		}

		$originalStartContainer = $this->startNode;
		$originalStartOffset = $this->startOffset;
		$originalEndContainer = $this->endNode;
		$originalEndOffset = $this->endOffset;

		if ( $originalStartContainer === $originalEndContainer
			&& ( $originalStartContainer instanceof Text
				|| $originalStartContainer instanceof ProcessingInstruction
				|| $originalStartContainer instanceof Comment )
		) {
			$clone = $originalStartContainer->cloneNode();
			$clone->nodeValue = $originalStartContainer->substringData(
				$originalStartOffset,
				$originalEndOffset - $originalStartOffset
			);
			$fragment->appendChild( $clone );

			return $fragment;
		}

		$commonAncestor = static::findCommonAncestorContainer(
			$originalStartContainer,
			$originalEndContainer
		);
		$firstPartiallyContainedChild = null;

		if ( !CommentUtils::contains( $originalStartContainer, $originalEndContainer ) ) {
			foreach ( $commonAncestor->childNodes as $node ) {
				if ( $this->isPartiallyContainedNode( $node ) ) {
					$firstPartiallyContainedChild = $node;
					break;
				}
			}
		}

		$lastPartiallyContainedChild = null;

		// Upstream uses lastChild then iterates over previousSibling, however this
		// is much slower that copying all the nodes to an array, at least when using
		// a native DOMNode, presumably because previousSibling is lazy-evaluated.
		if ( !CommentUtils::contains( $originalEndContainer, $originalStartContainer ) ) {
			$childNodes = iterator_to_array( $commonAncestor->childNodes );

			foreach ( array_reverse( $childNodes ) as $node ) {
				if ( $this->isPartiallyContainedNode( $node ) ) {
					$lastPartiallyContainedChild = $node;
					break;
				}
			}
		}

		$containedChildrenStart = null;
		$containedChildrenEnd = null;

		$child = $firstPartiallyContainedChild ?: $commonAncestor->firstChild;
		for ( ; $child; $child = $child->nextSibling ) {
			if ( $this->isFullyContainedNode( $child ) ) {
				$containedChildrenStart = $child;
				break;
			}
		}

		$child = $lastPartiallyContainedChild ?: $commonAncestor->lastChild;
		for ( ; $child !== $containedChildrenStart; $child = $child->previousSibling ) {
			if ( $this->isFullyContainedNode( $child ) ) {
				$containedChildrenEnd = $child;
				break;
			}
		}
		if ( !$containedChildrenEnd ) {
			$containedChildrenEnd = $containedChildrenStart;
		}

		// $containedChildrenStart and $containedChildrenEnd may be null here, but this loop still works correctly
		for ( $child = $containedChildrenStart; $child !== $containedChildrenEnd; $child = $child->nextSibling ) {
			if ( $child instanceof DocumentType ) {
				throw new DOMException();
			}
		}

		if ( $firstPartiallyContainedChild instanceof Text
			|| $firstPartiallyContainedChild instanceof ProcessingInstruction
			|| $firstPartiallyContainedChild instanceof Comment
		) {
			$clone = $originalStartContainer->cloneNode();
			Assert::precondition(
				$firstPartiallyContainedChild === $originalStartContainer,
				'Only possible when the node is the startContainer'
			);
			$clone->nodeValue = $firstPartiallyContainedChild->substringData(
				$originalStartOffset,
				$firstPartiallyContainedChild->length - $originalStartOffset
			);
			$fragment->appendChild( $clone );
		} elseif ( $firstPartiallyContainedChild ) {
			$clone = $firstPartiallyContainedChild->cloneNode();
			$fragment->appendChild( $clone );
			$subrange = new self(
				$originalStartContainer, $originalStartOffset,
				$firstPartiallyContainedChild,
				// @phan-suppress-next-line PhanUndeclaredProperty
				$firstPartiallyContainedChild->length ?? $firstPartiallyContainedChild->childNodes->length
			);
			$subfragment = $subrange->cloneContents();
			if ( $subfragment->hasChildNodes() ) {
				$clone->appendChild( $subfragment );
			}
		}

		// $containedChildrenStart and $containedChildrenEnd may be null here, but this loop still works correctly
		for ( $child = $containedChildrenStart; $child !== $containedChildrenEnd; $child = $child->nextSibling ) {
			$clone = $child->cloneNode( true );
			$fragment->appendChild( $clone );
		}
		// If not null, this node wasn't processed by the loop
		if ( $containedChildrenEnd ) {
			$clone = $containedChildrenEnd->cloneNode( true );
			$fragment->appendChild( $clone );
		}

		if ( $lastPartiallyContainedChild instanceof Text
			|| $lastPartiallyContainedChild instanceof ProcessingInstruction
			|| $lastPartiallyContainedChild instanceof Comment
		) {
			Assert::precondition(
				$lastPartiallyContainedChild === $originalEndContainer,
				'Only possible when the node is the endContainer'
			);
			$clone = $lastPartiallyContainedChild->cloneNode();
			$clone->nodeValue = $lastPartiallyContainedChild->substringData(
				0,
				$originalEndOffset
			);
			$fragment->appendChild( $clone );
		} elseif ( $lastPartiallyContainedChild ) {
			$clone = $lastPartiallyContainedChild->cloneNode();
			$fragment->appendChild( $clone );
			$subrange = new self(
				$lastPartiallyContainedChild, 0,
				$originalEndContainer, $originalEndOffset
			);
			$subfragment = $subrange->cloneContents();
			if ( $subfragment->hasChildNodes() ) {
				$clone->appendChild( $subfragment );
			}
		}

		return $fragment;
	}

	/**
	 * Inserts a new Node into at the start of the Range.
	 *
	 * Ported from https://github.com/TRowbotham/PHPDOM (MIT)
	 *
	 * @see https://dom.spec.whatwg.org/#dom-range-insertnode
	 *
	 * @param Node $node The Node to be inserted.
	 */
	public function insertNode( Node $node ): void {
		if ( ( $this->startNode instanceof ProcessingInstruction
				|| $this->startNode instanceof Comment )
			|| ( $this->startNode instanceof Text
				&& $this->startNode->parentNode === null )
		) {
			throw new DOMException();
		}

		$referenceNode = null;

		if ( $this->startNode instanceof Text ) {
			$referenceNode = $this->startNode;
		} else {
			$referenceNode = $this
				->startNode
				->childNodes
				->item( $this->startOffset );
		}

		$parent = !$referenceNode
			? $this->startNode
			: $referenceNode->parentNode;
		// TODO: Restore this validation check?
		// $parent->ensurePreinsertionValidity( $node, $referenceNode );

		if ( $this->startNode instanceof Text ) {
			$referenceNode = $this->startNode->splitText( $this->startOffset );
		}

		if ( $node === $referenceNode ) {
			$referenceNode = $referenceNode->nextSibling;
		}

		if ( $node->parentNode ) {
			$node->parentNode->removeChild( $node );
		}

		// TODO: Restore this validation check?
		// $parent->preinsertNode( $node, $referenceNode );

		// $referenceNode may be null, this is okay
		$parent->insertBefore( $node, $referenceNode );
	}

	/**
	 * Wraps the content of Range in a new Node and inserts it in to the Document.
	 *
	 * Ported from https://github.com/TRowbotham/PHPDOM (MIT)
	 *
	 * @see https://dom.spec.whatwg.org/#dom-range-surroundcontents
	 *
	 * @param Node $newParent New parent node for contents
	 */
	public function surroundContents( Node $newParent ): void {
		$commonAncestor = $this->commonAncestorContainer;

		if ( $commonAncestor ) {
			$tw = new TreeWalker( $commonAncestor );
			$node = $tw->nextNode();

			while ( $node ) {
				if ( !$node instanceof Text && $this->isPartiallyContainedNode( $node ) ) {
					throw new DOMException();
				}

				$node = $tw->nextNode();
			}
		}

		if (
			$newParent instanceof Document
			|| $newParent instanceof DocumentType
			|| $newParent instanceof DocumentFragment
		) {
			throw new DOMException();
		}

		$fragment = $this->extractContents();

		while ( $newParent->firstChild ) {
			$newParent->removeChild( $newParent->firstChild );
		}

		$this->insertNode( $newParent );
		$newParent->appendChild( $fragment );
		// TODO: Return new range?
	}

	/**
	 * Compares the position of two boundary points.
	 *
	 * Ported from https://github.com/TRowbotham/PHPDOM (MIT)
	 * @internal
	 *
	 * @see https://dom.spec.whatwg.org/#concept-range-bp-position
	 *
	 * @return string 'before'|'after'|'equal'
	 */
	private function computePosition(
		Node $nodeA, int $offsetA, Node $nodeB, int $offsetB
	): string {
		// 1. Assert: nodeA and nodeB have the same root.
		// Removed, not necessary for our usage

		// 2. If nodeA is nodeB, then return equal if offsetA is offsetB, before if offsetA is less than offsetB, and
		// after if offsetA is greater than offsetB.
		if ( $nodeA === $nodeB ) {
			if ( $offsetA === $offsetB ) {
				return 'equal';
			} elseif ( $offsetA < $offsetB ) {
				return 'before';
			} else {
				return 'after';
			}
		}

		$commonAncestor = $this->findCommonAncestorContainer( $nodeB, $nodeA );
		if ( $commonAncestor === $nodeA ) {
			$AFollowsB = false;
		} elseif ( $commonAncestor === $nodeB ) {
			$AFollowsB = true;
		} else {
			// A was not found inside B. Traverse both A & B up to the nodes
			// before their common ancestor, then see if A is in the nextSibling
			// chain of B.
			$b = $nodeB;
			while ( $b->parentNode !== $commonAncestor ) {
				$b = $b->parentNode;
			}
			$a = $nodeA;
			while ( $a->parentNode !== $commonAncestor ) {
				$a = $a->parentNode;
			}
			$AFollowsB = false;
			while ( $b ) {
				if ( $a === $b ) {
					$AFollowsB = true;
					break;
				}
				$b = $b->nextSibling;
			}
		}

		if ( $AFollowsB ) {
			// Swap variables
			[ $nodeB, $nodeA ] = [ $nodeA, $nodeB ];
			[ $offsetB, $offsetA ] = [ $offsetA, $offsetB ];
		}

		$ancestor = $nodeB->parentNode;

		while ( $ancestor ) {
			if ( $ancestor === $nodeA ) {
				break;
			}

			$ancestor = $ancestor->parentNode;
		}

		if ( $ancestor ) {
			$child = $nodeB;

			while ( $child ) {
				if ( $child->parentNode === $nodeA ) {
					break;
				}

				$child = $child->parentNode;
			}

			// Phan complains that $child may be null here, but that can't happen, because at this point
			// we know that $nodeA is an ancestor of $nodeB, so the loop above will stop before the root.
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
			if ( CommentUtils::childIndexOf( $child ) < $offsetA ) {
				return $AFollowsB ? 'before' : 'after';
			}
		}

		return $AFollowsB ? 'after' : 'before';
	}

	public const START_TO_START = 0;
	public const START_TO_END = 1;
	public const END_TO_END = 2;
	public const END_TO_START = 3;

	/**
	 * Compares the boundary points of this Range with another Range.
	 *
	 * Ported from https://github.com/TRowbotham/PHPDOM (MIT)
	 *
	 * @see https://dom.spec.whatwg.org/#dom-range-compareboundarypoints
	 *
	 * @param int $how One of ImmutableRange::END_TO_END, ImmutableRange::END_TO_START,
	 *     ImmutableRange::START_TO_END, ImmutableRange::START_TO_START
	 * @param ImmutableRange $sourceRange A Range whose boundary points are to be compared.
	 * @return int -1, 0, or 1
	 */
	public function compareBoundaryPoints( int $how, self $sourceRange ): int {
		if ( static::getRootNode( $this->startNode ) !== static::getRootNode( $sourceRange->startContainer ) ) {
			throw new DOMException();
		}

		switch ( $how ) {
			case static::START_TO_START:
				$thisPoint = [ $this->startNode, $this->startOffset ];
				$otherPoint = [ $sourceRange->startContainer, $sourceRange->startOffset ];
				break;

			case static::START_TO_END:
				$thisPoint = [ $this->endNode, $this->endOffset ];
				$otherPoint = [ $sourceRange->startContainer, $sourceRange->startOffset ];
				break;

			case static::END_TO_END:
				$thisPoint = [ $this->endNode, $this->endOffset ];
				$otherPoint = [ $sourceRange->endContainer, $sourceRange->endOffset ];
				break;

			case static::END_TO_START:
				$thisPoint = [ $this->startNode, $this->startOffset ];
				$otherPoint = [ $sourceRange->endContainer, $sourceRange->endOffset ];
				break;

			default:
				throw new DOMException();
		}

		switch ( $this->computePosition( ...$thisPoint, ...$otherPoint ) ) {
			case 'before':
				return -1;

			case 'equal':
				return 0;

			case 'after':
				return 1;

			default:
				throw new DOMException();
		}
	}
}
