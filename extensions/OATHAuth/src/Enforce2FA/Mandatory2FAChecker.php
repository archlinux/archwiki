<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Enforce2FA;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupAssignmentService;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\RestrictedUserGroupConfigReader;
use MediaWiki\User\UserGroupManagerFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserRequirementsConditionCheckerFactory;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IDBAccessObject;

class Mandatory2FAChecker {

	public function __construct(
		private readonly UserRequirementsConditionCheckerFactory $userRequirementsCheckerFactory,
		private readonly RestrictedUserGroupConfigReader $restrictedUserGroupConfigReader,
		private readonly UserGroupManagerFactory $userGroupManagerFactory,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly Config $config
	) {
	}

	/**
	 * For a given user, returns a list of their groups that require 2FA. This function checks the requirement
	 * in the context of a particular user, as some other group conditions may apply.
	 *
	 * @param UserIdentity $user The user to check
	 * @return list<string> List of groups the user is a member of that require 2FA.
	 */
	public function getGroupsRequiring2FA( UserIdentity $user ): array {
		$ugm = $this->userGroupManagerFactory->getUserGroupManager( $user->getWikiId() );
		$groupRestrictions = $this->restrictedUserGroupConfigReader->getConfig( $user->getWikiId() );

		$groups = array_merge( $ugm->getUserGroups( $user ), $ugm->getUserImplicitGroups( $user ) );
		$groupsRequiring2FA = [];
		foreach ( $groups as $group ) {
			if ( !isset( $groupRestrictions[$group] ) ) {
				continue;
			}
			$restrictions = $groupRestrictions[$group];
			if ( $this->isUserRequiredToHave2FAToMeetConditions( $user, $restrictions->getMemberConditions() ) ) {
				$groupsRequiring2FA[] = $group;
			}
		}
		return $groupsRequiring2FA;
	}

	/**
	 * For every wiki on the wiki farm, returns a list of groups the user is a member of that require 2FA.
	 * This function uses the group requirements as they are specified on the remote wiki.
	 *
	 * If CentralAuth is not installed, this will just return the result for the local wiki.
	 * If CentralAuth is installed, global groups requiring 2FA are also included in the result,
	 * keyed under the central wiki ID (or the current wiki ID if no central wiki is configured).
	 * @param UserIdentity $localUser
	 * @return array<string, list<string>> An associative array where the keys are wiki IDs and the values are groups.
	 *     A given key is present in the array only if the list of groups for that wiki is non-empty. Therefore,
	 *     it's safe to check for the resulting array being non-empty to determine if the user has to have 2FA enabled
	 *     on any wiki on the farm.
	 */
	public function getGroupsRequiring2FAAcrossWikiFarm( UserIdentity $localUser ): array {
		$centralAuthLoaded = $this->extensionRegistry->isLoaded( 'CentralAuth' );

		if ( $centralAuthLoaded ) {
			$userName = $localUser->getName();
			$centralUser = CentralAuthUser::getInstanceByName( $userName );
			// T419772: Read attached accounts from replicas to avoid warnings about accounts
			// which are just being created. Any newly created accounts are probably not in any
			// interesting local groups, so this is fine.
			$attachments = $centralUser->queryAttached( IDBAccessObject::READ_NORMAL );

			// Only wikis where the user has any groups are of interest to us
			$attachments = array_filter(
				$attachments,
				static fn ( $attachment ) => count( $attachment['groupMemberships'] ) > 0
			);
			$identities = [];
			foreach ( $attachments as $attachment ) {
				$wikiId = $attachment['wiki'];
				if ( WikiMap::isCurrentWikiId( $wikiId ) ) {
					$wikiId = UserIdentity::LOCAL;
				}
				$identities[] = UserIdentityValue::newRegistered( $attachment['id'], $userName, $wikiId );
			}
		} else {
			$identities = [ $localUser ];
		}

		$groupsRequiring2FA = [];
		foreach ( $identities as $identity ) {
			$groupsForWiki = $this->getGroupsRequiring2FA( $identity );
			if ( !$groupsForWiki ) {
				continue;
			}
			$wikiId = $identity->getWikiId();
			if ( $wikiId === UserIdentity::LOCAL ) {
				$wikiId = WikiMap::getCurrentWikiId();
			}
			$groupsRequiring2FA[$wikiId] = $groupsForWiki;
		}

		if ( $centralAuthLoaded ) {
			$centralWiki = $this->config->get( 'CentralAuthCentralWiki' ) ?? WikiMap::getCurrentWikiId();
			$globalGroups = $this->getGlobalGroupsRequiring2FA( $localUser, $centralWiki );
			if ( $globalGroups ) {
				if ( isset( $groupsRequiring2FA[$centralWiki] ) ) {
					$groupsRequiring2FA[$centralWiki] = array_values( array_unique(
						array_merge( $groupsRequiring2FA[$centralWiki], $globalGroups )
					) );
				} else {
					$groupsRequiring2FA[$centralWiki] = $globalGroups;
				}
			}
		}

		return $groupsRequiring2FA;
	}

