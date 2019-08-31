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

use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\DBConnRef;

class OATHUserRepository {
	/** @var ILoadBalancer */
	protected $lb;

	/** @var BagOStuff */
	protected $cache;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * OATHUserRepository constructor.
	 * @param ILoadBalancer $lb
	 * @param BagOStuff $cache
	 */
	public function __construct( ILoadBalancer $lb, BagOStuff $cache ) {
		$this->lb = $lb;
		$this->cache = $cache;

		$this->setLogger( \MediaWiki\Logger\LoggerFactory::getInstance( 'authentication' ) );
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param User $user
	 * @return OATHUser
	 */
	public function findByUser( User $user ) {
		$oathUser = $this->cache->get( $user->getName() );
		if ( !$oathUser ) {
			$oathUser = new OATHUser( $user, null );

			$uid = CentralIdLookup::factory()->centralIdFromLocalUser( $user );
			$res = $this->getDB( DB_REPLICA )->selectRow(
				'oathauth_users',
				'*',
				[ 'id' => $uid ],
				__METHOD__
			);
			if ( $res ) {
				$key = new OATHAuthKey( $res->secret, explode( ',', $res->scratch_tokens ) );
				$oathUser->setKey( $key );
			}

			$this->cache->set( $user->getName(), $oathUser );
		}
		return $oathUser;
	}

	/**
	 * @param OATHUser $user
	 * @param string $clientInfo
	 */
	public function persist( OATHUser $user, $clientInfo ) {
		$prevUser = $this->findByUser( $user->getUser() );

		$this->getDB( DB_MASTER )->replace(
			'oathauth_users',
			[ 'id' ],
			[
				'id' => CentralIdLookup::factory()->centralIdFromLocalUser( $user->getUser() ),
				'secret' => $user->getKey()->getSecret(),
				'scratch_tokens' => implode( ',', $user->getKey()->getScratchTokens() ),
			],
			__METHOD__
		);

		$userName = $user->getUser()->getName();
		$this->cache->set( $userName, $user );

		if ( $prevUser !== false ) {
			$this->logger->info( 'OATHAuth updated for {user} from {clientip}', [
				'user' => $userName,
				'clientip' => $clientInfo,
			] );
		} else {
			// If findByUser() has returned false, there was no user row or cache entry
			$this->logger->info( 'OATHAuth enabled for {user} from {clientip}', [
				'user' => $userName,
				'clientip' => $clientInfo,
			] );
		}
	}

	/**
	 * @param OATHUser $user
	 * @param string $clientInfo
	 */
	public function remove( OATHUser $user, $clientInfo ) {
		$this->getDB( DB_MASTER )->delete(
			'oathauth_users',
			[ 'id' => CentralIdLookup::factory()->centralIdFromLocalUser( $user->getUser() ) ],
			__METHOD__
		);

		$userName = $user->getUser()->getName();
		$this->cache->delete( $userName );

		$this->logger->info( 'OATHAuth disabled for {user} from {clientip}', [
			'user' => $userName,
			'clientip' => $clientInfo,
		] );
	}

	/**
	 * @param int $index DB_MASTER/DB_REPLICA
	 * @return DBConnRef
	 */
	private function getDB( $index ) {
		global $wgOATHAuthDatabase;

		return $this->lb->getConnectionRef( $index, [], $wgOATHAuthDatabase );
	}
}
