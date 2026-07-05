<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Key;

use Base32\Base32;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use RuntimeException;
use UnexpectedValueException;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use Wikimedia\Timestamp\TimestampFormat as TS;

/**
 * Represents a single recovery code, which is a part of the {@see RecoveryCodeKeys} module.
 * It abstracts out the code encryption and expiration.
 */
class RecoveryCode {

	/**
	 * Length (in bytes) that recovery codes should be
	 */
	private const RECOVERY_CODE_LENGTH = 10;

	/**
	 * Creates a new recovery code for the specified plaintext code
	 * @param string $plaintext The recovery code
	 * @param array $data Additional data to store with this key, {@see self::__construct}
	 */
	public static function newFromPlaintext( string $plaintext, array $data = [] ): RecoveryCode {
		return new self( self::getEncryptionHelper(), $plaintext, $data );
	}

	/**
	 * Creates a new recovery code, by decoding the passed ciphertext
	 * @param string $encrypted Encrypted recovery code
	 * @param string $nonce Nonce used for encrypting this code
	 * @param array $data Additional data to store with this key, {@see self::__construct}
	 */
	public static function newFromEncrypted( string $encrypted, string $nonce, array $data = [] ): RecoveryCode {
		$encryptionHelper = self::getEncryptionHelper();
		if ( !$encryptionHelper->isEnabled() ) {
			throw new UnexpectedValueException( 'Loading encrypted recovery code, but encryption is not configured' );
		}

		$plaintext = $encryptionHelper->decrypt( $encrypted, $nonce );
		return new self( $encryptionHelper, $plaintext, $data, $encrypted, $nonce );
	}

	/**
	 * Creates a new recovery code with random string associated
	 * @param array $data Additional data to store with this key, {@see self::__construct}
	 */
	public static function newRandom( array $data = [] ): RecoveryCode {
		$code = Base32::encode( random_bytes( self::RECOVERY_CODE_LENGTH ) );
		return new self( self::getEncryptionHelper(), $code, $data );
	}

	/**
	 * @param EncryptionHelper $encryptionHelper A service to use when encrypting the codes
	 * @param string $plaintextCode The recovery code in plain text
	 * @param array $data Optional additional data to be stored along this key.
	 *   * 'expiry' - a MediaWiki-style timestamp when this code becomes expired (and unusable). If unspecified or set
	 *     to an invalid value, the code doesn't expire.
	 * @param string|null $encryptedCode The encrypted representation of this code. If set, will be used as a cache,
	 *     to prevent re-encrypting of the code on saving.
	 * @param string|null $nonce The nonce used for encrypting this code. Should be specified together with
	 *     $encryptedCode.
	 */
	public function __construct(
		private readonly EncryptionHelper $encryptionHelper,
		private readonly string $plaintextCode,
		private readonly array $data = [],
		private ?string $encryptedCode = null,
		private ?string $nonce = null,
	) {
	}

	/** Returns a plaintext representation of this code */
	public function getCode(): string {
		return $this->plaintextCode;
	}

	/** Returns the additional data associated with this key */
	public function getData(): array {
		return $this->data;
	}

	/** Returns the nonce used when encrypting this code */
	public function getNonce(): ?string {
		return $this->nonce;
	}

	/**
	 * Encrypts code, using the specified nonce.
	 * The encrypted code is cached, so any subsequent calls with the same nonce will not cause re-encryption.
	 */
	public function encryptCode( string $nonce ): string {
		if ( $nonce !== $this->nonce || !$this->encryptedCode ) {
			if ( !$this->encryptionHelper->isEnabled() ) {
				throw new RuntimeException( 'Encrypting recovery code, but encryption is not configured' );
			}

			$result = $this->encryptionHelper->encrypt( $this->plaintextCode, $nonce );
			$this->encryptedCode = $result['secret'];
			$this->nonce = $result['nonce'];
		}
		return $this->encryptedCode;
	}

	/**
	 * Returns true if the supplied token matches this code, false otherwise.
	 * The comparison is resistant to timing attacks.
	 * If the code is expired, returns false, regardless of the input data.
	 */
	public function test( string $suppliedToken ): bool {
		if ( $this->isExpired() ) {
			return false;
		}
		return hash_equals( $this->getCode(), $suppliedToken );
	}

	/** Returns the timestamp at which the code expires or null if it doesn't expire. */
	public function getExpiryTimestamp(): ?string {
		if ( !isset( $this->data['expiry'] ) ) {
			return null;
		}
		$expiry = ConvertibleTimestamp::convert( TS::MW, $this->data['expiry'] );
		if ( $expiry === false ) {
			return null;
		}
		return $expiry;
	}

	/** Returns true if this code has no expiry time set. */
	public function isPermanent(): bool {
		return $this->getExpiryTimestamp() === null;
	}

	/** Checks if this code has expired. Unless explicitly specified, recovery codes don't expire. */
	public function isExpired(): bool {
		$expiry = $this->getExpiryTimestamp();
		if ( $expiry === null ) {
			return false;
		}
		return ConvertibleTimestamp::now() > $expiry;
	}

	private static function getEncryptionHelper(): EncryptionHelper {
		return OATHAuthServices::getInstance()->getEncryptionHelper();
	}
}
