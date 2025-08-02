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

use InvalidArgumentException;
use MediaWiki\Config\ConfigException;
use MediaWiki\Exception\MWException;
use MediaWiki\Extension\OATHAuth\Notifications\Manager;
use MediaWiki\Json\FormatJson;
use MediaWiki\User\CentralId\CentralIdLookupFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\Rdbms\IConnectionProvider;

class OATHUserRepository implements LoggerAwareInterface {
	private IConnectionProvider $dbProvider;

	private BagOStuff $cache;

	private OATHAuthModuleRegistry $moduleRegistry;

	private CentralIdLookupFactory $centralIdLookupFactory;

	private LoggerInterface $logger;

	public function __construct(
		IConnectionProvider $dbProvider,
		BagOStuff $cache,
		OATHAuthModuleRegistry $moduleRegistry,
		CentralIdLookupFactory $centralIdLookupFactory,
		LoggerInterface $logger
	) {
		$this->dbProvider = $dbProvider;
		$this->cache = $cache;
		$this->moduleRegistry = $moduleRegistry;
		$this->centralIdLookupFactory = $centralIdLookupFactory;
		$this->setLogger( $logger );
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param UserIdentity $user
	 * @return OATHUser
	 * @throws ConfigException
	 * @throws MWException
	 */
	public function findByUser( UserIdentity $user ) {
		$oathUser = $this->cache->get( $user->getName() );
		if ( !$oathUser ) {
			$uid = $this->centralIdLookupFactory->getLookup()
				->centralIdFromLocalUser( $user );
			$oathUser = new OATHUser( $user, $uid );
			$this->loadKeysFromDatabase( $oathUser );

			$this->cache->set( $user->getName(), $oathUser );
		}
		return $oathUser;
	}

	/**
	 * Persists the given OAuth key in the database.
	 *
	 * @param OATHUser $user
	 * @param IModule $module
	 * @param array $keyData
	 * @param string $clientInfo
	 * @return IAuthKey
	 */
	public function createKey( OATHUser $user, IModule $module, array $keyData, string $clientInfo ): IAuthKey {
		if ( $user->getModule() && $user->getModule()->getName() !== $module->getName() ) {
			throw new InvalidArgumentException(
				"User already has a key from a different module enabled ({$user->getModule()->getName()})"
			);
		}

		$uid = $user->getCentralId();
		if ( !$uid ) {
			throw new InvalidArgumentException( "Can't persist a key for user with no central ID available" );
		}

		$moduleId = $this->moduleRegistry->getModuleId( $module->getName() );

		$dbw = $this->dbProvider->getPrimaryDatabase( 'virtual-oathauth' );
		$dbw->newInsertQueryBuilder()
			->insertInto( 'oathauth_devices' )
			->row( [
				'oad_user' => $uid,
				'oad_type' => $moduleId,
				'oad_data' => FormatJson::encode( $keyData ),
				'oad_created' => $dbw->timestamp(),
			] )
			->caller( __METHOD__ )
			->execute();
		$id = $dbw->insertId();

		$hasExistingKey = $user->isTwoFactorAuthEnabled();

		$key = $module->newKey( $keyData + [ 'id' => $id ] );
		$user->addKey( $key );

		$this->logger->info( 'OATHAuth {oathtype} key {key} added for {user} from {clientip}', [
			'key' => $id,
			'user' => $user->getUser()->getName(),
			'clientip' => $clientInfo,
			'oathtype' => $module->getName(),
		] );

		if ( !$hasExistingKey ) {
			$user->setModule( $module );
			Manager::notifyEnabled( $user );
		}

		return $key;
	}

	/**
	 * Saves an existing key in the database.
	 *
	 * @param OATHUser $user
	 * @param IAuthKey $key
	 * @return void
	 */
	public function updateKey( OATHUser $user, IAuthKey $key ): void {
		$keyId = $key->getId();
		if ( !$keyId ) {
			throw new InvalidArgumentException( 'updateKey() can only be used with already existing keys' );
		}

		$dbw = $this->dbProvider->getPrimaryDatabase( 'virtual-oathauth' );
		$dbw->newUpdateQueryBuilder()
			->table( 'oathauth_devices' )
			->set( [ 'oad_data' => FormatJson::encode( $key->jsonSerialize() ) ] )
			->where( [ 'oad_user' => $user->getCentralId(), 'oad_id' => $keyId ] )
			->caller( __METHOD__ )
			->execute();

		$this->logger->info( 'OATHAuth key {keyId} updated for {user}', [
			'keyId' => $keyId,
			'user' => $user->getUser()->getName(),
		] );
	}

	/**
	 * @param OATHUser $user
	 * @param IAuthKey $key
	 * @param string $clientInfo
	 * @param bool $self Whether they disabled it themselves
	 */
	public function removeKey( OATHUser $user, IAuthKey $key, string $clientInfo, bool $self ) {
		$keyId = $key->getId();
		if ( !$keyId ) {
			throw new InvalidArgumentException( 'A non-persisted key cannot be removed' );
		}

		$this->dbProvider->getPrimaryDatabase( 'virtual-oathauth' )
			->newDeleteQueryBuilder()
			->deleteFrom( 'oathauth_devices' )
			->where( [ 'oad_user' => $user->getCentralId(), 'oad_id' => $keyId ] )
			->caller( __METHOD__ )
			->execute();

		// Remove the key from the user object
		$user->setKeys(
			array_values(
				array_filter(
					$user->getKeys(),
					static function ( IAuthKey $key ) use ( $keyId ) {
						return $key->getId() !== $keyId;
					}
				)
			)
		);

		if ( !$user->getKeys() ) {
			$user->setModule( null );
		}

		$userName = $user->getUser()->getName();
		$this->cache->delete( $userName );

		$this->logger->info( 'OATHAuth removed {oathtype} key {key} for {user} from {clientip}', [
			'key' => $keyId,
			'user' => $userName,
			'clientip' => $clientInfo,
			'oathtype' => $key->getModule(),
		] );

		Manager::notifyDisabled( $user, $self );
	}

	/**
	 * @param OATHUser $user
	 * @param string $clientInfo
	 * @param bool $self Whether the user disabled the 2FA themselves
	 *
	 * @deprecated since 1.41, use removeAll() instead
	 */
	public function remove( OATHUser $user, $clientInfo, bool $self ) {
		$this->removeAll( $user, $clientInfo, $self );
	}

	/**
	 * @param OATHUser $user
	 * @param string $clientInfo
	 * @param bool $self Whether they disabled it themselves
	 */
	public function removeAll( OATHUser $user, $clientInfo, bool $self ) {
		$this->dbProvider->getPrimaryDatabase( 'virtual-oathauth' )
			->newDeleteQueryBuilder()
			->deleteFrom( 'oathauth_devices' )
			->where( [ 'oad_user' => $user->getCentralId() ] )
			->caller( __METHOD__ )
			->execute();

		$keyTypes = array_map(
			static fn ( IAuthKey $key ) => $key->getModule(),
			$user->getKeys()
		);

		$user->disable();

		$userName = $user->getUser()->getName();
		$this->cache->delete( $userName );

		$this->logger->info( 'OATHAuth disabled for {user} from {clientip}', [
			'user' => $userName,
			'clientip' => $clientInfo,
			'oathtype' => implode( ',', $keyTypes ),
		] );

		Manager::notifyDisabled( $user, $self );
	}

	private function loadKeysFromDatabase( OATHUser $user ): void {
		$uid = $user->getCentralId();
		if ( !$uid ) {
			// T379442
			return;
		}

		$res = $this->dbProvider
			->getReplicaDatabase( 'virtual-oathauth' )
			->newSelectQueryBuilder()
			->select( [
				'oad_id',
				'oad_data',
				'oat_name',
			] )
			->from( 'oathauth_devices' )
			->join( 'oathauth_types', null, [ 'oat_id = oad_type' ] )
			->where( [ 'oad_user' => $uid ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$module = null;

		// Clear stored key list before loading keys
		$user->disable();

		foreach ( $res as $row ) {
			if ( $module && $row->oat_name !== $module->getName() ) {
				// Not supported by current application-layer code.
				throw new RuntimeException( "User {$uid} has multiple different two-factor modules defined" );
			}

			if ( !$module ) {
				$module = $this->moduleRegistry->getModuleByKey( $row->oat_name );
				$user->setModule( $module );

				if ( !$module ) {
					throw new MWException( 'oathauth-module-invalid' );
				}
			}

			$keyData = FormatJson::decode( $row->oad_data, true );
			$user->addKey( $module->newKey( $keyData + [ 'id' => (int)$row->oad_id ] ) );
		}
	}
}
