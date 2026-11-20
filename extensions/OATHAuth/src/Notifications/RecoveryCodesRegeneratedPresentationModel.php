<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\OATHAuth\Notifications;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\SpecialPage\SpecialPage;

class RecoveryCodesRegeneratedPresentationModel extends EchoEventPresentationModel {

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
	public function getBodyMessage() {
		return $this->getMessageWithAgent( 'notification-body-oathauth-recoverycodes-regenerated' );
	}

}
