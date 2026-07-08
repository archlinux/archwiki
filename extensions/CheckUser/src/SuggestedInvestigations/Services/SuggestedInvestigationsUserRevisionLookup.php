<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Provides revision-related lookups for Suggested Investigations.
 */
class SuggestedInvestigationsUserRevisionLookup {

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
	) {
	}

	/**
	 * Checks if the given revision is the first revision authored by the user,
	 * considering both live revisions and revisions archived due to page deletion.
	 */
	public function isFirstEditByUser( UserIdentity $userIdentity, int $revId ): bool {
		$db = $this->dbProvider->getPrimaryDatabase();
		$actorName = $userIdentity->getName();

		$revCount = $db->newSelectQueryBuilder()
			->from( 'revision' )
			->join( 'actor', null, 'actor_id = rev_actor' )
			->where( [
				'actor_name' => $actorName,
				$db->expr( 'rev_id', '<=', $revId ),
			] )
			->limit( 2 )
			->caller( __METHOD__ )
			->fetchRowCount();

		if ( $revCount >= 2 ) {
			return false;
		}

		$archiveCount = $db->newSelectQueryBuilder()
			->from( 'archive' )
			->join( 'actor', null, 'actor_id = ar_actor' )
			->where( [
				'actor_name' => $actorName,
				$db->expr( 'ar_rev_id', '<=', $revId ),
			] )
			->limit( 2 - $revCount )
			->caller( __METHOD__ )
			->fetchRowCount();

		return ( $revCount + $archiveCount ) === 1;
	}
}
