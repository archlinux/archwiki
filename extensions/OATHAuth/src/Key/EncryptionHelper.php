<?php
/**
 * Copyright (C) 2022 Kunal Mehta <legoktm@debian.org>
 *
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

namespace MediaWiki\Extension\OATHAuth\Key;

use Base32\Base32;
use MediaWiki\Config\ServiceOptions;
use RuntimeException;
use UnexpectedValueException;

/**
 * Wrapper around sodium's cryptobox to encrypt and decrypt the OTP secret
 */
class EncryptionHelper {
	/** @internal */
	public const CONSTRUCTOR_OPTIONS = [
		'OATHSecretKey',
	];

	public function __construct(
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/** @return bool Whether encryption is enabled. */
	public function isEnabled(): bool {
		$key = $this->options->get( 'OATHSecretKey' );
		if ( !$key ) {
			return false;
		}

		if ( !extension_loaded( 'sodium' ) ) {
			// @codeCoverageIgnoreStart
			throw new RuntimeException( 'OATHAuth encryption requires ext-sodium' );
			// @codeCoverageIgnoreEnd
		}

		if ( strlen( $key ) !== ( SODIUM_CRYPTO_SECRETBOX_KEYBYTES * 2 ) ) {
			throw new UnexpectedValueException( 'OATHAuth encryption key has invalid length' );
		}

		if ( !ctype_xdigit( $key ) ) {
			throw new UnexpectedValueException( 'OATHAuth encryption key must be in hexadecimal' );
		}

		return true;
	}

	/**
	 * Get the encryption secret key as bytes
	 */
	private function getKey(): string {
		return sodium_hex2bin( $this->options->get( 'OATHSecretKey' ) );
	}

	/**
	 * Decrypt the given ciphertext
	 *
	 * @param string $ciphertext base32 encoded
	 * @param string $nonce base32 encoded
	 * @return string
	 * @throws UnexpectedValueException When decryption fails
	 */
	public function decrypt( string $ciphertext, string $nonce ) {
		$plaintext = sodium_crypto_secretbox_open(
			Base32::decode( $ciphertext ),
			Base32::decode( $nonce ),
			$this->getKey(),
		);
		if ( $plaintext === false ) {
			throw new UnexpectedValueException( 'Unable to decrypt ciphertext' );
		}
		return $plaintext;
	}

	/**
	 * Encrypt the given plaintext
	 *
	 * @param string $plaintext What to encrypt
	 * @return string[] Array with 'secret' and 'nonce' keys, both base32 encoded
	 */
	public function encrypt( string $plaintext, string $nonce = '' ) {
		// Generate a unique nonce
		if ( $nonce === '' ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		} else {
			// reused nonces (for array elements et al) are expected to be Base32-encoded
			$nonce = Base32::decode( $nonce );
		}

		$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $this->getKey() );
		return [
			'secret' => Base32::encode( $ciphertext ),
			'nonce' => Base32::encode( $nonce ),
		];
	}

	public function decryptStringArrayValues( array $array, string $nonce ): array {
		$decryptedArray = [];
		foreach ( $array as $key => $value ) {
			$decryptedValue = $this->decrypt( strval( $value ), $nonce );
			$decryptedArray[$key] = $decryptedValue;
		}
		return $decryptedArray;
	}

	public function encryptStringArrayValues( array $array, string $nonce = '' ): array {
		$encryptedArray = [];
		foreach ( $array as $key => $value ) {
			$encryptedValue = $this->encrypt( strval( $value ), $nonce );
			$encryptedArray[$key] = $encryptedValue['secret'];
			if ( $nonce === '' ) {
				$nonce = $encryptedValue['nonce'];
			}
		}
		return [ 'encrypted_array' => $encryptedArray, 'nonce' => $nonce ];
	}
}
