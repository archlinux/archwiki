<?php

namespace MediaWiki\Extension\AbuseFilter\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Utils\MWTimestamp;

class PurgeOldLogIPData extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Purge old IP address data from the abuse_filter_log table' );
		$this->setBatchSize( 200 );

		$this->requireExtension( 'Abuse Filter' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->output( "Purging old data from abuse_filter_log...\n" );
		$dbw = $this->getDB( DB_PRIMARY );
		$cutoffUnix = (int)MWTimestamp::now( TS_UNIX ) - $this->getConfig()->get( 'AbuseFilterLogIPMaxAge' );

		$count = 0;
		do {
			$ids = $dbw->newSelectQueryBuilder()
				->select( 'afl_id' )
				->from( 'abuse_filter_log' )
				->where( [
					$dbw->expr( 'afl_ip', '!=', '' ),
					$dbw->expr( 'afl_timestamp', '<', $dbw->timestamp( $cutoffUnix ) ),
				] )
				->limit( $this->getBatchSize() )
				->caller( __METHOD__ )
				->fetchFieldValues();

			if ( $ids ) {
				$dbw->newUpdateQueryBuilder()
					->update( 'abuse_filter_log' )
					->set( [ 'afl_ip' => '' ] )
					->where( [ 'afl_id' => $ids ] )
					->caller( __METHOD__ )
					->execute();
				$count += $dbw->affectedRows();
				$this->output( "$count\n" );

				$this->waitForReplication();
			}
		} while ( count( $ids ) >= $this->getBatchSize() );

		$this->output( "$count rows.\n" );

		$this->output( "Done.\n" );
	}

}

$maintClass = PurgeOldLogIPData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
