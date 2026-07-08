<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks;

use MediaWiki\Block\UserBlockTarget;
use MediaWiki\Extension\GlobalBlocking\Services\GlobalBlockLookup;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;

class GlobalBlockCheck implements IndefiniteBlockCheckInterface, BlockCheckInterface {

	public function __construct(
		private readonly GlobalBlockLookup $globalBlockLookup,
		private readonly CentralIdLookup $centralIdLookup,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly bool $applyGlobalBlocksEnabled,
	) {
	}

	/** @inheritDoc */
	public function getIndefinitelyBlockedUserIds( array $userIds ): array {
		if ( !$this->applyGlobalBlocksEnabled ) {
			return [];
		}

		$globalBlocks = $this->getGlobalBlocksForLocalUserIds( $userIds );
		$blockedUserIds = [];
		foreach ( $globalBlocks as $globalBlock ) {
			$blockTarget = $globalBlock->getTarget();
			if ( $blockTarget instanceof UserBlockTarget && $globalBlock->isIndefinite() ) {
				$blockedUserIds[] = $blockTarget->getUserIdentity()->getId();
			}
		}

		return array_unique( $blockedUserIds );
	}

	/** @inheritDoc */
	public function getBlockedUserIds( array $userIds ): array {
		if ( !$this->applyGlobalBlocksEnabled ) {
			return [];
		}

		$globalBlocks = $this->getGlobalBlocksForLocalUserIds( $userIds );
		$blockedUserIds = [];
		foreach ( $globalBlocks as $globalBlock ) {
			$blockTarget = $globalBlock->getTarget();
			if ( $blockTarget instanceof UserBlockTarget ) {
				$blockedUserIds[] = $blockTarget->getUserIdentity()->getId();
			}
		}

		return array_unique( $blockedUserIds );
	}

	private function getGlobalBlocksForLocalUserIds( array $userIds ): array {
		if ( count( $userIds ) === 0 ) {
			return [];
		}

		$userIdentities = $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $userIds )
			->caller( __METHOD__ )
			->fetchUserIdentities();
		$userNames = array_map(
			static fn ( UserIdentity $userIdentity ) => $userIdentity->getName(),
			iterator_to_array( $userIdentities )
		);

		// Returns a list of usernames to central IDs, where the IDs are false if the username isn't
		// attached to a central account. We use array_filter to remove any entries where no central ID was found.
		$centralIds = array_filter( $this->centralIdLookup->lookupAttachedUserNames(
			array_fill_keys( $userNames, false ),
			CentralIdLookup::AUDIENCE_RAW
		) );

		return $this->globalBlockLookup->getGlobalBlocksForCentralIds( $centralIds );
	}
}
