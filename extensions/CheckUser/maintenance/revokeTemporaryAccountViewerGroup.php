<?php

namespace MediaWiki\Extension\CheckUser\Maintenance;

use MediaWiki\Context\RequestContext;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupAssignmentService;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Psr\Log\LoggerInterface;
use Wikimedia\Timestamp\ConvertibleTimestamp;

// @codeCoverageIgnoreStart
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname( __DIR__, 3 );
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Revokes 'temporary-account-viewer' group membership from users who
 * have been inactive for a specified amount of time.
 *
 * Activity is defined as:
 *   - an undeleted revision
 *   - a logged action
 */
class RevokeTemporaryAccountViewerGroup extends Maintenance {

	protected LoggerInterface $logger;
	protected UserGroupManager $userGroupManager;
	protected UserIdentityLookup $userIdentityLookup;
	protected UserGroupAssignmentService $userGroupAssignmentService;

	protected UltimateAuthority $maintenanceScriptAuthority;

	public function __construct() {
		parent::__construct();

		$this->addDescription(
			'Revoke the temporary-account-viewer group membership ' .
			'of users who have been inactive for over N days'
		);
		$this->addOption(
			'expiry',
			'Revoke the group membership of accounts that haven\'t been active in N days. ' .
			'If you need to remove everyone from the group, use emptyUserGroup.php instead.',
			true,
			true
		);
		$this->addOption( 'verbose', 'Verbose logging output' );
		$this->setBatchSize( 100 );
	}

	/**
	 * Construct services the script needs to use
	 */
	private function initServices(): void {
		$services = $this->getServiceContainer();

		$this->logger = LoggerFactory::getInstance( 'CheckUser' );
		$this->userGroupManager = $services->getUserGroupManager();
		$this->userIdentityLookup = $services->getUserIdentityLookup();
		$this->userGroupAssignmentService = $services->getUserGroupAssignmentService();
	}

	/**
	 * If --verbose is passed, log to output
	 *
	 * @param string $log
	 * @return void
	 */
	private function verboseLog( string $log ) {
		if ( $this->hasOption( 'verbose' ) ) {
			$this->output( $log );
		}
	}

	/**
	 * @return int|bool true if success, user id to be marked if failure
	 */
	private function revokeTempAccountViewerGroupMembership( UserIdentity $userIdentity ) {
		// Remove user from group
		$context = RequestContext::getMain();
		$revokedMembership = $this->userGroupAssignmentService->saveChangesToUserGroups(
			$this->maintenanceScriptAuthority,
			$userIdentity,
			[],
			[ 'temporary-account-viewer' ],
			[],
			$context->msg( 'checkuser-temporary-account-autorevoke-userright-reason' )->text()
		);

		// saveChangesToUserGroups() returns the removed groups in the second array. If it's empty, there was a problem.
		if (
			!count( $revokedMembership[1] ) ||
			!in_array( 'temporary-account-viewer', $revokedMembership[1] )
		) {
			$this->error( 'Problem revoking membership for ' . $userIdentity->getName() );
			$this->logger->error( 'Problem revoking temporary-account-viewer membership for {user}', [
				'user' => $userIdentity->getName(),
			] );
			return $userIdentity->getId();
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->initServices();

		// Prepare for revocations:
		// Get the expiry timestamp to search against
		$dbr = $this->getReplicaDB();
		$expiryAfterDays = $this->getOption( 'expiry' );
		if ( !is_numeric( $expiryAfterDays ) ) {
			$this->fatalError( 'Expiry value must be a number' );
		}
		$expiryAfterDays = (int)$expiryAfterDays;
		$expiryTimestamp = $dbr->timestamp( ConvertibleTimestamp::time() - ( 86_400 * $expiryAfterDays ) );

		// Get the system user to use as the performer for SpecialUserRights. It needs to be
		// an UltimateAuthority so that it has the right to remove group membership.
		$maintUser = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
		if ( !$maintUser ) {
			$this->fatalError( 'Maintenance script user not acquired but required to continue' );
		}
		$this->maintenanceScriptAuthority = new UltimateAuthority( $maintUser );

		$maxUserId = 0;
		$revokeCount = 0;
		$skippedUsers = [];
		do {
			// Get users with 'temporary-account-viewer' membership that should be revoked
			// by doing a left join on revision and logging with the expiry range.
			// This works by setting null on all rows in the revision/logging tables
			// that don't match the range which can then be filtered for. Any row
			// with a valid timestamp would indicate activity from the user.
			$userQuerySelector = $dbr->newSelectQueryBuilder()
				->select( 'ug_user' )
				->distinct()
				->from( 'user_groups' )
				->where( $dbr->expr( 'ug_user', '>', $maxUserId ) )
				->join( 'actor', null, 'ug_user=actor_user' )
				->orderBy( 'ug_user' )
				->leftJoin( 'revision', null, [
					'actor_id=rev_actor',
					$dbr->expr( 'rev_timestamp', '>', $expiryTimestamp ),
				] )
				->leftJoin( 'logging', null, [
					'actor_id=log_actor',
					$dbr->expr( 'log_timestamp', '>', $expiryTimestamp ),
				] )
				->where( [
					'ug_group' => 'temporary-account-viewer',
					$dbr->expr( 'ug_expiry', '>', $dbr->timestamp() )
						->or( 'ug_expiry', '=', null ),
					'rev_timestamp' => null,
					'log_timestamp' => null,
				] )
				->limit( $this->getBatchSize() );

			// Ignore users that have been attempted but for whatever reason were marked as failures
			if ( count( $skippedUsers ) ) {
				$userQuerySelector->andWhere( $dbr->expr( 'ug_user', '!=', $skippedUsers ) );
			}
			$usersToRevokeGroupMembershipFrom = $userQuerySelector
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $usersToRevokeGroupMembershipFrom as $userRow ) {
				$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( (int)$userRow->ug_user );
				if ( !$userIdentity ) {
					$this->error( 'Actor row found but no user identity obtainable for ' . $userRow->ug_user );
					$this->logger->error( 'Actor row found but no user identity obtainable for {userId}', [
						'userId' => $userRow->ug_user,
					] );
					$skippedUsers[] = $userRow->ug_user;
					continue;
				}
				$revokeStatus = $this->revokeTempAccountViewerGroupMembership( $userIdentity );
				if ( $revokeStatus === true ) {
					$revokeCount++;
					$this->verboseLog(
						'Removed ' . $userIdentity->getName() . ' from temporary-account-viewer group' . PHP_EOL
					);
				} else {
					$skippedUsers[] = $revokeStatus;
				}
				$maxUserId = (int)$userRow->ug_user;
			}
			$this->waitForReplication();
		} while ( $usersToRevokeGroupMembershipFrom->numRows() );

		$this->output( "Removed $revokeCount user(s) from temporary-account-viewer group." . PHP_EOL );
		if ( count( $skippedUsers ) ) {
			$this->output( "Attempted and failed to remove user(s) " . implode( ',', $skippedUsers ) .
			' from temporary-account-viewer group.' . PHP_EOL );
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = RevokeTemporaryAccountViewerGroup::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
