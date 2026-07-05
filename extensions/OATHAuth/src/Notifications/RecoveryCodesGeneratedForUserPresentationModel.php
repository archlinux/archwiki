<?php

namespace MediaWiki\Extension\OATHAuth\Notifications;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class RecoveryCodesGeneratedForUserPresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'site';
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => SpecialPage::getTitleFor( 'OATHManage' )->getLocalURL(),
			'label' => $this->msg( 'oathauth-notifications-recoverycodes-generated-for-user-primary' )->text()
		];
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		$links = [];

		$helpLink = $this->msg( 'oathauth-notifications-recoverycodes-generated-for-user-helplink' )
			->inContentLanguage();
		$helpTitle = Title::newFromText( $helpLink->plain() );
		if ( $helpTitle ) {
			$links[] = [
				'url' => $helpTitle->getLocalURL(),
				'label' => $this->msg( 'oathauth-notifications-recoverycodes-generated-for-user-help' )->text(),
				'icon' => 'help',
			];
		}

		$contactUrl = $this->msg( 'oathauth-notifications-recoverycodes-generated-for-user-contacturl' )
			->inContentLanguage()->plain();
		if ( $contactUrl ) {
			// Ensure that the URL is valid and uses supported scheme (to guard from e.g. javascript:)
			$parsedUrl = MediaWikiServices::getInstance()->getUrlUtils()->parse( $contactUrl );
			if ( $parsedUrl !== null ) {
				$links[] = [
					'url' => $contactUrl,
					'label' => $this->msg( 'oathauth-notifications-recoverycodes-generated-for-user-contact' )->text(),
					'icon' => 'message',
				];
			}
		}
		return $links;
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		$msg = $this->msg( $this->getHeaderMessageKey() );
		// Leave 'agent' params unused, just in case we use them in future
		$msg->params( '', '' );
		$msg->params( $this->event->getExtraParam( 'codeCount', 0 ) );
		return $msg;
	}

	/** @inheritDoc */
	public function getBodyMessage() {
		$msg = $this->msg( 'notification-body-oathauth-recoverycodes-generated-for-user' );
		$msg->params( $this->event->getExtraParam( 'codeCount', 0 ) );
		$msg->params(
			$this->event->getExtraParam(
				'generatedCount',
				MediaWikiServices::getInstance()->getMainConfig()->get( 'OATHRecoveryCodesCount' )
			)
		);
		return $msg;
	}

}
