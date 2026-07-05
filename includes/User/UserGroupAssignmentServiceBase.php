<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\User;

use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Permissions\Authority;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use Wikimedia\Timestamp\TimestampFormat as TS;

/**
 * This class represents a base for service that provides high-level operations on user groups.
 * It is responsible for common operations, such as validation and logging primitives.
 *
 * @since 1.46
 * @ingroup User
 */
abstract class UserGroupAssignmentServiceBase {

	public function __construct(
		private readonly HookRunner $hookRunner,
	) {
	}

	/**
	 * Returns the groups that the performer can add or remove from the target user.
	 * The result of this function is cached for the duration of the request.
	 * @param Authority $performer
	 * @param UserIdentity $target
	 * @param bool $evaluatePrivateConditionsForRestrictedGroups If true, all conditions for restricted groups will
	 *     be evaluated (including private). If false, private conditions will be left unevaluated and 'condition-met'
	 *     can be potentially null, if the result depends on private conditions.
	 * @return array [
	 *   'add' => [ addablegroups ],
	 *   'remove' => [ removablegroups ],
	 *   'restricted' => [ groupname => [
	 *     'condition-met' => ?bool,
	 *     'ignore-condition' => bool,
	 *     'message' => string
	 *   ] ]
	 *  ]
	 * @phan-return array{add:list<string>,remove:list<string>,restricted:array<string,array>}
	 */
	abstract public function getChangeableGroups(
		Authority $performer,
		UserIdentity $target,
		bool $evaluatePrivateConditionsForRestrictedGroups = true
	): array;

	/**
	 * Validates the requested changes to user groups and returns an array, specifying if some groups are unchangeable
	 * and for what reasons.
	 * @param Authority $performer
	 * @param UserIdentity $target
	 * @param list<string> $addGroups
	 * @param list<string> $removeGroups
	 * @param array<string, ?string> $newExpiries
	 * @param array<string, UserGroupMembership> $groupMemberships
	 * @return array<string, string> Map of user groups to the reason why they cannot be given, removed or updated,
	 *     keyed by the group names. The supported reasons are: 'rights', 'restricted', 'private-condition'.
	 */
	public function validateUserGroups(
		Authority $performer,
		UserIdentity $target,
		array $addGroups,
		array $removeGroups,
		array $newExpiries,
		array $groupMemberships,
	): array {
		// We have to find out which groups the user is unable to change and also whether it's due to
		// private conditions or not. In the former case, we need to be able to log access to the conditions.
		$permittedChangesNoPrivate = $this->getChangeableGroups( $performer, $target, false );
		$permittedChangesWithPrivate = $this->getChangeableGroups( $performer, $target );

		[ $unaddableNoPrivate, $irremovableNoPrivate ] = $this->getDisallowedGroupChanges(
			$addGroups, $removeGroups, $newExpiries, $groupMemberships, $permittedChangesNoPrivate );
		[ $unaddableWithPrivate, $irremovableWithPrivate ] = $this->getDisallowedGroupChanges(
			$addGroups, $removeGroups, $newExpiries, $groupMemberships, $permittedChangesWithPrivate );

		$unchangeableGroupsNoPrivate = array_merge( $unaddableNoPrivate, $irremovableNoPrivate );
		$unchangeableGroupsWithPrivate = array_merge( $unaddableWithPrivate, $irremovableWithPrivate );

		$unchangeableGroupsDueToPrivate = array_diff( $unchangeableGroupsWithPrivate, $unchangeableGroupsNoPrivate );

		$restrictedGroups = $permittedChangesWithPrivate['restricted'];
		$knownGroups = $this->getKnownGroups( $target );

		$result = [];
		foreach ( $unchangeableGroupsWithPrivate as $group ) {
			// Sometimes people are assigned to groups that no longer are defined. Let's ignore them for validation
			if ( !in_array( $group, $knownGroups ) ) {
				continue;
			}

			if ( in_array( $group, $unchangeableGroupsDueToPrivate ) ) {
				$result[$group] = 'private-condition';
			} elseif ( isset( $restrictedGroups[$group] ) ) {
				$result[$group] = 'restricted';
			} else {
				$result[$group] = 'rights';
			}
		}
		return $result;
	}

	/**
	 * Returns a list of possible known groups for a given identity. The result of this function is used to
	 * skip undefined pre-existing groups when validating group changes.
	 * @param UserIdentity $target The target user
	 * @return string[]
	 */
	abstract protected function getKnownGroups( UserIdentity $target ): array;

