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
