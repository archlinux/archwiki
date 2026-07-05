<?php
/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Language\RawMessage;

/**
 * AuthManager value object for the Recovery Codes second factor of authentication:
 * a pre-generated recovery code (aka scratch token) that is created whenever an OATH
 * user enables at least one form of 2FA (TOTP, WebAuthn, etc.) and is regenerated upon
 * each successful usage of a recovery code.
 */
class RecoveryCodesAuthenticationRequest extends AuthenticationRequest {
	public string $RecoveryCode;

	/** @inheritDoc */
	public function describeCredentials() {
		return [
			'provider' => wfMessage( 'oathauth-describe-provider' ),
			'account' => new RawMessage( '$1', [ $this->username ] ),
		] + parent::describeCredentials();
	}

	/** @inheritDoc */
	public function getFieldInfo() {
		return [
			'RecoveryCode' => [
				'type' => 'string',
				'label' => wfMessage( 'oathauth-auth-recovery-code-label' ),
				'help' => wfMessage( 'oathauth-auth-recovery-code-help' ),
				'optional' => true
			]
		];
	}
}
