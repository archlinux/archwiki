<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthGlobalUserLockStatusChangedHook;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;

readonly class UserInfoCardCacheInvalidationOnGlobalLockHandler implements
	CentralAuthGlobalUserLockStatusChangedHook
{

	public function __construct(
		private UserInfoCardBlockStatusCache $blockStatusCache,
	) {
	}

	/** @inheritDoc */
	public function onCentralAuthGlobalUserLockStatusChanged(
		CentralAuthUser $centralAuthUser,
		bool $isLocked
	): void {
		$this->blockStatusCache->invalidateGlobal( $centralAuthUser->getName() );
	}
}
