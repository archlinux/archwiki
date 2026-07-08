<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\IndefiniteBlockCheckInterface;

class CompositeIndefiniteBlockChecker {

	/** @param IndefiniteBlockCheckInterface[] $blockChecks */
	public function __construct( private readonly array $blockChecks ) {
	}

	/**
	 * Return user IDs that are not indefinitely blocked by any of the registered block checks.
	 *
	 * @param int[] $localUserIds
	 * @return int[] User IDs that remain unblocked
	 * @stable to call since 1.46
	 */
	public function getUserIdsNotIndefinitelyBlocked( array $localUserIds ): array {
		$unblockedUserIds = $localUserIds;
		foreach ( $this->blockChecks as $check ) {
			$blockedUserIds = $check->getIndefinitelyBlockedUserIds( $unblockedUserIds );
			$unblockedUserIds = array_values( array_diff( $unblockedUserIds, $blockedUserIds ) );
			if ( $unblockedUserIds === [] ) {
				return [];
			}
		}

		return $unblockedUserIds;
	}

	/**
	 * @deprecated since 1.46 Use getUserIdsNotIndefinitelyBlocked instead
	 * @param int[] $localUserIds
	 * @return int[] User IDs that remain unblocked
	 */
	public function getUnblockedUserIds( array $localUserIds ): array {
		return $this->getUserIdsNotIndefinitelyBlocked( $localUserIds );
	}
}
// @codeCoverageIgnoreStart
/**
 * @deprecated since 1.46
 */
class_alias(
	CompositeIndefiniteBlockChecker::class,
	// old namespace without "Extension"
	'MediaWiki\\CheckUser\\SuggestedInvestigations\\Services\\CompositeIndefiniteBlockChecker'
);
// @codeCoverageIgnoreEnd
