<?php

namespace MediaWiki\Extension\DiscussionTools;

use Html;
use Language;
use MediaWiki\Extension\DiscussionTools\Hooks\HookUtils;
use MediaWiki\Extension\DiscussionTools\ThreadItem\ContentCommentItem;
use MediaWiki\Extension\DiscussionTools\ThreadItem\ContentHeadingItem;
use MediaWiki\Extension\DiscussionTools\ThreadItem\ContentThreadItem;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use MWExceptionHandler;
use MWTimestamp;
use Parser;
use ParserOutput;
use Sanitizer;
use Throwable;
use Title;
use WebRequest;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Wt2Html\XMLSerializer;
use Wikimedia\Timestamp\TimestampException;

class CommentFormatter {
	// List of features which, when enabled, cause the comment formatter to run
	public const USE_WITH_FEATURES = [
		HookUtils::REPLYTOOL,
		HookUtils::TOPICSUBSCRIPTION,
		HookUtils::VISUALENHANCEMENTS
	];

	/**
	 * Get a comment parser object for a DOM element
	 *
	 * This method exists so it can mocked in tests.
	 *
	 * @return CommentParser
	 */
	protected static function getParser(): CommentParser {
		return MediaWikiServices::getInstance()->getService( 'DiscussionTools.CommentParser' );
	}

	/**
	 * Add discussion tools to some HTML
	 *
	 * @param string &$text Parser text output (modified by reference)
	 * @param ParserOutput $pout ParserOutput object for metadata, e.g. parser limit report
	 * @param Parser $parser
	 */
	public static function addDiscussionTools( string &$text, ParserOutput $pout, Parser $parser ): void {
		$title = $parser->getTitle();
		$start = microtime( true );
		$requestId = null;

		try {
			$text = static::addDiscussionToolsInternal( $text, $pout, $title );

		} catch ( Throwable $e ) {
			// Catch errors, so that they don't cause the entire page to not display.
			// Log it and report the request ID to make it easier to find in the logs.
			MWExceptionHandler::logException( $e );
			$requestId = WebRequest::getRequestId();
		}

		$duration = microtime( true ) - $start;

		$stats = MediaWikiServices::getInstance()->getStatsdDataFactory();
		$stats->timing( 'discussiontools.addReplyLinks', $duration * 1000 );

		// How long this method took, in seconds
		$pout->setLimitReportData(
			'discussiontools-limitreport-timeusage',
			sprintf( '%.3f', $duration )
		);
		if ( $requestId ) {
			// Request ID where errors were logged (only if an error occurred)
			$pout->setLimitReportData(
				'discussiontools-limitreport-errorreqid',
				$requestId
			);
		}
	}