	/**
	 * For a given local user, returns their global groups that require 2FA.
	 * Must only be called when CentralAuth is loaded.
	 *
	 * @param UserIdentity $localUser User for whom to check global restricted groups. It will be matched to a
	 *     CentralAuthUser by name.
	 * @param string $centralWiki The central wiki ID.
	 * @return list<string> List of global groups the user is a member of that require 2FA.
	 */
	private function getGlobalGroupsRequiring2FA( UserIdentity $localUser, string $centralWiki ): array {
		$centralUser = CentralAuthUser::getInstanceByName( $localUser->getName() );
		$globalGroups = $centralUser->getGlobalGroups();

		if ( !$globalGroups ) {
			return [];
		}

		$groupRestrictions = $this->restrictedUserGroupConfigReader
			->getConfig( $centralWiki, GlobalGroupAssignmentService::RESTRICTION_SCOPE );

		$groupsRequiring2FA = [];
		foreach ( $globalGroups as $group ) {
			if ( !isset( $groupRestrictions[$group] ) ) {
				continue;
			}
			if ( $this->isUserRequiredToHave2FAToMeetConditions(
				$localUser, $groupRestrictions[$group]->getMemberConditions()
			) ) {
				$groupsRequiring2FA[] = $group;
			}
		}
		return $groupsRequiring2FA;
	}

	/**
	 * Checks whether the user is required to keep 2FA enabled to meet the given conditions.
	 * @param UserIdentity $user
	 * @param array $conditions The conditions for a group member, to check against the user.
	 * @return bool True when the conditions are met if the user has 2FA enabled and not met without 2FA,
	 *   false otherwise
	 */
	private function isUserRequiredToHave2FAToMeetConditions( UserIdentity $user, array $conditions ): bool {
		$evaluator = new UserRequirementsConditionEvaluatorWith2FAAssumption();
		$conditionChecker = $this->userRequirementsCheckerFactory->getCheckerWithCustomConditions(
			$this->userGroupManagerFactory->getUserGroupManager( $user->getWikiId() ),
			[ $evaluator ]
		);

		$conditionsUsed = $conditionChecker->extractConditions( $conditions );
		if ( !in_array( APCOND_OATH_HAS2FA, $conditionsUsed ) ) {
			// If the condition doesn't even use 2FA state, it's not relevant for mandatory 2FA.
			return false;
		}

		$evaluator->setAssumed2FAState( true );
		$resultWith2FA = $conditionChecker->recursivelyCheckCondition( $conditions, $user );

		$evaluator->setAssumed2FAState( false );
		$resultWithout2FA = $conditionChecker->recursivelyCheckCondition( $conditions, $user );

		return $resultWith2FA && !$resultWithout2FA;
	}
}
