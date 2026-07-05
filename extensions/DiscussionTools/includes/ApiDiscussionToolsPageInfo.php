<?php

namespace MediaWiki\Extension\DiscussionTools;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\DiscussionTools\Hooks\HookUtils;
use MediaWiki\Extension\DiscussionTools\ThreadItem\CommentItem;
use MediaWiki\Extension\DiscussionTools\ThreadItem\ContentCommentItem;
use MediaWiki\Extension\DiscussionTools\ThreadItem\ContentHeadingItem;
use MediaWiki\Extension\DiscussionTools\ThreadItem\ContentThreadItem;
use MediaWiki\Extension\VisualEditor\VisualEditorParsoidClientFactory;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Text;
use Wikimedia\Parsoid\Ext\DOMUtils;

class ApiDiscussionToolsPageInfo extends ApiBase {

	public function __construct(
		ApiMain $main,
		string $name,
		private readonly VisualEditorParsoidClientFactory $parsoidClientFactory,
		private readonly CommentParser $commentParser,
		private readonly RevisionLookup $revisionLookup,
	) {
		parent::__construct( $main, $name );
	}

	/**
	 * @inheritDoc
	 * @throws ApiUsageException
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$this->requireAtLeastOneParameter( $params, 'page', 'oldid' );
		$threadItemSet = $this->getThreadItemSet( $params );

		$result = [];
		$prop = array_fill_keys( $params['prop'], true );

		if ( isset( $prop['transcludedfrom'] ) ) {
			$result['transcludedfrom'] = static::getTranscludedFrom( $threadItemSet );
		}

		if ( isset( $prop['threaditemshtml'] ) ) {
			$flags = $params['threaditemsflags'] ?? [];
			$excludeSignatures = $params['excludesignatures'] || in_array( 'excludesignatures', $flags );
			$noReplies = in_array( 'noreplies', $flags );
			$extraActivity = in_array( 'activity', $flags );
			$result['threaditemshtml'] = static::getThreadItemsHtml(
				$threadItemSet, $excludeSignatures, $noReplies, $extraActivity
			);
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * Get the thread item set for the specified revision
	 *
	 * @throws ApiUsageException
	 * @param array $params
	 * @return ContentThreadItemSet
	 */
	private function getThreadItemSet( $params ) {
		if ( isset( $params['page'] ) ) {
			$title = Title::newFromText( $params['page'] );
			if ( !$title ) {
				throw ApiUsageException::newWithMessage(
					$this,
					[ 'apierror-invalidtitle', wfEscapeWikiText( $params['page'] ) ]
				);
			}
		}

		if ( isset( $params['oldid'] ) ) {
			$revision = $this->revisionLookup->getRevisionById( $params['oldid'] );
			if ( !$revision ) {
				throw ApiUsageException::newWithMessage(
					$this,
					[ 'apierror-nosuchrevid', $params['oldid'] ]
				);
			}
		} else {
			$title = Title::newFromText( $params['page'] );
			if ( !$title ) {
				throw ApiUsageException::newWithMessage(
					$this,
					[ 'apierror-invalidtitle', wfEscapeWikiText( $params['page'] ) ]
				);
			}
			$revision = $this->revisionLookup->getRevisionByTitle( $title );
			if ( !$revision ) {
				throw ApiUsageException::newWithMessage(
					$this,
					[ 'apierror-missingrev-title', wfEscapeWikiText( $title->getPrefixedText() ) ],
					'nosuchrevid'
				);
			}
		}
		$title = Title::castFromPageIdentity( $revision->getPage() );

		if ( !$title || !HookUtils::isAvailableForTitle( $title ) ) {
			// T325477: don't parse non-discussion pages
			return new ContentThreadItemSet;
		}

		$this->checkTitleUserPermissions( $title, 'read' );

		if ( !$revision->audienceCan( RevisionRecord::DELETED_TEXT, RevisionRecord::FOR_PUBLIC ) ) {
			$this->dieWithError( [ 'apierror-missingcontent-revid', $revision->getId() ], 'missingcontent' );
		}

		$status = HookUtils::parseRevisionParsoidHtml( $revision, __METHOD__ );
		if ( !$status->isOK() ) {
			$this->dieStatus( $status );
		}
		return $status->getValueOrThrow();
	}

