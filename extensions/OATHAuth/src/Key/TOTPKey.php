<?php

namespace MediaWiki\Extension\OATHAuth\Key;

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

use Base32\Base32;
use DomainException;
use Exception;
use jakobo\HOTP\HOTP;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;
use Wikimedia\ObjectCache\EmptyBagOStuff;

/**
 * Class representing a two-factor key
 *
 * Keys can be tied to OATHUsers
 *
 * @ingroup Extensions
 */
class TOTPKey implements IAuthKey {
	/** @var array TOTP binary secret */
	private $secret;

	/**
	 * @return TOTPKey
	 * @throws Exception
	 */
	public static function newFromRandom() {
		return new self(
			null,
			null,
			null,
			// 26 digits to give 128 bits - https://phabricator.wikimedia.org/T396951
			self::removeBase32Padding( Base32::encode( random_bytes( 26 ) ) ),
		);
	}

	/**
	 * @param string $paddedBase32String
	 * @return string
	 * @see T408225, T401393
	 */
	public static function removeBase32Padding( string $paddedBase32String ) {
		return rtrim( $paddedBase32String, '=' );
	}

	/**
	 * @param array $data
	 * @return TOTPKey|null on invalid data
	 * @throws UnexpectedValueException When encryption is not configured but db is encrypted
	 */
	public static function newFromArray( array $data ) {
		if ( !isset( $data['secret'] ) ) {
			return null;
		}

		if ( isset( $data['nonce'] ) ) {
			$encryptionHelper = OATHAuthServices::getInstance()->getEncryptionHelper();
			if ( !$encryptionHelper->isEnabled() ) {
				throw new UnexpectedValueException(
					'Encryption is not configured but OATHAuth is attempting to use encryption'
				);
			}
			$data['encrypted_secret'] = $data['secret'];
			$data['secret'] = $encryptionHelper->decrypt( $data['secret'], $data['nonce'] );
		} else {
			$data['encrypted_secret'] = '';
			$data['nonce'] = '';
		}

		return new static(
			$data['id'] ?? null,
			$data['friendly_name'] ?? null,
			$data['created_timestamp'] ?? null,
			$data['secret'] ?? '',
			$data['encrypted_secret'],
			$data['nonce']
		);
	}

	public function __construct(
		private readonly ?int $id,
		private readonly ?string $friendlyName,
		private readonly ?string $createdTimestamp,
		string $secret,
		string $encryptedSecret = '',
		string $nonce = ''
	) {
		// Currently hardcoded values; might be used in the future
		$this->secret = [
			'mode' => 'hotp',
			'secret' => $secret,
			'period' => 30,
			'algorithm' => 'SHA1',
			'encrypted_secret' => $encryptedSecret,
			'nonce' => $nonce
		];
	}

	public function getId(): ?int {
		return $this->id;
	}

	public function getFriendlyName(): ?string {
		return $this->friendlyName;
	}

	public function getSecret(): string {
		return $this->secret['secret'];
	}

	public function getCreatedTimestamp(): ?string {
		return $this->createdTimestamp;
	}

	public function setEncryptedSecretAndNonce( string $encryptedSecret, string $nonce ) {
		$this->secret['encrypted_secret'] = $encryptedSecret;
		$this->secret['nonce'] = $nonce;
	}

	public function getEncryptedSecretAndNonce(): array {
		return [
			$this->secret['encrypted_secret'],
			$this->secret['nonce'],
		];
	}

	/**
	 * @param array $data
	 * @param OATHUser $user
	 * @return bool
	 * @throws DomainException
	 */
	public function verify( $data, OATHUser $user ) {
		global $wgOATHAuthWindowRadius;

		$token = $data['token'] ?? '';

		if ( $this->secret['mode'] !== 'hotp' ) {
			throw new DomainException( 'OATHAuth extension does not support non-HOTP tokens' );
		}

		// Prevent replay attacks
		$services = MediaWikiServices::getInstance();
		$store = $services->getMainObjectStash();

		if ( $store instanceof EmptyBagOStuff ) {
			// Try and find some usable cache if the MainObjectStash isn't useful
			$store = $services->getObjectCacheFactory()->getLocalServerInstance( CACHE_ANYTHING );
		}

		$key = $store->makeKey( 'oathauth-totp', 'usedtokens', $user->getCentralId() );
		$lastWindow = (int)$store->get( $key );

		$results = HOTP::generateByTimeWindow(
			Base32::decode( $this->secret['secret'] ),
			$this->secret['period'],
			-$wgOATHAuthWindowRadius,
			$wgOATHAuthWindowRadius
		);

		// Remove any whitespace from the received token, which can be an intended group separator
		$token = preg_replace( '/\s+/', '', $token );

		$clientIP = RequestContext::getMain()->getRequest()->getIP();

		$logger = $this->getLogger();

		// Check to see if the user's given token is in the list of tokens generated
		// for the time window.
		foreach ( $results as $window => $result ) {
			if ( $window <= $lastWindow || !hash_equals( $result->toHOTP( 6 ), $token ) ) {
				continue;
			}

			$lastWindow = $window;

			$logger->info( 'OATHAuth user {user} entered a valid OTP from {clientip}', [
				'user' => $user->getAccount(),
				'clientip' => $clientIP,
			] );

			$store->set(
				$key,
				$lastWindow,
				$this->secret['period'] * ( 1 + 2 * $wgOATHAuthWindowRadius )
			);

			return true;
		}

		// TODO: We should deprecate (T408043) logging in on the TOTP form using recovery codes, and eventually
		// remove this ability (T408044).
		$moduleDbKeysRecCodes = $user->getKeysForModule( RecoveryCodes::MODULE_NAME );

		if ( array_key_exists( 0, $moduleDbKeysRecCodes ) ) {
			/** @var RecoveryCodeKeys $recoveryCodeKeys */
			$recoveryCodeKeys = array_shift( $moduleDbKeysRecCodes );
			'@phan-var RecoveryCodeKeys $recoveryCodeKeys';
			$res = $recoveryCodeKeys->verify( [ 'recoverycode' => $token ], $user );
			if ( $res ) {
				$logger->info(
					// phpcs:ignore
					"OATHAuth {user} used a recovery code from {clientip} on TOTP form.", [
						'user' => $user->getUser()->getName(),
						'clientip' => $clientIP
					]
				);
			}

			return $res;
		}

		return false;
	}

	/** @inheritDoc */
	public function getModule(): string {
		return TOTP::MODULE_NAME;
	}

	/**
	 * @return LoggerInterface
	 */
	private function getLogger(): LoggerInterface {
		return LoggerFactory::getInstance( 'authentication' );
	}

	public function jsonSerialize(): array {
		$encryptedData = $this->getEncryptedSecretAndNonce();
		$encryptionHelper = OATHAuthServices::getInstance()->getEncryptionHelper();
		if ( $encryptionHelper->isEnabled() && in_array( '', $encryptedData ) ) {
			$data = $encryptionHelper->encrypt( $this->getSecret() );
			$this->setEncryptedSecretAndNonce( $data['secret'], $data['nonce'] );
		} elseif ( $encryptionHelper->isEnabled() ) {
			$data = [
				'secret' => $encryptedData[0],
				'nonce' => $encryptedData[1]
			];
		} else {
			$data = [ 'secret' => $this->getSecret() ];
		}

		$data['friendly_name'] = $this->friendlyName;
		return $data;
	}
}
