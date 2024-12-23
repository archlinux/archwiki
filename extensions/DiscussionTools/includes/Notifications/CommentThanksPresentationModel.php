<?php
/**
 * EchoEventPresentationModel for comment thanks notifications
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\DiscussionTools\Notifications;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\Extension\Notifications\Formatters\EchoPresentationModelSection;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Language\Language;
use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\User;

class CommentThanksPresentationModel extends EchoEventPresentationModel {

	use DiscussionToolsEventTrait;

	/**
	 * @var EchoPresentationModelSection
	 */
	private $section;

	/**
	 * @inheritDoc
	 */
	protected function __construct( Event $event, Language $language, User $user, $distributionType ) {
		parent::__construct( $event, $language, $user, $distributionType );
		$this->section = new EchoPresentationModelSection( $event, $user, $language );
	}

	/**
	 * @inheritDoc
	 */
	public function getIconType() {
		return 'thanks';
	}

	/**
	 * @inheritDoc
	 */
	public function canRender() {
		return (bool)$this->event->getTitle();
	}

	/**
	 * @inheritDoc
	 */
	public function getPrimaryLink() {
		return [
			'url' => $this->getCommentLink() ?: $this->event->getTitle()->getFullURL(),
			'label' => $this->msg( 'discussiontools-notification-subscribed-new-comment-view' )->text()
		];
	}

	/**
	 * Get a link to the individual comment, if available.
	 *
	 * @return string|null Full URL linking to the comment, null if not available
	 */
	protected function getCommentLink(): ?string {
		if ( !$this->userCan( RevisionRecord::DELETED_TEXT ) ) {
			return null;
		}
		// Thanks notifications are bundled by comment-id, so the link will always be to a single comment
		// (unlike in DiscussionToolsEventTrait)
		$commentId = $this->event->getExtraParam( 'comment-id' );
		if ( !$commentId ) {
			return null;
		}
		$title = $this->event->getTitle();
		return $title->createFragmentTarget( $commentId )->getFullURL();
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderMessageKey() {
		if ( $this->isBundled() ) {
			return 'discussiontools-notification-comment-thank-header-bundled';
		} else {
			return 'discussiontools-notification-comment-thank-header';
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getHeaderMessage() {
		$title = $this->section->getTruncatedSectionTitle();
		if ( !$title ) {
			// Comment could have been at the top of the page before
			// any section titles. Use the page title instead.
			$title = $this->event->getTitle()->getPrefixedText();
		}
		if ( $this->isBundled() ) {
			$count = $this->getNotificationCountForOutput();
			$msg = $this->msg( $this->getHeaderMessageKey() );

			// Params 1, 2, 3:
			$msg->numParams( $count );
			$msg->plaintextParams( $title );
			$msg->params( $this->getViewingUserForGender() );
			return $msg;
		} else {
			$msg = parent::getHeaderMessage();
			// Params 3, 4:
			$msg->plaintextParams( $title );
			$msg->params( $this->getViewingUserForGender() );
			return $msg;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getCompactHeaderMessage() {
		$msg = $this->getMessageWithAgent( 'discussiontools-notification-comment-thank-header-compact' );
		// Param 3:
		$msg->params( $this->getViewingUserForGender() );
		return $msg;
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyMessage() {
		if ( !$this->isBundled() ) {
			return new RawMessage( '$1', [ Message::plaintextParam( $this->getContentSnippet() ) ] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getSecondaryLinks() {
		$pageLink = $this->getPageLink( $this->event->getTitle(), '', true );
		if ( $this->isBundled() ) {
			return [ $pageLink ];
		} else {
			return [ $this->getAgentLink(), $pageLink ];
		}
	}
}
