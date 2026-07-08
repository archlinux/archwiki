<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Specials\Hook\BlockIpCompleteHook;
use MediaWiki\Specials\Hook\UnblockUserCompleteHook;

readonly class UserInfoCardCacheInvalidationHandler implements
	BlockIpCompleteHook,
	UnblockUserCompleteHook
{

	public function __construct(
		private UserInfoCardBlockStatusCache $blockStatusCache,
	) {
	}

	/** @inheritDoc */
	public function onBlockIpComplete( $block, $user, $priorBlock ): void {
		if ( !$block->isSitewide() ) {
			return;
		}
		$targetUser = $block->getTargetUserIdentity();
		if ( $targetUser === null || !$targetUser->isRegistered() ) {
			return;
		}
		$this->blockStatusCache->invalidateLocal( $targetUser->getName() );
	}

	/** @inheritDoc */
	public function onUnblockUserComplete( $block, $user ): void {
		$targetUser = $block->getTargetUserIdentity();
		if ( $targetUser === null || !$targetUser->isRegistered() ) {
			return;
		}
		$this->blockStatusCache->invalidateLocal( $targetUser->getName() );
	}
}
