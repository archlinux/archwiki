<?php

namespace MediaWiki\Extension\Notifications\Formatters;

/**
 * Presenter for 'article-reminder' notification
 *
 * @author Ela Opper
 *
 * @license MIT
 */
class EchoArticleReminderPresentationModel extends EchoEventPresentationModel {
	/** @inheritDoc */
	public function canRender() {
		return (bool)$this->event->getTitle();
	}

	/** @inheritDoc */
	public function getIconType() {
		return 'article-reminder';
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		$msg = $this->getMessageWithAgent( 'notification-header-article-reminder' );
		$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
		return $msg;
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => $this->event->getTitle()->getLocalURL(),
			'label' => $this->msg( 'notification-link-article-reminder' )->text(),
		];
	}
}
