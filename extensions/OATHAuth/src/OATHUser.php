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

namespace MediaWiki\Extension\OATHAuth;

use MediaWiki\User\UserIdentity;

/**
 * Class representing a user from OATH's perspective
 *
 * @ingroup Extensions
 */
class OATHUser {
	private UserIdentity $user;
	private int $centralId;

	/** @var IAuthKey[] */
	private array $keys = [];

	/**
	 * Constructor. Can't be called directly. Use OATHUserRepository::findByUser instead.
	 * @param UserIdentity $user
	 * @param int $centralId
	 */
	public function __construct( UserIdentity $user, int $centralId ) {
		$this->user = $user;
		$this->centralId = $centralId;
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

	/**
	 * @return string
	 */
	public function getIssuer() {
		global $wgSitename, $wgOATHAuthAccountPrefix;

		if ( $wgOATHAuthAccountPrefix !== false ) {
			return $wgOATHAuthAccountPrefix;
		}
		return $wgSitename;
	}

	/**
	 * @return string
	 */
	public function getAccount() {
		return $this->user->getName();
	}

	/**
	 * Get the key associated with this user.
	 *
	 * @return IAuthKey[]
	 */
	public function getKeys(): array {
		return $this->keys;
	}

	/**
	 * @param string $moduleName As in IModule::getName().
	 * @return IAuthKey[]
	 */
	public function getKeysForModule( string $moduleName ): array {
		return array_values(
			array_filter(
				$this->keys,
				static fn ( IAuthKey $key ) => $key->getModule() === $moduleName
			)
		);
	}

	public function removeKey( IAuthKey $key ) {
		$keyId = $key->getId();
		$this->keys = array_values(
			array_filter(
				$this->keys,
				static fn ( IAuthKey $key ) => $key->getId() !== $keyId
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
				static fn ( IAuthKey $key ) => $key->getModule() !== $moduleName
			)
		);
	}

	/**
	 * Adds single key to the key array
	 *
	 * @param IAuthKey $key
	 */
	public function addKey( IAuthKey $key ) {
		$this->keys[] = $key;
	}

	/**
	 * Gets the module instance associated with this user
	 *
	 * @return IModule|null
	 * @deprecated Use {@link IAuthKey::getModule()} instead
	 */
	public function getModule() {
		wfDeprecated( 'OATHUser::getModule()', '1.44', 'OATHAuth' );
		if ( !$this->keys ) {
			return null;
		}
		$key = $this->keys[0];
		return OATHAuthServices::getInstance()->getModuleRegistry()->getModuleByKey( $key->getModule() );
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
}
