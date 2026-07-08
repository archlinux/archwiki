<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\BlockCheckInterface;

class CompositeBlockChecker {

	/** @param BlockCheckInterface[] $blockChecks */
	public function __construct( private readonly array $blockChecks ) {
	}

	/**
	 * Return user IDs that are not blocked by any of the registered block checks.
	 *
	 * @param int[] $localUserIds
	 * @return int[] User IDs that remain unblocked
	 */
	public function getUserIdsNotBlocked( array $localUserIds ): array {
		$unblockedUserIds = $localUserIds;
		foreach ( $this->blockChecks as $check ) {
			$blockedUserIds = $check->getBlockedUserIds( $unblockedUserIds );
			$unblockedUserIds = array_values( array_diff( $unblockedUserIds, $blockedUserIds ) );
			if ( $unblockedUserIds === [] ) {
				return [];
			}
		}

		return $unblockedUserIds;
	}
}
