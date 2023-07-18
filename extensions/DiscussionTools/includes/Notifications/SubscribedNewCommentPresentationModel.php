<?php
/**
 * EchoEventPresentationModel for new comments in a subscribed section
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\DiscussionTools\Notifications;

use EchoEvent;
use EchoEventPresentationModel;
use Language;
use MediaWiki\MediaWikiServices;
use Message;
use RawMessage;
use User;
use Wikimedia\Timestamp\TimestampException;

class SubscribedNewCommentPresentationModel extends EchoEventPresentationModel {
	use DiscussionToolsEventTrait;

	protected PlaintextEchoPresentationModelSection $section;

	/**
	 * @inheritDoc
	 */
	protected function __construct( EchoEvent $event, Language $language, User $user, $distributionType ) {
		parent::__construct( $event, $language, $user, $distributionType );
		$this->section = new PlaintextEchoPresentationModelSection( $event, $user, $language );
	}

	/**
	 * @inheritDoc
	 */
	public function getIconType() {
		return 'chat';
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
	 * @inheritDoc
	 * @throws TimestampException
	 */
	public function jsonSerialize(): array {
		$array = parent::jsonSerialize();

		$legacyPrimaryLink = $this->getPrimaryLink();
		$legacyPrimaryLink['url'] = $this->section->getTitleWithSection()->getFullURL();
		$array['links']['legacyPrimary'] = $this->addMarkAsRead( $legacyPrimaryLink ) ?: [];

		return $array;
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderMessageKey() {
		if ( $this->isBundled() ) {
			return 'discussiontools-notification-subscribed-new-comment-header-bundled';
		} else {
			return 'discussiontools-notification-subscribed-new-comment-header';
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getHeaderMessage() {
		if ( $this->isBundled() ) {
			$count = $this->getNotificationCountForOutput();
			$msg = $this->msg( $this->getHeaderMessageKey() );

			// Repeat is B/C until unused parameter is removed from translations
			$msg->numParams( $count, $count );
			$msg->plaintextParams( $this->section->getTruncatedSectionTitle() );
			return $msg;
		} else {
			$msg = parent::getHeaderMessage();
			$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
			$msg->plaintextParams( $this->section->getTruncatedSectionTitle() );
			return $msg;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getCompactHeaderMessage() {
		$msg = $this->getMessageWithAgent( 'discussiontools-notification-subscribed-new-comment-header-compact' );
		$msg->plaintextParams( $this->getContentSnippet() );
		return $msg;
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyMessage() {
		if ( !$this->isBundled() ) {
			return new RawMessage( '$1', [ Message::plaintextParam( $this->getContentSnippet() ) ] );
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getSecondaryLinks() {
		$title = $this->event->getTitle();

		$url = $title->getLocalURL( [
			'oldid' => 'prev',
			'diff' => $this->event->getExtraParam( 'revid' )
		] );
		$viewChangesLink = [
			'url' => $url,
			'label' => $this->msg( 'notification-link-text-view-changes', $this->getViewingUserForGender() )->text(),
			'description' => '',
			'icon' => 'changes',
			'prioritized' => true,
		];

		$links = [
			$this->getAgentLink(),
			$viewChangesLink,
		];

		$subscriptionStore = MediaWikiServices::getInstance()->getService( 'DiscussionTools.SubscriptionStore' );
		$items = $subscriptionStore->getSubscriptionItemsForUser(
			$this->getUser(),
			[ $this->event->getExtraParam( 'subscribed-comment-name' ) ]
		);
		$isSubscribed = count( $items ) && !$items[0]->isMuted();
		if ( $isSubscribed ) {
			$commentName = $this->event->getExtraParam( 'subscribed-comment-name' );
			$links[] = $this->getDynamicActionLink(
				$this->event->getTitle(),
				'bellOutline',
				$this->msg( 'discussiontools-topicsubscription-action-unsubscribe-button' )->text(),
				null,
				[
					'tokenType' => 'csrf',
					'params' => [
						'action' => 'discussiontoolssubscribe',
						'page' => $this->event->getTitle(),
						'commentname' => $commentName,
						// 'subscribe' is unset
					],
					'messages' => [
						'confirmation' => [
							'title' => $this->msg( 'discussiontools-topicsubscription-notify-unsubscribed-title' ),
							'description' => $this->msg( 'discussiontools-topicsubscription-notify-unsubscribed-body' )
						]
					]
				],
				[
					'action' => 'dtunsubscribe',
					'commentname' => $commentName,
				]
			);
		}

		return $links;
	}
}
