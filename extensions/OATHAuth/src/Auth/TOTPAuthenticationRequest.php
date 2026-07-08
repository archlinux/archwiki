<?php

/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Language\RawMessage;

/**
 * AuthManager value object for the TOTP second factor of an authentication:
 * a pseudorandom token that is generated from the current time independently
 * by the server and the client.
 */
class TOTPAuthenticationRequest extends AuthenticationRequest {
	public string $OATHToken;

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
			'OATHToken' => [
				'type' => 'string',
				'label' => wfMessage( 'oathauth-auth-token-label' ),
				'help' => wfMessage( 'oathauth-auth-token-help' )
			]
		];
	}
}
