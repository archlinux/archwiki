<?php

namespace MediaWiki\Extension\Notifications\Formatters;

use MediaWiki\MediaWikiServices;

class EchoEditUserPagePresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function canRender() {
		return (bool)$this->event->getTitle();
	}

	/** @inheritDoc */
	public function getIconType() {
		return 'edit-user-page';
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => $this->getDiffLinkUrl(),
			'label' => $this->msg( 'notification-link-text-view-edit' )->text()
		];
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		if ( $this->isBundled() ) {
			return [];
		} else {
			return [ $this->getAgentLink() ];
		}
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		if ( $this->isBundled() ) {
			$msg = $this->msg( 'notification-bundle-header-edit-user-page' );
			$count = $this->getNotificationCountForOutput();

			$msg->numParams( $count );
			$msg->params( $this->getViewingUserForGender() );
			return $msg;
		} else {
			// Messages: notification-header-edit-user-page
			$msg = parent::getHeaderMessage();
			$msg->params( $this->getViewingUserForGender() );
			return $msg;
		}
	}

	/** @inheritDoc */
	public function getCompactHeaderMessage() {
		$msg = $this->getMessageWithAgent( 'notification-compact-header-edit-user-page' );
		$msg->params( $this->getViewingUserForGender() );
		return $msg;
	}

	/** @inheritDoc */
	public function getBodyMessage() {
		if ( $this->isBundled() ) {
			return false;
		}
		return $this->getRevisionCommentMessage();
	}

	private function getDiffLinkUrl(): string {
		$revId = $this->event->getExtraParam( 'revid' );
		$oldId = $this->isBundled() ? $this->getRevBeforeFirstNotification() : 'prev';
		$query = [
			'oldid' => $oldId,
			'diff' => $revId,
		];
		return $this->event->getTitle()->getFullURL( $query );
	}

	private function getRevBeforeFirstNotification(): int {
		$events = $this->getBundledEvents();
		$firstNotificationRevId = end( $events )->getExtraParam( 'revid' );
		$revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
		$revisionRecord = $revisionLookup->getRevisionById( $firstNotificationRevId );
		$previousRevision = $revisionRecord ? $revisionLookup->getPreviousRevision( $revisionRecord ) : null;
		$oldRevisionID = $previousRevision ? $previousRevision->getId() : 0;

		return $oldRevisionID;
	}

	/** @inheritDoc */
	protected function getSubjectMessageKey() {
		return 'notification-edit-user-page-email-subject';
	}
}
