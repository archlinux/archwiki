<?php

namespace MediaWiki\Extension\AbuseFilter\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use Wikimedia\IPUtils;

/**
 * Populates the afl_ip_hex column in the abuse_filter_log using the values from afl_ip
 *
 * @since 1.45
 */
class PopulateAbuseFilterLogIPHex extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Abuse Filter' );
		$this->addDescription(
			'Populates the afl_ip_hex column in the abuse_filter_log table using the value of the afl_ip column.'
		);
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
	public function doDBUpdates() {
		$this->output( "Populating afl_ip_hex in abuse_filter_log with value from afl_ip...\n" );

		$mainLb = $this->getServiceContainer()->getDBLoadBalancerFactory()->getMainLB();
		$maintainableDb = $mainLb->getMaintenanceConnectionRef( DB_PRIMARY );
		if ( !$maintainableDb->fieldExists( 'abuse_filter_log', 'afl_ip' ) ) {
			$this->output( "Nothing to do as afl_ip does not exist in abuse_filter_log.\n" );
			return true;
		}

		$dbr = $this->getReplicaDB();
		$dbw = $this->getPrimaryDB();

		$count = 0;
		do {
			$rowsToMigrate = $dbr->newSelectQueryBuilder()
				->select( [ 'afl_id', 'afl_ip' ] )
				->from( 'abuse_filter_log' )
				->where( [
					$dbr->expr( 'afl_ip', '!=', '' ),
					$dbr->expr( 'afl_ip_hex', '=', '' )
				] )
				->limit( $this->getBatchSize() )
				->caller( __METHOD__ )
				->fetchResultSet();

			if ( $rowsToMigrate->count() ) {
				foreach ( $rowsToMigrate as $rowToMigrate ) {
					$dbw->newUpdateQueryBuilder()
						->update( 'abuse_filter_log' )
						->set( [ 'afl_ip_hex' => IPUtils::toHex( $rowToMigrate->afl_ip ) ] )
						->where( [ 'afl_id' => $rowToMigrate->afl_id ] )
						->caller( __METHOD__ )
						->execute();
					$count += $dbw->affectedRows();
				}
				$this->output( "... $count\n" );

				sleep( intval( $this->getOption( 'sleep', 0 ) ) );
				$this->waitForReplication();
			}
		} while ( $rowsToMigrate->count() >= $this->getBatchSize() );

		$this->output( "Done. Migrated $count rows.\n" );
		return true;
	}
}

// @codeCoverageIgnoreStart
$maintClass = PopulateAbuseFilterLogIPHex::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
