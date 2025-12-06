<?php

namespace MediaWiki\Extension\OATHAuth\Module;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\OATHAuth\Auth\RecoveryCodesSecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\HTMLForm\IManageForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\RecoveryCodesStatusForm;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Message\Message;
use UnexpectedValueException;

class RecoveryCodes implements IModule {
	public const MODULE_NAME = "recoverycodes";

	public function __construct( private readonly OATHUserRepository $userRepository ) {
	}

	/** @inheritDoc */
	public function getName() {
		return self::MODULE_NAME;
	}

	/** @inheritDoc */
	public function getDisplayName() {
		return wfMessage( 'oathauth-module-recoverycodes-label' );
	}

	/**
	 * @inheritDoc
	 * @throws UnexpectedValueException
	 */
	public function newKey( array $data ) {
		if ( !isset( $data['recoverycodekeys'] ) ) {
			throw new UnexpectedValueException( 'oathauth-invalid-recovery-code-data-format' );
		}
		return RecoveryCodeKeys::newFromArray( $data );
	}

	public function getSecondaryAuthProvider(): RecoveryCodesSecondaryAuthenticationProvider {
		return new RecoveryCodesSecondaryAuthenticationProvider(
			$this,
			$this->userRepository
		);
	}

	public function verify( OATHUser $user, array $data ): bool {
		if ( !isset( $data['recoverycode'] ) ) {
			return false;
		}

		$recoveryCodeKeys = $user->getRecoveryCodes();

		if ( $recoveryCodeKeys === [] ) {
			return false;
		}

		/** @var RecoveryCodeKeys $recoveryCodeKey */
		$recoveryCodeKey = $recoveryCodeKeys[0];

		if ( $recoveryCodeKey->verify( $data, $user ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Is this module currently enabled for the given user?
	 */
	public function isEnabled( OATHUser $user ): bool {
		return (bool)$user->getRecoveryCodes();
	}

	/** @inheritDoc */
	public function getManageForm(
		$action,
		OATHUser $user,
		OATHUserRepository $repo,
		IContextSource $context
	): ?IManageForm {
		return new RecoveryCodesStatusForm( $user, $repo, $this, $context );
	}

	/** @inheritDoc */
	public function getDescriptionMessage() {
		return wfMessage( 'oathauth-recoverycodes-description' );
	}

	/** @inheritDoc */
	public function getDisableWarningMessage() {
		return null;
	}

	public function getAddKeyMessage(): ?Message {
		return null;
	}

	public function getLoginSwitchButtonMessage(): Message {
		return wfMessage( 'oathauth-auth-use-recovery-code' );
	}

	/** @inheritDoc */
	public function isSpecial(): bool {
		return true;
	}
}
