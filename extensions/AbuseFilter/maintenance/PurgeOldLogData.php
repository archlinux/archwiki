<?php

namespace MediaWiki\Extension\AbuseFilter\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Json\FormatJson;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Maintenance\Maintenance;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class PurgeOldLogData extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Purges sensitive data in the abuse_filter_log table. This purges the IP address in the' .
				'afl_ip_hex column as well as any protected variable values in afl_var_dump.'
		);
		$this->setBatchSize( 200 );

		$this->requireExtension( 'Abuse Filter' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->purgeIPs();
		$this->purgeProtectedVariables();
	}

	/**
	 * Purges IP addresses from the afl_ip_hex column if the row was created more than
	 * $wgAbuseFilterLogIPMaxAge seconds ago.
	 */
	private function purgeIPs(): void {
		$this->output( "Purging afl_ip_hex column in rows that are expired in abuse_filter_log...\n" );
		$dbr = $this->getReplicaDB();
		$dbw = $this->getPrimaryDB();
		$ipPurgeCutoff = ConvertibleTimestamp::time() - $this->getConfig()->get( 'AbuseFilterLogIPMaxAge' );

		$count = 0;
		do {
			$ids = $dbr->newSelectQueryBuilder()
				->select( 'afl_id' )
				->from( 'abuse_filter_log' )
				->where( [
					$dbr->expr( 'afl_ip_hex', '!=', '' ),
					$dbr->expr( 'afl_timestamp', '<', $dbr->timestamp( $ipPurgeCutoff ) ),
				] )
				->limit( $this->getBatchSize() )
				->caller( __METHOD__ )
				->fetchFieldValues();

			if ( $ids ) {
				$dbw->newUpdateQueryBuilder()
					->update( 'abuse_filter_log' )
					->set( [ 'afl_ip_hex' => '' ] )
					->where( [ 'afl_id' => $ids ] )
					->caller( __METHOD__ )
					->execute();
				$count += $dbw->affectedRows();
				$this->output( "... $count\n" );

				$this->waitForReplication();
			}
		} while ( count( $ids ) >= $this->getBatchSize() );

		$this->output( "Done. Purged $count IPs.\n" );
	}

	/**
	 * Purges protected variable values from the afl_var_dump column for rows where the log was created more than
	 * $wgAbuseFilterLogProtectedVariablesMaxAge seconds ago.
	 */
	private function purgeProtectedVariables(): void {
		// Return early if protected variable values are not purged.
		$protectedVariablesMaxAge = $this->getConfig()->get( 'AbuseFilterLogProtectedVariablesMaxAge' );
		if ( !$protectedVariablesMaxAge ) {
			return;
		}

		$this->output( "Purging protected variables from afl_var_dump...\n" );
		$dbr = $this->getReplicaDB();
		$dbw = $this->getPrimaryDB();
		$logger = LoggerFactory::getInstance( 'AbuseFilter' );
		$protectedVariablesPurgeCutoff = ConvertibleTimestamp::time() - $protectedVariablesMaxAge;

		$count = 0;
		$skippedIds = [];
		do {
			// Find a batch of rows where afl_var_dump contains JSON and therefore by extension protected variable
			// values. JSON will always start with "{", so look for that.
			$queryBuilder = $dbr->newSelectQueryBuilder()
				->select( [ 'afl_id', 'afl_var_dump' ] )
				->from( 'abuse_filter_log' )
				->where( [
					$dbr->expr( 'afl_var_dump', IExpression::LIKE, new LikeValue( '{', $dbr->anyString() ) ),
					$dbr->expr( 'afl_timestamp', '<', $dbr->timestamp( $protectedVariablesPurgeCutoff ) ),
				] );
			if ( count( $skippedIds ) > 0 ) {
				// Skip rows we found to contain invalid JSON, as these will not have been modified and reselecting
				// them could cause an infinite loop.
				$queryBuilder->where( $dbr->expr( 'afl_id', '!=', $skippedIds ) );
			}
			$rowsToProcess = $queryBuilder
				->caller( __METHOD__ )
				->limit( $this->getBatchSize() )
				->fetchResultSet();

			foreach ( $rowsToProcess as $row ) {
				// Convert the afl_var_dump JSON to a PHP array. If this fails log a warning and skip the row.
				$varDumpArray = FormatJson::decode( $row->afl_var_dump, true );
				if ( !$varDumpArray ) {
					$logger->warning(
						'Invalid JSON in afl_var_dump for row {afl_id}',
						[ 'afl_id' => $row->afl_id ]
					);
					$this->output(
						"Invalid JSON in afl_var_dump for row with ID $row->afl_id. Skipping this row.\n"
					);
					$skippedIds[] = $row->afl_id;
					continue;
				}

				// All values in the array except in the key '_blob' are protected variables that should be purged,
				// so replace the JSON array with the data in '_blob'.
				$dbw->newUpdateQueryBuilder()
					->update( 'abuse_filter_log' )
					->set( [ 'afl_var_dump' => $varDumpArray['_blob'] ] )
					->where( [ 'afl_id' => $row->afl_id ] )
					->caller( __METHOD__ )
					->execute();
				$count += $dbw->affectedRows();
			}
			$this->output( "... $count\n" );

			$this->waitForReplication();
		} while ( $rowsToProcess->numRows() >= $this->getBatchSize() );

		$this->output( "Done. Purged $count var dumps.\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = PurgeOldLogData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