	/**
	 * Add a topic container around a heading element
	 *
	 * @param Element $headingElement Heading element
	 * @param ContentHeadingItem|null $headingItem Heading item
	 * @param array|null &$tocInfo TOC info
	 * @return Element Wrapper element (either found or newly added)
	 */
	protected static function addTopicContainer(
		Element $headingElement,
		?ContentHeadingItem $headingItem = null,
		&$tocInfo = null
	): Element {
		$doc = $headingElement->ownerDocument;
		$wrapperNode = $headingElement->parentNode;
		if ( !(
			$wrapperNode instanceof Element &&
			DOMCompat::getClassList( $wrapperNode )->contains( 'mw-heading' )
		) ) {
			$wrapperNode = $doc->createElement( 'div' );
			$wrapperNode->setAttribute( 'class', 'mw-heading mw-heading2' );
			$headingElement->parentNode->insertBefore( $wrapperNode, $headingElement );
			$wrapperNode->appendChild( $headingElement );
		}

		DOMCompat::getClassList( $wrapperNode )->add( 'ext-discussiontools-init-section' );

		if ( !$headingItem ) {
			return $wrapperNode;
		}

		$headingNameEscaped = htmlspecialchars( $headingItem->getName(), ENT_NOQUOTES );

		// Replaced in ::postprocessTopicSubscription() as the text depends on user state
		if ( $headingItem->isSubscribable() ) {
			$subscribeLink = $doc->createComment( '__DTSUBSCRIBELINK__' . $headingNameEscaped );
			$headingElement->insertBefore( $subscribeLink, $headingElement->firstChild );

			$subscribeButton = $doc->createComment( '__DTSUBSCRIBEBUTTONDESKTOP__' . $headingNameEscaped );
			$wrapperNode->insertBefore( $subscribeButton, $wrapperNode->firstChild );
		}

		$ellipsisButton = $doc->createComment( '__DTELLIPSISBUTTON__' );
		$wrapperNode->appendChild( $ellipsisButton );

		// Visual enhancements: topic containers
		$latestReplyItem = $headingItem->getLatestReply();
		if ( $latestReplyItem ) {
			$latestReplyJSON = json_encode( static::getJsonArrayForCommentMarker( $latestReplyItem ) );
			$latestReply = $doc->createComment(
				// Timestamp output varies by user timezone, so is formatted later
				'__DTLATESTCOMMENTTHREAD__' . htmlspecialchars( $latestReplyJSON, ENT_NOQUOTES ) . '__'
			);

			$commentCount = $doc->createComment(
				'__DTCOMMENTCOUNT__' . $headingItem->getCommentCount() . '__'
			);

			$authorCount = $doc->createComment(
				'__DTAUTHORCOUNT__' . count( $headingItem->getAuthorsBelow() ) . '__'
			);

			// Topic subscriptions
			$metadata = $doc->createElement( 'div' );
			$metadata->setAttribute(
				'class',
				'ext-discussiontools-init-section-metadata'
			);

			$metadata->appendChild( $latestReply );
			$metadata->appendChild( $commentCount );
			$metadata->appendChild( $authorCount );

			$actions = $doc->createElement( 'div' );
			$actions->setAttribute(
				'class',
				'ext-discussiontools-init-section-actions'
			);

			if ( $headingItem->isSubscribable() ) {
				$subscribeButton = $doc->createComment( '__DTSUBSCRIBEBUTTONMOBILE__' . $headingNameEscaped );
				$actions->appendChild( $subscribeButton );
			}

			$bar = $doc->createElement( 'div' );
			$bar->setAttribute(
				'class',
				'ext-discussiontools-init-section-bar'
			);

			$bar->appendChild( $metadata );
			$bar->appendChild( $actions );

			$wrapperNode->appendChild( $bar );

			$tocInfo[ $headingItem->getLinkableTitle() ] = [
				'commentCount' => $headingItem->getCommentCount(),
			];
		}

		return $wrapperNode;
	}

