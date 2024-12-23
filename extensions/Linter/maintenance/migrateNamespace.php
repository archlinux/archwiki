<?php

namespace MediaWiki\Linter\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;

/**
 * Maintenance script that migrates the page table page_namespace field values
 * to the linter table linter_namespace field to improve linter search performance.
 * Note: The schema migration "patch-linter-add-namespace.json" is expected to have been done.
 * The extension now populates this new field by default. This script will migrate any data
 * in existing records to this new field.
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class MigrateNamespace extends LoggedUpdateMaintenance {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Linter' );
		$this->addDescription(
			'Copy the namespace data from the page table into the linter table'
		);
		$this->addOption(
			'sleep',
			'Sleep time (in seconds) between every batch. Default: 1 seconds',
			false,
			true
		);
		$this->setBatchSize( 1000 );
	}

	/**
	 * The Linter migrate namespace script can take a day to run using: --wiki enwiki
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$this->output( "Running linter migrate namespace function, this may take a while\n" );

		$batchSize = $this->getBatchSize();
		$sleep = (int)$this->getOption( 'sleep', 1 );

		$dbw = $this->getDB( DB_PRIMARY );
		if ( !$dbw->fieldExists( 'linter', 'linter_namespace', __METHOD__ ) ) {
			$this->output( "Run update.php to add linter_namespace field to the linter table.\n" );
			return false;
		}

		$this->output( "Migrating the page table page_namespace field to the linter table...\n" );

		$database = $this->getServiceContainer()->get( 'Linter.Database' );
		$updated = $database->migrateNamespace( $batchSize, $batchSize, $sleep );

		$this->output( "Completed migration of page_namespace data to the linter table, $updated rows updated.\n" );

		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return 'migrate namespace id from page to linter table';
	}
}

$maintClass = MigrateNamespace::class;
require_once RUN_MAINTENANCE_IF_MAIN;
