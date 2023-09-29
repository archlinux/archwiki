<?php

namespace MediaWiki\Extension\OATHAuth\Hook;

use ConfigException;
use DatabaseUpdater;
use FormatJson;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IMaintainableDatabase;

class UpdateTables implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();
		$typePath = dirname( __DIR__, 2 ) . "/sql/{$type}";

		$updater->addExtensionTable(
			'oathauth_users',
			"$typePath/tables-generated.sql"
		);

		switch ( $type ) {
			case 'mysql':
			case 'sqlite':
				// 1.34
				$updater->addExtensionField(
					'oathauth_users',
					'module',
					"$typePath/patch-add_generic_fields.sql"
				);

				$updater->addExtensionUpdate(
					[ [ __CLASS__, 'schemaUpdateSubstituteForGenericFields' ] ]
				);
				$updater->dropExtensionField(
					'oathauth_users',
					'secret',
					"$typePath/patch-remove_module_specific_fields.sql"
				);

				$updater->addExtensionUpdate(
					[ [ __CLASS__, 'schemaUpdateTOTPToMultipleKeys' ] ]
				);

				$updater->addExtensionUpdate(
					[ [ __CLASS__, 'schemaUpdateTOTPScratchTokensToArray' ] ]
				);

				break;

			case 'postgres':
				// 1.38
				$updater->modifyExtensionTable(
					'oathauth_users',
					"$typePath/patch-oathauth_users-drop-oathauth_users_id_seq.sql"
				);
				break;
		}
	}

	/**
	 * @return IMaintainableDatabase
	 */
	private static function getDatabase() {
		$services = MediaWikiServices::getInstance();

		// Global can be `null` during installation, ensure we pass `false` instead (T270147)
		// can't rely on OATHAuthModuleRegistry or the setting existing for the same reason
		$database = false;
		if ( $services->getMainConfig()->has( 'OATHAuthDatabase' ) ) {
			$database = $services->getMainConfig()->get( 'OATHAuthDatabase' ) ?? false;
		}

		return $services->getDBLoadBalancerFactory()
			->getMainLB( $database )
			->getConnectionRef( DB_PRIMARY, [], $database );
	}

	/**
	 * Helper function for converting old, TOTP specific, column values to new structure
	 * @param DatabaseUpdater $updater
	 * @return bool
	 * @throws ConfigException
	 */
	public static function schemaUpdateSubstituteForGenericFields( DatabaseUpdater $updater ) {
		return self::convertToGenericFields( self::getDatabase() );
	}

	/**
	 * Helper function for converting single TOTP keys to multi-key system
	 * @param DatabaseUpdater $updater
	 * @return bool
	 * @throws ConfigException
	 */
	public static function schemaUpdateTOTPToMultipleKeys( DatabaseUpdater $updater ) {
		return self::switchTOTPToMultipleKeys( self::getDatabase() );
	}

	/**
	 * Helper function for converting single TOTP keys to multi-key system
	 * @param DatabaseUpdater $updater
	 * @return bool
	 * @throws ConfigException
	 */
	public static function schemaUpdateTOTPScratchTokensToArray( DatabaseUpdater $updater ) {
		return self::switchTOTPScratchTokensToArray( self::getDatabase() );
	}

	/**
	 * Converts old, TOTP specific, column values to new structure
	 * @param IMaintainableDatabase $db
	 * @return bool
	 * @throws ConfigException
	 */
	public static function convertToGenericFields( IMaintainableDatabase $db ) {
		if ( !$db->fieldExists( 'oathauth_users', 'secret', __METHOD__ ) ) {
			return true;
		}

		$services = MediaWikiServices::getInstance();
		$batchSize = $services->getMainConfig()->get( 'UpdateRowsPerQuery' );
		$lbFactory = $services->getDBLoadBalancerFactory();
		while ( true ) {
			$lbFactory->waitForReplication();

			$res = $db->select(
				'oathauth_users',
				[ 'id', 'secret', 'scratch_tokens' ],
				[
					'module' => '',
					'data IS NULL',
					'secret IS NOT NULL'
				],
				__METHOD__,
				[ 'LIMIT' => $batchSize ]
			);

			if ( $res->numRows() === 0 ) {
				return true;
			}

			foreach ( $res as $row ) {
				$db->update(
					'oathauth_users',
					[
						'module' => 'totp',
						'data' => FormatJson::encode( [
							'keys' => [ [
								'secret' => $row->secret,
								'scratch_tokens' => $row->scratch_tokens
							] ]
						] )
					],
					[ 'id' => $row->id ],
					__METHOD__
				);
			}
		}
	}

	/**
	 * Switch from using single keys to multi-key support
	 *
	 * @param IMaintainableDatabase $db
	 * @return bool
	 * @throws ConfigException
	 */
	public static function switchTOTPToMultipleKeys( IMaintainableDatabase $db ) {
		if ( !$db->fieldExists( 'oathauth_users', 'data', __METHOD__ ) ) {
			return true;
		}

		$res = $db->select(
			'oathauth_users',
			[ 'id', 'data' ],
			[
				'module' => 'totp'
			],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$data = FormatJson::decode( $row->data, true );
			if ( isset( $data['keys'] ) ) {
				continue;
			}
			$db->update(
				'oathauth_users',
				[
					'data' => FormatJson::encode( [
						'keys' => [ $data ]
					] )
				],
				[ 'id' => $row->id ],
				__METHOD__
			);
		}

		return true;
	}

	/**
	 * Switch scratch tokens from string to an array
	 *
	 * @param IMaintainableDatabase $db
	 * @return bool
	 * @throws ConfigException
	 */
	public static function switchTOTPScratchTokensToArray( IMaintainableDatabase $db ) {
		if ( !$db->fieldExists( 'oathauth_users', 'data' ) ) {
			return true;
		}

		$res = $db->select(
			'oathauth_users',
			[ 'id', 'data' ],
			[
				'module' => 'totp'
			],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$data = FormatJson::decode( $row->data, true );

			$updated = false;
			foreach ( $data['keys'] as &$k ) {
				if ( is_string( $k['scratch_tokens'] ) ) {
					$k['scratch_tokens'] = explode( ',', $k['scratch_tokens'] );
					$updated = true;
				}
			}

			if ( !$updated ) {
				continue;
			}

			$db->update(
				'oathauth_users',
				[
					'data' => FormatJson::encode( $data )
				],
				[ 'id' => $row->id ],
				__METHOD__
			);
		}

		return true;
	}

}
