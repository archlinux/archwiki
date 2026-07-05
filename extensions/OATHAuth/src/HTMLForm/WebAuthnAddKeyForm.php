<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\OATHAuth\HTMLField\AddKeyLayout;
use MediaWiki\Extension\OATHAuth\HTMLField\NoJsInfoField;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Module\IModule;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Status\Status;

class WebAuthnAddKeyForm extends OATHAuthOOUIHTMLForm {

	use KeySessionStorageTrait;
	use RecoveryCodesTrait;

	/** @inheritDoc */
	public function __construct(
		OATHUser $oathUser,
		OATHUserRepository $oathRepo,
		IModule $module,
		IContextSource $context,
		OATHAuthModuleRegistry $registry
	) {
		parent::__construct( $oathUser, $oathRepo, $module, $context, $registry );

		$this->setId( 'webauthn-add-key-form' );
		$this->suppressDefaultSubmit();

		$this->panelPadded = false;
		$this->panelFramed = false;
	}

	/** @inheritDoc */
	public function getHTML( $submitResult ) {
		$html = parent::getHTML( $submitResult );

		$this->getOutput()->addModules( 'ext.webauthn.register' );

		$moduleDbKeys = $this->oathUser->getKeysForModule( $this->module->getName() );

		$recCodeKeys = [ 'recoverycodekeys' => [] ];
		if ( array_key_exists( 0, $moduleDbKeys ) ) {
			$objRecoveryCodeKeys = array_shift( $moduleDbKeys );
			if ( $objRecoveryCodeKeys instanceof RecoveryCodeKeys ) {
				$recCodeKeys = $objRecoveryCodeKeys->jsonSerialize();
			}
		}
		$recCodeKeysForDisplay = $this->setKeyDataInSession(
			'RecoveryCodeKeys',
			$recCodeKeys,
		);
		$recCodeKeysForContent = $this->getRecoveryCodesForDisplay( $recCodeKeysForDisplay );
		$this->getOutput()->addModules( 'ext.oath.recovery' );
		$this->getOutput()->addModuleStyles( 'ext.oath.recovery.styles' );
		$this->setOutputJsConfigVars( $recCodeKeysForContent );

		return $html . $this->generateRecoveryCodesContent( $recCodeKeysForContent, true );
	}

	public function onSuccess(): void {
		// Not used - redirect is handled client-side after API call
	}

	public function onSubmit( array $formData ): Status|bool|array|string {
		// Registration is handled client-side via API (action=webauthn&func=register)
		return [ 'webauthn-javascript-required' ];
	}

	protected function getDescriptors(): array {
		return [
			'nojs' => [
				'class' => NoJsInfoField::class,
				'section' => 'webauthn-add-key-section-name',
			],
			'name-layout' => [
				'label-message' => 'oathauth-webauthn-ui-key-register-help',
				'class' => AddKeyLayout::class,
				'raw' => true,
				'section' => 'webauthn-add-key-section-name'
			],
		];
	}
}