	/**
	 * Triggers a hook that allows extensions to log when user read some private conditions.
	 * @param Authority $performer The user who submitted request to change the user groups
	 * @param UserIdentity $target The user whose groups are changed
	 * @param list<string> $addGroups A list of groups that were attempted to be added (or have expiry changed)
	 * @param array<string, ?string> $newExpiries New expiration times for the groups (null or missing means infinity)
	 * @param array<string, UserGroupMembership> $existingUGMs Existing group memberships for the target user
	 * @return void
	 */
	public function logAccessToPrivateConditions(
		Authority $performer,
		UserIdentity $target,
		array $addGroups,
		array $newExpiries,
		array $existingUGMs,
	): void {
		// Potentially changeable - groups that might be changed if it weren't for private conditions
		$potentiallyChangeable = $this->getChangeableGroups( $performer, $target, false );
		$conditions = $this->getPrivateConditionsInvolvedInChange(
			$target,
			$addGroups,
			$newExpiries,
			$existingUGMs,
			$potentiallyChangeable
		);

		if ( !$conditions ) {
			return;
		}

		$this->hookRunner->onReadPrivateUserRequirementsCondition( $performer->getUser(), $target, $conditions );
	}

	/**
	 * For added or prolonged groups, returns a list of private conditions that the groups depends on.
	 * @param UserIdentity $target The user whose rights were changed (used to load the restriction
	 *     configuration from a proper wiki)
	 * @param list<string> $addGroups
	 * @param array<string, ?string> $newExpiries
	 * @param array<string, UserGroupMembership> $existingUGMs
	 * @param array $potentiallyChangeableGroups Information about changeable groups without evaluating private
	 *     conditions.
	 * @return list<mixed>
	 */
	private function getPrivateConditionsInvolvedInChange(
		UserIdentity $target,
		array $addGroups,
		array $newExpiries,
		array $existingUGMs,
		array $potentiallyChangeableGroups,
	): array {
		$restrictedGroups = $potentiallyChangeableGroups['restricted'];
		$groupsWithPrivateConditionsInvolved = [];
		foreach ( $restrictedGroups as $group => $groupData ) {
			if ( $groupData['condition-met'] === null && !$groupData['ignore-condition'] ) {
				$groupsWithPrivateConditionsInvolved[] = $group;
			}
		}

		$groupsToCheck = [];
		foreach ( $addGroups as $group ) {
			// If a group is for sure unaddable or addable, private conditions don't matter, so we don't
			// consider them as involved into the change
			if ( !in_array( $group, $groupsWithPrivateConditionsInvolved ) ) {
				continue;
			}

			// Ensure that we only test groups that are added or prolonged (conditions don't apply for
			// removals from groups)
			if ( !isset( $existingUGMs[$group] ) ) {
				$groupsToCheck[] = $group;
				continue;
			}
			$currentExpiry = $existingUGMs[$group]->getExpiry() ?? 'infinity';
			$newExpiry = $newExpiries[$group] ?? 'infinity';

			if ( $newExpiry > $currentExpiry ) {
				$groupsToCheck[] = $group;
			}
		}

		$privateConditions = [];
		$restrictedGroupChecker = $this->getRestrictedGroupChecker( $target );
		foreach ( $groupsToCheck as $group ) {
			$privateConditions = array_merge(
				$privateConditions,
				$restrictedGroupChecker->getPrivateConditionsForGroup( $group )
			);
		}
		return array_values( array_unique( $privateConditions ) );
	}

	/**
	 * Returns an instance of {@see RestrictedUserGroupChecker} suitable for use with the target user.
	 */
	abstract protected function getRestrictedGroupChecker( UserIdentity $target ): RestrictedUserGroupChecker;

