<?php

require_once __DIR__ . '/Maintenance.php';

/**
 * Maintenance script that cleans unused rows in linktarget table
 *
 * @ingroup Maintenance
 * @since 1.39
 */
class PruneUnusedLinkTargetRows extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Clean unused rows in linktarget table'
		);
		$this->addOption(
			'sleep',
			'Sleep time (in seconds) between every batch. Default: 0',
			false,
			true
		);
		$this->addOption( 'dry', 'Dry run', false );
		$this->addOption( 'start', 'Start after this lt_id', false, true );
		$this->setBatchSize( 50 );
	}

	public function execute() {
		$dbw = $this->getDB( DB_PRIMARY );
		$dbr = $this->getDB( DB_REPLICA );
		$maxLtId = (int)$dbr->newSelectQueryBuilder()
			->select( 'MAX(lt_id)' )
			->from( 'linktarget' )
			->fetchField();
		// To avoid race condition of newly added linktarget rows
		// being deleted before getting a chance to be used, let's ignore the newest ones.
		$maxLtId = min( [ $maxLtId - 1, (int)( $maxLtId * 0.99 ) ] );

		$ltCounter = (int)$this->getOption( 'start', 0 );

		$this->output( "Deleting unused linktarget rows...\n" );
		$deleted = 0;
		$linksMigration = \MediaWiki\MediaWikiServices::getInstance()->getLinksMigration();
		while ( $ltCounter < $maxLtId ) {
			$batchMaxLtId = min( $ltCounter + $this->getBatchSize(), $maxLtId ) + 1;
			$this->output( "Checking lt_id between $ltCounter and $batchMaxLtId...\n" );
			$queryBuilder = $dbr->newSelectQueryBuilder()
				->select( [ 'lt_id' ] )
				->from( 'linktarget' );
			$queryBuilder->where( [
				'lt_id < ' . $dbr->addQuotes( $batchMaxLtId ),
				'lt_id > ' . $dbr->addQuotes( $ltCounter )
			] );
			foreach ( $linksMigration::$mapping as $table => $tableData ) {
				$queryBuilder->leftJoin( $table, null, $tableData['target_id'] . '=lt_id' );
				$queryBuilder->andWhere( [
					$tableData['target_id'] => null
				] );
			}
			$ltIdsToDelete = $queryBuilder->caller( __METHOD__ )->fetchFieldValues();
			if ( !$ltIdsToDelete ) {
				$ltCounter += $this->getBatchSize();
				continue;
			}

			// Run against primary as well with a faster query plan, just to be safe.
			// Also having a bit of time in between helps in cases of immediate removal and insertion of use.
			$queryBuilder = $dbr->newSelectQueryBuilder()
				->select( [ 'lt_id' ] )
				->from( 'linktarget' )
				->where( [
					'lt_id' => $ltIdsToDelete,
				] );
			foreach ( $linksMigration::$mapping as $table => $tableData ) {
				$queryBuilder->leftJoin( $table, null, $tableData['target_id'] . '=lt_id' );
				$queryBuilder->andWhere( [
					$tableData['target_id'] => null
				] );
			}
			$ltIdsToDelete = $queryBuilder->caller( __METHOD__ )->fetchFieldValues();
			if ( !$ltIdsToDelete ) {
				$ltCounter += $this->getBatchSize();
				continue;
			}

			if ( !$this->getOption( 'dry' ) ) {
				$dbw->delete( 'linktarget', [ 'lt_id' => $ltIdsToDelete ], __METHOD__ );
			}
			$deleted += count( $ltIdsToDelete );
			$ltCounter += $this->getBatchSize();

			// Sleep between batches for replication to catch up
			$this->waitForReplication();
			$sleep = (int)$this->getOption( 'sleep', 0 );
			if ( $sleep > 0 ) {
				sleep( $sleep );
			}
		}

		$this->output(
			"Completed clean up linktarget table, "
			. "$deleted rows deleted.\n"
		);

		return true;
	}

}

$maintClass = PruneUnusedLinkTargetRows::class;
require_once RUN_MAINTENANCE_IF_MAIN;
