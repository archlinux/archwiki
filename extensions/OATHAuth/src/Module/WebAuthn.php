<?php

namespace MediaWiki\Extension\OATHAuth\Module;

use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\Auth\WebAuthnSecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\HTMLForm\OATHAuthOOUIHTMLForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\WebAuthnAddKeyForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\WebAuthnManageForm;
use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\Special\OATHManage;
use MediaWiki\Message\Message;

class WebAuthn implements IModule {
	/**
	 * Custom action for the manage form
	 */
	public const ACTION_ADD_KEY = 'addkey';

	public const MODULE_ID = "webauthn";

	public static function factory(): IModule {
		return new static();
	}

	/**
	 * @return WebAuthnKey[]
	 */
	public static function getWebAuthnKeys( OATHUser $user ): array {
		// @phan-suppress-next-line PhanTypeMismatchReturn
		return $user->getKeysForModule( self::MODULE_ID );
	}

	/** @inheritDoc */
	public function getName(): string {
		return self::MODULE_ID;
	}

	public function getDisplayName(): Message {
		return wfMessage( 'oathauth-webauthn-module-label' );
	}

	public function newKey( array $data = [] ): WebAuthnKey {
		if ( !$data ) {
			return WebAuthnKey::newKey();
		}
		return WebAuthnKey::newFromData( $data );
	}

	public function getSecondaryAuthProvider(): WebAuthnSecondaryAuthenticationProvider {
		return new WebAuthnSecondaryAuthenticationProvider();
	}

	/** @inheritDoc */
	public function isEnabled( OATHUser $user ): bool {
		return (bool)self::getWebAuthnKeys( $user );
	}

	/**
	 * Run the validation for each of the registered keys
	 */
	public function verify( OATHUser $user, array $data ): bool {
		$keys = self::getWebAuthnKeys( $user );
		foreach ( $keys as $key ) {
			// Pass if any of the keys matches
			if ( $key->verify( $user, $data ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns the appropriate form for the given action.
	 * If the ability to add new credentials is disabled by configuration,
	 * the empty string will be returned for any action other than ACTION_DISABLE.
	 * The value null will be returned If no suitable form is found otherwise.
	 */
	public function getManageForm(
		string $action,
		OATHUser $user,
		OATHUserRepository $repo,
		IContextSource $context,
		OATHAuthModuleRegistry $registry
	): ?OATHAuthOOUIHTMLForm {
		if ( $context->getConfig()->get( 'WebAuthnNewCredsDisabled' ) === false ) {
			if ( $action === OATHManage::ACTION_ENABLE || $action === static::ACTION_ADD_KEY ) {
				return new WebAuthnAddKeyForm( $user, $repo, $this, $context, $registry );
			}
			if ( $this->isEnabled( $user ) ) {
				return new WebAuthnManageForm( $user, $repo, $this, $context, $registry );
			}
		}

		return null;
	}

	/** @inheritDoc */
	public function getDescriptionMessage(): Message {
		return wfMessage( 'oathauth-webauthn-module-description' );
	}

	/** @inheritDoc */
	public function getDisableWarningMessage(): ?Message {
		return null;
	}

	/** @inheritDoc */
	public function getAddKeyMessage(): ?Message {
		return RequestContext::getMain()->getConfig()->get( 'WebAuthnNewCredsDisabled' ) ?
			null :
			wfMessage( 'oathauth-webauthn-add-security-key' );
	}

	/** @inheritDoc */
	public function isSpecial(): bool {
		return false;
	}

	/** @inheritDoc */
	public function getLoginSwitchButtonMessage(): Message {
		return wfMessage( 'oathauth-webauthn-auth-switch-module-label-passkey' );
	}
}