	/**
	 * Get transcluded=from data for a ContentThreadItemSet
	 */
	private static function getTranscludedFrom( ContentThreadItemSet $threadItemSet ): array {
		$threadItems = $threadItemSet->getThreadItems();
		$transcludedFrom = [];
		foreach ( $threadItems as $threadItem ) {
			$from = $threadItem->getTranscludedFrom();

			// Key by IDs and names. This assumes that they can never conflict.

			$transcludedFrom[ $threadItem->getId() ] = $from;

			$name = $threadItem->getName();
			if ( isset( $transcludedFrom[ $name ] ) && $transcludedFrom[ $name ] !== $from ) {
				// Two or more items with the same name, transcluded from different pages.
				// Consider them both to be transcluded from unknown source.
				$transcludedFrom[ $name ] = true;
			} else {
				$transcludedFrom[ $name ] = $from;
			}
		}

		return $transcludedFrom;
	}

	/**
	 * Get thread items HTML for a ContentThreadItemSet
	 */
	private static function getThreadItemsHtml(
		ContentThreadItemSet $threadItemSet,
		bool $excludeSignatures, bool $noReplies, bool $extraActivity
	): array {
		// This function assumes that the start of the ranges associated with
		// HeadingItems are going to be at the start of their associated
		// heading node (`<h2>^heading</h2>`), i.e. in the position generated
		// by getHeadlineNode.
		$threads = $threadItemSet->getThreads();
		if ( count( $threads ) > 0 && !$threads[0]->isPlaceholderHeading() ) {
			$firstHeading = $threads[0];
			$firstRange = $firstHeading->getRange();
			$rootNode = $firstHeading->getRootNode();
			// We need a placeholder if there's content between the beginning
			// of rootnode and the start of firstHeading. An ancestor of the
			// first heading with a previousSibling is evidence that there's
			// probably content. If this is giving false positives we could
			// perhaps use linearWalkBackwards and DomUtils::isContentNode.
			$closest = CommentUtils::closestElementWithSibling( $firstRange->startContainer, 'previous' );
			if ( $closest && !$rootNode->isSameNode( $closest ) ) {
				$range = new ImmutableRange( $rootNode, 0, $rootNode, 0 );
				$fakeHeading = new ContentHeadingItem( $range, false, null );
				$fakeHeading->setRootNode( $rootNode );
				$fakeHeading->setName( 'h-' );
				$fakeHeading->setId( 'h-' );
				array_unshift( $threads, $fakeHeading );
			}
		}
		$output = array_map( static function ( ContentThreadItem $item )
			use ( $excludeSignatures, $noReplies, $extraActivity ) {
			return $item->jsonSerialize( !$noReplies, static function ( array &$array, ContentThreadItem $item ) use (
				$excludeSignatures, $noReplies, $extraActivity
			) {
				if ( $item instanceof ContentCommentItem && $excludeSignatures ) {
					$array['html'] = $item->getBodyHTML( true );
				} else {
					$array['html'] = $item->getHTML();
				}

				if ( $item instanceof ContentHeadingItem ) {
					$array['commentCount'] = $item->getCommentCount();
					$array['authorCount'] = count( $item->getAuthorsBelow() );
					$latestReply = $item->getLatestReply();
					if ( $latestReply ) {
						$array['latestReplyTimestamp'] = static::formatItemTimestamp( $latestReply );
						if ( $extraActivity ) {
							$array['latestReply'] = $latestReply->jsonSerialize( false );
							$array['latestReply']['timestamp'] = $array['latestReplyTimestamp'];
							unset( $array['latestReply']['replies'] );
						}
					} else {
						$array['latestReplyTimestamp'] = null;
						if ( $extraActivity ) {
							$array['latestReply'] = null;
						}
					}
					if ( $extraActivity ) {
						$oldestReply = $item->getOldestReply();
						if ( $oldestReply ) {
							$array['oldestReply'] = $oldestReply->jsonSerialize( false );
							$array['oldestReply']['timestamp'] = static::formatItemTimestamp( $oldestReply );
							unset( $array['oldestReply']['replies'] );
						} else {
							$array['oldestReply'] = null;
						}
					}
				}

				if ( $item instanceof CommentItem ) {
					$array['timestamp'] = static::formatItemTimestamp( $item );
				}

				if ( $noReplies ) {
					unset( $array['replies'] );
				}
			} );
		}, $threads );
		foreach ( $threads as $index => $item ) {
			// need to loop over this to fix up empty sections, because we
			// need context that's not available inside the array map
			if ( $item instanceof ContentHeadingItem && count( $item->getReplies() ) === 0 ) {
				// If there are no replies we want to include whatever's
				// inside this section as "othercontent". We create a range
				// that's between the end of this section's heading and the
				// start of next section's heading. The main difficulty here
				// is avoiding catching any of the heading's tags within the
				// range.
				$nextItem = $threads[ $index + 1 ] ?? false;
				$startRange = $item->getRange();
				if ( $item->isPlaceholderHeading() ) {
					// Placeholders don't have any heading to avoid
					$startNode = $startRange->startContainer;
					$startOffset = $startRange->startOffset;
				} else {
					$startNode = CommentUtils::closestElementWithSibling( $startRange->endContainer, 'next' );
					if ( !$startNode ) {
						// If there's no siblings here this means we're on a
						// heading that is the final heading on a page and
						// which has no contents at all. We can skip the rest.
						continue;
					} else {
						$startNode = $startNode->nextSibling;
						$startOffset = 0;
					}
				}

				if ( !$startNode ) {
					 $startNode = $startRange->endContainer;
					 $startOffset = $startRange->endOffset;
				}

				if ( $nextItem ) {
					$nextStart = $nextItem->getRange()->startContainer;
					$endContainer = CommentUtils::closestElementWithSibling( $nextStart, 'previous' );
					$endContainer = $endContainer && $endContainer->previousSibling ?
						$endContainer->previousSibling : $nextStart;
					$endOffset = CommentUtils::childIndexOf( $endContainer );
					if ( $endContainer instanceof Text ) {
						// This probably means that there's a wrapping node
						// e.g. <div>foo\n==heading==\nbar</div>
						$endOffset += $endContainer->length;
					} elseif (
						$endContainer instanceof Element &&
						strtolower( $endContainer->tagName ) === 'section'
					) {
						// if we're in sections, make sure we're selecting the
						// end of the previous section
						$endOffset = $endContainer->childNodes->length;
					} elseif ( $endContainer->parentNode ) {
						$endContainer = $endContainer->parentNode;
					}
					$betweenRange = new ImmutableRange(
						$startNode, $startOffset,
						$endContainer ?: $nextStart, $endOffset
					);
				} else {
					// This is the last section, so we want to go to the end of the rootnode
					$betweenRange = new ImmutableRange(
						$startNode, $startOffset,
						$item->getRootNode(), $item->getRootNode()->childNodes->length
					);
				}
				$fragment = $betweenRange->cloneContents();
				CommentModifier::unwrapFragment( $fragment );
				$otherContent = trim( DOMUtils::getFragmentInnerHTML( $fragment ) );
				if ( $otherContent ) {
					// A completely empty section will result in otherContent
					// being an empty string. In this case we should just not include it.
					$output[$index]['othercontent'] = $otherContent;
				}

			}
		}
		return $output;
	}

	/**
	 * We want timestamps to be consistently formatted in API output instead
	 * of varying based on comment time(T315400). The format used here is
	 * equivalent to 'Y-m-d\TH:i:s\Z'
	 */
	private static function formatItemTimestamp( CommentItem $item ): string {
		return wfTimestamp( TS_ISO_8601, $item->getTimestamp()->getTimestamp() );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'page' => [
				ApiBase::PARAM_HELP_MSG => 'apihelp-visualeditoredit-param-page',
			],
			'oldid' => [
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'prop' => [
				ParamValidator::PARAM_DEFAULT => 'transcludedfrom',
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'transcludedfrom',
					'threaditemshtml'
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'threaditemsflags' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'noreplies',
					'activity',
					'excludesignatures'
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'excludesignatures' => [
				ParamValidator::PARAM_DEPRECATED => true
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function isInternal() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return false;
	}
}