	/**
	 * Add discussion tools to some HTML
	 *
	 * @param string $html HTML
	 * @param ParserOutput $pout
	 * @param Title $title
	 * @return string HTML with discussion tools
	 */
	protected static function addDiscussionToolsInternal( string $html, ParserOutput $pout, Title $title ): string {
		// The output of this method can end up in the HTTP cache (Varnish). Avoid changing it;
		// and when doing so, ensure that frontend code can handle both the old and new outputs.
		// See controller#init in JS.

		$doc = DOMUtils::parseHTML( $html );
		$container = DOMCompat::getBody( $doc );

		$threadItemSet = static::getParser()->parse( $container, $title->getTitleValue() );
		$threadItems = $threadItemSet->getThreadItems();

		$tocInfo = [];

		$newestComment = null;
		$newestCommentData = null;
		$newestCommentJSON = null;

		// Iterate in reverse order, because adding the range markers for a thread item
		// can invalidate the ranges of subsequent thread items (T298096)
		foreach ( array_reverse( $threadItems ) as $threadItem ) {
			// Create a dummy node to attach data to.
			if ( $threadItem instanceof ContentHeadingItem && $threadItem->isPlaceholderHeading() ) {
				$node = $doc->createElement( 'span' );
				$container->insertBefore( $node, $container->firstChild );
				$threadItem->setRange( new ImmutableRange( $node, 0, $node, 0 ) );
			}

			// Add start and end markers to range
			$id = $threadItem->getId();
			$range = $threadItem->getRange();
			$startMarker = $doc->createElement( 'span' );
			$startMarker->setAttribute( 'data-mw-comment-start', '' );
			$startMarker->setAttribute( 'id', $id );
			$endMarker = $doc->createElement( 'span' );
			$endMarker->setAttribute( 'data-mw-comment-end', $id );

			// Extend the range if the start or end is inside an element which can't have element children.
			// (There may be other problematic elements... but this seems like a good start.)
			while ( CommentUtils::cantHaveElementChildren( $range->startContainer ) ) {
				$range = $range->setStart(
					$range->startContainer->parentNode,
					CommentUtils::childIndexOf( $range->startContainer )
				);
			}
			while ( CommentUtils::cantHaveElementChildren( $range->endContainer ) ) {
				$range = $range->setEnd(
					$range->endContainer->parentNode,
					CommentUtils::childIndexOf( $range->endContainer ) + 1
				);
			}

			$range->setStart( $range->endContainer, $range->endOffset )->insertNode( $endMarker );
			$range->insertNode( $startMarker );

			if ( $threadItem instanceof ContentHeadingItem ) {
				// <span class="mw-headline" …>, or <hN …> in Parsoid HTML
				$headline = $threadItem->getRange()->endContainer;
				Assert::precondition( $headline instanceof Element, 'HeadingItem refers to an element node' );
				$headline->setAttribute( 'data-mw-thread-id', $threadItem->getId() );
				if ( $threadItem->getHeadingLevel() === 2 ) {
					$headingElement = CommentUtils::closestElement( $headline, [ 'h2' ] );

					if ( $headingElement ) {
						static::addTopicContainer( $headingElement, $threadItem, $tocInfo );
					}
				}
			} elseif ( $threadItem instanceof ContentCommentItem ) {
				$replyButtons = $doc->createElement( 'span' );
				$replyButtons->setAttribute( 'class', 'ext-discussiontools-init-replylink-buttons' );
				$replyButtons->setAttribute( 'data-mw-thread-id', $threadItem->getId() );
				$replyButtons->appendChild( $doc->createComment( '__DTREPLYBUTTONSCONTENT__' ) );

				if ( !$newestComment || $threadItem->getTimestamp() > $newestComment->getTimestamp() ) {
					$newestComment = $threadItem;
					// Needs to calculated before DOM modifications change ranges
					$newestCommentData = static::getJsonArrayForCommentMarker( $threadItem, true );
					$newestCommentJSON = json_encode( $newestCommentData );
				}

				CommentModifier::addReplyLink( $threadItem, $replyButtons );
			}
		}

		$pout->setExtensionData( 'DiscussionTools-tocInfo', $tocInfo );

		if ( $newestCommentJSON ) {
			$newestCommentMarker = $doc->createComment(
				'__DTLATESTCOMMENTPAGE__' . htmlspecialchars( $newestCommentJSON, ENT_NOQUOTES ) . '__'
			);
			$container->appendChild( $newestCommentMarker );
			$pout->setExtensionData( 'DiscussionTools-newestComment', $newestCommentData );
		}

		$startOfSections = DOMCompat::querySelector( $container, 'meta[property="mw:PageProp/toc"]' );

		// Enhance other <h2>'s which aren't part of a thread
		$headings = DOMCompat::querySelectorAll( $container, 'h2' );
		foreach ( $headings as $headingElement ) {
			$wrapper = $headingElement->parentNode;
			if ( $wrapper instanceof Element && DOMCompat::getClassList( $wrapper )->contains( 'toctitle' ) ) {
				continue;
			}
			$headingElement = static::addTopicContainer( $headingElement );
			if ( !$startOfSections ) {
				$startOfSections = $headingElement;
			}
		}

		if (
			// Page has no headings but some content
			( !$startOfSections && $container->childNodes->length ) ||
			// Page has content before the first heading / TOC
			( $startOfSections && $startOfSections->previousSibling !== null )
		) {
			$container->appendChild( $doc->createComment( '__DTHASLEDECONTENT__' ) );
			$pout->setExtensionData( 'DiscussionTools-hasLedeContent', true );
		}
		if (
			// Placeholder heading indicates that there are comments in the lede section (T324139).
			// We can't really separate them from the lede content.
			isset( $threadItems[0] ) &&
			$threadItems[0] instanceof ContentHeadingItem &&
			$threadItems[0]->isPlaceholderHeading()
		) {
			$container->appendChild( $doc->createComment( '__DTHASCOMMENTSINLEDECONTENT__' ) );
			$pout->setExtensionData( 'DiscussionTools-hasCommentsInLedeContent', true );
		}

		if ( count( $threadItems ) === 0 ) {
			$container->appendChild( $doc->createComment( '__DTEMPTYTALKPAGE__' ) );
			$pout->setExtensionData( 'DiscussionTools-isEmptyTalkPage', true );
		}

		$threadsJSON = array_map( static function ( ContentThreadItem $item ) {
			return $item->jsonSerialize( true );
		}, $threadItemSet->getThreadsStructured() );

		$pout->setJsConfigVar( 'wgDiscussionToolsPageThreads', $threadsJSON );

		// Like DOMCompat::getInnerHTML(), but disable 'smartQuote' for compatibility with
		// ParserOutput::EDITSECTION_REGEX matching 'mw:editsection' tags (T274709)
		$html = XMLSerializer::serialize( $container, [ 'innerXML' => true, 'smartQuote' => false ] )['html'];

		return $html;
	}

