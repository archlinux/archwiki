<?php

/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Key;

use Base32\Base32;
use DomainException;
use jakobo\HOTP\HOTP;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * Class representing a two-factor key
 *
 * Keys can be tied to OATHUsers
 *
 * @ingroup Extensions
 */
class TOTPKey extends AuthKey {
	/** TOTP binary secret */
	private array $secret;

	public static function newFromRandom(): TOTPKey {
		return new self(
			null,
			null,
			null,
			// 26 bytes to give at least 128 bits (26 * 8 = 208 bits of entropy)
			// https://phabricator.wikimedia.org/T396951
			self::removeBase32Padding( Base32::encode( random_bytes( 26 ) ) ),
		);
	}

	/**
	 * @see T408225, T401393
	 */
	private static function removeBase32Padding( string $paddedBase32String ): string {
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
			$encryptionHelper = self::getEncryptionHelper();
			if ( !$encryptionHelper->isEnabled() ) {
				// @codeCoverageIgnoreStart
				throw new UnexpectedValueException(
					'Encryption is not configured but OATHAuth is attempting to use encryption'
				);
				// @codeCoverageIgnoreEnd
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
		?int $id,
		?string $friendlyName,
		?string $createdTimestamp,
		string $secret,
		string $encryptedSecret = '',
		string $nonce = ''
	) {
		parent::__construct( $id, $friendlyName, $createdTimestamp );
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

	public function getSecret(): string {
		return $this->secret['secret'];
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

	public function verify( OATHUser $user, array $data ): bool {
		global $wgOATHAuthWindowRadius;

		$token = $data['token'] ?? '';

		if ( $this->secret['mode'] !== 'hotp' ) {
			// @codeCoverageIgnoreStart
			throw new DomainException( 'OATHAuth extension does not support non-HOTP tokens' );
			// @codeCoverageIgnoreEnd
		}

		// Prevent replay attacks
		$services = MediaWikiServices::getInstance();
		$store = $services->getMainObjectStash();

		if ( $store instanceof EmptyBagOStuff ) {
			// @codeCoverageIgnoreStart
			// Try and find some usable cache if the MainObjectStash isn't useful
			$store = $services->getObjectCacheFactory()->getLocalServerInstance( CACHE_ANYTHING );
			// @codeCoverageIgnoreEnd
		}

		$key = $store->makeKey( 'oathauth-totp', 'usedtokens', $user->getCentralId() );
		$lastWindow = (int)$store->get( $key );

		$results = HOTP::generateByTimeWindow(
			Base32::decode( $this->secret['secret'] ),
			$this->secret['period'],
			-$wgOATHAuthWindowRadius,
			$wgOATHAuthWindowRadius,
			(int)ConvertibleTimestamp::now( TimestampFormat::UNIX )
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
		$encryptionHelper = self::getEncryptionHelper();
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

		$data['friendly_name'] = $this->getFriendlyName();
		return $data;
	}

	private static function getEncryptionHelper(): EncryptionHelper {
		return OATHAuthServices::getInstance()->getEncryptionHelper();
	}
}
