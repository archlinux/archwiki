<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Status\Status;

/**
 * @property RecoveryCodes $module
 */
class RecoveryCodesRemoveTemporaryForm extends OATHAuthOOUIHTMLForm {

	private int $temporaryCodesCount = 0;
	private int $permanentCodesCount = 0;

	public function __construct(
		OATHUser $oathUser,
		OATHUserRepository $oathRepo,
		RecoveryCodes $module,
		IContextSource $context,
		OATHAuthModuleRegistry $moduleRegistry
	) {
		$key = $module->ensureExistence( $oathUser );

		foreach ( $key->getRecoveryCodes() as $code ) {
			if ( $code->isPermanent() ) {
				$this->permanentCodesCount++;
			} else {
				$this->temporaryCodesCount++;
			}
		}

		parent::__construct( $oathUser, $oathRepo, $module, $context, $moduleRegistry );
	}

	/** @inheritDoc */
	public function getHTML( $submitResult ) {
		$out = $this->getOutput();
		$out->addModuleStyles( 'ext.oath.recovery.styles' );
		$out->addModules( 'ext.oath.recovery' );
		$out->setPageTitleMsg(
			$this->msg( 'oathauth-recoverycodes-temporary-remove-header' )
				->numParams( $this->temporaryCodesCount )
		);
		return parent::getHTML( $submitResult );
	}

	protected function getDescriptors(): array {
		if ( $this->oathUser->userHasNonSpecialEnabledKeys() ) {
			$submitMsg = $this->msg( 'oathauth-recoverycodes-temporary-remove-label' )
				->numParams( $this->temporaryCodesCount );
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
				'default' => $this->msg( 'oathauth-recoverycodes-temporary-remove-text' )
					->numParams( $this->temporaryCodesCount )
					->parse(),
				'raw' => true,
			] ];
	}

	/**
	 * Add content to output when the operation was successful
	 */
	public function onSuccess(): void {
		$output = $this->getOutput();
		$output->addWikiMsg(
			$this->msg( 'oathauth-recoverycodes-temporary-remove-success' )
				->numParams( $this->temporaryCodesCount, $this->permanentCodesCount )
		);
	}

	public function onSubmit( array $formData ): Status|bool|array|string {
		$key = $this->module->ensureExistence( $this->oathUser );
		$key->removeTemporaryCodes();
		$this->oathRepo->updateKey( $this->oathUser, $key );

		LoggerFactory::getInstance( 'authentication' )->info(
			"OATHAuth {user} invalidated temporary recovery codes from {clientip}", [
				'user' => $this->getUser()->getName(),
				'clientip' => $this->getRequest()->getIP(),
			]
		);

		return true;
	}
}
