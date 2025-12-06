<?php

namespace MediaWiki\Extension\Nuke\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\Storage\NameTableAccessException;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Converts all uses of the `Nuke` tag to the `nuke` tag and
 * removes the old tag from the database.
 */
class NormalizeNukeTags extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Converts all uses of the `Nuke` tag to the `nuke` tag and removes the old tag from the database.'
		);
		$this->setBatchSize( 500 );
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$this->output( "... Checking state of old Nuke tag...\n" );

		// Find out the ID
		$changeTagDefStore = $this->getServiceContainer()->getChangeTagDefStore();
		try {
			$nukeOldTagId = $changeTagDefStore->getId( 'Nuke' );
		} catch ( NameTableAccessException ) {
			// The tag doesn't exist. No work to do here.
			$this->output( "... Old Nuke tag does not exist.\n" );
			return true;
		}

		$this->output( "... Found old Nuke tag ID: $nukeOldTagId\n" );
		$this->output( "... Updating all uses of the Nuke tag...\n" );

		$dbr = $this->getReplicaDB();
		$dbw = $this->getPrimaryDB();

		$nukeNewTagId = null;
		do {
			// Find uses of the old tag (as much as the batch size)
			$batchIDs = $dbr->newSelectQueryBuilder()
				->select( 'ct_id' )
				->from( 'change_tag' )
				->where( [
					"ct_tag_id" => $nukeOldTagId
				] )
				->limit( $this->getBatchSize() )
				->caller( __METHOD__ )
				->fetchFieldValues();
			if ( count( $batchIDs ) === 0 ) {
				// No uses left. We can proceed.
				break;
			}

			// Get the tag ID for the new tag, if we don't have it yet
			if ( !$nukeNewTagId ) {
				$nukeNewTagId = $changeTagDefStore->acquireId( 'nuke' );
			}

			// Replace those uses.
			$dbw->newUpdateQueryBuilder()
				->update( 'change_tag' )
				->set( [
					"ct_tag_id" => $nukeNewTagId
				] )
				->where( [
					"ct_id" => $batchIDs
				] )
				->caller( __METHOD__ )
				->execute();

			$this->commitTransactionRound( __METHOD__ );
		} while ( true );

		// Tags are all updated. Now we can remove the old tag.
		$this->output( "... Removing old Nuke tag...\n" );
		$dbw->newDeleteQueryBuilder()
			->delete( 'change_tag_def' )
			->where( [
				"ctd_id" => $nukeOldTagId
			] )
			->caller( __METHOD__ )
			->execute();

		// Update the tag definition store to recount the tag uses.
		$this->output( "... Updating tag hitcount for 'nuke'...\n" );

		// This may be an expensive query, don't run on master.
		$nukeNewTagHitcount = $dbr->newSelectQueryBuilder()
			->select( [ 'count(*)' ] )
			->from( 'change_tag' )
			->where( [
				"ct_tag_id" => $nukeNewTagId
			] )
			->caller( __METHOD__ )
			->fetchField();
		$this->output( "... Found $nukeNewTagHitcount changes...\n" );
		$dbw->newUpdateQueryBuilder()
			->update( 'change_tag_def' )
			->set( [ 'ctd_count' => $nukeNewTagHitcount ] )
			->where( [ 'ctd_id' => $nukeNewTagId ] )
			->caller( __METHOD__ )
			->execute();

		$this->output( "... Nuke tags normalized.\n" );

		return true;
	}

}

// @codeCoverageIgnoreStart
$maintClass = NormalizeNukeTags::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
