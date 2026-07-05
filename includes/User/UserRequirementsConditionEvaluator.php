<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\User;

use LogicException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\MainConfigNames;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Permissions\GroupPermissionsLookup;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Timestamp\TimestampFormat as TS;

class UserRequirementsConditionEvaluator extends UserRequirementsConditionEvaluatorBase {

	/** @internal For use by UserRequirementsConditionCheckerFactory */
	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::AutoConfirmAge,
		MainConfigNames::AutoConfirmCount,
		MainConfigNames::EmailAuthentication,
	];

	/**
	 * @internal For use preventing an infinite loop when checking APCOND_BLOCKED
	 * @var array An assoc. array mapping the getCacheKey userKey to a bool indicating
	 * an ongoing condition check.
	 */
	private array $recursionMap = [];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly GroupPermissionsLookup $groupPermissionsLookup,
		private readonly UserEditTracker $userEditTracker,
		private readonly UserRegistrationLookup $userRegistrationLookup,
		private readonly UserFactory $userFactory,
		private readonly IContextSource $context,
		private readonly UserGroupManager $userGroupManager,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/** @inheritDoc */
	public function checkCondition(
		string|int $conditionType,
		array $args,
		UserIdentity $user,
		bool $isPerformingRequest
	): ?bool {
		// Some checks depend on hooks or other dynamically-determined state, so we can fetch them only
		// for the local wiki and not for remote users. The latter may require API requests to the remote
		// wiki, which has not been implemented for now due to performance concerns.
		$isCurrentWiki = ( $user->getWikiId() === false ) || WikiMap::isCurrentWikiId( $user->getWikiId() );

		switch ( $conditionType ) {
			case APCOND_EMAILCONFIRMED:
				if ( !$isCurrentWiki ) {
					return false;
				}
				$userObject = $this->userFactory->newFromUserIdentity( $user );
				return Sanitizer::validateEmail( $userObject->getEmail() ) &&
					( !$this->options->get( MainConfigNames::EmailAuthentication ) ||
						$userObject->getEmailAuthenticationTimestamp() );
			case APCOND_EDITCOUNT:
				$reqEditCount = $args[0] ?? $this->options->get( MainConfigNames::AutoConfirmCount );

				// T157718: Avoid edit count lookup if the specified edit count is 0 or invalid
				if ( $reqEditCount <= 0 ) {
					return true;
				}
				return (int)$this->userEditTracker->getUserEditCount( $user ) >= $reqEditCount;
			case APCOND_AGE:
				$reqAge = $args[0] ?? $this->options->get( MainConfigNames::AutoConfirmAge );
				if ( $reqAge <= 0 ) {
					return true;
				}
				$registration = $this->userRegistrationLookup->getRegistration( $user );
				$age = time() - (int)wfTimestampOrNull( TS::UNIX, $registration );
				return $age >= $reqAge;
			case APCOND_AGE_FROM_EDIT:
				$reqAge = $args[0] ?? $this->options->get( MainConfigNames::AutoConfirmAge );
				if ( $reqAge <= 0 ) {
					return true;
				}
				$firstEdit = $this->userEditTracker->getFirstEditTimestamp( $user );
				if ( $firstEdit === false ) {
					// If the user has no edits, then they don't meet the requirement.
					return false;
				}
				$age = time() - (int)wfTimestampOrNull( TS::UNIX, $firstEdit );
				return $age >= $reqAge;
			case APCOND_INGROUPS:
				if ( !$isCurrentWiki ) {
					return false;
				}
				return count( array_intersect(
						$args,
						$this->userGroupManager->getUserGroups( $user )
					) ) === count( $args );
			case APCOND_ISIP:
				// Since the IPs are not permanently bound to users, the IP conditions can only be checked
				// for the requesting user. Otherwise, assume the condition is false.
				return $isPerformingRequest && $args[0] === $this->context->getRequest()->getIP();
			case APCOND_IPINRANGE:
				return $isPerformingRequest && IPUtils::isInRange( $this->context->getRequest()->getIP(), $args[0] );
			case APCOND_BLOCKED:
				if ( !$isCurrentWiki ) {
					// This condition is more likely to be used as "! APCOND_BLOCKED", so ensure it can't be bypassed
					// when tested from a remote wiki.
					return true;
				}
				// Because checking for ipblock-exempt leads back to here (thus infinite recursion),
				// we if we've been here before for this user without having returned a value.
				// See T270145 and T349608
				$userKey = $this->getCacheKey( $user );
				if ( $this->recursionMap[$userKey] ?? false ) {
					throw new LogicException(
						"Unexpected recursion! APCOND_BLOCKED is being checked during" .
						" an existing APCOND_BLOCKED check for \"{$user->getName()}\"!"
					);
				}
				$this->recursionMap[$userKey] = true;
				// Setting the second parameter here to true prevents us from getting back here
				// during standard MediaWiki core behavior
				$userObject = $this->userFactory->newFromUserIdentity( $user );
				$block = $userObject->getBlock( IDBAccessObject::READ_LATEST, true );
				$this->recursionMap[$userKey] = false;

				return (bool)$block?->isSitewide();
			case APCOND_ISBOT:
				if ( !$isCurrentWiki ) {
					return false;
				}
				return in_array( 'bot', $this->groupPermissionsLookup
					->getGroupPermissions(
						$this->userGroupManager->getUserGroups( $user )
					)
				);
			default:
				return null;
		}
	}

	/** Gets a unique key for various caches. */
	private function getCacheKey( UserIdentity $user ): string {
		// As long as it's for in-memory caches, identifying users by name is fine
		return $user->getWikiId() . ':' . $user->getName();
	}
}
