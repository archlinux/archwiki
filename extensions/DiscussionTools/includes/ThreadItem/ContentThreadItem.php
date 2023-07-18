<?php

namespace MediaWiki\Extension\DiscussionTools\ThreadItem;

use JsonSerializable;
use LogicException;
use MediaWiki\Extension\DiscussionTools\CommentModifier;
use MediaWiki\Extension\DiscussionTools\CommentUtils;
use MediaWiki\Extension\DiscussionTools\ImmutableRange;
use Sanitizer;
use Title;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * A thread item, either a heading or a comment
 */
abstract class ContentThreadItem implements JsonSerializable, ThreadItem {
	use ThreadItemTrait;

	protected $type;
	protected $range;
	protected $rootNode;
	protected $level;
	protected $parent;
	protected $warnings = [];

	protected $name = null;
	protected $id = null;
	protected $replies = [];

	protected $authors = null;
	protected $commentCount;
	protected $oldestReply;
	protected $latestReply;

	/**
	 * @param string $type `heading` or `comment`
	 * @param int $level Indentation level
	 * @param ImmutableRange $range Object describing the extent of the comment, including the
	 *  signature and timestamp.
	 */
	public function __construct(
		string $type, int $level, ImmutableRange $range
	) {
		$this->type = $type;
		$this->level = $level;
		$this->range = $range;
	}

	/**
	 * Get summary metadata for a thread.
	 */
	private function calculateThreadSummary(): void {
		if ( $this->authors !== null ) {
			return;
		}
		$authors = [];
		$commentCount = 0;
		$oldestReply = null;
		$latestReply = null;
		$threadScan = static function ( ContentThreadItem $comment ) use (
			&$authors, &$commentCount, &$oldestReply, &$latestReply, &$threadScan
		) {
			if ( $comment instanceof ContentCommentItem ) {
				$author = $comment->getAuthor();
				if ( $author ) {
					$authors[ $author ] = true;
				}
				if (
					!$oldestReply ||
					( $comment->getTimestamp() < $oldestReply->getTimestamp() )
				) {
					$oldestReply = $comment;
				}
				if (
					!$latestReply ||
					( $latestReply->getTimestamp() < $comment->getTimestamp() )
				) {
					$latestReply = $comment;
				}
				$commentCount++;
			}
			// Get the set of authors in the same format from each reply
			$replies = $comment->getReplies();
			array_walk( $replies, $threadScan );
		};
		$replies = $this->getReplies();
		array_walk( $replies, $threadScan );

		ksort( $authors );

		$this->authors = array_keys( $authors );
		$this->commentCount = $commentCount;
		$this->oldestReply = $oldestReply;
		$this->latestReply = $latestReply;
	}

	/**
	 * Get the list of authors in the tree below this thread item.
	 *
	 * Usually called on a HeadingItem to find all authors in a thread.
	 *
	 * @return string[] Author usernames
	 */
	public function getAuthorsBelow(): array {
		$this->calculateThreadSummary();
		return $this->authors;
	}

	/**
	 * Get the number of comment items in the tree below this thread item.
	 *
	 * @return int
	 */
	public function getCommentCount(): int {
		$this->calculateThreadSummary();
		return $this->commentCount;
	}

	/**
	 * Get the latest reply in the tree below this thread item, null if there are no replies
	 *
	 * @return ContentCommentItem|null
	 */
	public function getLatestReply(): ?ContentCommentItem {
		$this->calculateThreadSummary();
		return $this->latestReply;
	}

	/**
	 * Get the oldest reply in the tree below this thread item, null if there are no replies
	 *
	 * @return ContentCommentItem|null
	 */
	public function getOldestReply(): ?ContentCommentItem {
		$this->calculateThreadSummary();
		return $this->oldestReply;
	}

	/**
	 * Get a flat list of thread items in the comment tree below this thread item.
	 *
	 * @return ContentThreadItem[] Thread items
	 */
	public function getThreadItemsBelow(): array {
		$threadItems = [];
		$getReplies = static function ( ContentThreadItem $threadItem ) use ( &$threadItems, &$getReplies ) {
			$threadItems[] = $threadItem;
			foreach ( $threadItem->getReplies() as $reply ) {
				$getReplies( $reply );
			}
		};

		foreach ( $this->getReplies() as $reply ) {
			$getReplies( $reply );
		}

		return $threadItems;
	}

