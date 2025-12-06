<?php

namespace MediaWiki\Extension\OATHAuth\Module;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\OATHAuth\Auth\TOTPSecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\HTMLForm\IManageForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\TOTPEnableForm;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\Special\OATHManage;
use MediaWiki\Message\Message;
use UnexpectedValueException;

class TOTP implements IModule {
	public const MODULE_NAME = "totp";

	/**
	 * @return TOTPKey[]
	 */
	public static function getTOTPKeys( OATHUser $user ): array {
		// @phan-suppress-next-line PhanTypeMismatchReturn
		return $user->getKeysForModule( self::MODULE_NAME );
	}

	public function __construct(
		private readonly OATHUserRepository $userRepository,
	) {
	}

	/** @inheritDoc */
	public function getName() {
		return self::MODULE_NAME;
	}

	/** @inheritDoc */
	public function getDisplayName() {
		return wfMessage( 'oathauth-module-totp-label' );
	}

	/**
	 * @inheritDoc
	 * @throws UnexpectedValueException
	 */
	public function newKey( array $data ) {
		if ( !isset( $data['secret'] ) ) {
			throw new UnexpectedValueException( 'oathauth-invalid-data-format' );
		}

		return TOTPKey::newFromArray( $data );
	}

	/**
	 * @return TOTPSecondaryAuthenticationProvider
	 */
	public function getSecondaryAuthProvider() {
		return new TOTPSecondaryAuthenticationProvider(
			$this,
			$this->userRepository
		);
	}

	/** @inheritDoc */
	public function verify( OATHUser $user, array $data ): bool {
		if ( !isset( $data['token'] ) ) {
			return false;
		}

		foreach ( self::getTOTPKeys( $user ) as $key ) {
			if ( $key->verify( $data, $user ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Is this module currently enabled for the given user?
	 */
	public function isEnabled( OATHUser $user ): bool {
		return (bool)self::getTOTPKeys( $user );
	}

	/**
	 * @param string $action
	 * @param OATHUser $user
	 * @param OATHUserRepository $repo
	 * @param IContextSource $context
	 * @return IManageForm|TOTPEnableForm|null
	 */
	public function getManageForm(
		$action,
		OATHUser $user,
		OATHUserRepository $repo,
		IContextSource $context
	) {
		if ( $action === OATHManage::ACTION_ENABLE ) {
			return new TOTPEnableForm( $user, $repo, $this, $context );
		}
		return null;
	}

	/** @inheritDoc */
	public function getDescriptionMessage() {
		return wfMessage( 'oathauth-totp-description' );
	}

	/** @inheritDoc */
	public function getDisableWarningMessage() {
		return wfMessage( 'oathauth-totp-disable-warning' );
	}

	/** @inheritDoc */
	public function getAddKeyMessage(): Message {
		return wfMessage( 'oathauth-totp-add-key' );
	}

	/** @inheritDoc */
	public function getLoginSwitchButtonMessage() {
		return wfMessage( 'oathauth-auth-switch-module-label' );
	}

	/** @inheritDoc */
	public function isSpecial(): bool {
		return false;
	}
}
