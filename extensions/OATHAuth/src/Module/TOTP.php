<?php

namespace MediaWiki\Extension\OATHAuth\Module;

use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\Auth\TOTPSecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\HTMLForm\OATHAuthOOUIHTMLForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\TOTPEnableForm;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\Special\OATHManage;
use MediaWiki\Logger\LoggerFactory;
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
	public function getName(): string {
		return self::MODULE_NAME;
	}

	public function getDisplayName(): Message {
		return wfMessage( 'oathauth-module-totp-label' );
	}

	public function newKey( array $data ): TOTPKey {
		if ( !isset( $data['secret'] ) ) {
			throw new UnexpectedValueException( 'oathauth-invalid-data-format' );
		}

		return TOTPKey::newFromArray( $data );
	}

	public function getSecondaryAuthProvider(): TOTPSecondaryAuthenticationProvider {
		return new TOTPSecondaryAuthenticationProvider(
			$this,
			$this->userRepository,
			OATHAuthServices::getInstance()->getLogger()
		);
	}

	/** @inheritDoc */
	public function verify( OATHUser $user, array $data ): bool {
		if ( !isset( $data['token'] ) ) {
			return false;
		}

		foreach ( self::getTOTPKeys( $user ) as $key ) {
			if ( $key->verify( $user, $data ) ) {
				return true;
			}
		}

		// Check recovery codes
		// TODO: We should deprecate (T408043) logging in on the TOTP form using recovery codes, and eventually
		// remove this ability (T408044).

		/** @var RecoveryCodes $recoveryCodes */
		$recoveryCodes = OATHAuthServices::getInstance()->getModuleRegistry()
			->getModuleByKey( RecoveryCodes::MODULE_NAME );
		$validRecoveryCode = $recoveryCodes->verify( $user, [ 'recoverycode' => $data['token'] ?? '' ] );
		if ( $validRecoveryCode ) {
			LoggerFactory::getInstance( 'authentication' )->info(
				// phpcs:ignore
				"OATHAuth {user} used a recovery code from {clientip} on TOTP form.", [
					'user' => $user->getUser()->getName(),
					'clientip' => RequestContext::getMain()->getRequest()->getIP()
				]
			);
			return true;
		}

		return false;
	}

	/**
	 * Is this module currently enabled for the given user?
	 */
	public function isEnabled( OATHUser $user ): bool {
		return (bool)self::getTOTPKeys( $user );
	}

	public function getManageForm(
		string $action,
		OATHUser $user,
		OATHUserRepository $repo,
		IContextSource $context,
		OATHAuthModuleRegistry $registry
	): ?OATHAuthOOUIHTMLForm {
		if ( $action === OATHManage::ACTION_ENABLE ) {
			return new TOTPEnableForm( $user, $repo, $this, $context, $registry );
		}
		return null;
	}

	/** @inheritDoc */
	public function getDescriptionMessage(): Message {
		return wfMessage( 'oathauth-totp-description' );
	}

	/** @inheritDoc */
	public function getDisableWarningMessage(): Message {
		return wfMessage( 'oathauth-totp-disable-warning' );
	}

	/** @inheritDoc */
	public function getAddKeyMessage(): Message {
		return wfMessage( 'oathauth-totp-add-key' );
	}

	/** @inheritDoc */
	public function getLoginSwitchButtonMessage(): Message {
		return wfMessage( 'oathauth-auth-switch-module-label' );
	}

	/** @inheritDoc */
	public function isSpecial(): bool {
		return false;
	}
}
