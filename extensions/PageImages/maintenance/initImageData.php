<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Title\Title;
use PageImages\Job\InitImageDataJob;

/**
 * @license WTFPL
 * @author Max Semenik
 */
class InitImageData extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Initializes PageImages data' );
		$this->addOption( 'namespaces',
			'Comma-separated list of namespace(s) to refresh', false, true );
		$this->addOption( 'earlier-than',
			'Run only on pages touched earlier than this timestamp', false, true );
		$this->addOption( 'later-than',
			'Run only on pages touched later than this timestamp', false, true );
		$this->addOption( 'start', 'Starting page ID', false, true );
		$this->addOption( 'queue-pressure', 'Maximum number of jobs to enqueue at a time. ' .
			'If not provided or 0 will be run in-process.', false, true );
		$this->addOption( 'quiet', "Don't report on job queue pressure" );
		$this->setBatchSize( 100 );

		$this->requireExtension( 'PageImages' );
	}

	/**
	 * Do the actual work of filling out page images
	 */
	public function execute() {
		$lastId = $this->getOption( 'start', 0 );
		$isQuiet = $this->getOption( 'quiet', false );
		$queue = null;
		$maxPressure = $this->getOption( 'queue-pressure', 0 );
		if ( $maxPressure > 0 ) {
			$queue = $this->getServiceContainer()->getJobQueueGroup();
		}

		do {
			$dbr = $this->getServiceContainer()->getDBLoadBalancerFactory()
				->getReplicaDatabase();
			$queryBuilder = $dbr->newSelectQueryBuilder()
				->select( 'page_id' )
				->from( 'page' )
				->leftJoin( 'imagelinks', null, 'page_id = il_from' )
				->where( [
					$dbr->expr( 'page_id', '>', (int)$lastId ),
					$dbr->expr( 'il_from', '!=', null ),
					'page_is_redirect' => 0,
				] )
				->orderBy( 'page_id' )
				->groupBy( 'page_id' )
				->limit( $this->mBatchSize )
				->caller( __METHOD__ );
			if ( $this->hasOption( 'namespaces' ) ) {
				$ns = explode( ',', $this->getOption( 'namespaces' ) );
				$queryBuilder->andWhere( [ 'page_namespace' => $ns ] );
			} else {
				$queryBuilder->andWhere( [
					'page_namespace' => $this->getServiceContainer()->getMainConfig()->get( 'PageImagesNamespaces' )
				] );
			}
			if ( $this->hasOption( 'earlier-than' ) ) {
				$queryBuilder->andWhere(
					$dbr->expr( 'page_touched', '<', $dbr->timestamp( $this->getOption( 'earlier-than' ) ) )
				);
			}
			if ( $this->hasOption( 'later-than' ) ) {
				$queryBuilder->andWhere(
					$dbr->expr( 'page_touched', '>', $dbr->timestamp( $this->getOption( 'later-than' ) ) )
				);
			}
			$pageIds = $queryBuilder->fetchFieldValues();
			$job = new InitImageDataJob(
				Title::newMainPage(),
				[ 'page_ids' => $pageIds ],
				$this->getServiceContainer()->getDBLoadBalancerFactory()
			);
			if ( $queue === null ) {
				$job->run();
			} else {
				$queue->push( $job );
				$this->waitForMaxPressure( $queue, $maxPressure, $isQuiet );
			}
			$lastId = end( $pageIds );
			$this->output( "$lastId\n" );
		} while ( $pageIds );
		$this->output( "done\n" );
	}

	/**
	 * @param JobQueueGroup $queue The job queue to fetch pressure from
	 * @param int $maxPressure The maximum number of queued + active
	 *  jobs that can exist when returning
	 * @param bool $isQuiet When false report on job queue pressure every 10s
	 */
	private function waitForMaxPressure( JobQueueGroup $queue, $maxPressure, $isQuiet ) {
		$group = $queue->get( 'InitImageDataJob' );
		$i = 0;
		do {
			sleep( 1 );
			$queued = $group->getSize();
			$running = $group->getAcquiredCount();
			$abandoned = $group->getAbandonedCount();

			if ( !$isQuiet && ++$i % 10 === 0 ) {
				$now = date( 'Y-m-d H:i:s T' );
				$this->output( "[$now] Queued: $queued Running: $running " .
					"Abandoned: $abandoned Max: $maxPressure\n" );
			}
		} while ( $queued + $running - $abandoned >= $maxPressure );
	}
}

$maintClass = InitImageData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
