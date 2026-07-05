<?php
/**
 * Copyright (C) 2022 Kunal Mehta <legoktm@debian.org>
 *
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Notifications;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class DisablePresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'site';
	}

	/** @inheritDoc */
	protected function getHeaderMessageKey() {
		return $this->event->getExtraParam( 'activeDevices', 0 ) === 0
			? 'notification-header-oathauth-disable'
			: 'notification-header-oathauth-remove-device';
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => SpecialPage::getTitleFor( 'OATHManage' )->getLocalURL(),
			'label' => $this->msg( 'oathauth-notifications-disable-primary' )->text()
		];
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		$link = $this->msg( 'oathauth-notifications-disable-helplink' )->inContentLanguage();
		$title = Title::newFromText( $link->plain() );
		if ( !$title ) {
			// Invalid title, skip
			return [];
		}
		return [ [
			'url' => $title->getLocalURL(),
			'label' => $this->msg( 'oathauth-notifications-disable-help' )->text(),
			'icon' => 'help',
		] ];
	}

	/** @inheritDoc */
	public function getBodyMessage() {
		$helpMessageKey = $this->event->getExtraParam( 'self', true )
			? 'notification-body-oathauth-disable'
			: 'notification-body-oathauth-disable-other';
		$message = $this->getMessageWithAgent( $helpMessageKey );

		if ( $this->event->getExtraParam( 'activeDevices', 0 ) >= 1 ) {
			$remainingMessage = $this->getMessageWithAgent( 'notification-body-oathauth-disable-remaining' );
			$remainingMessage->params( $this->event->getExtraParam( 'activeDevices', 0 ) );

			$message = $this->msg( 'rawmessage' )->rawParams(
				$message->escaped()
				. $this->msg( 'word-separator' )->escaped()
				. $remainingMessage->escaped()
			);
		}

		return $message;
	}
}
