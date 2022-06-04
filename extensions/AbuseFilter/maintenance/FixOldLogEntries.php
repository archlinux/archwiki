<?php

namespace MediaWiki\Extension\AbuseFilter\Maintenance;

use LoggedUpdateMaintenance;

// @codeCoverageIgnoreStart
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Fix old log entries with log_type = 'abusefilter' where log_params are imploded with '\n'
 * instead of "\n" (using single quotes), which causes a broken display.
 * This was caused by the addMissingLoggingEntries script creating broken entries, see T208931
 * and T228655.
 * It also fixes a problem which caused addMissingLoggingEntries to insert duplicate rows foreach
 * non-legacy entries
 *
 * @codeCoverageIgnore
 * No need to cover: old, single-use script.
 */
class FixOldLogEntries extends LoggedUpdateMaintenance {
	/** @var bool */
	private $dryRun;

	/** @var int Amount of rows in the logging table */
	private $loggingRowsCount;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Fix old rows in logging which hold broken log_params' );

		$this->addOption( 'verbose', 'Print some more debug info' );
		$this->addOption( 'dry-run', 'Perform a dry run' );
		$this->requireExtension( 'Abuse Filter' );
		$this->setBatchSize( 500 );
	}

	/**
	 * @inheritDoc
	 */
	public function getUpdateKey() {
		return 'FixOldLogEntries';
	}

	/**
	 * This method will delete duplicated logging rows created by addMissingLoggingEntries. This
	 * happened because the script couldn't recognize non-legacy entries, and considered them to be
	 * absent if the script was ran after the format update. See T228655#5360754 and T228655#5408193
	 *
	 * @return int[] The IDs of the affected rows
	 */
	private function deleteDuplicatedRows() {
		$dbr = wfGetDB( DB_REPLICA, 'vslow' );
		$dbw = wfGetDB( DB_PRIMARY );
		$newFormatLike = $dbr->buildLike( $dbr->anyString(), 'historyId', $dbr->anyString() );
		$batchSize = $this->getBatchSize();
		$prevID = 0;
		$curID = $batchSize;
		$ret = [];

		// Select all non-legacy entries
		do {
			$res = $dbr->selectFieldValues(
				'logging',
				'log_params',
				[
					'log_type' => 'abusefilter',
					"log_params $newFormatLike",
					"log_id > $prevID",
					"log_id <= $curID",
				],
				__METHOD__
			);
			$prevID = $curID;
			$curID += $batchSize;

			if ( !$res ) {
				continue;
			}

			$legacyParams = [];
			foreach ( $res as $logParams ) {
				$params = unserialize( $logParams );
				if ( $params === false ) {
					// Sanity check
					$this->fatalError( __METHOD__ . ": Cannot unserialize $logParams" );
				}
				// The script always inserted duplicates with the wrong '\n'
				$legacyParams[] = $params['historyId'] . '\n' . $params['newId'];
			}

			// Save the IDs for later. Note: it is guaranteed that we'll get all and only the entries
			// that we want, because they contain a reference to afh_id, which is unique.
			$deleteIDs = $dbr->selectFieldValues(
				'logging',
				'log_id',
				[
					'log_type' => 'abusefilter',
					'log_params' => $legacyParams
				],
				__METHOD__
			);
			$ret = array_merge( $ret, $deleteIDs );

			if ( !$this->dryRun && $deleteIDs ) {
				// Note that we delete entries with legacy format, which are the ones erroneously inserted
				// by the script.
				$dbw->delete(
					'logging',
					[ 'log_id' => $deleteIDs ],
					__METHOD__
				);
			}
		} while ( $prevID <= $this->loggingRowsCount );

		return $ret;
	}

	/**
	 * Change single-quote newlines to double-quotes newlines
	 *
	 * @param int[] $deleted log_id's that deleteDuplicatedRows would delete/has deleted.
	 *  This is used in dry-run to avoid reporting them twice.
	 * @return int[] Affected log_id's
	 */
	private function changeNewlineType( array $deleted ) {
		$dbr = wfGetDB( DB_REPLICA, 'vslow' );
		$dbw = wfGetDB( DB_PRIMARY );
		$batchSize = $this->getBatchSize();
		$prevID = 1;
		$curID = $batchSize;
		$ret = [];

		$likeClause = $dbr->buildLike(
			$dbr->anyString(),
			'\n',
			$dbr->anyString()
		);
		$replaceClause = $dbw->strreplace(
			'log_params',
			$dbw->addQuotes( '\n' ),
			$dbw->addQuotes( "\n" )
		);
		// Don't pass an empty array to makeList
		$extraConds = $this->dryRun && $deleted ?
			[ 'log_id NOT IN (' . $dbr->makeList( $deleted ) . ')' ] :
			[];
		do {
			$ids = $dbr->selectFieldValues(
				'logging',
				'log_id',
				array_merge(
					[
						'log_type' => 'abusefilter',
						'log_params ' . $likeClause,
						"log_id >= $prevID",
						"log_id <= $curID",
					],
					$extraConds
				),
				__METHOD__
			);
			$prevID = $curID + 1;
			$curID += $batchSize;

			if ( !$this->dryRun && $ids ) {
				// Keep the entries legacy
				$dbw->update(
					'logging',
					[ 'log_params = ' . $replaceClause ],
					[ 'log_id' => $ids ],
					__METHOD__
				);
			}
			$ret = array_merge( $ret, $ids );
		} while ( $prevID <= $this->loggingRowsCount );
		return $ret;
	}

	/**
	 * Fix other minor errors caused by addMissingLoggingEntries, and align the values to the
	 * ones inserted by the actual code. Namely:
	 * - Set log_page to 0 instead of NULL
	 * - Don't set log_deleted based on afh_deleted (note: this will miss any log entry created
	 *   by addMissingLoggingEntries and manually deleted afterwards. However, there shouldn't
	 *   be any reason to delete these entries)
	 *
	 * @param int[] $deleted Same as self::changeNewlineType
	 * @return int[]
	 */
	private function updateLoggingFields( array $deleted ) {
		$dbr = wfGetDB( DB_REPLICA, 'vslow' );
		$dbw = wfGetDB( DB_PRIMARY );
		$batchSize = $this->getBatchSize();
		$prevID = 1;
		$curID = $batchSize;
		$ret = [];

		// Don't pass an empty array to makeList
		$extraConds = $this->dryRun && $deleted ?
			[ 'log_id NOT IN (' . $dbr->makeList( $deleted ) . ')' ] :
			[];
		do {
			// 'log_page IS NULL' is guaranteed to return all and only the entries created by the script
			$legacyIDs = $dbr->selectFieldValues(
				'logging',
				'log_id',
				array_merge(
					[
						'log_type' => 'abusefilter',
						'log_page IS NULL',
						"log_id >= $prevID",
						"log_id <= $curID",
					],
					$extraConds
				),
				__METHOD__
			);
			$prevID = $curID + 1;
			$curID += $batchSize;

			if ( !$this->dryRun && $legacyIDs ) {
				$dbw->update(
					'logging',
					[
						'log_page' => 0,
						'log_deleted' => 0
					],
					[ 'log_id' => $legacyIDs ],
					__METHOD__
				);
			}
			$ret = array_merge( $ret, $legacyIDs );
		} while ( $prevID <= $this->loggingRowsCount );
		return $ret;
	}

	/**
	 * @inheritDoc
	 */
	public function doDBUpdates() {
		$this->dryRun = $this->hasOption( 'dry-run' );
		$this->loggingRowsCount = (int)wfGetDB( DB_REPLICA )->selectField(
			'logging',
			'MAX(log_id)',
			[],
			__METHOD__
		);

		$deleted = $this->deleteDuplicatedRows();

		$deleteVerb = $this->dryRun ? 'would delete' : 'deleted';
		$numDel = count( $deleted );
		$this->output(
			__CLASS__ . " $deleteVerb $numDel rows.\n"
		);
		if ( $deleted && $this->hasOption( 'verbose' ) ) {
			$this->output( 'The affected log_id\'s are: ' . implode( ', ', $deleted ) . "\n" );
		}

		$updatedNewLines = $this->changeNewlineType( $deleted );
		$updatedFields = $this->updateLoggingFields( $deleted );
		$updateVerb = $this->dryRun ? 'would update' : 'updated';

		$numNewLine = count( $updatedNewLines );
		$this->output(
			__CLASS__ . " $updateVerb newlines for $numNewLine rows.\n"
		);
		if ( $updatedNewLines && $this->hasOption( 'verbose' ) ) {
			$this->output( 'The affected log_id\'s are: ' . implode( ', ', $updatedNewLines ) . "\n" );
		}

		$numFields = count( $updatedFields );
		$this->output(
			__CLASS__ . " $updateVerb fields for $numFields rows.\n"
		);
		if ( $updatedFields && $this->hasOption( 'verbose' ) ) {
			$this->output( 'The affected log_id\'s are: ' . implode( ', ', $updatedFields ) . "\n" );
		}

		return !$this->dryRun;
	}
}

$maintClass = FixOldLogEntries::class;
require_once RUN_MAINTENANCE_IF_MAIN;
