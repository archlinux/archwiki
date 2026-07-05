<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks;

interface IndefiniteBlockCheckInterface {

	/**
	 * Given a list of local user IDs, return those that are indefinitely blocked according to this check.
	 *
	 * @param int[] $userIds local user IDs
	 * @return int[] User IDs that are indefinitely blocked
	 */
	public function getIndefinitelyBlockedUserIds( array $userIds ): array;
}
