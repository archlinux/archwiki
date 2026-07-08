<?php
/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Language\RawMessage;

class WebAuthnAuthenticationRequest extends AuthenticationRequest {

	public string $credential;

	/**
	 * @param string $authInfo Serialized JSON blob obtained from
	 *   WebAuthnAuthenticator::startAuthentication()
	 * @param bool $showPrompt Whether to display the prompt telling the user to use their security key.
	 */
	public function __construct(
		public string $authInfo,
		public bool $showPrompt = true
	) {
	}

	/** @inheritDoc */
	public function describeCredentials() {
		return [
			'provider' => wfMessage( 'oathauth-describe-provider' ),
			'account' => new RawMessage( '$1', [ $this->username ] ),
		] + parent::describeCredentials();
	}

	/** @inheritDoc */
	public function getFieldInfo() {
		return ( $this->showPrompt ? [
			'label' => [
				'type' => 'null',
				'value' => wfMessage( 'oathauth-webauthn-ui-login-prompt' ),
				// TODO: Use a different message for help?
				'help' => wfMessage( 'oathauth-webauthn-ui-login-prompt' ),
			]
		] : [] ) + [
			// The hidden auth_info field only exists to send the authInfo JSON blob to the client.
			// It's not used for authentication and ignored when submitted back to us, we get the
			// authInfo blob from the session instead.
			'auth_info' => [
				'type' => 'hidden',
				'value' => $this->authInfo,
				'label' => wfMessage( 'oathauth-webauthn-authentication-info-label' ),
				'help' => wfMessage( 'oathauth-webauthn-authentication-info-help' ),
			],
			'credential' => [
				'type' => 'hidden',
				'value' => '',
				'label' => wfMessage( 'oathauth-webauthn-credential-label' ),
				'help' => wfMessage( 'oathauth-webauthn-credential-help' ),
			]
		];
	}

	/** @inheritDoc */
	public function loadFromSubmission( array $data ) {
		if ( !isset( $data['credential'] ) ) {
			return false;
		}
		$this->credential = $data['credential'];

		return true;
	}

	public function getSubmittedData(): array {
		// Don't trust the submitted auth_info, otherwise the user could control which challenge
		// we're validating against and do a replay attack. Instead, we use the authInfo blob
		// in the session, which we stored there when we issued the challenge.
		return [
			'credential' => $this->credential
		];
	}
}
