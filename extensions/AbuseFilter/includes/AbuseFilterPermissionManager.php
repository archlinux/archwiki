<?php

namespace MediaWiki\Extension\AbuseFilter;

use LogicException;
use MapCacheLRU;
use MediaWiki\Extension\AbuseFilter\Filter\AbstractFilter;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Extension\AbuseFilter\Variables\AbuseFilterProtectedVariablesLookup;
use MediaWiki\Logging\LogPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use MediaWiki\RecentChanges\RCCacheEntry;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\TempUser\TempUserConfig;

/**
 * This class simplifies the interactions between the AbuseFilter code and Authority, knowing
 * what rights are required to perform AF-related actions.
 */
class AbuseFilterPermissionManager {
	public const SERVICE_NAME = 'AbuseFilterPermissionManager';

	/**
	 * @var string[] All protected variables
	 */
	private array $protectedVariables;

	private MapCacheLRU $canViewProtectedVariablesCache;

	private TempUserConfig $tempUserConfig;
	private ExtensionRegistry $extensionRegistry;
	private RuleCheckerFactory $ruleCheckerFactory;
	private AbuseFilterHookRunner $hookRunner;

	public function __construct(
		TempUserConfig $tempUserConfig,
		ExtensionRegistry $extensionRegistry,
		AbuseFilterProtectedVariablesLookup $protectedVariablesLookup,
		RuleCheckerFactory $ruleCheckerFactory,
		AbuseFilterHookRunner $hookRunner
	) {
		$this->tempUserConfig = $tempUserConfig;
		$this->extensionRegistry = $extensionRegistry;
		$this->protectedVariables = $protectedVariablesLookup->getAllProtectedVariables();
		$this->ruleCheckerFactory = $ruleCheckerFactory;
		$this->hookRunner = $hookRunner;

		$this->canViewProtectedVariablesCache = new MapCacheLRU( 10 );
	}

	public function canEdit( Authority $performer ): bool {
		$block = $performer->getBlock();
		return (
			!( $block && $block->isSitewide() ) &&
			$performer->isAllowed( 'abusefilter-modify' )
		);
	}

	public function canEditGlobal( Authority $performer ): bool {
		return $performer->isAllowed( 'abusefilter-modify-global' );
	}

	/**
	 * Whether the user can edit the given filter.
	 *
	 * @param Authority $performer
	 * @param AbstractFilter $filter
	 * @return bool
	 */
	public function canEditFilter( Authority $performer, AbstractFilter $filter ): bool {
		return (
			$this->canEdit( $performer ) &&
			!( $filter->isGlobal() && !$this->canEditGlobal( $performer ) )
		);
	}

	/**
	 * Whether the user can edit a filter with restricted actions enabled.
	 *
	 * @param Authority $performer
	 * @return bool
	 */
	public function canEditFilterWithRestrictedActions( Authority $performer ): bool {
		return $performer->isAllowed( 'abusefilter-modify-restricted' );
	}

	public function canViewPrivateFilters( Authority $performer ): bool {
		$block = $performer->getBlock();
		return (
			!( $block && $block->isSitewide() ) &&
			$performer->isAllowedAny(
				'abusefilter-modify',
				'abusefilter-view-private'
			)
		);
	}

	/**
	 * Whether the given user can see all of the protected variables used in the given filter.
	 *
	 * @param Authority $performer
	 * @param AbstractFilter $filter
	 * @return AbuseFilterPermissionStatus
	 * @throws LogicException If the provided $filter is not protected. Check if the filter is protected using
	 *   {@link AbstractFilter::isProtected} before calling this method.
	 */
	public function canViewProtectedVariablesInFilter(
		Authority $performer, AbstractFilter $filter
	): AbuseFilterPermissionStatus {
		if ( !$filter->isProtected() ) {
			throw new LogicException(
				'::canViewProtectedVariablesInFilter should not be called when the provided $filter is not protected'
			);
		}
		$ruleChecker = $this->ruleCheckerFactory->newRuleChecker();
		$usedVars = $ruleChecker->getUsedVars( $filter->getRules() );
		return $this->canViewProtectedVariables( $performer, $usedVars );
	}

	/**
	 * Returns the cache key used to access the MapCacheLRU instance that
	 * caches the return values of {@link self::canViewProtectedVariables}.
	 *
	 * @param Authority $performer
	 * @param array $variables
	 * @return string
	 */
	private function getCacheKey( Authority $performer, array $variables ): string {
		// Sort the $variables array as the order of the variables will not affect
		// the return value from the cached methods.
		sort( $variables );

		return $performer->getUser()->getId() . '-' . implode( ',', $variables );
	}