	/**
	 * Get the name of the page from which this thread item is transcluded (if any). Replies to
	 * transcluded items must be posted on that page, instead of the current one.
	 *
	 * This is tricky, because we don't want to mark items as trancluded when they're just using a
	 * template (e.g. {{ping|…}} or a non-substituted signature template). Sometimes the whole comment
	 * can be template-generated (e.g. when using some wrapper templates), but as long as a reply can
	 * be added outside of that template, we should not treat it as transcluded.
	 *
	 * The start/end boundary points of comment ranges and Parsoid transclusion ranges don't line up
	 * exactly, even when to a human it's obvious that they cover the same content, making this more
	 * complicated.
	 *
	 * @return string|bool `false` if this item is not transcluded. A string if it's transcluded
	 *   from a single page (the page title, in text form with spaces). `true` if it's transcluded, but
	 *   we can't determine the source.
	 */
	public function getTranscludedFrom() {
		// General approach:
		//
		// Compare the comment range to each transclusion range on the page, and if it overlaps any of
		// them, examine the overlap. There are a few cases:
		//
		// * Comment and transclusion do not overlap:
		//   → Not transcluded.
		// * Comment contains the transclusion:
		//   → Not transcluded (just a template).
		// * Comment is contained within the transclusion:
		//   → Transcluded, we can determine the source page (unless it's a complex transclusion).
		// * Comment and transclusion overlap partially:
		//   → Transcluded, but we can't determine the source page.
		// * Comment (almost) exactly matches the transclusion:
		//   → Maybe transcluded (it could be that the source page only contains that single comment),
		//     maybe not transcluded (it could be a wrapper template that covers a single comment).
		//     This is very sad, and we decide based on the namespace.
		//
		// Most transclusion ranges on the page trivially fall in the "do not overlap" or "contains"
		// cases, and we only have to carefully examine the two transclusion ranges that contain the
		// first and last node of the comment range.
		//
		// To check for almost exact matches, we walk between the relevant boundary points, and if we
		// only find uninteresting nodes (that would be ignored when detecting comments), we treat them
		// like exact matches.

		$commentRange = $this->getRange();
		$startTransclNode = CommentUtils::getTranscludedFromElement(
			CommentUtils::getRangeFirstNode( $commentRange )
		);
		$endTransclNode = CommentUtils::getTranscludedFromElement(
			CommentUtils::getRangeLastNode( $commentRange )
		);

		// We only have to examine the two transclusion ranges that contain the first/last node of the
		// comment range (if they exist). Ignore ranges outside the comment or in the middle of it.
		$transclNodes = [];
		if ( $startTransclNode ) {
			$transclNodes[] = $startTransclNode;
		}
		if ( $endTransclNode && $endTransclNode !== $startTransclNode ) {
			$transclNodes[] = $endTransclNode;
		}

		foreach ( $transclNodes as $transclNode ) {
			$transclRange = static::getTransclusionRange( $transclNode );
			$compared = CommentUtils::compareRanges( $commentRange, $transclRange );
			$transclTitles = $this->getTransclusionTitles( $transclNode );
			$simpleTransclTitle = count( $transclTitles ) === 1 ? $transclTitles[0] : null;

			switch ( $compared ) {
				case 'equal':
					// Comment (almost) exactly matches the transclusion
					if ( $simpleTransclTitle === null ) {
						// Allow replying to some accidental complex transclusions consisting of only templates
						// and wikitext (T313093)
						if ( count( $transclTitles ) > 1 ) {
							foreach ( $transclTitles as $transclTitle ) {
								if ( $transclTitle && !$transclTitle->inNamespace( NS_TEMPLATE ) ) {
									return true;
								}
							}
							// Continue examining the other ranges.
							break;
						}
						// Multi-template transclusion, or a parser function call, or template-affected wikitext outside
						// of a template call, or a mix of the above
						return true;

					} elseif ( $simpleTransclTitle->inNamespace( NS_TEMPLATE ) ) {
						// Is that a subpage transclusion with a single comment, or a wrapper template
						// transclusion on this page? We don't know, but let's guess based on the namespace.
						// (T289873)
						// Continue examining the other ranges.
						break;
					} else {
						return $simpleTransclTitle->getPrefixedText();
					}

				case 'contains':
					// Comment contains the transclusion

					// If the entire transclusion is contained within the comment range, that's just a
					// template. This is the same as a transclusion in the middle of the comment, which we
					// ignored earlier, it just takes us longer to get here in this case.

					// Continue examining the other ranges.
					break;

				case 'contained':
					// Comment is contained within the transclusion
					if ( $simpleTransclTitle === null ) {
						return true;
					} else {
						return $simpleTransclTitle->getPrefixedText();
					}

				case 'after':
				case 'before':
					// Comment and transclusion do not overlap

					// This should be impossible, because we ignored these ranges earlier.
					throw new LogicException( 'Unexpected transclusion or comment range' );

				case 'overlapstart':
				case 'overlapend':
					// Comment and transclusion overlap partially
					return true;

				default:
					throw new LogicException( 'Unexpected return value from compareRanges()' );
			}
		}

		// If we got here, the comment range was not contained by or overlapping any of the transclusion
		// ranges. Comment is not transcluded.
		return false;
	}

