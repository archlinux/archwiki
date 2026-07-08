<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Maintenance;

use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForCaseJob;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Queues auto-close jobs for all existing open Suggested Investigations cases.
 * This is a one-time backfill for cases created before auto-close was introduced.
 */
class QueueAutoCloseSICases extends LoggedUpdateMaintenance {

	private IConnectionProvider $dbProvider;
	private int $queued = 0;

	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Queue auto-close jobs for all existing open Suggested Investigations cases.'
		);
		$this->setBatchSize( 100 );
		$this->requireExtension( 'CheckUser' );
	}

	/** @inheritDoc */
	protected function getUpdateKey(): string {
		return __CLASS__;
	}

	/** @inheritDoc */
	protected function doDBUpdates(): bool {
		$this->output( 'Queuing auto-close jobs for open Suggested Investigations cases...' . PHP_EOL );

		if (
			$this->getConfig()->has( 'CheckUserSuggestedInvestigationsEnabled' ) &&
			!$this->getConfig()->get( 'CheckUserSuggestedInvestigationsEnabled' )
		) {
			$this->output( 'Nothing to do as CheckUser Suggested Investigations is not enabled.' . PHP_EOL );

			return true;
		}

		$this->dbProvider = $this->getServiceContainer()->getConnectionProvider();

		$lastId = 0;
		do {
			$caseIds = $this->getNextOpenCaseBatch( $lastId );

			$this->beginTransactionRound( __METHOD__ );
			$this->queueJobsForCases( $caseIds );

			if ( $caseIds !== [] ) {
				$lastId = (int)end( $caseIds );
				$this->output( "Processed up to case ID $lastId, queued {$this->queued} jobs total" . PHP_EOL );
			}

			$this->commitTransactionRound( __METHOD__ );
		} while ( count( $caseIds ) === $this->mBatchSize );

		$this->output( "Done. Queued $this->queued auto-close job(s)." . PHP_EOL );

		return true;
	}

	/** @return int[] */
	private function getNextOpenCaseBatch( int $lastId ): array {
		$dbr = $this->dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		$ids = $dbr->newSelectQueryBuilder()
			->select( 'sic_id' )
			->from( 'cusi_case' )
			->where( [
				'sic_status' => CaseStatus::Open->value,
				$dbr->expr( 'sic_id', '>', $lastId ),
			] )
			->orderBy( 'sic_id', SelectQueryBuilder::SORT_ASC )
			->limit( $this->mBatchSize )
			->caller( __METHOD__ )
			->fetchFieldValues();

		return array_map( 'intval', $ids );
	}

	/** @param int[] $caseIds */
	private function queueJobsForCases( array $caseIds ): void {
		if ( $caseIds === [] ) {
			return;
		}

		$jobs = array_map(
			static fn ( int $id ) => SuggestedInvestigationsAutoCloseForCaseJob::newSpec( $id, false ),
			$caseIds
		);

		$this->getServiceContainer()->getJobQueueGroup()->push( $jobs );

		$this->queued += count( $jobs );
	}
}

// @codeCoverageIgnoreStart
$maintClass = QueueAutoCloseSICases::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