	/**
	 * Whether the given user can see all of the specified protected variables.
	 *
	 * @param Authority $performer
	 * @param string[] $variables The variables, which do not need to filtered to just protected variables.
	 * @return AbuseFilterPermissionStatus
	 */
	public function canViewProtectedVariables( Authority $performer, array $variables ): AbuseFilterPermissionStatus {
		$variables = $this->getUsedProtectedVariables( $variables );

		// Check if we have the result in cache, and return it if we do.
		$cacheKey = $this->getCacheKey( $performer, $variables );
		if ( $this->canViewProtectedVariablesCache->has( $cacheKey ) ) {
			return $this->canViewProtectedVariablesCache->get( $cacheKey );
		}

		$returnStatus = $this->checkCanViewProtectedVariables( $performer );
		if ( !$returnStatus->isGood() ) {
			$this->canViewProtectedVariablesCache->set( $cacheKey, $returnStatus );
			return $returnStatus;
		}

		$this->hookRunner->onAbuseFilterCanViewProtectedVariables( $performer, $variables, $returnStatus );

		$this->canViewProtectedVariablesCache->set( $cacheKey, $returnStatus );
		return $returnStatus;
	}

	/**
	 * Checks that the user is allowed to see protected variables without
	 * checking variable specific restrictions.
	 *
	 * @param Authority $performer
	 * @return AbuseFilterPermissionStatus
	 */
	private function checkCanViewProtectedVariables( Authority $performer ): AbuseFilterPermissionStatus {
		$block = $performer->getBlock();
		if ( $block && $block->isSitewide() ) {
			return AbuseFilterPermissionStatus::newBlockedError( $block );
		}

		if ( !$performer->isAllowed( 'abusefilter-access-protected-vars' ) ) {
			return AbuseFilterPermissionStatus::newPermissionError( 'abusefilter-access-protected-vars' );
		}

		return AbuseFilterPermissionStatus::newGood();
	}

	/**
	 * Return all used protected variables from an array of variables. Ignore user permissions.
	 *
	 * @param string[] $usedVariables
	 * @return string[] The protected variables in $usedVariables, with any duplicates removed.
	 */
	public function getUsedProtectedVariables( array $usedVariables ): array {
		return array_intersect( $this->protectedVariables, $usedVariables );
	}

	/**
	 * Check if the filter uses variables that the user is not allowed to use (i.e., variables that are protected, if
	 * the user can't view protected variables), and return them.
	 *
	 * @param Authority $performer
	 * @param string[] $usedVariables
	 * @return string[]
	 */
	public function getForbiddenVariables( Authority $performer, array $usedVariables ): array {
		$usedProtectedVariables = $this->getUsedProtectedVariables( $usedVariables );
		// All good if protected variables aren't used, or the user can view them.
		if (
			count( $usedProtectedVariables ) === 0 ||
			$this->canViewProtectedVariables( $performer, $usedProtectedVariables )->isGood()
		) {
			return [];
		}
		return $usedProtectedVariables;
	}

	/**
	 * Return an array of protected variables. Convenience method that calls
	 * {@link AbuseFilterProtectedVariablesLookup::getAllProtectedVariables}.
	 *
	 * @return string[]
	 */
	public function getProtectedVariables() {
		return $this->protectedVariables;
	}

	public function canViewPrivateFiltersLogs( Authority $performer ): bool {
		return $this->canViewPrivateFilters( $performer ) ||
			$performer->isAllowed( 'abusefilter-log-private' );
	}

	public function canViewAbuseLog( Authority $performer ): bool {
		return $performer->isAllowed( 'abusefilter-log' );
	}

	public function canHideAbuseLog( Authority $performer ): bool {
		return $performer->isAllowed( 'abusefilter-hide-log' );
	}

	public function canRevertFilterActions( Authority $performer ): bool {
		return $performer->isAllowed( 'abusefilter-revert' );
	}

	/**
	 * Check whether an authority can view temporary account IP addresses, as determined
	 * by the CheckUser extension (if loaded). If they can, this overrides any restrictions
	 * on seeing IP addresses due to not having the necessary AbuseFilter permissions.
	 */
	private function canViewTemporaryAccountIPs( Authority $performer ): bool {
		return $this->extensionRegistry->isLoaded( 'CheckUser' ) &&
			MediaWikiServices::getInstance()->getService( 'CheckUserPermissionManager' )
				->canAccessTemporaryAccountIPAddresses( $performer )->isGood();
	}

