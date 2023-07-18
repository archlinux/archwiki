<?php
/**
 * Value object representing a user's identity.
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
 *
 * @file
 */

namespace MediaWiki\User;

use MediaWiki\DAO\WikiAwareEntityTrait;
use Wikimedia\Assert\Assert;

/**
 * Value object representing a user's identity.
 *
 * @newable
 *
 * @since 1.31
 */
class UserIdentityValue implements UserIdentity {
	use WikiAwareEntityTrait;

	/**
	 * @var int
	 */
	private $id;

	/**
	 * @var string
	 */
	private $name;

	/** @var string|false */
	private $wikiId;

	/**
	 * @stable to call
	 *
	 * @note Signature in 1.35 was: ( $id, $name, $actor ). This is still supported for
	 *        backwards compatibility until 1.37. $actor is ignored.
	 *
	 * @param int $id user ID
	 * @param string $name user name
	 * @param string|false $wikiId wiki ID or self::LOCAL for the local wiki
	 */
	public function __construct( int $id, string $name, $wikiId = self::LOCAL ) {
		if ( is_int( $wikiId ) ) {
			// Handle old signature: ( $id, $name, $actor, $wikiId )
			$args = func_get_args();
			$actor = $args[2];
			$wikiId = $args[3] ?? self::LOCAL;

			if ( $actor > 0 || $wikiId !== self::LOCAL ) {
				// NOTE: For now, we only trigger a deprecation warning if $actor is not 0,
				//       or a $wikiId is given.
				//       A lot of tests construct UserIdentityValue with $actor = 0.
				// TODO: Trigger a deprecation warning even if $actor is 0
				wfDeprecatedMsg( 'Old constructor signature: $actor is no longer supported', '1.36' );
			}
		}

		$this->assertWikiIdParam( $wikiId );

		$this->id = $id;
		$this->name = $name;
		$this->wikiId = $wikiId;
	}

	/**
	 * Create UserIdentity for an anonymous user.
	 *
	 * @since 1.36
	 * @param string $name
	 * @param string|false $wikiId wiki ID or self::LOCAL for the local wiki
	 * @return UserIdentityValue
	 */
	public static function newAnonymous( string $name, $wikiId = self::LOCAL ): self {
		return new self( 0, $name, $wikiId );
	}

	/**
	 * Create UserIdentity for a registered user.
	 *
	 * @since 1.36
	 * @param int $userId
	 * @param string $name
	 * @param string|false $wikiId wiki ID or self::LOCAL for the local wiki
	 * @return UserIdentityValue
	 */
	public static function newRegistered( int $userId, string $name, $wikiId = self::LOCAL ): self {
		Assert::parameter( $userId > 0, '$userId', 'must be greater than zero (user must exist)' );
		return new self( $userId, $name, $wikiId );
	}

	/**
	 * Create UserIdentity for an external user with $prefix and $name
	 *
	 * @since 1.36
	 * @param string $prefix
	 * @param string $name
	 * @param string|false $wikiId wiki ID or self::LOCAL for the local wiki
	 * @return UserIdentityValue
	 */
	public static function newExternal( string $prefix, string $name, $wikiId = self::LOCAL ): self {
		// > is a standard separator for external users in the database, see ExternalUserNames
		return new self( 0, "$prefix>$name", $wikiId );
	}

	/**
	 * Get the ID of the wiki this UserIdentity belongs to.
	 *
	 * @since 1.36
	 * @see RevisionRecord::getWikiId()
	 *
	 * @return string|false The wiki's logical name or self::LOCAL to indicate the local wiki
	 */
	public function getWikiId() {
		return $this->wikiId;
	}

	/**
	 * The numerical user ID provided to the constructor.
	 *
	 * @param string|false $wikiId The wiki ID expected by the caller
	 * @return int The user ID. May be 0 for anonymous users or for users with no local account.
	 *
	 */
	public function getId( $wikiId = self::LOCAL ): int {
		$this->assertWiki( $wikiId );
		return $this->id;
	}

	/**
	 * @return string The user's logical name. May be an IPv4 or IPv6 address for anonymous users.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @deprecated since 1.36, use ActorNormalization::acquireActorId instead.
	 *
	 * @param string|false $wikiId
	 *
	 * @return int always 0.
	 */
	public function getActorId( $wikiId = self::LOCAL ): int {
		wfDeprecated( __METHOD__, '1.36' );
		return 0;
	}

	/**
	 * @since 1.32
	 *
	 * @param UserIdentity|null $user
	 * @return bool
	 */
	public function equals( ?UserIdentity $user ): bool {
		if ( !$user ) {
			return false;
		}
		// XXX it's not clear whether central ID providers are supposed to obey this
		return $this->getName() === $user->getName();
	}

	/**
	 * @since 1.34
	 *
	 * @return bool True if user is registered on this wiki, i.e., has a user ID. False if user is
	 *   anonymous or has no local account (which can happen when importing). This is equivalent to
	 *   getId() != 0 and is provided for code readability.
	 */
	public function isRegistered(): bool {
		return $this->getId( $this->wikiId ) != 0;
	}

	public function __toString(): string {
		return $this->getName();
	}

}
