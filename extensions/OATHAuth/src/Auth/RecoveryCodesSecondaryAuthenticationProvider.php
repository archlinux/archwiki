<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

namespace MediaWiki\Extension\OATHAuth\Auth;

use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Message\Message;

/**
 * AuthManager secondary authentication provider for Recovery Codes second-factor authentication.
 *
 * After a successful primary authentication, requests a recovery code from the user.
 *
 * @see AuthManager
 */
class RecoveryCodesSecondaryAuthenticationProvider extends AbstractSecondaryAuthenticationProvider {

	public function __construct(
		private readonly RecoveryCodes $module,
		private readonly OATHUserRepository $userRepository
	) {
	}

	/** @inheritDoc */
	public function getAuthenticationRequests( $action, array $options ): array {
		// don't ask for anything initially, so the second factor is on a separate screen
		return [];
	}

	/**
	 * If the user has a recovery code module enabled, request a second factor.
	 *
	 * @inheritDoc
	 */
	public function beginSecondaryAuthentication( $user, array $reqs ): AuthenticationResponse {
		$authUser = $this->userRepository->findByUser( $user );

		if ( !$this->module->isEnabled( $authUser ) ) {
			return AuthenticationResponse::newAbstain();
		}

		return AuthenticationResponse::newUI(
			[ new RecoveryCodesAuthenticationRequest() ],
			wfMessage( 'oathauth-auth-recovery-code-help' ),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function continueSecondaryAuthentication( $user, array $reqs ) {
		/** @var RecoveryCodesAuthenticationRequest $request */
		$request = AuthenticationRequest::getRequestByClass( $reqs, RecoveryCodesAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newUI( [ new RecoveryCodesAuthenticationRequest() ],
				wfMessage( 'oathauth-recovery-code-login-failed' ), 'error' );
		}

		// Don't increase pingLimiter, just check for limit exceeded.
		if ( $user->pingLimiter( 'badoath', 0 ) ) {
			return AuthenticationResponse::newUI(
				[ new RecoveryCodesAuthenticationRequest() ],
				new Message(
					'oathauth-throttled',
					// Arbitrary duration given here
					[ Message::durationParam( 60 ) ]
				), 'error' );
		}

		$authUser = $this->userRepository->findByUser( $user );
		$recoveryCode = $request->RecoveryCode;

		if ( $this->module->verify( $authUser, [ 'recoverycode' => $recoveryCode ] ) ) {
			return AuthenticationResponse::newPass();
		}

		// Increase rate limit counter for failed request
		$user->pingLimiter( 'badoath' );

		$this->logger->info( 'OATHAuth user {user} failed recovery code from {clientip}', [
			'user'     => $user->getName(),
			'clientip' => $user->getRequest()->getIP(),
		] );

		return AuthenticationResponse::newUI(
			[ new RecoveryCodesAuthenticationRequest() ],
			wfMessage( 'oathauth-recovery-code-login-failed' ),
			'error'
		);
	}

	/** @inheritDoc */
	public function beginSecondaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}
}
