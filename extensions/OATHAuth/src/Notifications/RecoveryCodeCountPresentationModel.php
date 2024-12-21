<?php

namespace MediaWiki\Extension\OATHAuth\Notifications;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class RecoveryCodeCountPresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'site';
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => SpecialPage::getTitleFor( 'OATHManage' )->getLocalURL(),
			'label' => $this->msg( 'oathauth-notifications-recoverycodesleft-primary' )->text()
		];
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		$link = $this->msg( 'oathauth-notifications-recoverycodesleft-helplink' )->inContentLanguage();
		$title = Title::newFromText( $link->plain() );
		if ( !$title ) {
			// Invalid title, skip
			return [];
		}
		return [ [
			'url' => $title->getLocalURL(),
			'label' => $this->msg( 'oathauth-notifications-recoverycodesleft-help' )->text(),
			'icon' => 'help',
		] ];
	}

	/** @inheritDoc */
	public function getBodyMessage() {
		$msg = $this->getMessageWithAgent( 'notification-body-oathauth-recoverycodesleft' );
		$msg->params( $this->event->getExtraParam( 'codeCount', 0 ) );
		$msg->params( $this->event->getExtraParam( 'generatedCount', TOTPKey::RECOVERY_CODES_COUNT ) );
		return $msg;
	}

}
