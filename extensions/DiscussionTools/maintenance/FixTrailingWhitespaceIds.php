<?php

namespace MediaWiki\Extension\DiscussionTools\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use Wikimedia\Rdbms\DBQueryError;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class FixTrailingWhitespaceIds extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Fix comment IDs with trailing whitespace' );
		$this->setBatchSize( 1000 );
		$this->requireExtension( 'DiscussionTools' );
	}

	/**
	 * @inheritDoc
	 */
	public function doDBUpdates() {
		$dbw = $this->getDB( DB_PRIMARY );

		$this->output( "Fixing DiscussionTools IDs with trailing whitespace..\n" );
		$total = 0;

		$skippedIds = [];

		do {
			// Match things that are possibly heading IDs with trailing underscores,
			// possibly followed by a timestamp.
			// As we are using LIKE there's a small chance of false positives, but
			// this will become no-ops as we use a stricter RegExp later.

			// Trailing underscore
			// _-%\_
			$like1 = new LikeValue( $dbw->anyChar(), '-', $dbw->anyString(), '_' );
			// Trailing underscore followed by short timestamp
			// _-%\_-2%00
			$like2 = new LikeValue( $dbw->anyChar(), '-', $dbw->anyString(), '_-2', $dbw->anyString(), '00' );
			// Trailing underscore followed by long timestamp
			// _-%\_-2%00.000Z
			$like3 = new LikeValue( $dbw->anyChar(), '-', $dbw->anyString(), '_-2', $dbw->anyString(), '00.000Z' );

			$itemIdQueryBuilder = $dbw->newSelectQueryBuilder()
				->from( 'discussiontools_item_ids' )
				->field( 'itid_itemid' )
				->where(
					$dbw->orExpr( [
						$dbw->expr( 'itid_itemid', IExpression::LIKE, $like1 ),
						$dbw->expr( 'itid_itemid', IExpression::LIKE, $like2 ),
						$dbw->expr( 'itid_itemid', IExpression::LIKE, $like3 ),
					] )
				)
				->caller( __METHOD__ )
				->limit( $this->getBatchSize() );

			if ( $skippedIds ) {
				$itemIdQueryBuilder->where( $dbw->expr( 'itid_itemid', '!=', $skippedIds ) );
			}

			$itemIds = $itemIdQueryBuilder->fetchFieldValues();

			if ( !$itemIds ) {
				break;
			}

			foreach ( $itemIds as $itemId ) {
				$fixedItemId = preg_replace(
					'/^([hc]\-.*)_(\-([0-9]{14}|[0-9-]{10}T[0-9:]{6}00.000Z))?$/',
					'$1$2',
					$itemId
				);
				if ( $fixedItemId === $itemId ) {
					// In the rare case we got a false positive from the LIKE, add this to a list of skipped IDs
					// so we don't keep selecting it, and end up in an infinite loop
					$skippedIds[] = $itemId;
					continue;
				}
				try {
					$dbw->newUpdateQueryBuilder()
						->update( 'discussiontools_item_ids' )
						->set( [ 'itid_itemid' => $fixedItemId ] )
						->where( [ 'itid_itemid' => $itemId ] )
						->caller( __METHOD__ )->execute();
				} catch ( DBQueryError $err ) {
					// Give up on updating in case of complex conflicts (T356196#9913698)
					$this->output( "Failed to update $itemId\n" );
					$skippedIds[] = $itemId;
					continue;
				}

				$total += $dbw->affectedRows();
			}

			$this->waitForReplication();
			$this->output( "$total\n" );
		} while ( true );

		$this->output( "Fixing DiscussionTools IDs with trailing whitespace: done.\n" );

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getUpdateKey() {
		return 'DiscussionToolsFixTrailingWhitespaceIds';
	}
}

$maintClass = FixTrailingWhitespaceIds::class;
require_once RUN_MAINTENANCE_IF_MAIN;
