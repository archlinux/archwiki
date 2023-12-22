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
 * @author Niklas Laxström
 */

use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Look up "gender" user preference.
 *
 * This primarily used in MediaWiki\Title\MediaWikiTitleCodec for title formatting
 * of pages in gendered namespace aliases, and in CoreParserFunctions for the
 * `{{gender:}}` parser function.
 *
 * @since 1.18
 * @ingroup Cache
 */
class GenderCache {
	protected $cache = [];
	protected $default = null;
	protected $misses = 0;
	/* @internal Exposed for MediaWiki core unit tests. */
	protected $missLimit = 1000;

	private NamespaceInfo $nsInfo;
	private ?IConnectionProvider $dbProvider;
	private UserOptionsLookup $userOptionsLookup;

	public function __construct(
		NamespaceInfo $nsInfo = null,
		IConnectionProvider $dbProvider = null,
		UserOptionsLookup $userOptionsLookup = null
	) {
		$this->nsInfo = $nsInfo ?? MediaWikiServices::getInstance()->getNamespaceInfo();
		$this->dbProvider = $dbProvider;
		$this->userOptionsLookup = $userOptionsLookup ?? MediaWikiServices::getInstance()->getUserOptionsLookup();
	}

	/**
	 * Get the default gender option on this wiki.
	 *
	 * @return string
	 */
	protected function getDefault() {
		$this->default ??= $this->userOptionsLookup->getDefaultOption( 'gender' );
		return $this->default;
	}

	/**
	 * Get the gender option for given username.
	 *
	 * @param string|UserIdentity $username
	 * @param string|null $caller Calling method for database profiling
	 * @return string
	 */
	public function getGenderOf( $username, $caller = '' ) {
		if ( $username instanceof UserIdentity ) {
			$username = $username->getName();
		}

		$username = self::normalizeUsername( $username );
		if ( !isset( $this->cache[$username] ) ) {
			if ( $this->misses < $this->missLimit ||
				RequestContext::getMain()->getUser()->getName() === $username
			) {
				$this->misses++;
				$this->doQuery( $username, $caller );
			}
			if ( $this->misses === $this->missLimit ) {
				// Log only once and don't bother incrementing beyond limit+1
				$this->misses++;
				wfDebug( __METHOD__ . ': too many misses, returning default onwards' );
			}
		}

		return $this->cache[$username] ?? $this->getDefault();
	}

	/**
	 * Wrapper for doQuery that processes raw LinkBatch data.
	 *
	 * @param array<int,array<string,mixed>> $data
	 * @param string|null $caller
	 */
	public function doLinkBatch( array $data, $caller = '' ) {
		$users = [];
		foreach ( $data as $ns => $pagenames ) {
			if ( $this->nsInfo->hasGenderDistinction( $ns ) ) {
				$users += $pagenames;
			}
		}
		$this->doQuery( array_keys( $users ), $caller );
	}

	/**
	 * Wrapper for doQuery that processes a title array.
	 *
	 * @since 1.20
	 * @param LinkTarget[] $titles
	 * @param string|null $caller Calling method for database profiling
	 */
	public function doTitlesArray( $titles, $caller = '' ) {
		$users = [];
		foreach ( $titles as $titleObj ) {
			if ( $this->nsInfo->hasGenderDistinction( $titleObj->getNamespace() ) ) {
				$users[] = $titleObj->getText();
			}
		}
		$this->doQuery( $users, $caller );
	}

	/**
	 * Preload gender option for multiple user names.
	 *
	 * @param string[]|string $users Usernames
	 * @param string|null $caller Calling method for database profiling
	 */
	public function doQuery( $users, $caller = '' ) {
		$default = $this->getDefault();

		$usersToFetch = [];
		foreach ( (array)$users as $value ) {
			$name = self::normalizeUsername( $value );
			if ( !isset( $this->cache[$name] ) ) {
				// This may be overwritten below by a fetched value
				$this->cache[$name] = $default;
				// T267054: We don't need to fetch data for invalid usernames, but filtering breaks DI
				$usersToFetch[] = $name;
			}
		}

		// Skip query when database is unavailable (e.g. via the installer)
		if ( !$usersToFetch || !$this->dbProvider ) {
			return;
		}

		$caller = __METHOD__ . ( $caller ? "/$caller" : '' );

		$res = $queryBuilder = $this->dbProvider->getReplicaDatabase()->newSelectQueryBuilder()
			->select( [ 'user_name', 'up_value' ] )
			->from( 'user' )
			->leftJoin( 'user_properties', null, [ 'user_id = up_user', 'up_property' => 'gender' ] )
			->where( [ 'user_name' => $usersToFetch ] )
			->caller( $caller )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$this->cache[$row->user_name] = $row->up_value ?: $default;
		}
	}

	private static function normalizeUsername( $username ) {
		// Strip off subpages
		$indexSlash = strpos( $username, '/' );
		if ( $indexSlash !== false ) {
			$username = substr( $username, 0, $indexSlash );
		}

		// normalize underscore/spaces
		return strtr( $username, '_', ' ' );
	}
}