	/**
	 * Replace placeholders for all interactive tools with nothing. This is intended for cases where
	 * interaction is unexpected, e.g. reply links while previewing an edit.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function removeInteractiveTools( string $text ) {
		$text = strtr( $text, [
			'<!--__DTREPLYBUTTONSCONTENT__-->' => '',
			'<!--__DTELLIPSISBUTTON__-->' => '',
			'<!--__DTEMPTYTALKPAGE__-->' => '',
		] );

		$text = preg_replace( '/<!--__DTSUBSCRIBELINK__(.*?)-->/', '', $text );
		$text = preg_replace( '/<!--__DTSUBSCRIBEBUTTON(DESKTOP|MOBILE)__(.*?)-->/', '', $text );

		return $text;
	}

	/**
	 * Replace placeholders for topic subscription buttons with the real thing.
	 *
	 * @param string $text
	 * @param Language $lang
	 * @param SubscriptionStore $subscriptionStore
	 * @param UserIdentity $user
	 * @param bool $isMobile
	 * @return string
	 */
	public static function postprocessTopicSubscription(
		string $text, Language $lang, SubscriptionStore $subscriptionStore, UserIdentity $user, bool $isMobile
	): string {
		$doc = DOMCompat::newDocument( true );

		$matches = [];
		preg_match_all( '/<!--__DTSUBSCRIBELINK__(.*?)-->/', $text, $matches );
		$itemNames = array_map(
			static function ( string $itemName ): string {
				return htmlspecialchars_decode( $itemName );
			},
			$matches[1]
		);

		$items = $subscriptionStore->getSubscriptionItemsForUser(
			$user,
			$itemNames
		);
		$itemsByName = [];
		foreach ( $items as $item ) {
			$itemsByName[ $item->getItemName() ] = $item;
		}

		$text = preg_replace_callback(
			'/<!--__DTSUBSCRIBELINK__(.*?)-->/',
			static function ( $matches ) use ( $doc, $itemsByName, $lang ) {
				$itemName = htmlspecialchars_decode( $matches[1] );
				$isSubscribed = isset( $itemsByName[ $itemName ] ) && !$itemsByName[ $itemName ]->isMuted();
				$subscribedState = isset( $itemsByName[ $itemName ] ) ? $itemsByName[ $itemName ]->getState() : null;

				$subscribe = $doc->createElement( 'span' );
				$subscribe->setAttribute(
					'class',
					'ext-discussiontools-init-section-subscribe mw-editsection-like'
				);

				$subscribeLink = $doc->createElement( 'a' );
				// Set empty 'href' to avoid a:not([href]) selector in MobileFrontend
				$subscribeLink->setAttribute( 'href', '' );
				$subscribeLink->setAttribute( 'class', 'ext-discussiontools-init-section-subscribe-link' );
				$subscribeLink->setAttribute( 'role', 'button' );
				$subscribeLink->setAttribute( 'tabindex', '0' );
				$subscribeLink->setAttribute( 'title', wfMessage(
					$isSubscribed ?
						'discussiontools-topicsubscription-button-unsubscribe-tooltip' :
						'discussiontools-topicsubscription-button-subscribe-tooltip'
				)->inLanguage( $lang )->text() );
				$subscribeLink->nodeValue = wfMessage(
					$isSubscribed ?
						'discussiontools-topicsubscription-button-unsubscribe' :
						'discussiontools-topicsubscription-button-subscribe'
				)->inLanguage( $lang )->text();

				if ( $subscribedState !== null ) {
					$subscribeLink->setAttribute( 'data-mw-subscribed', (string)$subscribedState );
				}

				$bracket = $doc->createElement( 'span' );
				$bracket->setAttribute( 'class', 'ext-discussiontools-init-section-subscribe-bracket' );
				$bracketOpen = $bracket->cloneNode( false );
				$bracketOpen->nodeValue = '[';
				$bracketClose = $bracket->cloneNode( false );
				$bracketClose->nodeValue = ']';

				$subscribe->appendChild( $bracketOpen );
				$subscribe->appendChild( $subscribeLink );
				$subscribe->appendChild( $bracketClose );

				return DOMCompat::getOuterHTML( $subscribe );
			},
			$text
		);

		$text = preg_replace_callback(
			'/<!--__DTSUBSCRIBEBUTTON(DESKTOP|MOBILE)__(.*?)-->/',
			static function ( $matches ) use ( $doc, $itemsByName, $lang, $isMobile ) {
				$buttonIsMobile = $matches[1] === 'MOBILE';
				if ( $buttonIsMobile !== $isMobile ) {
					return '';
				}
				$itemName = htmlspecialchars_decode( $matches[2] );
				$isSubscribed = isset( $itemsByName[ $itemName ] ) && !$itemsByName[ $itemName ]->isMuted();
				$subscribedState = isset( $itemsByName[ $itemName ] ) ? $itemsByName[ $itemName ]->getState() : null;

				$subscribe = new \OOUI\ButtonWidget( [
					'classes' => [ 'ext-discussiontools-init-section-subscribeButton' ],
					'framed' => false,
					'icon' => $isSubscribed ? 'bell' : 'bellOutline',
					'flags' => [ 'progressive' ],
					'label' => wfMessage( $isSubscribed ?
						'discussiontools-topicsubscription-button-unsubscribe-label' :
						'discussiontools-topicsubscription-button-subscribe-label'
					)->inLanguage( $lang )->text(),
					'title' => wfMessage( $isSubscribed ?
						'discussiontools-topicsubscription-button-unsubscribe-tooltip' :
						'discussiontools-topicsubscription-button-subscribe-tooltip'
					)->inLanguage( $lang )->text(),
					'infusable' => true,
				] );

				if ( $subscribedState !== null ) {
					$subscribe->setAttributes( [ 'data-mw-subscribed' => (string)$subscribedState ] );
				}

				return $subscribe->toString();
			},
			$text
		);

		return $text;
	}

