<?php

namespace MediaWiki\Extension\AbuseFilter\Maintenance;

// @codeCoverageIgnoreStart
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use Maintenance;
use MediaWiki\MediaWikiServices;
use MWTimestamp;

class PurgeOldLogIPData extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Purge old IP Address data from AbuseFilter logs' );
		$this->setBatchSize( 200 );

		$this->requireExtension( 'Abuse Filter' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->output( "Purging old IP Address data from abuse_filter_log...\n" );
		$dbw = wfGetDB( DB_PRIMARY );
		$factory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$cutoffUnix = (int)MWTimestamp::now( TS_UNIX ) - $this->getConfig()->get( 'AbuseFilterLogIPMaxAge' );

		$count = 0;
		do {
			$ids = $dbw->selectFieldValues(
				'abuse_filter_log',
				'afl_id',
				[
					'afl_ip <> ""',
					"afl_timestamp < " . $dbw->addQuotes( $dbw->timestamp( $cutoffUnix ) )
				],
				__METHOD__,
				[ 'LIMIT' => $this->getBatchSize() ]
			);

			if ( $ids ) {
				$dbw->update(
					'abuse_filter_log',
					[ 'afl_ip' => '' ],
					[ 'afl_id' => $ids ],
					__METHOD__
				);
				$count += $dbw->affectedRows();
				$this->output( "$count\n" );

				$factory->waitForReplication();
			}
		} while ( count( $ids ) >= $this->getBatchSize() );

		$this->output( "$count rows.\n" );

		$this->output( "Done.\n" );
	}

}

$maintClass = PurgeOldLogIPData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
