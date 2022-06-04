<?php

namespace MediaWiki\Extension\OATHAuth\Hook\LoadExtensionSchemaUpdates;

use ConfigException;
use DatabaseUpdater;
use FormatJson;
use MediaWiki\MediaWikiServices;
use Wikimedia;
use Wikimedia\AtEase\AtEase;
use Wikimedia\Rdbms\IDatabase;

class UpdateTables {
	/**
	 * @var DatabaseUpdater
	 */
	protected $updater;

	/**
	 * @var string
	 */
	protected $base;

	/**
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function callback( $updater ) {
		$dir = dirname( __DIR__, 3 );
		$handler = new static( $updater, $dir );
		return $handler->execute();
	}

	/**
	 * @param DatabaseUpdater $updater
	 * @param string $base
	 */
	protected function __construct( $updater, $base ) {
		$this->updater = $updater;
		$this->base = $base;
	}

	protected function execute() {
		$type = $this->updater->getDB()->getType();
		$typePath = "{$this->base}/sql/{$type}";

		$this->updater->addExtensionTable(
			'oathauth_users',
			"$typePath/tables-generated.sql"
		);

		switch ( $type ) {
			case 'mysql':
			case 'sqlite':
				$this->updater->addExtensionUpdate( [ [ $this, 'schemaUpdateOldUsersFromInstaller' ] ] );
				$this->updater->dropExtensionField(
					'oathauth_users',
					'secret_reset',
					"{$this->base}/sql/mysql/patch-remove_reset.sql"
				);

				$this->updater->addExtensionField(
					'oathauth_users',
					'module',
					"$typePath/patch-add_generic_fields.sql"
				);

				$this->updater->addExtensionUpdate(
					[ [ __CLASS__, 'schemaUpdateSubstituteForGenericFields' ] ]
				);
				$this->updater->dropExtensionField(
					'oathauth_users',
					'secret',
					"$typePath/patch-remove_module_specific_fields.sql"
				);

				$this->updater->addExtensionUpdate(
					[ [ __CLASS__, 'schemaUpdateTOTPToMultipleKeys' ] ]
				);

				$this->updater->addExtensionUpdate(
					[ [ __CLASS__, 'schemaUpdateTOTPScratchTokensToArray' ] ]
				);

				break;

			case 'postgres':
				$this->updater->modifyExtensionTable(
					'oathauth_users',
					"$typePath/patch-oathauth_users-drop-oathauth_users_id_seq.sql"
				);
				$this->updater->modifyExtensionField(
					'oathauth_users',
					'id',
					"$typePath/patch-oathauth_users-drop-id-nextval.sql"
				);
				break;
		}

		return true;
	}

	/**
	 * @return Wikimedia\Rdbms\DBConnRef
	 */
	private static function getDatabase() {
		global $wgOATHAuthDatabase;
		// Global can be `null` during installation, ensure we pass `false` instead (T270147)
		$database = $wgOATHAuthDatabase ?? false;
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getMainLB( $database );
		return $lb->getConnectionRef( DB_PRIMARY, [], $database );
	}

	/**
	 * Helper function for converting old users to the new schema
	 *
	 * @param DatabaseUpdater $updater
	 *
	 * @return bool
	 */
	public function schemaUpdateOldUsersFromInstaller( DatabaseUpdater $updater ) {
		return self::schemaUpdateOldUsers( self::getDatabase() );
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
	 * @param IDatabase $db
	 * @return bool
	 * @throws ConfigException
	 */
	public static function convertToGenericFields( IDatabase $db ) {
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
	 * @param IDatabase $db
	 * @return bool
	 * @throws ConfigException
	 */
	public static function switchTOTPToMultipleKeys( IDatabase $db ) {
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
	 * @param IDatabase $db
	 * @return bool
	 * @throws ConfigException
	 */
	public static function switchTOTPScratchTokensToArray( IDatabase $db ) {
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

	/**
	 * Helper function for converting old users to the new schema
	 *
	 * @param IDatabase $db
	 * @return bool
	 */
	public static function schemaUpdateOldUsers( IDatabase $db ) {
		if ( !$db->fieldExists( 'oathauth_users', 'secret_reset', __METHOD__ ) ) {
			return true;
		}

		$res = $db->select(
			'oathauth_users',
			[ 'id', 'scratch_tokens' ],
			[ 'is_validated != 0' ],
			__METHOD__
		);

		foreach ( $res as $row ) {
			AtEase::suppressWarnings();
			$scratchTokens = unserialize( base64_decode( $row->scratch_tokens ) );
			AtEase::restoreWarnings();
			if ( $scratchTokens ) {
				$db->update(
					'oathauth_users',
					[ 'scratch_tokens' => implode( ',', $scratchTokens ) ],
					[ 'id' => $row->id ],
					__METHOD__
				);
			}
		}

		// Remove rows from the table where user never completed the setup process
		$db->delete( 'oathauth_users', [ 'is_validated' => 0 ], __METHOD__ );

		return true;
	}
}
