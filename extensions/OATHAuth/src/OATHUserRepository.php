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
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\CentralId\CentralIdLookupFactory;
use MWException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use RequestContext;
use RuntimeException;
use User;

class OATHUserRepository implements LoggerAwareInterface {
	/** @var ServiceOptions */
	private ServiceOptions $options;

	/** @var OATHAuthDatabase */
	private OATHAuthDatabase $database;

	/** @var BagOStuff */
	private BagOStuff $cache;

	/** @var OATHAuthModuleRegistry */
	private OATHAuthModuleRegistry $moduleRegistry;

	/** @var CentralIdLookupFactory */
	private CentralIdLookupFactory $centralIdLookupFactory;

	/** @var LoggerInterface */
	private LoggerInterface $logger;

	/** @internal Only public for service wiring use. */
	public const CONSTRUCTOR_OPTIONS = [
		'OATHAuthMultipleDevicesMigrationStage',
	];

	/**
	 * OATHUserRepository constructor.
	 *
	 * @param ServiceOptions $options
	 * @param OATHAuthDatabase $database
	 * @param BagOStuff $cache
	 * @param OATHAuthModuleRegistry $moduleRegistry
	 * @param CentralIdLookupFactory $centralIdLookupFactory
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		ServiceOptions $options,
		OATHAuthDatabase $database,
		BagOStuff $cache,
		OATHAuthModuleRegistry $moduleRegistry,
		CentralIdLookupFactory $centralIdLookupFactory,
		LoggerInterface $logger
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->database = $database;
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
	 * @param User $user
	 * @return OATHUser
	 * @throws \ConfigException
	 * @throws \MWException
	 */
	public function findByUser( User $user ) {
		$oathUser = $this->cache->get( $user->getName() );
		if ( !$oathUser ) {
			$oathUser = new OATHUser( $user, [] );

			$uid = $this->centralIdLookupFactory->getLookup()
				->centralIdFromLocalUser( $user );

			$moduleId = null;
			$keys = [];

			if ( $this->getMultipleDevicesMigrationStage() & SCHEMA_COMPAT_READ_NEW ) {
				$res = $this->database->getDB( DB_REPLICA )->newSelectQueryBuilder()
					->select( [
						'oad_data',
						'oat_name',
					] )
					->from( 'oathauth_devices' )
					->join( 'oathauth_types', null, [ 'oat_id = oad_type' ] )
					->where( [ 'oad_user' => $uid ] )
					->caller( __METHOD__ )
					->fetchResultSet();

				foreach ( $res as $row ) {
					if ( $moduleId && $row->oat_name !== $moduleId ) {
						// Not supported by current application-layer code.
						throw new RuntimeException( "user {$uid} has multiple different oathauth modules defined" );
					}

					$moduleId = $row->oat_name;
					$keys[] = FormatJson::decode( $row->oad_data, true );
				}
			} elseif ( $this->getMultipleDevicesMigrationStage() & SCHEMA_COMPAT_READ_OLD ) {
				$res = $this->database->getDB( DB_REPLICA )->selectRow(
					'oathauth_users',
					[ 'module', 'data' ],
					[ 'id' => $uid ],
					__METHOD__
				);

				if ( $res ) {
					$moduleId = $res->module;
					$decodedData = FormatJson::decode( $res->data, true );

					if ( is_array( $decodedData['keys'] ) ) {
						$keys = $decodedData['keys'];
					}
				}
			} else {
				throw new RuntimeException( 'Either READ_NEW or READ_OLD must be set' );
			}

			if ( $moduleId ) {
				$module = $this->moduleRegistry->getModuleByKey( $moduleId );
				if ( $module === null ) {
					throw new MWException( 'oathauth-module-invalid' );
				}

				$oathUser->setModule( $module );

				foreach ( $keys as $keyData ) {
					$key = $module->newKey( $keyData );
					$oathUser->addKey( $key );
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
		$userId = $this->centralIdLookupFactory->getLookup()->centralIdFromLocalUser( $user->getUser() );

		if ( $this->getMultipleDevicesMigrationStage() & SCHEMA_COMPAT_WRITE_NEW ) {
			$moduleId = $this->moduleRegistry->getModuleId( $user->getModule()->getName() );
			$rows = [];
			foreach ( $user->getKeys() as $key ) {
				$rows[] = [
					'oad_user' => $userId,
					'oad_type' => $moduleId,
					'oad_data' => FormatJson::encode( $key->jsonSerialize() )
				];
			}

			$dbw = $this->database->getDB( DB_PRIMARY );
			$dbw->startAtomic( __METHOD__ );

			// TODO: only update changed rows
			$dbw->delete(
				'oathauth_devices',
				[ 'oad_user' => $userId ],
				__METHOD__
			);
			$dbw->insert(
				'oathauth_devices',
				$rows,
				__METHOD__
			);
			$dbw->endAtomic( __METHOD__ );
		}

		if ( $this->getMultipleDevicesMigrationStage() & SCHEMA_COMPAT_WRITE_OLD ) {
			$data = [
				'keys' => []
			];

			foreach ( $user->getKeys() as $key ) {
				$data['keys'][] = $key->jsonSerialize();
			}

			$this->database->getDB( DB_PRIMARY )->replace(
				'oathauth_users',
				'id',
				[
					'id' => $userId,
					'module' => $user->getModule()->getName(),
					'data' => FormatJson::encode( $data )
				],
				__METHOD__
			);
		}

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
			Manager::notifyEnabled( $user );
		}
	}

	/**
	 * @param OATHUser $user
	 * @param string $clientInfo
	 * @param bool $self Whether they disabled it themselves
	 */
	public function remove( OATHUser $user, $clientInfo, bool $self ) {
		$userId = $this->centralIdLookupFactory->getLookup()
			->centralIdFromLocalUser( $user->getUser() );
		if ( $this->getMultipleDevicesMigrationStage() & SCHEMA_COMPAT_WRITE_NEW ) {
			$this->database->getDB( DB_PRIMARY )->delete(
				'oathauth_devices',
				[ 'oad_user' => $userId ],
				__METHOD__
			);
		}

		if ( $this->getMultipleDevicesMigrationStage() & SCHEMA_COMPAT_WRITE_OLD ) {
			$this->database->getDB( DB_PRIMARY )->delete(
				'oathauth_users',
				[ 'id' => $userId ],
				__METHOD__
			);
		}

		$userName = $user->getUser()->getName();
		$this->cache->delete( $userName );

		$this->logger->info( 'OATHAuth disabled for {user} from {clientip}', [
			'user' => $userName,
			'clientip' => $clientInfo,
			'oathtype' => $user->getModule()->getName(),
		] );
		Notifications\Manager::notifyDisabled( $user, $self );
	}

	private function getMultipleDevicesMigrationStage(): int {
		return $this->options->get( 'OATHAuthMultipleDevicesMigrationStage' );
	}
}
