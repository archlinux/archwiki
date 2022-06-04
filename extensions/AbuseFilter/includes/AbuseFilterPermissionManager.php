<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Extension\AbuseFilter\Filter\AbstractFilter;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserIdentity;
use User;

/**
 * This class acts as a mediator between the AbuseFilter code and the PermissionManager, knowing
 * what rights are required to perform AF-related actions.
 */
class AbuseFilterPermissionManager {
	public const SERVICE_NAME = 'AbuseFilterPermissionManager';

	/** @var PermissionManager */
	private $permissionManager;

	/**
	 * @param PermissionManager $pm
	 */
	public function __construct( PermissionManager $pm ) {
		$this->permissionManager = $pm;
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	public function canEdit( User $user ): bool {
		$block = $user->getBlock();
		return (
			!( $block && $block->isSitewide() ) &&
			$this->permissionManager->userHasRight( $user, 'abusefilter-modify' )
		);
	}

	/**
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function canEditGlobal( UserIdentity $user ): bool {
		return $this->permissionManager->userHasRight( $user, 'abusefilter-modify-global' );
	}

	/**
	 * Whether the user can edit the given filter.
	 *
	 * @param User $user
	 * @param AbstractFilter $filter
	 * @return bool
	 */
	public function canEditFilter( User $user, AbstractFilter $filter ): bool {
		return (
			$this->canEdit( $user ) &&
			!( $filter->isGlobal() && !$this->canEditGlobal( $user ) )
		);
	}

	/**
	 * Whether the user can edit a filter with restricted actions enabled.
	 *
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function canEditFilterWithRestrictedActions( UserIdentity $user ): bool {
		return $this->permissionManager->userHasRight( $user, 'abusefilter-modify-restricted' );
	}

	/**
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function canViewPrivateFilters( UserIdentity $user ): bool {
		return $this->permissionManager->userHasAnyRight(
			$user,
			'abusefilter-modify',
			'abusefilter-view-private'
		);
	}

	/**
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function canViewPrivateFiltersLogs( UserIdentity $user ): bool {
		return $this->canViewPrivateFilters( $user ) ||
			$this->permissionManager->userHasRight( $user, 'abusefilter-log-private' );
	}

	/**
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function canViewAbuseLog( UserIdentity $user ): bool {
		return $this->permissionManager->userHasRight( $user, 'abusefilter-log' );
	}

	/**
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function canHideAbuseLog( UserIdentity $user ): bool {
		return $this->permissionManager->userHasRight( $user, 'abusefilter-hide-log' );
	}

	/**
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function canRevertFilterActions( UserIdentity $user ): bool {
		return $this->permissionManager->userHasRight( $user, 'abusefilter-revert' );
	}

	/**
	 * @param UserIdentity $user
	 * @param bool|int $filterHidden Whether the filter is hidden
	 * @todo Take a Filter parameter
	 * @return bool
	 */
	public function canSeeLogDetailsForFilter( UserIdentity $user, $filterHidden ): bool {
		if ( $filterHidden ) {
			return $this->canSeeLogDetails( $user ) && $this->canViewPrivateFiltersLogs( $user );
		}

		return $this->canSeeLogDetails( $user );
	}

	/**
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function canSeeLogDetails( UserIdentity $user ): bool {
		return $this->permissionManager->userHasRight( $user, 'abusefilter-log-detail' );
	}

	/**
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function canSeePrivateDetails( UserIdentity $user ): bool {
		return $this->permissionManager->userHasRight( $user, 'abusefilter-privatedetails' );
	}

	/**
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function canSeeHiddenLogEntries( UserIdentity $user ): bool {
		return $this->permissionManager->userHasRight( $user, 'abusefilter-hidden-log' );
	}

	/**
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function canUseTestTools( UserIdentity $user ): bool {
		// TODO: make independent
		return $this->canViewPrivateFilters( $user );
	}

}
