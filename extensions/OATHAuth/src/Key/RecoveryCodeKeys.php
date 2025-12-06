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
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use stdClass;
use UnexpectedValueException;

/**
 * Class representing a two-factor recovery code
 *
 * Recovery codes are tied to OATHUsers
 *
 * @ingroup Extensions
 */
class RecoveryCodeKeys implements IAuthKey {
	/** @var int|null */
	private ?int $id;

	/** @var string|null timestamp created for recovery code */
	private ?string $createdTimestamp;

	/** @var string[] List of recovery codes */
	public $recoveryCodeKeys = [];

	/** @var string[] List of encrypted recovery codes */
	private $recoveryCodeKeysEncrypted = [];

	/** @var string optional nonce for encryption */
	private $nonce = '';

	/**
	 * Length (in bytes) that recovery codes should be
	 */
	private const RECOVERY_CODE_LENGTH = 10;

	/**
	 * Amount of recovery code module instances allowed per user in oathauth_devices
	 */
	public const RECOVERY_CODE_MODULE_COUNT = 1;

	/**
	 * @param array $data
	 * @return RecoveryCodeKeys|null on invalid data
	 * @throws UnexpectedValueException When encryption is not configured but db is encrypted
	 */
	public static function newFromArray( array $data ) {
		if ( !array_key_exists( 'recoverycodekeys', $data ) ) {
			return null;
		}
		if ( isset( $data['nonce'] ) ) {
			$encryptionHelper = OATHAuthServices::getInstance()->getEncryptionHelper();
			if ( !$encryptionHelper->isEnabled() ) {
				throw new UnexpectedValueException( 'Encryption is not configured but database has encrypted data' );
			}
			$data['recoverycodekeysencrypted'] = $data['recoverycodekeys'];
			$data['recoverycodekeys'] = $encryptionHelper->decryptStringArrayValues(
				$data['recoverycodekeys'],
				$data['nonce']
			);
		} else {
			$data['recoverycodekeysencrypted'] = [];
			$data['nonce'] = '';
		}

		return new static(
			$data['id'] ?? null,
			$data['created_timestamp'] ?? null,
			$data['recoverycodekeys'],
			$data['recoverycodekeysencrypted'],
			$data['nonce']
		);
	}

	/**
	 * @param int|null $id the database id of this key
	 * @param string|null $createdTimestamp
	 * @param array $recoveryCodeKeys
	 * @param array $recoveryCodeKeysEncrypted
	 * @param string $nonce
	 */
	public function __construct(
		?int $id,
		?string $createdTimestamp,
		array $recoveryCodeKeys,
		array $recoveryCodeKeysEncrypted,
		string $nonce = ''
	) {
		$this->id = $id;
		$this->createdTimestamp = $createdTimestamp;
		$this->recoveryCodeKeys = array_values( $recoveryCodeKeys );
		$this->recoveryCodeKeysEncrypted = array_values( $recoveryCodeKeysEncrypted );
		$this->nonce = $nonce;
	}

	/** @inheritDoc */
	public function getId(): ?int {
		return $this->id;
	}

	public function getFriendlyName(): ?string {
		return null;
	}

	public function getCreatedTimestamp(): ?string {
		return $this->createdTimestamp;
	}

	public function getRecoveryCodeKeys(): array {
		return $this->recoveryCodeKeys;
	}

	public function getRecoveryCodeKeysEncryptedAndNonce(): array {
		return [ $this->recoveryCodeKeysEncrypted, $this->nonce ];
	}

	public function setRecoveryCodeKeysEncryptedAndNonce( array $recoveryCodeKeysEncrypted, string $nonce ): void {
		$this->recoveryCodeKeysEncrypted = $recoveryCodeKeysEncrypted;
		$this->nonce = $nonce;
		$this->createdTimestamp = null;
	}

	/**
	 * @param array|stdClass $data
	 * @param OATHUser $user
	 */
	public function verify( $data, OATHUser $user ): bool {
		if ( !isset( $data['recoverycode'] ) ) {
			return false;
		}

		$clientData = RequestContext::getMain()->getRequest()->getSecurityLogContext( $user->getUser() );
		$logger = $this->getLogger();

		foreach ( $this->recoveryCodeKeys as $userRecoveryCode ) {
			if ( !hash_equals(
				$this->normaliseRecoveryCode( $data['recoverycode'] ),
				$userRecoveryCode
			) ) {
				continue;
			}

			self::maybeCreateOrUpdateRecoveryCodeKeys( $user, $this, $userRecoveryCode );

			$logger->info(
				// phpcs:ignore
				"OATHAuth {user} used a recovery code from {clientip} and had their existing recovery codes regenerated automatically.", [
					'user' => $user->getUser()->getName(),
					'clientip' => $clientData['clientIp']
				]
			);

			return true;
		}

		return false;
	}

