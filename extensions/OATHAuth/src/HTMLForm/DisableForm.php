<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Status\Status;

class DisableForm extends OATHAuthOOUIHTMLForm {

	public function onSuccess(): void {
		$this->getOutput()->addWikiMsg( 'oathauth-disabledoath' );
	}

	protected function getDescriptors(): array {
		$this->setSubmitTextMsg( 'oathauth-disable-generic' );
		$this->setSubmitDestructive();

		$disableWarning = $this->msg(
			'oathauth-disable-method-warning',
			$this->module->getDisplayName()
		)->parseAsBlock();
		$customMessage = $this->module->getDisableWarningMessage();
		if ( $customMessage instanceof Message ) {
			$disableWarning .= $customMessage->parseAsBlock();
		}

		return [
			'warning' => [
				'type' => 'info',
				'raw' => true,
				'default' => $disableWarning
			]
		];
	}

	public function onSubmit( array $formData ): Status|bool|array|string {
		$this->oathRepo->removeAllOfType(
			$this->oathUser,
			$this->module->getName(),
			$this->getRequest()->getIP(),
			true
		);

		if ( !$this->oathUser->getKeys() && ExtensionRegistry::getInstance()->isLoaded( 'CheckUser' ) ) {
			$logEntry = new ManualLogEntry( 'oath', 'disable-self' );
			$logEntry->setPerformer( $this->getUser() );
			$logEntry->setTarget( $this->getUser()->getUserPage() );
			/** @var CheckUserInsert $checkUserInsert */
			$checkUserInsert = MediaWikiServices::getInstance()->get( 'CheckUserInsert' );
			$checkUserInsert->updateCheckUserData( $logEntry->getRecentChange() );
		}

		return true;
	}
}
