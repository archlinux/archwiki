<?php
namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\Hook\UserGroupsChangedHook;
use Wikimedia\ObjectCache\WANObjectCache;

class GroupsHandler implements
	UserGroupsChangedHook
{

	public function __construct(
		private readonly CentralIdLookup $centralIdLookup,
		private readonly WANObjectCache $wanCache,
		private readonly SpecialPageFactory $specialPageFactory
	) {
	}

	/**
	 * Clear user's cached known external wiki permissions on user group change
	 *
	 * @inheritDoc
	 */
	public function onUserGroupsChanged(
		$user,
		$added,
		$removed,
		$performer,
		$reason,
		$oldUGMs,
		$newUGMs
	) {
		// Do nothing if the user's group memberships didn't change
		if ( $newUGMs === $oldUGMs ) {
			return;
		}

		// Do nothing if Special:GlobalContributions doesn't exist, as it's the sole generator of this data
		if ( !$this->specialPageFactory->exists( 'GlobalContributions' ) ) {
			return;
		}

		// Do nothing if user has no central id, as there will be no permissions cached for them
		$centralUserId = $this->centralIdLookup->centralIdFromLocalUser( $user );
		if ( !$centralUserId ) {
			return;
		}

		$checkKey = $this->wanCache->makeGlobalKey(
			'globalcontributions-ext-permissions',
			$centralUserId
		);

		// Clear the cache value if it exists as changing user groups may change the user's stored access permissions
		$this->wanCache->touchCheckKey( $checkKey );
	}
}