	/**
	 * Return the page titles for each part of the transclusion, or nulls for each part that isn't
	 * transcluded from another page.
	 *
	 * If the node represents a single-page transclusion, this will return an array containing a
	 * single Title object.
	 *
	 * @param Element $node
	 * @return (?Title)[]
	 */
	private function getTransclusionTitles( Element $node ): array {
		$dataMw = json_decode( $node->getAttribute( 'data-mw' ) ?? '', true );
		$out = [];

		foreach ( $dataMw['parts'] ?? [] as $part ) {
			if (
				!is_string( $part ) &&
				// 'href' will be unset if this is a parser function rather than a template
				isset( $part['template']['target']['href'] )
			) {
				$parsoidHref = $part['template']['target']['href'];
				Assert::precondition( substr( $parsoidHref, 0, 2 ) === './', "href has valid format" );
				$out[] = Title::newFromText( urldecode( substr( $parsoidHref, 2 ) ) );
			} else {
				$out[] = null;
			}
		}

		return $out;
	}

	/**
	 * Given a transclusion's first node (e.g. returned by CommentUtils::getTranscludedFromElement()),
	 * return a range starting before the node and ending after the transclusion's last node.
	 *
	 * @param Element $startNode
	 * @return ImmutableRange
	 */
	private function getTransclusionRange( Element $startNode ): ImmutableRange {
		$endNode = $startNode;
		while (
			// Phan doesn't realize that the conditions on $nextSibling can terminate the loop
			// @phan-suppress-next-line PhanInfiniteLoop
			$endNode &&
			( $nextSibling = $endNode->nextSibling ) &&
			$nextSibling instanceof Element &&
			$nextSibling->getAttribute( 'about' ) === $endNode->getAttribute( 'about' )
		) {
			$endNode = $nextSibling;
		}

		$range = new ImmutableRange(
			$startNode->parentNode,
			CommentUtils::childIndexOf( $startNode ),
			$endNode->parentNode,
			CommentUtils::childIndexOf( $endNode ) + 1
		);

		return $range;
	}

	/**
	 * Get the HTML of this thread item
	 *
	 * @return string HTML
	 */
	public function getHTML(): string {
		$fragment = $this->getRange()->cloneContents();
		CommentModifier::unwrapFragment( $fragment );
		return DOMUtils::getFragmentInnerHTML( $fragment );
	}

	/**
	 * Get the text of this thread item
	 *
	 * @return string Text
	 */
	public function getText(): string {
		$html = $this->getHTML();
		return Sanitizer::stripAllTags( $html );
	}

	/**
	 * @return string Thread item type
	 */
	public function getType(): string {
		return $this->type;
	}

	/**
	 * @return int Indentation level
	 */
	public function getLevel(): int {
		return $this->level;
	}

	/**
	 * @return ContentThreadItem|null Parent thread item
	 */
	public function getParent(): ?ThreadItem {
		return $this->parent;
	}

	/**
	 * @return ImmutableRange Range of the entire thread item
	 */
	public function getRange(): ImmutableRange {
		return $this->range;
	}

	/**
	 * @return Element Root node (level is relative to this node)
	 */
	public function getRootNode(): Element {
		return $this->rootNode;
	}

	/**
	 * @return string Thread item name
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @return string Thread ID
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * @return ContentThreadItem[] Replies to this thread item
	 */
	public function getReplies(): array {
		return $this->replies;
	}

	/**
	 * @return string[] Warnings
	 */
	public function getWarnings(): array {
		return $this->warnings;
	}

	/**
	 * @param int $level Indentation level
	 */
	public function setLevel( int $level ): void {
		$this->level = $level;
	}

	/**
	 * @param ContentThreadItem $parent
	 */
	public function setParent( ContentThreadItem $parent ): void {
		$this->parent = $parent;
	}

	/**
	 * @param ImmutableRange $range Thread item range
	 */
	public function setRange( ImmutableRange $range ): void {
		$this->range = $range;
	}

	/**
	 * @param Element $rootNode Root node (level is relative to this node)
	 */
	public function setRootNode( Element $rootNode ): void {
		$this->rootNode = $rootNode;
	}

	/**
	 * @param string|null $name Thread item name
	 */
	public function setName( ?string $name ): void {
		$this->name = $name;
	}

	/**
	 * @param string|null $id Thread ID
	 */
	public function setId( ?string $id ): void {
		$this->id = $id;
	}

	/**
	 * @param string $warning
	 */
	public function addWarning( string $warning ): void {
		$this->warnings[] = $warning;
	}

	/**
	 * @param string[] $warnings
	 */
	public function addWarnings( array $warnings ): void {
		$this->warnings = array_merge( $this->warnings, $warnings );
	}

	/**
	 * @param ContentThreadItem $reply Reply comment
	 */
	public function addReply( ContentThreadItem $reply ): void {
		$this->replies[] = $reply;
	}
}
