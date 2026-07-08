<?php

namespace MediaWiki\Extension\CheckUser\Maintenance;

use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\CheckUser\Services\CheckUserInsert;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;

// @codeCoverageIgnoreStart
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname( __DIR__, 3 );
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class PopulateUserAgentTable extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CheckUser' );
		$this->addDescription(
			'Populates the cu_useragent table, and populates the agent_id column in the ' .
			'CheckUser result tables with references to cu_useragent'
		);
		$this->addOption(
			'sleep',
			'Sleep time (in seconds) between every batch. Default: 0',
			false,
			true
		);
		$this->setBatchSize( 200 );
	}

	/** @inheritDoc */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/** @inheritDoc */
	public function execute() {
		// If the CheckUserInsert service is not defined, we cannot run the script as we require
		// the service. This happens during install.php and when update.php is run the service
		// should be defined (at which point we can run the script to mark it as completed).
		if ( !$this->getServiceContainer()->has( 'CheckUserInsert' ) ) {
			return true;
		}

		return parent::execute();
	}

	/** @inheritDoc */
	public function doDBUpdates() {
		$dbr = $this->getReplicaDB();
		$dbw = $this->getPrimaryDB();

		$mainLb = $this->getServiceContainer()->getDBLoadBalancerFactory()->getMainLB();
		$maintainableDbw = $mainLb->getMaintenanceConnectionRef( DB_PRIMARY );

		if ( !$maintainableDbw->fieldExists( 'cu_private_event', 'cupe_agent', __METHOD__ ) ) {
			$this->output(
				"The cupe_agent field does not exist in cu_private_event which is needed for the migration.\n"
			);
			return true;
		}

		/** @var CheckUserInsert $checkUserInsert */
		$checkUserInsert = $this->getServiceContainer()->get( 'CheckUserInsert' );

		foreach ( CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX as $table => $prefix ) {
			$userAgentIdColumn = $prefix . 'agent_id';
			$userAgentColumn = $prefix . 'agent';
			$idColumn = $prefix . 'id';

			$this->output( "Populating $userAgentIdColumn in $table\n" );

			$count = 0;
			do {
				// Get a batch of rows which have their agent_id column as 0 (and so need populating)
				$batchOfRowsToUpdate = $dbr->newSelectQueryBuilder()
					->select( [ $idColumn, $userAgentColumn ] )
					->from( $table )
					->where( [ $userAgentIdColumn => 0 ] )
					->limit( $this->getBatchSize() ?? 200 )
					->caller( __METHOD__ )
					->fetchResultSet();

				$userAgentValueToUserAgentId = [];
				$userAgentValueToRowIds = [];
				foreach ( $batchOfRowsToUpdate as $row ) {
					$userAgent = $row->$userAgentColumn ?? '';

					// Acquire cu_useragent table row IDs for each distinct value
					// of the user agent column within the batch
					if ( !array_key_exists( $userAgent, $userAgentValueToUserAgentId ) ) {
						$userAgentTableId = $checkUserInsert->acquireUserAgentTableId(
							$userAgent
						);
						$userAgentValueToUserAgentId[$userAgent] = $userAgentTableId;
					}

					// Group the row IDs by their user agent so that the rows
					// with the same user agent can be updated at the same time
					if ( !array_key_exists( $userAgent, $userAgentValueToRowIds ) ) {
						$userAgentValueToRowIds[$userAgent] = [];
					}
					$userAgentValueToRowIds[$userAgent][] = $row->$idColumn;
				}

				// Populate the agent_id column with the generated value for all
				// the rows in the batch
				foreach ( $userAgentValueToRowIds as $agentValue => $rowIds ) {
					$dbw->newUpdateQueryBuilder()
						->update( $table )
						// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
						->set( [ $userAgentIdColumn => $userAgentValueToUserAgentId[$agentValue] ] )
						->where( [ $idColumn => $rowIds ] )
						->caller( __METHOD__ )
						->execute();
					$count += $dbw->affectedRows();
				}

				if ( $batchOfRowsToUpdate->numRows() !== 0 ) {
					$this->output( "... $count rows populated\n" );
				}

				sleep( intval( $this->getOption( 'sleep', 0 ) ) );
				$this->waitForReplication();
			} while ( $batchOfRowsToUpdate->numRows() !== 0 );

			$this->output( "Done. Populated $count rows.\n" );
		}

		return true;
	}
}

// @codeCoverageIgnoreStart
$maintClass = PopulateUserAgentTable::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
