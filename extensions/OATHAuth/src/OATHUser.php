<?php
/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth;

use MediaWiki\Extension\OATHAuth\Key\AuthKey;
use MediaWiki\User\UserIdentity;

/**
 * Class representing a user from OATH's perspective
 *
 * @ingroup Extensions
 */
class OATHUser {
	/** @var AuthKey[] */
	private array $keys = [];

	private ?string $userHandle = null;

	/**
	 * Constructor. Can't be called directly. Use OATHUserRepository::findByUser instead.
	 */
	public function __construct(
		private readonly UserIdentity $user,
		private readonly int $centralId,
	) {
	}

	public function getUser(): UserIdentity {
		return $this->user;
	}

	/**
	 * @return int The central ID of this user
	 */
	public function getCentralId(): int {
		return $this->centralId;
	}

	public function getIssuer(): string {
		global $wgSitename, $wgOATHAuthAccountPrefix;

		if ( $wgOATHAuthAccountPrefix !== false ) {
			return $wgOATHAuthAccountPrefix;
		}
		return $wgSitename;
	}

	public function getAccount(): string {
		return $this->user->getName();
	}

	/**
	 * Get the key associated with this user.
	 *
	 * @return AuthKey[]
	 */
	public function getKeys(): array {
		return $this->keys;
	}

	/**
	 * @param string $moduleName As in IModule::getName().
	 * @return AuthKey[]
	 */
	public function getKeysForModule( string $moduleName ): array {
		return array_values(
			array_filter(
				$this->keys,
				static fn ( AuthKey $key ) => $key->getModule() === $moduleName
			)
		);
	}

	public function getKeyById( int $id ): ?AuthKey {
		$matchingKeys = array_values(
			array_filter(
				$this->keys,
				static fn ( AuthKey $key ) => $key->getId() === $id
			)
		);
		return $matchingKeys[0] ?? null;
	}

	public function removeKey( AuthKey $key ) {
		$keyId = $key->getId();
		$this->keys = array_values(
			array_filter(
				$this->keys,
				static fn ( AuthKey $key ) => $key->getId() !== $keyId
			)
		);
	}

	/**
	 * @param string $moduleName As in IModule::getName()
	 */
	public function removeKeysForModule( string $moduleName ): void {
		$this->keys = array_values(
			array_filter(
				$this->keys,
				static fn ( AuthKey $key ) => $key->getModule() !== $moduleName
			)
		);
	}

	/**
	 * Adds a single key to the key array
	 */
	public function addKey( AuthKey $key ) {
		$this->keys[] = $key;
	}

	/**
	 * @return bool Whether this user has two-factor authentication enabled or not
	 */
	public function isTwoFactorAuthEnabled(): bool {
		return count( $this->getKeys() ) >= 1;
	}

	/**
	 * Disables current (if any) auth method
	 */
	public function disable() {
		$this->keys = [];
	}

	/**
	 * Get all the user's keys, but exclude special keys
	 * @return AuthKey[]
	 */
	public function getNonSpecialKeys(): array {
		$moduleRegistry = OATHAuthServices::getInstance()->getModuleRegistry();
		return array_values(
			array_filter(
				$this->keys,
				static fn ( AuthKey $key ) => !$moduleRegistry->getModuleByKey( $key->getModule() )->isSpecial()
			)
		);
	}

	/**
	 * Returns a bool indicating whether a user has _any_ 2fa modules enabled
	 * which are not considered "special" modules, as defined via IModule::isSpecial()
	 */
	public function userHasNonSpecialEnabledKeys(): bool {
		return count( $this->getNonSpecialKeys() ) > 0;
	}

	/**
	 * Get the user's WebAuthn User Handle value. The User Handle appears in each of the user's
	 * WebAuthn keys, and is used to identify the user based on one of their WebAuthn keys.
	 * See also OATHUserRepository::findByUserHandle().
	 *
	 * For more information about User Handles, see
	 * https://developers.yubico.com/WebAuthn/WebAuthn_Developer_Guide/User_Handle.html
	 */
	public function getUserHandle(): ?string {
		return $this->userHandle;
	}

	/**
	 * Set the User Handle. This should only be used by OATHUserRepository.
	 * @internal
	 */
	public function setUserHandle( ?string $userHandle ): void {
		$this->userHandle = $userHandle;
	}
}
