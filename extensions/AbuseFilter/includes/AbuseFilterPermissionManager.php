<?php

namespace MediaWiki\Extension\AbuseFilter;

use LogicException;
use MapCacheLRU;
use MediaWiki\Extension\AbuseFilter\Filter\AbstractFilter;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Extension\AbuseFilter\Variables\AbuseFilterProtectedVariablesLookup;
use MediaWiki\Permissions\Authority;

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

	private RuleCheckerFactory $ruleCheckerFactory;
	private AbuseFilterHookRunner $hookRunner;

	public function __construct(
		AbuseFilterProtectedVariablesLookup $protectedVariablesLookup,
		RuleCheckerFactory $ruleCheckerFactory,
		AbuseFilterHookRunner $hookRunner
	) {
		$this->protectedVariables = $protectedVariablesLookup->getAllProtectedVariables();
		$this->ruleCheckerFactory = $ruleCheckerFactory;
		$this->hookRunner = $hookRunner;

		$this->canViewProtectedVariablesCache = new MapCacheLRU( 10 );
	}

	/**
	 * @param Authority $performer
	 * @return bool
	 */
	public function canEdit( Authority $performer ): bool {
		$block = $performer->getBlock();
		return (
			!( $block && $block->isSitewide() ) &&
			$performer->isAllowed( 'abusefilter-modify' )
		);
	}

	/**
	 * @param Authority $performer
	 * @return bool
	 */
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

	/**
	 * @param Authority $performer
	 * @return bool
	 */
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

	/**
	 * @param Authority $performer
	 * @return bool
	 */
	public function canViewPrivateFiltersLogs( Authority $performer ): bool {
		return $this->canViewPrivateFilters( $performer ) ||
			$performer->isAllowed( 'abusefilter-log-private' );
	}

	/**
	 * @param Authority $performer
	 * @return bool
	 */
	public function canViewAbuseLog( Authority $performer ): bool {
		return $performer->isAllowed( 'abusefilter-log' );
	}

	/**
	 * @param Authority $performer
	 * @return bool
	 */
	public function canHideAbuseLog( Authority $performer ): bool {
		return $performer->isAllowed( 'abusefilter-hide-log' );
	}

	/**
	 * @param Authority $performer
	 * @return bool
	 */
	public function canRevertFilterActions( Authority $performer ): bool {
		return $performer->isAllowed( 'abusefilter-revert' );
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

	/**
	 * @param Authority $performer
	 * @return bool
	 */
	public function canSeeLogDetails( Authority $performer ): bool {
		return $performer->isAllowed( 'abusefilter-log-detail' );
	}

	/**
	 * @param Authority $performer
	 * @return bool
	 */
	public function canSeePrivateDetails( Authority $performer ): bool {
		return $performer->isAllowed( 'abusefilter-privatedetails' );
	}

	/**
	 * @param Authority $performer
	 * @return bool
	 */
	public function canSeeHiddenLogEntries( Authority $performer ): bool {
		return $performer->isAllowed( 'abusefilter-hidden-log' );
	}

	/**
	 * @param Authority $performer
	 * @return bool
	 */
	public function canUseTestTools( Authority $performer ): bool {
		// TODO: make independent
		return $this->canViewPrivateFilters( $performer );
	}

}