	/**
	 * Replace placeholders for reply links with the real thing.
	 *
	 * @param string $text
	 * @param Language $lang
	 * @param bool $isMobile
	 * @return string
	 */
	public static function postprocessReplyTool(
		string $text, Language $lang, bool $isMobile
	): string {
		$doc = DOMCompat::newDocument( true );
		$replyLinkText = wfMessage( 'discussiontools-replylink' )->inLanguage( $lang )->escaped();
		$replyButtonText = wfMessage( 'discussiontools-replybutton' )->inLanguage( $lang )->escaped();

		$text = preg_replace_callback(
			'/<!--__DTREPLYBUTTONSCONTENT__-->/',
			static function ( $matches ) use ( $doc, $replyLinkText, $replyButtonText, $isMobile, $lang ) {
				$replyLinkButtons = $doc->createElement( 'span' );

				// Reply
				$replyLink = $doc->createElement( 'a' );
				$replyLink->setAttribute( 'class', 'ext-discussiontools-init-replylink-reply' );
				$replyLink->setAttribute( 'role', 'button' );
				$replyLink->setAttribute( 'tabindex', '0' );
				// Set empty 'href' to avoid a:not([href]) selector in MobileFrontend
				$replyLink->setAttribute( 'href', '' );
				$replyLink->textContent = $replyLinkText;

				$bracket = $doc->createElement( 'span' );
				$bracket->setAttribute( 'class', 'ext-discussiontools-init-replylink-bracket' );
				$bracketOpen = $bracket->cloneNode( false );
				$bracketClose = $bracket->cloneNode( false );
				$bracketOpen->textContent = '[';
				$bracketClose->textContent = ']';

				// Visual enhancements button
				$useIcon = $isMobile || static::isLanguageRequiringReplyIcon( $lang );
				$replyLinkButton = new \OOUI\ButtonWidget( [
					'classes' => [ 'ext-discussiontools-init-replybutton' ],
					'framed' => false,
					'label' => $replyButtonText,
					'icon' => $useIcon ? 'share' : null,
					'flags' => [ 'progressive' ],
					'infusable' => true,
				] );

				DOMCompat::setInnerHTML( $replyLinkButtons, $replyLinkButton->toString() );
				$replyLinkButtons->appendChild( $bracketOpen );
				$replyLinkButtons->appendChild( $replyLink );
				$replyLinkButtons->appendChild( $bracketClose );

				return DOMCompat::getInnerHTML( $replyLinkButtons );
			},
			$text
		);

		return $text;
	}