	/**
	 * Ensures that the content of $addGroups, $removeGroups and $newExpiries is compliant
	 * with the possible changes defined in $permittedChanges. If there's a change that
	 * is not permitted, it is removed from the respective array.
	 * @param list<string> &$addGroups
	 * @param list<string> &$removeGroups
	 * @param array<string, ?string> &$newExpiries
	 * @param array<string, UserGroupMembership> $existingUGMs
	 * @param array{add:list<string>,remove:list<string>} $permittedChanges
	 * @return void
	 */
	public static function enforceChangeGroupPermissions(
		array &$addGroups,
		array &$removeGroups,
		array &$newExpiries,
		array $existingUGMs,
		array $permittedChanges
	): void {
		[ $unaddableGroups, $irremovableGroups ] = self::getDisallowedGroupChanges(
			$addGroups, $removeGroups, $newExpiries, $existingUGMs, $permittedChanges
		);

		$addGroups = array_diff( $addGroups, $unaddableGroups );
		$removeGroups = array_diff( $removeGroups, $irremovableGroups );
		foreach ( $unaddableGroups as $group ) {
			unset( $newExpiries[$group] );
		}
	}

	/**
	 * Returns which of the attempted group changed are not allowed, given $permittedChanges.
	 * @param list<string> $addGroups
	 * @param list<string> $removeGroups
	 * @param array<string, ?string> $newExpiries
	 * @param array<string, UserGroupMembership> $existingUGMs
	 * @param array{add:list<string>,remove:list<string>} $permittedChanges
	 * @return array{0:list<string>,1:list<string>} List of unaddable groups and list of irremovable groups
	 */
	private static function getDisallowedGroupChanges(
		array $addGroups,
		array $removeGroups,
		array $newExpiries,
		array $existingUGMs,
		array $permittedChanges
	): array {
		$canAdd = $permittedChanges['add'];
		$canRemove = $permittedChanges['remove'];
		$involvedGroups = array_unique( array_merge( array_keys( $existingUGMs ), $addGroups, $removeGroups ) );

		// These do not reflect actual permissions, but rather the groups to remove from $addGroups and $removeGroups
		$unaddableGroups = [];
		$irremovableGroups = [];

		foreach ( $involvedGroups as $group ) {
			$hasGroup = isset( $existingUGMs[$group] );
			$wantsAddGroup = in_array( $group, $addGroups );
			$wantsRemoveGroup = in_array( $group, $removeGroups );

			// Better safe than sorry - catch it if the input is contradictory
			if (
				( !$hasGroup && $wantsRemoveGroup ) ||
				( $wantsAddGroup && $wantsRemoveGroup )
			) {
				$unaddableGroups[] = $group;
				$irremovableGroups[] = $group;
				continue;
			}
			// If there's no change, we don't have to change anything
			if ( !$hasGroup && !$wantsAddGroup ) {
				continue;
			}
			if ( $hasGroup && !$wantsRemoveGroup && !$wantsAddGroup ) {
				// We have to check for adding group, because it's set when changing expiry
				continue;
			}

			if ( $hasGroup && $wantsRemoveGroup ) {
				if ( !in_array( $group, $canRemove ) ) {
					$irremovableGroups[] = $group;
				}
			} elseif ( !$hasGroup && $wantsAddGroup ) {
				if ( !in_array( $group, $canAdd ) ) {
					$unaddableGroups[] = $group;
				}
			} elseif ( $hasGroup && $wantsAddGroup ) {
				$currentExpiry = $existingUGMs[$group]->getExpiry() ?? 'infinity';
				$wantedExpiry = $newExpiries[$group] ?? 'infinity';

				if ( $wantedExpiry > $currentExpiry ) {
					// Prolongation requires 'add' permission
					$canChange = in_array( $group, $canAdd );
				} else {
					// Shortening requires 'remove' permission
					$canChange = in_array( $group, $canRemove );
				}

				if ( !$canChange ) {
					// Restore the original group expiry if user can't change it
					$unaddableGroups[] = $group;
				}
			}
		}

		return [ $unaddableGroups, $irremovableGroups ];
	}

	/**
	 * Converts a user group membership expiry string into a timestamp. Words like
	 * 'existing' or 'other' should have been filtered out before calling this
	 * function.
	 *
	 * @param string $expiry
	 * @return string|null|false A string containing a valid timestamp, or null
	 *   if the expiry is infinite, or false if the timestamp is not valid
	 */
	public static function expiryToTimestamp( $expiry ) {
		if ( wfIsInfinity( $expiry ) ) {
			return null;
		}

		$unix = strtotime( $expiry, ConvertibleTimestamp::time() );

		if ( !$unix || $unix === -1 ) {
			return false;
		}

		// @todo FIXME: Non-qualified absolute times are not in users specified timezone
		// and there isn't notice about it in the ui (see ProtectionForm::getExpiry)
		return wfTimestamp( TS::MW, $unix );
	}
}
