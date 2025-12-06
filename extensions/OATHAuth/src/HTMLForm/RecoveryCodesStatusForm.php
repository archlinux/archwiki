<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Status\Status;
use UnexpectedValueException;

class RecoveryCodesStatusForm extends OATHAuthOOUIHTMLForm {
	use KeySessionStorageTrait;
	use RecoveryCodesTrait;

	/**
	 * @param array|bool|Status|string $submitResult
	 * @return string
	 */
	public function getHTML( $submitResult ) {
		$out = $this->getOutput();
		$out->addModuleStyles( 'ext.oath.recovery.styles' );
		$out->addModules( 'ext.oath.recovery' );
		$out->setPageTitleMsg( $this->msg( 'oathauth-recoverycodes-header-create' ) );
		return parent::getHTML( $submitResult );
	}

	/** @inheritDoc */
	protected function getDescriptors() {
		if ( $this->oathUser->userHasNonSpecialEnabledKeys() ) {
			$submitMsg = $this->msg(
				'oathauth-recoverycodes-create-label',
				$this->getConfig()->get( 'OATHRecoveryCodesCount' )
			);
			$this->setSubmitTextMsg( $submitMsg );
			$this->setSubmitDestructive();
			$this->showCancel();
			$this->setCancelTarget( $this->getTitle() );
		} else {
			$this->suppressDefaultSubmit();
		}
		return [
			'warning' => [
				'type' => 'info',
				'default' => $this->msg( 'oathauth-recoverycodes-regenerate-warning',
					$this->getConfig()->get( 'OATHRecoveryCodesCount' ) )->parse(),
				'raw' => true,
			] ];
	}

	/**
	 * Add content to output when operation was successful
	 */
	public function onSuccess() {
		$moduleDbKeys = $this->oathUser->getKeysForModule( $this->module->getName() );

		if ( count( $moduleDbKeys ) > RecoveryCodeKeys::RECOVERY_CODE_MODULE_COUNT ) {
			throw new UnexpectedValueException( $this->msg( 'oathauth-recoverycodes-too-many-instances' )->escaped() );
		}

		if ( array_key_exists( 0, $moduleDbKeys ) ) {
			$recoveryCodes = $this->getRecoveryCodesForDisplay( array_shift( $moduleDbKeys ) );
			$output = $this->getOutput();
			$output->addModuleStyles( 'ext.oath.recovery.styles' );
			$output->addModules( 'ext.oath.recovery' );
			$output->addHtml(
				$this->generateRecoveryCodesContent( $recoveryCodes )
			);
		}
	}

	/**
	 * @param array $formData
	 * @return array|bool
	 */
	public function onSubmit( array $formData ) {
		$keys = $this->oathUser->getKeysForModule( $this->module->getName() );
		if ( $keys ) {
			/** @var RecoveryCodeKeys $objRecoveryCodeKeys */
			$objRecoveryCodeKeys = array_shift( $keys );
			'@phan-var RecoveryCodeKeys $objRecoveryCodeKeys';
			$objRecoveryCodeKeys->regenerateRecoveryCodeKeys();
		}

		RecoveryCodeKeys::maybeCreateOrUpdateRecoveryCodeKeys( $this->oathUser );

		LoggerFactory::getInstance( 'authentication' )->info(
			"OATHAuth {user} generated new recovery codes from {clientip}", [
				'user' => $this->getUser()->getName(),
				'clientip' => $this->getRequest()->getIP(),
			]
		);

		return true;
	}
}