	/**
	 * Create a meta item label
	 *
	 * @param string $className
	 * @param string|\OOUI\HtmlSnippet $label Label
	 * @return \OOUI\Tag
	 */
	private static function metaLabel( string $className, $label ): \OOUI\Tag {
		return ( new \OOUI\Tag( 'span' ) )
			->addClasses( [ 'ext-discussiontools-init-section-metaitem', $className ] )
			->appendContent( $label );
	}

	/**
	 * Get JSON data for a commentItem that can be inserted into a comment marker
	 *
	 * @param ContentCommentItem $commentItem Comment item
	 * @param bool $includeTopicAndAuthor Include metadata about topic and author
	 * @return array
	 */
	private static function getJsonArrayForCommentMarker(
		ContentCommentItem $commentItem,
		bool $includeTopicAndAuthor = false
	): array {
		$JSON = [
			'id' => $commentItem->getId(),
			'timestamp' => $commentItem->getTimestampString()
		];
		if ( $includeTopicAndAuthor ) {
			$JSON['author'] = $commentItem->getAuthor();
			$heading = $commentItem->getSubscribableHeading();
			if ( $heading ) {
				$JSON['heading'] = $heading->jsonSerialize();
				$JSON['heading']['text'] = $heading->getText();
				$JSON['heading']['linkableTitle'] = $heading->getLinkableTitle();
			}
		}
		return $JSON;
	}

	/**
	 * Get a relative timestamp from a signature timestamp.
	 *
	 * Signature timestamps don't have seconds-level accuracy, so any
	 * time difference of less than 120 seconds is treated as being
	 * posted "just now".
	 *
	 * @param MWTimestamp $timestamp
	 * @param Language $lang
	 * @param UserIdentity $user
	 * @return string
	 */
	public static function getSignatureRelativeTime(
		MWTimestamp $timestamp, Language $lang, UserIdentity $user
	): string {
		try {
			$diff = time() - intval( $timestamp->getTimestamp() );
		} catch ( TimestampException $ex ) {
			// Can't happen
			$diff = 0;
		}
		if ( $diff < 120 ) {
			$timestamp = new MWTimestamp();
		}
		return $lang->getHumanTimestamp( $timestamp, null, $user );
	}

