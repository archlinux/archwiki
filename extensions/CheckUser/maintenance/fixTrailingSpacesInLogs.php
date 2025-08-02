<?php

namespace MediaWiki\CheckUser\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script for fixing trailing spaces issue in cu_log (see T275704)
 */
class FixTrailingSpacesInLogs extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CheckUser' );
		$this->addDescription( 'Remove trailing spaces from all cu_log entries, if there are any' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return 'CheckUserFixTrailingSpacesInLogs';
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$dbr = $this->getReplicaDB();
		$dbw = $this->getPrimaryDB();
		$batchSize = $this->getBatchSize();

		$this->output( "Removing trailing spaces from cu_log entries...\n" );

		$maxId = $dbr->newSelectQueryBuilder()
			->field( 'MAX(cul_id)' )
			->table( 'cu_log' )
			->caller( __METHOD__ )
			->fetchField();

		if ( !$maxId ) {
			$this->output( "cu_log is empty; nothing to process.\n" );
			return true;
		}

		$prevId = 0;
		$curId = $batchSize;
		do {
			$dbw->newUpdateQueryBuilder()
				->update( 'cu_log' )
				->set( [ 'cul_target_text = TRIM(cul_target_text)' ] )
				->where( [
					$dbw->expr( 'cul_id', '>', $prevId ),
					$dbw->expr( 'cul_id', '<=', $curId )
				] )
				->caller( __METHOD__ )
				->execute();
			$this->waitForReplication();

			$this->output( "Processed $batchSize rows out of $maxId.\n" );
			$prevId = $curId;
			$curId += $batchSize;
		} while ( $prevId <= $maxId );

		return true;
	}
}

$maintClass = FixTrailingSpacesInLogs::class;
require_once RUN_MAINTENANCE_IF_MAIN;
