<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthGlobalUserGroupMembershipChangedHook;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\SpecialPage\SpecialPageFactory;
use Wikimedia\ObjectCache\WANObjectCache;

class CentralAuthHandler implements CentralAuthGlobalUserGroupMembershipChangedHook {
	private WANObjectCache $wanCache;
	private SpecialPageFactory $specialPageFactory;

	public function __construct(
		WANObjectCache $wanCache,
		SpecialPageFactory $specialPageFactory
	) {
		$this->wanCache = $wanCache;
		$this->specialPageFactory = $specialPageFactory;
	}

	/**
	 * Clear user's cached known external wiki permissions on global user group change
	 *
	 * @inheritDoc
	 */
	public function onCentralAuthGlobalUserGroupMembershipChanged(
		CentralAuthUser $centralAuthUser,
		array $oldGroups,
		array $newGroups
	) {
		// Do nothing if Special:GlobalContributions doesn't exist, as it's the sole generator of this data
		if ( !$this->specialPageFactory->exists( 'GlobalContributions' ) ) {
			return;
		}

		$checkKey = $this->wanCache->makeGlobalKey(
			'globalcontributions-ext-permissions',
			$centralAuthUser->getId()
		);

		// Clear the cache value if it exists as changing user groups may change the user's
		// stored access permissions
		$this->wanCache->touchCheckKey( $checkKey );
	}
}
