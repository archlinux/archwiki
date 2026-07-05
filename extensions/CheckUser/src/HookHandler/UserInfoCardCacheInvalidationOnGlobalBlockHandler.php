<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Extension\GlobalBlocking\GlobalBlock;
use MediaWiki\Extension\GlobalBlocking\Hooks\GlobalBlockingGlobalBlockAuditHook;
use MediaWiki\Extension\GlobalBlocking\Hooks\GlobalBlockingGlobalUnblockAuditHook;

readonly class UserInfoCardCacheInvalidationOnGlobalBlockHandler implements
	GlobalBlockingGlobalBlockAuditHook,
	GlobalBlockingGlobalUnblockAuditHook
{

	public function __construct(
		private UserInfoCardBlockStatusCache $blockStatusCache,
	) {
	}

	/** @inheritDoc */
	public function onGlobalBlockingGlobalBlockAudit( GlobalBlock $globalBlock ): void {
		$targetUser = $globalBlock->getTargetUserIdentity();
		if ( $targetUser === null || !$targetUser->isRegistered() ) {
			return;
		}
		$this->blockStatusCache->invalidateGlobal( $targetUser->getName() );
	}

	/** @inheritDoc */
	public function onGlobalBlockingGlobalUnblockAudit( GlobalBlock $globalBlock ): void {
		$targetUser = $globalBlock->getTargetUserIdentity();
		if ( $targetUser === null || !$targetUser->isRegistered() ) {
			return;
		}
		$this->blockStatusCache->invalidateGlobal( $targetUser->getName() );
	}
}
