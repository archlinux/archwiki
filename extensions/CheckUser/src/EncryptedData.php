<?php

namespace MediaWiki\CheckUser;

class EncryptedData {

	/** @var string|null The data symmetrically encrypted with a random key */
	public $encString;

	/** @var string|null Symmetric key, encrypted with the public key */
	public $envKeys;

	/**
	 * @var string algorithm name, passed into openssl 'method' param. Kept as a variable here in case
	 * the class definition needs to change, and we have serialized objects stored.
	 */
	private $algName;

	/**
	 * @var int Hash of the public key, in case you've used multiple keys, and need to identify the
	 * correct private key
	 */
	private $keyHash;

	/**
	 * Create an EncryptedData object from
	 *
	 * @param mixed $data Data/object to be encryted
	 * @param string $publicKey Public key for encryption
	 * @param string $algorithmName
	 */
	public function __construct( $data, $publicKey, $algorithmName = 'rc4' ) {
		$this->keyHash = crc32( $publicKey );
		$this->algName = $algorithmName;
		$this->encryptData( serialize( $data ), $publicKey );
	}

	/**
	 * Decrypt the text in this object
	 *
	 * @param string $privateKey String with ascii-armored block,
	 *   or the return of openssl_get_privatekey
	 * @return string|false plaintext
	 */
	public function getPlaintext( $privateKey ) {
		$result = \openssl_open(
			$this->encString,
			$plaintextData,
			$this->envKeys,
			$privateKey,
			$this->algName
		);

		if ( !$result ) {
			return false;
		}

		return unserialize( $plaintextData );
	}

	/**
	 * Encrypt data with a public key
	 *
	 * @param string $data
	 * @param string $publicKey String with ascii-armored block,
	 *   or the return of openssl_get_publickey
	 */
	private function encryptData( $data, $publicKey ) {
		// @phan-suppress-next-line PhanTypeMismatchArgumentInternal
		\openssl_seal( $data, $encryptedString, $envelopeKeys, [ $publicKey ], $this->algName );
		$this->encString = $encryptedString;
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		$this->envKeys = $envelopeKeys[0];
	}
}
