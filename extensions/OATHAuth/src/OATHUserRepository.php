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

use BagOStuff;
use ConfigException;
use FormatJson;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MWException;
use Psr\Log\LoggerInterface;
use RequestContext;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class OATHUserRepository {
	/** @var ILoadBalancer */
	protected $lb;

	/** @var BagOStuff */
	protected $cache;

	/**
	 * @var OATHAuth
	 */
	protected $auth;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * OATHUserRepository constructor.
	 * @param ILoadBalancer $lb
	 * @param BagOStuff $cache
	 * @param OATHAuth $auth
	 */
	public function __construct( ILoadBalancer $lb, BagOStuff $cache, OATHAuth $auth ) {
		$this->lb = $lb;
		$this->cache = $cache;
		$this->auth = $auth;

		$this->setLogger( LoggerFactory::getInstance( 'authentication' ) );
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
	 * @throws \ConfigException
	 * @throws \MWException
	 */
	public function findByUser( User $user ) {
		$oathUser = $this->cache->get( $user->getName() );
		if ( !$oathUser ) {
			$oathUser = new OATHUser( $user, [] );

			$uid = MediaWikiServices::getInstance()
				->getCentralIdLookupFactory()
				->getLookup()
				->centralIdFromLocalUser( $user );
			$res = $this->getDB( DB_REPLICA )->selectRow(
				'oathauth_users',
				[ 'module', 'data' ],
				[ 'id' => $uid ],
				__METHOD__
			);
			if ( $res ) {
				$module = $this->auth->getModuleByKey( $res->module );
				if ( $module === null ) {
					throw new MWException( 'oathauth-module-invalid' );
				}

				$oathUser->setModule( $module );
				$decodedData = FormatJson::decode( $res->data, true );
				if ( is_array( $decodedData['keys'] ) ) {
					foreach ( $decodedData['keys'] as $keyData ) {
						$key = $module->newKey( $keyData );
						$oathUser->addKey( $key );
					}
				}
			}

			$this->cache->set( $user->getName(), $oathUser );
		}
		return $oathUser;
	}

	/**
	 * @param OATHUser $user
	 * @param string|null $clientInfo
	 * @throws ConfigException
	 * @throws MWException
	 */
	public function persist( OATHUser $user, $clientInfo = null ) {
		if ( !$clientInfo ) {
			$clientInfo = RequestContext::getMain()->getRequest()->getIP();
		}
		$prevUser = $this->findByUser( $user->getUser() );
		$data = $user->getModule()->getDataFromUser( $user );

		$this->getDB( DB_PRIMARY )->replace(
			'oathauth_users',
			'id',
			[
				'id' => MediaWikiServices::getInstance()
					->getCentralIdLookupFactory()
					->getLookup()
					->centralIdFromLocalUser( $user->getUser() ),
				'module' => $user->getModule()->getName(),
				'data' => FormatJson::encode( $data )
			],
			__METHOD__
		);

		$userName = $user->getUser()->getName();
		$this->cache->set( $userName, $user );

		if ( $prevUser !== false ) {
			$this->logger->info( 'OATHAuth updated for {user} from {clientip}', [
				'user' => $userName,
				'clientip' => $clientInfo,
				'oldoathtype' => $prevUser->getModule()->getName(),
				'newoathtype' => $user->getModule()->getName(),
			] );
		} else {
			// If findByUser() has returned false, there was no user row or cache entry
			$this->logger->info( 'OATHAuth enabled for {user} from {clientip}', [
				'user' => $userName,
				'clientip' => $clientInfo,
				'oathtype' => $user->getModule()->getName(),
			] );
			Notifications\Manager::notifyEnabled( $user );
		}
	}

	/**
	 * @param OATHUser $user
	 * @param string $clientInfo
	 * @param bool $self Whether they disabled it themselves
	 */
	public function remove( OATHUser $user, $clientInfo, bool $self ) {
		$this->getDB( DB_PRIMARY )->delete(
			'oathauth_users',
			[ 'id' => MediaWikiServices::getInstance()
				->getCentralIdLookupFactory()
				->getLookup()
				->centralIdFromLocalUser( $user->getUser() ) ],
			__METHOD__
		);

		$userName = $user->getUser()->getName();
		$this->cache->delete( $userName );

		$this->logger->info( 'OATHAuth disabled for {user} from {clientip}', [
			'user' => $userName,
			'clientip' => $clientInfo,
			'oathtype' => $user->getModule()->getName(),
		] );
		Notifications\Manager::notifyDisabled( $user, $self );
	}

	/**
	 * @param int $index DB_PRIMARY/DB_REPLICA
	 * @return IDatabase
	 */
	private function getDB( $index ) {
		global $wgOATHAuthDatabase;

		return $this->lb->getConnectionRef( $index, [], $wgOATHAuthDatabase );
	}
}