	public function regenerateRecoveryCodeKeys(): void {
		$recoveryCodesCount = OATHAuthServices::getInstance()->getConfig()->get( 'OATHRecoveryCodesCount' );
		$this->recoveryCodeKeys = [];
		for ( $i = 0; $i < $recoveryCodesCount; $i++ ) {
			$this->recoveryCodeKeys[] = Base32::encode( random_bytes( self::RECOVERY_CODE_LENGTH ) );
		}
		// reset this when we regenerate codes
		$this->setRecoveryCodeKeysEncryptedAndNonce( [], '' );
	}

	/** @inheritDoc */
	public function getModule(): string {
		return RecoveryCodes::MODULE_NAME;
	}

	/** @inheritDoc */
	private function getLogger(): LoggerInterface {
		return LoggerFactory::getInstance( 'authentication' );
	}

	/** @inheritDoc */
	public function jsonSerialize(): array {
		$encryptionHelper = OATHAuthServices::getInstance()->getEncryptionHelper();
		if ( !$encryptionHelper->isEnabled() ) {
			// fallback to unencrypted recovery codes
			return [
				// T408299 - array_values() to renumber array keys
				'recoverycodekeys' => array_values( $this->getRecoveryCodeKeys() )
			];
		}

		[ $keys, $nonce ] = $this->getRecoveryCodeKeysEncryptedAndNonce();
		if ( $keys !== [] ) {
			// do not re - encrypt existing recovery codes
			return [
				// T408299 - array_values() to renumber array keys
				'recoverycodekeys' => array_values( $keys ),
				'nonce' => $nonce,
			];
		}

		// brand new set of recovery codes
		$nonce ??= '';
		$encData = $encryptionHelper->encryptStringArrayValues(
			// T408299 - array_values() to renumber array keys
			array_values( $this->getRecoveryCodeKeys() ),
			$nonce
		);
		$this->setRecoveryCodeKeysEncryptedAndNonce( $encData['encrypted_array'], $encData['nonce'] );
		return [
			'recoverycodekeys' => $encData['encrypted_array'],
			'nonce' => $encData['nonce']
		];
	}

	/**
	 * @throws UnexpectedValueException
	 */
	public static function maybeCreateOrUpdateRecoveryCodeKeys(
		OATHUser $user,
		?RecoveryCodeKeys $recoveryKeys = null,
		string $usedRecoveryCode = ''
	): void {
		$uid = $user->getCentralId();
		if ( !$uid ) {
			throw new UnexpectedValueException( wfMessage( 'oathauth-invalidrequest' )->escaped() );
		}

		if ( $recoveryKeys === null ) {
			// see if recovery codes module exists for user
			$moduleDbKeys = $user->getKeysForModule( RecoveryCodes::MODULE_NAME );

			if ( count( $moduleDbKeys ) > self::RECOVERY_CODE_MODULE_COUNT ) {
				throw new UnexpectedValueException( wfMessage( 'oathauth-recoverycodes-too-many-instances' ) );
			}

			if ( array_key_exists( 0, $moduleDbKeys ) && $moduleDbKeys[0] instanceof self ) {
				$recoveryKeys = $moduleDbKeys[0];
			} else {
				$recoveryKeys = self::newFromArray( [ 'recoverycodekeys' => [] ] );
			}
		}

		// attempt to remove used recovery code
		if ( $usedRecoveryCode ) {
			$key = array_search( $usedRecoveryCode, $recoveryKeys->recoveryCodeKeys );
			if ( $key !== false ) {
				unset( $recoveryKeys->recoveryCodeKeys[$key] );
				// T408297 - Unset the key for the same encrypted token.
				// Can we assume the array key is the same?
				unset( $recoveryKeys->recoveryCodeKeysEncrypted[$key] );
			}
		}

		// only regenerate if there are no tokens left or these are brand-new recovery codes
		if ( count( $recoveryKeys->recoveryCodeKeys ) === 0 ) {
			$recoveryKeys->regenerateRecoveryCodeKeys();
		}

		$recoveryCodeKeys = $recoveryKeys->getRecoveryCodeKeys();
		if ( count( $recoveryCodeKeys ) > 0 && !in_array( '', $recoveryCodeKeys ) ) {
			$oathRepo = OATHAuthServices::getInstance()->getUserRepository();
			$moduleRegistry = OATHAuthServices::getInstance()->getModuleRegistry();
			$module = $moduleRegistry->getModuleByKey( $recoveryKeys->getModule() );
			if ( $module->isEnabled( $user ) ) {
				$oathRepo->updateKey(
					$user,
					// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
					$recoveryKeys
				);
			} else {
				$oathRepo->createKey(
					$user,
					$module,
					$recoveryKeys->jsonSerialize(),
					RequestContext::getMain()->getRequest()->getIP()
				);
			}
		}
	}

	private function normaliseRecoveryCode( string $token ): string {
		return (string)preg_replace( '/\s+/', '', $token );
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
		foreach ( $this->recoveryCodeKeys as $key ) {
			if ( hash_equals( $key, $token ) ) {
				return true;
			}
		}
		return false;
	}
}
