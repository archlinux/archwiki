<?php
/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth;

use InvalidArgumentException;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\Extension\OATHAuth\Key\AuthKey;
use MediaWiki\Extension\OATHAuth\Key\WebAuthnKey;
use MediaWiki\Extension\OATHAuth\Module\IModule;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Extension\OATHAuth\Notifications\Manager;
use MediaWiki\Json\FormatJson;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Registration\ExtensionRegistry;
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
	 * Used for a "cheap" lookup whether a user has 2FA enabled.
	 *
	 * If you have no subsequent need for access to the full OATHUser object,
	 * or their 2FA keys, use this function, rather than findByUser or similar.
	 *
	 * This function will check the cache first. If the OATHUser object is in
	 * the cache, we can use OATHUser::isTwoFactorAuthEnabled(). If it is not, it
	 * will query the database to check for oathauth_devices rows for this user.
	 *
	 * This can be more performant than loading all the oathauth_devices rows,
	 * de-serializing them, and potentially decrypting values that would never be
	 * used.
	 */
	public function userHas2FAEnabled( UserIdentity $user ): bool {
		/** @var OATHUser $oathUser */
		$oathUser = $this->cache->get( $user->getName() );
		// Use the OATHUser from cache if it exists (though this is HashBagOfStuff, so limited in-process use only)
		if ( $oathUser ) {
			return $oathUser->isTwoFactorAuthEnabled();
		}

		// Else look it up from the database
		return $this->dbProvider
			->getReplicaDatabase( 'virtual-oathauth' )
			->newSelectQueryBuilder()
			->from( 'oathauth_devices' )
			->where( [
				'oad_user' => $this->centralIdLookupFactory->getLookup()
					->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW )
			] )
			->caller( __METHOD__ )
			->fetchRowCount() > 0;
	}

	/**
	 * Find the user who owns a given User Handle, and load an OATHUser object for them.
	 * Use this to identify a user when you only have their WebAuthn authentication result.
	 * @param string $userHandle User Handle value from the user's WebAuthn key
	 * @return OATHUser|null OATHUser object for the user, or null if no user was found for the
	 *   given User Handle
	 */
	public function findByUserHandle( string $userHandle ): ?OATHUser {
		$userId = $this->dbProvider
			->getReplicaDatabase( 'virtual-oathauth' )
			->newSelectQueryBuilder()
			->select( 'oah_user' )
			->from( 'oathauth_user_handles' )
			->where( [ 'oah_handle' => base64_encode( $userHandle ) ] )
			->caller( __METHOD__ )
			->fetchField();
		if ( $userId === false ) {
			return null;
		}

		$user = $this->centralIdLookupFactory->getLookup()->localUserFromCentralId(
			$userId, CentralIdLookup::AUDIENCE_RAW
		);
		if ( $user === null ) {
			return null;
		}

		$oathUser = new OATHUser( $user, $userId );
		$oathUser->setUserHandle( $userHandle );
		$this->loadKeysFromDatabase( $oathUser );
		$this->cache->set( $user->getName(), $oathUser );
		return $oathUser;
	}

	/**
	 * Persists the given key in the database.
	 */
	public function createKey( OATHUser $user, IModule $module, array $keyData, string $clientInfo ): AuthKey {
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

		$this->logger->info( 'OATHAuth added {oathtype} key {key} for {user} from {clientip}', [
			'key' => $id,
			'user' => $user->getUser()->getName(),
			'clientip' => $clientInfo,
			'oathtype' => $module->getName(),
		] );

		// If the user added a WebAuthn key, but doesn't have a User Handle yet, add this key's
		// User Handle to the oathauth_user_handles table
		if ( $key instanceof WebAuthnKey && $user->getUserHandle() === null ) {
			$user->setUserHandle( $key->getUserHandle() );
			$this->insertUserHandle( $user );
		}

		if ( !$hasExistingKey ) {
			Manager::notifyEnabled( $user );

			if ( ExtensionRegistry::getInstance()->isLoaded( 'CheckUser' ) ) {
				$logEntry = new ManualLogEntry( 'oath', 'enable-self' );
				$logEntry->setPerformer( $user->getUser() );
				$logEntry->setTarget(
					PageReferenceValue::localReference( NS_USER, $user->getUser()->getName() )
				);
				/** @var CheckUserInsert $checkUserInsert */
				$checkUserInsert = MediaWikiServices::getInstance()->get( 'CheckUserInsert' );
				$checkUserInsert->updateCheckUserData( $logEntry->getRecentChange() );
			}
		}

		return $key;
	}

	/**
	 * Saves an existing key in the database.
	 */
	public function updateKey( OATHUser $user, AuthKey $key ): void {
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

	public function removeKey( OATHUser $user, AuthKey $key, string $clientInfo, bool $self ) {
		$keyId = $key->getId();
		if ( !$keyId ) {
			throw new InvalidArgumentException( 'A non-persisted key cannot be removed' );
		}

		$this->removeSomeKeys( $user, [ 'oad_id' => $keyId ] );
		$user->removeKey( $key );

		$moduleName = $key->getModule();
		// If the user just deleted their last WebAuthn key, delete their User Handle
		if ( $moduleName === WebAuthn::MODULE_ID && $user->getKeysForModule( $moduleName ) === [] ) {
			$this->deleteUserHandle( $user );
		}

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

		// If the user just deleted all of their WebAuthn keys, delete their User Handle
		if ( $keyType === WebAuthn::MODULE_ID ) {
			$this->deleteUserHandle( $user );
		}

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
			static fn ( AuthKey $key ) => $key->getModule(),
			$user->getKeys()
		) );
		$user->disable();

		$this->deleteUserHandle( $user );

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

		if ( $user->getUserHandle() === null ) {
			$userHandle = $this->dbProvider
				->getReplicaDatabase( 'virtual-oathauth' )
				->newSelectQueryBuilder()
				->select( 'oah_handle' )
				->from( 'oathauth_user_handles' )
				->where( [ 'oah_user' => $uid ] )
				->caller( __METHOD__ )
				->fetchField();
			if ( $userHandle !== false ) {
				$user->setUserHandle( base64_decode( $userHandle ) );
			} else {
				// If the user has any WebAuthn keys, derive their userHandle from that
				/** @var WebAuthnKey[] $webauthnKeys */
				$webauthnKeys = $user->getKeysForModule( WebAuthn::MODULE_ID );
				'@phan-var WebAuthnKey[] $webauthnKeys';
				if ( $webauthnKeys ) {
					$user->setUserHandle( $webauthnKeys[0]->getUserHandle() );
				}
			}
		}
	}

	private function insertUserHandle( OATHUser $user ): void {
		$userHandle = $user->getUserHandle();
		if ( $userHandle === null ) {
			return;
		}
		$this->dbProvider
			->getPrimaryDatabase( 'virtual-oathauth' )
			->newInsertQueryBuilder()
			->insertInto( 'oathauth_user_handles' )
			->ignore()
			->row( [
				'oah_user' => $user->getCentralId(),
				'oah_handle' => base64_encode( $userHandle )
			] )
			->caller( __METHOD__ )
			->execute();
	}

	private function deleteUserHandle( OATHUser $user ): void {
		$user->setUserHandle( null );
		$this->dbProvider
			->getPrimaryDatabase( 'virtual-oathauth' )
			->newDeleteQueryBuilder()
			->deleteFrom( 'oathauth_user_handles' )
			->where( [ 'oah_user' => $user->getCentralId() ] )
			->caller( __METHOD__ )
			->execute();
	}
}
