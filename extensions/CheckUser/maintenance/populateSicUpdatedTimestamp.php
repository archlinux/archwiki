<?php

namespace MediaWiki\Extension\CheckUser\Maintenance;

use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use Wikimedia\Rdbms\RawSQLValue;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Maintenance script that populates the `sic_updated_timestamp` column in the `cusi_case` table.
 */
class PopulateSicUpdatedTimestamp extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CheckUser' );
		$this->addDescription( 'Populate the sic_updated_timestamp column in cusi_case.' );
		$this->addOption(
			'sleep',
			'Sleep time (in seconds) between every batch. Default: 0',
			false,
			true
		);
	}

	/** @inheritDoc */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/** @inheritDoc */
	protected function doDBUpdates() {
		$this->output( "Populating sic_updated_timestamp in cusi_case...\n" );

		// If suggested investigations is not enabled, then return early. We cannot do this check if
		// this is being run by install.php as config is not fully available there.
		// If being run by install.php, the cusi_case table should exist and so we should just continue.
		if (
			$this->getConfig()->has( 'CheckUserSuggestedInvestigationsEnabled' ) &&
			!$this->getConfig()->get( 'CheckUserSuggestedInvestigationsEnabled' )
		) {
			$this->output( "Nothing to do as CheckUser Suggested Investigations is not enabled.\n" );
			return true;
		}

		$dbProvider = $this->getServiceContainer()->getConnectionProvider();
		$dbw = $dbProvider->getPrimaryDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );
		$dbr = $dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		$rowsToUpdateExpr = $dbr->expr( 'sic_updated_timestamp', '=', null );
		if ( $dbr->getType() !== 'postgres' ) {
			// During a few non-release branches of 1.46, the default of sic_updated_timestamp
			// was an empty string so we should check for that as well. Postgres DBs do not
			// allow empty strings, so this was never an issue for postgres.
			$rowsToUpdateExpr = $rowsToUpdateExpr->or(
				'sic_updated_timestamp',
				'=',
				// Because MySQL / MariaDB uses a BINARY field, we need to cast the
				// string to BINARY so that it's padded to 14 bytes
				$dbr->getType() === 'mysql' ? new RawSQLValue( "CAST('' AS BINARY(14))" ) : ''
			);
		}

		$count = 0;
		do {
			$batchOfRows = $dbr->newSelectQueryBuilder()
				->select( [ 'sic_id', 'sic_created_timestamp' ] )
				->from( 'cusi_case' )
				->where( $rowsToUpdateExpr )
				->limit( $this->getBatchSize() ?? 200 )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $batchOfRows as $row ) {
				$dbw->newUpdateQueryBuilder()
					->update( 'cusi_case' )
					->set( [ 'sic_updated_timestamp' => $row->sic_created_timestamp ] )
					->where( [ 'sic_id' => $row->sic_id ] )
					->caller( __METHOD__ )
					->execute();
				$count += $dbw->affectedRows();
			}
			$this->output( "... $count rows populated\n" );

			sleep( intval( $this->getOption( 'sleep', 0 ) ) );
			$this->waitForReplication();
		} while ( $batchOfRows->numRows() > 0 );

		$this->output( "Done. Populated $count rows.\n" );
		return true;
	}
}

// @codeCoverageIgnoreStart
$maintClass = PopulateSicUpdatedTimestamp::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
