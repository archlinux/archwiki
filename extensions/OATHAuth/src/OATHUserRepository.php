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
use MediaWiki\Extension\OATHAuth\Notifications\Manager;
use MediaWiki\Json\FormatJson;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\CentralId\CentralIdLookupFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\Rdbms\IConnectionProvider;

class OATHUserRepository implements LoggerAwareInterface {
	private LoggerInterface $logger;

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		private readonly BagOStuff $cache,
		private readonly OATHAuthModuleRegistry $moduleRegistry,
		private readonly CentralIdLookupFactory $centralIdLookupFactory,
		LoggerInterface $logger,
	) {
		$this->setLogger( $logger );
	}

	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	public function findByUser( UserIdentity $user ): OATHUser {
		$oathUser = $this->cache->get( $user->getName() );
		if ( !$oathUser ) {
			$uid = $this->centralIdLookupFactory->getLookup()
				->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW );
			$oathUser = new OATHUser( $user, $uid );
			$this->loadKeysFromDatabase( $oathUser );

			$this->cache->set( $user->getName(), $oathUser );
		}
		return $oathUser;
	}

	/**
	 * Persists the given OAuth key in the database.
	 *
	 * @throws InvalidArgumentException
	 */
	public function createKey( OATHUser $user, IModule $module, array $keyData, string $clientInfo ): IAuthKey {
		$uid = $user->getCentralId();
		if ( !$uid ) {
			throw new InvalidArgumentException( "Can't persist a key for user with no central ID available" );
		}

		$moduleId = $this->moduleRegistry->getModuleId( $module->getName() );
		$dbw = $this->dbProvider->getPrimaryDatabase( 'virtual-oathauth' );
		$createdTimestamp = $dbw->timestamp();
		$dbw->newInsertQueryBuilder()
			->insertInto( 'oathauth_devices' )
			->row( [
				'oad_user' => $uid,
				'oad_type' => $moduleId,
				'oad_data' => FormatJson::encode( $keyData ),
				'oad_created' => $createdTimestamp,
			] )
			->caller( __METHOD__ )
			->execute();
		$id = $dbw->insertId();

		$hasExistingKey = $user->isTwoFactorAuthEnabled();

		$key = $module->newKey( $keyData + [ 'id' => $id, 'created_timestamp' => $createdTimestamp ] );
		$user->addKey( $key );

		$this->logger->info( 'OATHAuth {oathtype} key {key} added for {user} from {clientip}', [
			'key' => $id,
			'user' => $user->getUser()->getName(),
			'clientip' => $clientInfo,
			'oathtype' => $module->getName(),
		] );

		if ( !$hasExistingKey ) {
			Manager::notifyEnabled( $user );
		}

		return $key;
	}

	/**
	 * Saves an existing key in the database.
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

	private function removeSomeKeys( OATHUser $user, array $where ): void {
		$this->dbProvider->getPrimaryDatabase( 'virtual-oathauth' )
			->newDeleteQueryBuilder()
			->deleteFrom( 'oathauth_devices' )
			->where( [ 'oad_user' => $user->getCentralId() ] )
			->where( $where )
			->caller( __METHOD__ )
			->execute();

		$this->cache->delete( $user->getUser()->getName() );
	}

	public function removeKey( OATHUser $user, IAuthKey $key, string $clientInfo, bool $self ) {
		$keyId = $key->getId();
		if ( !$keyId ) {
			throw new InvalidArgumentException( 'A non-persisted key cannot be removed' );
		}

		$this->removeSomeKeys( $user, [ 'oad_id' => $keyId ] );
		$user->removeKey( $key );

		$this->logger->info( 'OATHAuth removed {oathtype} key {key} for {user} from {clientip}', [
			'key' => $keyId,
			'user' => $user->getUser()->getName(),
			'clientip' => $clientInfo,
			'oathtype' => $key->getModule(),
		] );

		if ( !$this->moduleRegistry->getModuleByKey( $key->getModule() )->isSpecial() ) {
			Manager::notifyDisabled( $user, $self );
		}
	}

	/**
	 * @param OATHUser $user
	 * @param string $keyType As in IModule::getName()
	 * @param string $clientInfo
	 * @param bool $self Whether they disabled it themselves
	 */
	public function removeAllOfType( OATHUser $user, string $keyType, string $clientInfo, bool $self ) {
		$moduleId = $this->moduleRegistry->getModuleId( $keyType );
		if ( !$moduleId ) {
			throw new InvalidArgumentException( 'Invalid key type: ' . $keyType );
		}

		$this->removeSomeKeys( $user, [ 'oad_type' => $moduleId ] );
		$user->removeKeysForModule( $keyType );

		$this->logger->info( 'OATHAuth removed {oathtype} keys for {user} from {clientip}', [
			'user' => $user->getUser()->getName(),
			'clientip' => $clientInfo,
			'oathtype' => $keyType,
		] );

		if ( !$this->moduleRegistry->getModuleByKey( $keyType )->isSpecial() ) {
			Manager::notifyDisabled( $user, $self );
		}
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
		$this->removeSomeKeys( $user, [] );

		$keyTypes = array_unique( array_map(
			static fn ( IAuthKey $key ) => $key->getModule(),
			$user->getKeys()
		) );
		$user->disable();

		$this->logger->info( 'OATHAuth disabled for {user} from {clientip}', [
			'user' => $user->getUser()->getName(),
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
				'oad_created',
			] )
			->from( 'oathauth_devices' )
			->join( 'oathauth_types', null, [ 'oat_id = oad_type' ] )
			->where( [ 'oad_user' => $uid ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		// Clear the stored key list before loading keys
		$user->disable();

		foreach ( $res as $row ) {
			$module = $this->moduleRegistry->getModuleByKey( $row->oat_name );
			$keyData = FormatJson::decode( $row->oad_data, true );

			$user->addKey(
				$module->newKey( $keyData + [
					'id' => (int)$row->oad_id,
					'created_timestamp' => $row->oad_created
				] )
			);
		}
	}
}
