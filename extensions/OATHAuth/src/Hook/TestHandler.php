<?php

namespace MediaWiki\Extension\OATHAuth\Hook;

use MediaWiki\Hook\UnitTestsAfterDatabaseSetupHook;
use MediaWiki\Hook\UnitTestsBeforeDatabaseTeardownHook;
use Wikimedia\Rdbms\ILoadBalancer;

class TestHandler implements
	UnitTestsAfterDatabaseSetupHook,
	UnitTestsBeforeDatabaseTeardownHook
{

	public function __construct(
		private readonly ILoadBalancer $loadBalancer,
	) {
	}

	/**
	 * If OATHAuth uses a different DB from the wiki default, create the tables in that DB.
	 * Largely follows UnitTestsHookHandler in CentralAuth.
	 * @inheritDoc
	 */
	public function onUnitTestsAfterDatabaseSetup( $db, $prefix ) {
		$originalPrefix = $db->tablePrefix();
		$db->tablePrefix( $prefix );
		if ( !$db->tableExists( 'oathauth_types', __METHOD__ ) ) {
			$engine = $db->getType();
			$db->sourceFile( __DIR__ . "/../../sql/$engine/tables-generated.sql" );
		}
		$db->tablePrefix( $originalPrefix );
	}

	public function onUnitTestsBeforeDatabaseTeardown() {
		$schema = json_decode( file_get_contents( __DIR__ . '/../../sql/tables.json' ), true );
		$tables = array_map( static fn ( $tableSchema ) => $tableSchema['name'], $schema );
		$dbw = $this->loadBalancer->getMaintenanceConnectionRef( DB_PRIMARY );
		foreach ( $tables as $table ) {
			$dbw->dropTable( $table );
		}
	}

}