	/**
	 * Post-process visual enhancements features (topic containers)
	 *
	 * @param string $text
	 * @param Language $lang
	 * @param UserIdentity $user
	 * @param bool $isMobile
	 * @return string
	 */
	public static function postprocessVisualEnhancements(
		string $text, Language $lang, UserIdentity $user, bool $isMobile
	): string {
		$text = preg_replace_callback(
			'/<!--__DTLATESTCOMMENTTHREAD__(.*?)__-->/',
			static function ( $matches ) use ( $lang, $user ) {
				$itemData = json_decode( htmlspecialchars_decode( $matches[1] ), true );
				if ( $itemData && $itemData['timestamp'] && $itemData['id'] ) {
					$relativeTime = static::getSignatureRelativeTime(
						new MWTimestamp( $itemData['timestamp'] ),
						$lang,
						$user
					);
					$commentLink = Html::element( 'a', [
						'href' => '#' . Sanitizer::escapeIdForLink( $itemData['id'] )
					], $relativeTime );

					$label = wfMessage( 'discussiontools-topicheader-latestcomment' )
						->rawParams( $commentLink )
						->inLanguage( $lang )->escaped();

					return CommentFormatter::metaLabel(
						'ext-discussiontools-init-section-timestampLabel',
						new \OOUI\HtmlSnippet( $label )
					);
				}
			},
			$text
		);
		$text = preg_replace_callback(
			'/<!--__DTCOMMENTCOUNT__([0-9]+)__-->/',
			static function ( $matches ) use ( $lang, $user ) {
				$count = $lang->formatNum( $matches[1] );
				$label = wfMessage(
					'discussiontools-topicheader-commentcount',
					$count
				)->inLanguage( $lang )->text();
				return CommentFormatter::metaLabel(
					'ext-discussiontools-init-section-commentCountLabel',
					$label
				);
			},
			$text
		);
		$text = preg_replace_callback(
			'/<!--__DTAUTHORCOUNT__([0-9]+)__-->/',
			static function ( $matches ) use ( $lang, $user ) {
				$count = $lang->formatNum( $matches[1] );
				$label = wfMessage(
					'discussiontools-topicheader-authorcount',
					$count
				)->inLanguage( $lang )->text();
				return CommentFormatter::metaLabel(
					'ext-discussiontools-init-section-authorCountLabel',
					$label
				);
			},
			$text
		);
		if ( $isMobile ) {
			$text = preg_replace_callback(
				'/<!--__DTELLIPSISBUTTON__-->/',
				static function ( $matches ) {
					$ellipsis = new ButtonMenuSelectWidget( [
						'classes' => [ 'ext-discussiontools-init-section-ellipsisButton' ],
						'framed' => false,
						'icon' => 'ellipsis',
						'infusable' => true,
					] );

					return $ellipsis->toString();
				},
				$text
			);
		} else {
			$text = preg_replace(
				'/<!--__DTELLIPSISBUTTON__-->/',
				'',
				$text
			);
		}
		return $text;
	}