	/**
	 * Check whether an authority can see IP addresses for logs of a given filter. This may
	 * differ depending on whether the log entry performer is a temporary user.
	 *
	 * @param Authority $performer
	 * @param AbstractFilter $filter
	 * @param string $userName Name of the performing user for the log entry
	 * @return bool
	 */
	public function canSeeIPForFilterLog(
		Authority $performer,
		AbstractFilter $filter,
		string $userName
	) {
		if ( $this->canSeeLogDetailsForFilter( $performer, $filter ) ) {
			return true;
		}

		if (
			$this->tempUserConfig->isTempName( $userName ) &&
			$this->canViewTemporaryAccountIPs( $performer )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if a user can see log details associated with a given filter.
	 *
	 * If the filter is protected, you should call {@link self::canViewProtectedVariables} providing the variables
	 * present in the log details.
	 *
	 * @param Authority $performer
	 * @param AbstractFilter $filter
	 * @return bool
	 */
	public function canSeeLogDetailsForFilter( Authority $performer, AbstractFilter $filter ): bool {
		if ( !$this->canSeeLogDetails( $performer ) ) {
			return false;
		}

		if ( $filter->isHidden() && !$this->canViewPrivateFiltersLogs( $performer ) ) {
			return false;
		}

		// Callers are expected to check access to the specific protected variables used in the given
		// log entries. This is because the variables in the logs may be different to the current filter.
		// We don't want to prevent access to past logs based on the variables currently in the filter,
		// to avoid hiding logs which the user should be able to see otherwise.
		if ( $filter->isProtected() && !$this->canViewProtectedVariables( $performer, [] )->isGood() ) {
			return false;
		}

		return true;
	}

	public function canSeeLogDetails( Authority $performer ): bool {
		return $performer->isAllowed( 'abusefilter-log-detail' );
	}

	public function canSeePrivateDetails( Authority $performer ): bool {
		return $performer->isAllowed( 'abusefilter-privatedetails' );
	}

	public function canSeeHiddenLogEntries( Authority $performer ): bool {
		return $performer->isAllowed( 'abusefilter-hidden-log' );
	}

	public function canUseTestTools( Authority $performer ): bool {
		// TODO: make independent
		return $this->canViewPrivateFilters( $performer );
	}

	/**
	 * Determine whether the current user is allowed to view a revision
	 * at all, given its current visibility restrictions.
	 *
	 * Unlike `RevisionRecord::userCanBitfield`, which checks whether the user
	 * may view a specific deleted aspect of a revision (e.g. text or comment),
	 * this method evaluates whether the revision itself is viewable, considering
	 * all applicable visibility flags together.
	 *
	 * @param int $visibility Current visibility bit field (the `rev_deleted` value)
	 * @param Authority $authority User on whose behalf to check access
	 * @return bool
	 * @todo Consider moving this to core if similar logic is needed elsewhere
	 * @see RevisionRecord::userCanBitfield
	 */
	public static function hasRevisionAccess( int $visibility, Authority $authority ): bool {
		if ( !$visibility ) {
			return true;
		}
		if ( $visibility & RevisionRecord::DELETED_RESTRICTED ) {
			// Suppressed revisions require suppressor rights regardless of other flags
			return $authority->isAllowedAny( 'suppressrevision', 'viewsuppressed' );
		}
		if ( ( $visibility & RevisionRecord::DELETED_TEXT ) && !$authority->isAllowed( 'deletedtext' ) ) {
			return false;
		}
		if ( ( $visibility & ( RevisionRecord::DELETED_COMMENT | RevisionRecord::DELETED_USER ) ) &&
			!$authority->isAllowed( 'deletedhistory' )
		) {
			return false;
		}
		return true;
	}

	/**
	 * Determine whether the current user is allowed to view a recent change row
	 * at all, given its source and current visibility restrictions.
	 *
	 * Unlike `ChangesList::userCan`, which checks whether the user may view a
	 * specific deleted aspect of a recent change and delegates to
	 * `LogEventsList::userCanBitfield` for log entries or
	 * `RevisionRecord::userCanBitfield` otherwise, this method evaluates whether
	 * the recent change row itself is viewable, considering all applicable
	 * visibility flags together, and delegates to `::hasRevisionAccess` for
	 * non-log entries.
	 *
	 * @param RCCacheEntry|RecentChange $rc
	 * @param Authority $authority User on whose behalf to check
	 * @return bool
	 * @see ChangesList::userCan
	 * @see LogEventsList::userCanBitfield
	 * @see RevisionRecord::userCanBitfield
	 * @see AbuseFilterPermissionManager::hasRevisionAccess
	 */
	public static function hasRCEntryAccess( $rc, Authority $authority ): bool {
		$visibility = (int)$rc->getAttribute( 'rc_deleted' );
		if ( $visibility === 0 ) {
			return true;
		}
		if ( $rc->getAttribute( 'rc_source' ) === RecentChange::SRC_LOG ) {
			if ( $visibility & LogPage::DELETED_RESTRICTED ) {
				return $authority->isAllowedAny( 'suppressrevision', 'viewsuppressed' );
			} else {
				return $authority->isAllowed( 'deletedhistory' );
			}
		}
		return self::hasRevisionAccess( $visibility, $authority );
	}

}
