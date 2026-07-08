<?php

/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Key;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use OutOfRangeException;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

/**
 * Class representing a two-factor recovery codes key
 *
 * Recovery codes are tied to OATHUsers
 *
 * @ingroup Extensions
 */
class RecoveryCodeKeys extends AuthKey {
	/** @var RecoveryCode[] List of recovery codes in this key */
	private array $recoveryCodes;

	/**
	 * @param array $data
	 * @return RecoveryCodeKeys|null on invalid data
	 * @throws UnexpectedValueException When encryption is not configured but db is encrypted
	 */
	public static function newFromArray( array $data ) {
		if ( !array_key_exists( 'recoverycodekeys', $data ) ) {
			return null;
		}
		$recoveryCodes = [];
		foreach ( $data['recoverycodekeys'] as $key ) {
			if ( is_array( $key ) ) {
				[ $code, $codeData ] = $key;
			} else {
				$code = $key;
				$codeData = [];
			}

			if ( isset( $data['nonce'] ) ) {
				$recoveryCodes[] = RecoveryCode::newFromEncrypted( $code, $data['nonce'], $codeData );
			} else {
				$recoveryCodes[] = RecoveryCode::newFromPlaintext( $code, $codeData );
			}
		}

		return new static(
			$data['id'] ?? null,
			null,
			$data['created_timestamp'] ?? null,
			$recoveryCodes,
		);
	}

	/**
	 * Any expired Recovery Codes will be removed
	 */
	public function __construct(
		?int $id,
		?string $friendlyName,
		?string $createdTimestamp,
		array $recoveryCodes
	) {
		parent::__construct( $id, $friendlyName, $createdTimestamp );
		$this->recoveryCodes = array_values(
			array_filter(
				$recoveryCodes,
				static fn ( RecoveryCode $code ) => !$code->isExpired()
			)
		);
	}

	/**
	 * Returns a list of all recovery codes in this key (both permanent and expiring ones)
	 * @return RecoveryCode[]
	 */
	public function getRecoveryCodes(): array {
		return $this->recoveryCodes;
	}

	/**
	 * Returns a list of all recovery codes in this key as strings. It's advised to call {@see getRecoveryCodes}
	 * instead, which returns full {@see RecoveryCode} objects, including whether they are permanent, and other
	 * attached data.
	 * @return string[]
	 */
	public function getRecoveryCodeKeys(): array {
		return array_map( static fn ( $k ) => $k->getCode(), $this->recoveryCodes );
	}

	public function verify( OATHUser $user, array $data ): bool {
		if ( !isset( $data['recoverycode'] ) ) {
			return false;
		}

		$enteredRecoveryCode = $this->normaliseRecoveryCode( $data['recoverycode'] );
		$clientData = RequestContext::getMain()->getRequest()->getSecurityLogContext( $user->getUser() );
		$logger = $this->getLogger();

		foreach ( $this->recoveryCodes as $code ) {
			if ( !$code->test( $enteredRecoveryCode ) ) {
				continue;
			}

			$logger->info(
				// phpcs:ignore
				"OATHAuth {user} used a recovery code from {clientip}.", [
					'user' => $user->getUser()->getName(),
					'clientip' => $clientData['clientIp']
				]
			);

			return true;
		}

		return false;
	}

	public function removeRecoveryCode( OATHUser $user, string $codeToRemove ) {
		$codeToRemove = $this->normaliseRecoveryCode( $codeToRemove );

		foreach ( $this->recoveryCodes as $key => $recoveryCode ) {
			if ( $recoveryCode->test( $codeToRemove ) ) {
				unset( $this->recoveryCodes[ $key ] );
				break;
			}
		}

		$remainingPermanentCodes = array_filter( $this->recoveryCodes, static fn ( $code ) => $code->isPermanent() );
		if ( $remainingPermanentCodes === [] ) {
			// Don't automatically invalidate existing temporary codes, as this is an automatic action and
			// the user didn't consciously choose to regenerate all codes
			// However, if we cannot generate the requested number of permanent codes, we will drop some
			// temporary ones. It makes some sense, as the user just logged in using a permanent code, so they
			// don't strictly need the "emergency" temporary codes.
			$numCodesToGenerate = $this->getNumberOfCodesToGenerate();
			$maxCodesToGenerate = $this->getMaxNumberOfCodes() - count( $this->recoveryCodes );
			if ( $maxCodesToGenerate < $numCodesToGenerate ) {
				$this->recoveryCodes = array_slice( $this->recoveryCodes, $maxCodesToGenerate - $numCodesToGenerate );
			}
			$this->generateAdditionalRecoveryCodeKeys( $numCodesToGenerate );

			$clientData = RequestContext::getMain()->getRequest()->getSecurityLogContext( $user->getUser() );
			$this->getLogger()->info(
				'OATHAuth {user} had their recovery codes automatically regenerated.', [
					'user' => $user->getUser()->getName(),
					'clientip' => $clientData['clientIp']
				]
			);
		}
	}

