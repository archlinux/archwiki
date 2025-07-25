<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

trait TemporaryAccountRevisionTrait {

	/**
	 * Finds IP addresses responsible for specified revisions.
	 *
	 * @param int $actorId the actor responsible for the revisions
	 * @param int[] $revisionIds an array of revision ids
	 * @param IReadableDatabase $dbr
	 * @return array<string, string> a map of revision ids to ip addresses
	 */
	protected function getRevisionsIps( int $actorId, array $revisionIds, IReadableDatabase $dbr ): array {
		if ( !count( $revisionIds ) ) {
			return [];
		}

		$revisionIds = $this->filterOutHiddenRevisions( $revisionIds );

		if ( !count( $revisionIds ) ) {
			// If all revisions were filtered out, return a results list with no IPs
			// which is what happens when there is no CU data for the revisions.
			return [];
		}

		$rows = $dbr->newSelectQueryBuilder()
			// T327906: 'cuc_actor' and 'cuc_timestamp' are selected
			// only to satisfy Postgres requirement where all ORDER BY
			// fields must be present in SELECT list.
			->select( [ 'cuc_this_oldid', 'cuc_ip', 'cuc_actor', 'cuc_timestamp' ] )
			->from( 'cu_changes' )
			->where( [
				'cuc_actor' => $actorId,
				'cuc_this_oldid' => $revisionIds,
			] )
			->orderBy( [ 'cuc_actor', 'cuc_ip', 'cuc_timestamp' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$ips = [];
		foreach ( $rows as $row ) {
			// In the unlikely case that there are rows with the same
			// revision ID, the final array will contain the most recent
			$ips[$row->cuc_this_oldid] = $row->cuc_ip;
		}

		return $ips;
	}

	/**
	 * Filter out revision IDs where the authority does not have permissions to
	 * view the performer of the revision.
	 *
	 * @param int[] $ids
	 * @return int[]
	 */
	protected function filterOutHiddenRevisions( array $ids ): array {
		// ::joinComment is needed because ::newRevisionsFromBatch needs the comment fields.
		$revisionRows = $this->getRevisionStore()
			->newSelectQueryBuilder( $this->getConnectionProvider()->getReplicaDatabase() )
			->joinComment()
			->where( [ 'rev_id' => $ids ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		// Create RevisionRecord objects for each row and then filter out the revisions that have the performer hidden.
		$status = $this->getRevisionStore()->newRevisionsFromBatch( $revisionRows );
		$filteredIds = $this->filterOutHiddenRevisionsInternal( $status->isOK() ? $status->getValue() : [] );
		// If the authority has the 'deletedhistory' right, also check the archive table for any IDs which
		// were not found in the revision table.
		if ( $this->getPermissionManager()->userHasRight( $this->getAuthority()->getUser(), 'deletedhistory' ) ) {
			// RevisionStore::newRevisionsFromBatch doesn't rewind the results after iterating over them.
			$revisionRows->rewind();
			// Find the IDs which were not found in the revision table so that we can check the archive table.
			$missingIds = array_diff(
				$ids,
				array_map( static function ( $row ) {
					return $row->rev_id;
				}, iterator_to_array( $revisionRows ) )
			);
			if ( count( $missingIds ) ) {
				// If IDs are missing, then they are probably in the archive table. If not they are not,
				// they will be ignored as invalid to avoid leaking data.
				$archiveRevisionRows = $this->getRevisionStore()
					->newArchiveSelectQueryBuilder( $this->getConnectionProvider()->getReplicaDatabase() )
					->joinComment()
					->where( [ 'ar_rev_id' => $missingIds ] )
					->caller( __METHOD__ )
					->fetchResultSet();
				$status = $this->getRevisionStore()
					->newRevisionsFromBatch( $archiveRevisionRows, [ 'archive' => true ] );
				$filteredIds = array_merge(
					$filteredIds,
					$this->filterOutHiddenRevisionsInternal( $status->isOK() ? $status->getValue() : [] )
				);
			}
		}
		return $filteredIds;
	}

	/**
	 * Actually perform the filtering of revisions where the performer is
	 * hidden from the authority.
	 *
	 * @param RevisionRecord[] $revisions
	 * @return int[] The revision IDs the authority is allowed to see.
	 */
	private function filterOutHiddenRevisionsInternal( array $revisions ): array {
		$filteredIds = [];
		foreach ( $revisions as $revisionRecord ) {
			if ( $revisionRecord->userCan( RevisionRecord::DELETED_USER, $this->getAuthority() ) ) {
				$filteredIds[] = $revisionRecord->getId();
			}
		}
		return $filteredIds;
	}

	abstract protected function getAuthority(): Authority;

	abstract protected function getPermissionManager(): PermissionManager;

	abstract protected function getConnectionProvider(): IConnectionProvider;

	abstract protected function getRevisionStore(): RevisionStore;
}
