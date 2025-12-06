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
 *
 * @file
 */

namespace MediaWiki\CheckUser\GlobalContributions;

/**
 * Value Object encapsulating information about a given set of rights
 * a user may have or lack on a set of wikis.
 *
 * @see CheckUserGlobalContributionsLookup
 */
class ExternalPermissions {

	public function __construct(
		private readonly array $permissions = [],
		private readonly bool $encounteredLookupError = false
	) {
	}

	/**
	 * Returns whether there was an error during the permission lookup. If so,
	 * the information about user's rights on other wikis may be incomplete.
	 */
	public function hasEncounteredLookupError(): bool {
		return $this->encounteredLookupError;
	}

	/**
	 * Checks if the user has the given right on the given wiki. If data for the
	 * wiki is not present, false is assumed.
	 */
	public function hasPermission( string $right, string $wikiId ): bool {
		// The array at [wiki][right] contains the error messages related to the user
		// not having that right. Therefore, check if the array exists and is empty.

		// Assign, so that Phan doesn't complain about modifying $this->permissions.
		$permissions = $this->permissions;

		return isset( $permissions[ $wikiId ][ $right ] ) &&
			count( $permissions[ $wikiId ][ $right ] ) === 0;
	}

	/**
	 * Returns an array of rights the user has on the given wiki.
	 * @return list<string>
	 */
	public function getPermissionsOnWiki( string $wikiId ): array {
		$rights = $this->permissions[$wikiId] ?? [];
		$rights = array_filter( $rights, static fn ( $errors ) => count( $errors ) === 0 );
		return array_keys( $rights );
	}

	/**
	 * Returns a list of wiki IDs for which this instance knows the permissions.
	 * Wiki is considered "known" also if the user has explicitly no rights on it.
	 * @return list<string>
	 */
	public function getKnownWikis(): array {
		return array_keys( $this->permissions );
	}

	/**
	 * Returns true if the instance stores permissions from at least one wiki.
	 */
	public function hasAnyWiki(): bool {
		return count( $this->permissions ) > 0;
	}
}
