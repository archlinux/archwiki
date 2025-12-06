<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Message\Message;

class DisableForm extends OATHAuthOOUIHTMLForm {

	/** @inheritDoc */
	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'oathauth-disabledoath' );
	}

	/** @inheritDoc */
	protected function getDescriptors() {
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

	/** @inheritDoc */
	public function onSubmit( array $formData ) {
		$this->oathRepo->removeAllOfType(
			$this->oathUser,
			$this->module->getName(),
			$this->getRequest()->getIP(),
			true
		);
		return true;
	}
}
