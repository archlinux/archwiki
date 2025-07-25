<?php

namespace MediaWiki\CheckUser\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use MediaWiki\Config\ConfigException;
use MediaWiki\Json\FormatJson;
use MediaWiki\Session\Session;
use MediaWiki\Utils\MWTimestamp;
use RuntimeException;

class TokenManager {
	/** @var string */
	private const SIGNING_ALGO = 'HS256';

	/** @var string|null */
	private $cipherMethod;

	private string $secret;

	/**
	 * @param string $secret
	 */
	public function __construct(
		string $secret
	) {
		if ( $secret === '' ) {
			throw new ConfigException(
				'CheckUser Token Manager requires $wgSecretKey to be set.'
			);
		}
		$this->secret = $secret;
	}

	/**
	 * Creates a token
	 *
	 * @param Session $session
	 * @param array $data
	 * @return string
	 */
	public function encode( Session $session, array $data ): string {
		$key = $this->getSessionKey( $session );
		$iv = $this->getInitializationVector();
		return JWT::encode(
			[
				// Expiration Time https://tools.ietf.org/html/rfc7519#section-4.1.4
				// 24 hours from now
				'exp' => MWTimestamp::time() + 86400,
				'iv' => base64_encode( $iv ),
				// Encrypt the form data to prevent it from being leaked.
				'data' => $this->encrypt( $data, $iv ),
			],
			$this->getSigningKey( $key ),
			self::SIGNING_ALGO
		);
	}

	/**
	 * Encrypt private data.
	 *
	 * @param mixed $input
	 * @param string $iv
	 * @return string
	 */
	private function encrypt( $input, string $iv ): string {
		return openssl_encrypt(
			FormatJson::encode( $input ),
			$this->getCipherMethod(),
			$this->secret,
			0,
			$iv
		);
	}

	/**
	 * Decode the JWT and return the targets.
	 *
	 * @param Session $session
	 * @param string $token
	 * @return array
	 */
	public function decode( Session $session, string $token ): array {
		$key = $this->getSessionKey( $session );
		$payload = JWT::decode(
			$token,
			new Key( $this->getSigningKey( $key ), self::SIGNING_ALGO )
		);

		return $this->decrypt(
			$payload->data,
			base64_decode( $payload->iv )
		);
	}

	/**
	 * Decrypt private data.
	 *
	 * @param string $input
	 * @param string $iv
	 * @return array
	 */
	private function decrypt( string $input, string $iv ): array {
		$decrypted = openssl_decrypt(
			$input,
			$this->getCipherMethod(),
			$this->secret,
			0,
			$iv
		);

		if ( $decrypted === false ) {
			throw new RuntimeException( 'Decryption Failed' );
		}

		return FormatJson::parse( $decrypted, FormatJson::FORCE_ASSOC )->getValue();
	}

	/**
	 * Get the initialization vector.
	 *
	 * This must be consistent between encryption and decryption,
	 * must be no more than 16 bytes in length and never repeat.
	 *
	 * @return string
	 */
	private function getInitializationVector(): string {
		return random_bytes( 16 );
	}

	/**
	 * Decide what type of encryption to use, based on system capabilities.
	 *
	 * @see Session::getEncryptionAlgorithm()
	 *
	 * @return string
	 */
	private function getCipherMethod(): string {
		if ( !$this->cipherMethod ) {
			$methods = openssl_get_cipher_methods();
			if ( in_array( 'aes-256-ctr', $methods, true ) ) {
				$this->cipherMethod = 'aes-256-ctr';
			} elseif ( in_array( 'aes-256-cbc', $methods, true ) ) {
				$this->cipherMethod = 'aes-256-cbc';
			} else {
				throw new ConfigException( 'No valid cipher method found with openssl_get_cipher_methods()' );
			}
		}

		return $this->cipherMethod;
	}

	/**
	 * Get the session key suitable for the signing key and initialization vector.
	 *
	 * For the initialization vector, this must be consistent between encryption and decryption
	 * and must be no more than 16 bytes in length.
	 *
	 * This is retrieved from the session or randomly generated and stored in the session. This means
	 * that a token cannot be shared between sessions.
	 *
	 * @param Session $session
	 *
	 * @return string
	 */
	private function getSessionKey( Session $session ): string {
		$key = $session->get( 'CheckUserTokenKey' );
		if ( $key === null ) {
			$key = base64_encode( random_bytes( 16 ) );
			$session->set( 'CheckUserTokenKey', $key );
		}

		return base64_decode( $key );
	}

	/**
	 * Get the signing key.
	 *
	 * @param string $sessionKey
	 * @return string
	 */
	private function getSigningKey( string $sessionKey ): string {
		return hash_hmac( 'sha256', $sessionKey, $this->secret );
	}
}