	/**
	 * Post-process visual enhancements features for page subtitle
	 *
	 * @param string &$text
	 * @param Language $lang
	 * @param UserIdentity $user
	 * @return ?string
	 */
	public static function postprocessVisualEnhancementsSubtitle(
		string &$text, Language $lang, UserIdentity $user
	): ?string {
		preg_match( '/<!--__DTLATESTCOMMENTPAGE__(.*?)__-->/', $text, $matches );
		// The subtitle is inserted into the correct place by the caller, so cleanup the comment here
		$text = preg_replace( '/<!--__DTLATESTCOMMENTPAGE__(.*?)-->/', '', $text );
		if ( count( $matches ) ) {
			$itemData = json_decode( htmlspecialchars_decode( $matches[1] ), true );

			if ( $itemData && $itemData['timestamp'] && $itemData['id'] ) {
				$relativeTime = static::getSignatureRelativeTime(
					new MWTimestamp( $itemData['timestamp'] ),
					$lang,
					$user
				);
				$commentLink = Html::element( 'a', [
					'href' => '#' . Sanitizer::escapeIdForLink( $itemData['id'] )
				], $relativeTime );

				if ( isset( $itemData['heading'] ) ) {
					$headingLink = Html::element( 'a', [
						'href' => '#' . Sanitizer::escapeIdForLink( $itemData['heading']['linkableTitle'] )
					], $itemData['heading']['text'] );
					$label = wfMessage( 'discussiontools-pageframe-latestcomment' )
						->rawParams( $commentLink )
						->params( $itemData['author'] )
						->rawParams( $headingLink )
						->inLanguage( $lang )->escaped();
				} else {
					$label = wfMessage( 'discussiontools-pageframe-latestcomment-notopic' )
						->rawParams( $commentLink )
						->params( $itemData['author'] )
						->inLanguage( $lang )->escaped();
				}

				return Html::rawElement(
					'div',
					[ 'class' => 'ext-discussiontools-init-pageframe-latestcomment' ],
					$label
				);
			}
		}
		return null;
	}

	/**
	 * Post-process visual enhancements features for table of contents
	 *
	 * @param ParserOutput $pout
	 * @param Language $lang
	 */
	public static function postprocessTableOfContents(
		ParserOutput $pout, Language $lang
	): void {
		$tocInfo = $pout->getExtensionData( 'DiscussionTools-tocInfo' );

		if ( $tocInfo && $pout->getTOCData() ) {
			$sections = $pout->getTOCData()->getSections();
			foreach ( $sections as $item ) {
				$key = str_replace( '_', ' ', $item->anchor );
				// Unset if we did not format this section as a topic container
				if ( isset( $tocInfo[$key] ) ) {
					$count = $lang->formatNum( $tocInfo[$key]['commentCount'] );
					$commentCount = wfMessage(
						'discussiontools-topicheader-commentcount',
						$count
					)->inLanguage( $lang )->text();

					$summary = Html::element( 'span', [
						'class' => 'ext-discussiontools-init-sidebar-meta'
					], $commentCount );
				} else {
					$summary = '';
				}

				// This also shows up in API action=parse&prop=sections output.
				$item->setExtensionData( 'DiscussionTools-html-summary', $summary );
			}
		}
	}

	/**
	 * Check if the talk page had no comments or headings.
	 *
	 * @param string $text
	 * @return bool
	 */
	public static function isEmptyTalkPage( string $text ): bool {
		return strpos( $text, '<!--__DTEMPTYTALKPAGE__-->' ) !== false;
	}

	/**
	 * Append content to an empty talk page
	 *
	 * @param string $text
	 * @param string $content
	 * @return string
	 */
	public static function appendToEmptyTalkPage( string $text, string $content ): string {
		return str_replace( '<!--__DTEMPTYTALKPAGE__-->', $content, $text );
	}

	/**
	 * Check if the talk page has content above the first heading, in the lede section.
	 *
	 * @param string $text
	 * @return bool
	 */
	public static function hasLedeContent( string $text ): bool {
		return strpos( $text, '<!--__DTHASLEDECONTENT__-->' ) !== false;
	}

	/**
	 * Check if the talk page has comments above the first heading, in the lede section.
	 *
	 * @param string $text
	 * @return bool
	 */
	public static function hasCommentsInLedeContent( string $text ): bool {
		return strpos( $text, '<!--__DTHASCOMMENTSINLEDECONTENT__-->' ) !== false;
	}

	public static function isLanguageRequiringReplyIcon( Language $lang ): bool {
		$dtConfig = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'discussiontools' );
		$languages = $dtConfig->get( 'DiscussionTools_visualenhancements_reply_icon_languages' );
		return in_array( $lang->getCode(), $languages );
	}

}
