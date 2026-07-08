<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\OATHAuth\HTMLField\NoJsInfoField;
use MediaWiki\Extension\OATHAuth\HTMLField\RegisteredKeyLayout;
use MediaWiki\Extension\OATHAuth\Module\IModule;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use OOUI\ButtonWidget;

/**
 * @property WebAuthn $module
 */
class WebAuthnManageForm extends OATHAuthOOUIHTMLForm {

	/** @inheritDoc */
	public function __construct(
		OATHUser $oathUser,
		OATHUserRepository $oathRepo,
		IModule $module,
		IContextSource $context,
		OATHAuthModuleRegistry $moduleRegistry
	) {
		parent::__construct( $oathUser, $oathRepo, $module, $context, $moduleRegistry );

		$this->setId( 'webauthn-manage-form' );
		$this->suppressDefaultSubmit();

		$this->panelPadded = false;
		$this->panelFramed = false;
	}

	/** @inheritDoc */
	public function getHTML( $submitResult ) {
		$this->getOutput()->addModules( 'ext.webauthn.manage' );
		return parent::getHTML( $submitResult );
	}

	/**
	 * @return ButtonWidget
	 */
	public function getButtons() {
		return new ButtonWidget( [
			'id' => 'button_add_key',
			'flags' => [ 'progressive', 'primary' ],
			'disabled' => true,
			'label' => wfMessage( 'oathauth-webauthn-ui-add-key' )->plain(),
			'href' => SpecialPage::getTitleFor( 'OATHManage' )->getLocalURL( [
				'module' => 'webauthn',
				'action' => WebAuthn::ACTION_ADD_KEY
			] ),
			'infusable' => true
		] );
	}

	public function onSuccess(): void {
		// Not used - redirect is handled client-side after API call
	}

	public function onSubmit( array $formData ): Status|bool|array|string {
		// This is handled client-side via API
		return [ 'oathauth-webauthn-javascript-required' ];
	}

	protected function getDescriptors(): array {
		$oathUser = $this->oathRepo->findByUser( $this->getUser() );
		$keys = WebAuthn::getWebAuthnKeys( $oathUser );

		$registeredKeys = [];
		foreach ( $keys as $idx => $key ) {
			$registeredKeys["reg_key_$idx"] = [
				'type' => 'null',
				'default' => [
					'name' => $key->getFriendlyName(),
					'signCount' => $key->getSignCounter()
				],
				'raw' => true,
				'class' => RegisteredKeyLayout::class,
				'section' => 'webauthn-registered-keys-section-name'
			];
		}

		return [
			'nojs' => [
				'class' => NoJsInfoField::class,
				'section' => 'webauthn-registered-keys-section-name',
			],
		] + $registeredKeys;
	}
}
