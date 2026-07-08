<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks;

interface BlockCheckInterface {

	/**
	 * Given a list of local user IDs, return those that are blocked according to this check,
	 * regardless of block duration or if the block is partial (if applicable)
	 *
	 * @param int[] $userIds local user IDs
	 * @return int[] User IDs that are blocked
	 */
	public function getBlockedUserIds( array $userIds ): array;
}