	/**
	 * Removes all codes with expiration date from this key
	 */
	public function removeTemporaryCodes(): void {
		$this->recoveryCodes = array_filter( $this->recoveryCodes, static fn ( $code ) => $code->isPermanent() );
	}

	/**
	 * Returns the number of recovery codes to generate by default. Ensures that the return value is not
	 * greater than value returned by {@see getMaxNumberOfCodes}.
	 */
	private function getNumberOfCodesToGenerate(): int {
		$codesCount = MediaWikiServices::getInstance()->getMainConfig()->get( 'OATHRecoveryCodesCount' );
		return min( $codesCount, $this->getMaxNumberOfCodes() );
	}

	/**
	 * Returns the maximum number of recovery codes that can be stored in this module.
	 */
	private function getMaxNumberOfCodes(): int {
		return MediaWikiServices::getInstance()->getMainConfig()->get( 'OATHMaxRecoveryCodesCount' );
	}

	/**
	 * Regenerate the full set of recovery codes, invalidating any existing ones.
	 * @param array $data Optional additional data to store along codes, see {@see RecoveryCode::__construct}
	 */
	public function regenerateRecoveryCodeKeys( array $data = [] ): void {
		$this->recoveryCodes = [];
		$this->generateAdditionalRecoveryCodeKeys( $this->getNumberOfCodesToGenerate(), $data );
	}

	/**
	 * Generate additional recovery codes and add them to the set, without invalidating existing ones.
	 * @param int $numCodes Number of codes to generate
	 * @param array $data Optional additional data to store along codes, see {@see RecoveryCode::__construct}
	 * @param bool $noThrow If true, keys will be generated only up to {@see getMaxNumberOfCodes} limit, but
	 *     no exception will be thrown. It's possible that no codes will get generated.
	 * @return list<string> Newly generated recovery codes
	 * @throws OutOfRangeException If the total number of codes would be greater than allowed by
	 *     {@see getMaxNumberOfCodes} and $noThrow is false.
	 */
	public function generateAdditionalRecoveryCodeKeys(
		int $numCodes,
		array $data = [],
		bool $noThrow = false
	): array {
		$maxCodes = $this->getMaxNumberOfCodes();
		if ( $numCodes + count( $this->recoveryCodes ) > $maxCodes ) {
			if ( $noThrow ) {
				$numCodes = $maxCodes - count( $this->recoveryCodes );
			} else {
				throw new OutOfRangeException(
					"After generating $numCodes codes, a maximum of $maxCodes codes would be exceeded."
				);
			}
		}

		$newCodes = [];
		for ( $i = 0; $i < $numCodes; $i++ ) {
			$newCodes[] = RecoveryCode::newRandom( $data );
		}
		$this->recoveryCodes = array_merge( $this->recoveryCodes, $newCodes );
		return array_map( static fn ( $code ) => $code->getCode(), $newCodes );
	}

	/** @inheritDoc */
	public function getModule(): string {
		return RecoveryCodes::MODULE_NAME;
	}

	private function getLogger(): LoggerInterface {
		return LoggerFactory::getInstance( 'authentication' );
	}

	/** @inheritDoc */
	public function jsonSerialize(): array {
		// T408299 - array_values() to renumber array keys
		$codes = array_values( $this->recoveryCodes );

		$encryptionHelper = OATHAuthServices::getInstance()->getEncryptionHelper();
		if ( !$encryptionHelper->isEnabled() || !count( $codes ) ) {
			// fallback to unencrypted recovery codes
			$plaintextCodes = [];
			foreach ( $codes as $code ) {
				if ( $code->getData() ) {
					$plaintextCodes[] = [ $code->getCode(), $code->getData() ];
				} else {
					$plaintextCodes[] = $code->getCode();
				}
			}
			return [
				'recoverycodekeys' => $plaintextCodes
			];
		}

		// Ensure that all codes are encoded using the same nonce
		$nonce = $codes[0]->getNonce() ?? $encryptionHelper->generateNonce();
		$encryptedCodes = [];
		foreach ( $codes as $code ) {
			if ( $code->getData() ) {
				$encryptedCodes[] = [ $code->encryptCode( $nonce ), $code->getData() ];
			} else {
				$encryptedCodes[] = $code->encryptCode( $nonce );
			}
		}

		return [
			'recoverycodekeys' => $encryptedCodes,
			'nonce' => $nonce,
		];
	}

	private function normaliseRecoveryCode( string $token ): string {
		return preg_replace( '/\s+/', '', $token );
	}

	/**
	 * Check if a token is valid for this key.
	 *
	 * @param string $token Token to verify
	 *
	 * @return bool true if this is a recovery code for this key.
	 */
	public function isValidRecoveryCode( string $token ): bool {
		$token = $this->normaliseRecoveryCode( $token );
		foreach ( $this->recoveryCodes as $key ) {
			if ( $key->test( $token ) ) {
				return true;
			}
		}
		return false;
	}
}
