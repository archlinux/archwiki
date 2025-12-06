<?php

namespace MediaWiki\CheckUser\Services;

use GrowthExperiments\UserImpact\UserImpactLookup;
use InvalidArgumentException;
use MediaWiki\Cache\GenderCache;
use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\LocalUserNotFoundException;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\GlobalBlocking\GlobalBlockingServices;
use MediaWiki\Extension\GlobalBlocking\Services\GlobalBlockLookup;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Logging\LogPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Message\ListType;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * A service for methods that interact with user info card components
 */
class CheckUserUserInfoCardService {

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserUserInfoCardCentralWikiId',
		'CUDMaxAge',
	];

	private const GLOBAL_RESTRICTIONS_LOCKED = 'locked';
	private const GLOBAL_RESTRICTIONS_BLOCKED = 'blocked';
	private const GLOBAL_RESTRICTIONS_BLOCKED_DISABLED = 'blocked-disabled';

	public function __construct(
		private readonly ?UserImpactLookup $userImpactLookup,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly UserRegistrationLookup $userRegistrationLookup,
		private readonly UserGroupManager $userGroupManager,
		private readonly ?CheckUserGlobalContributionsLookup $globalContributionsLookup,
		private readonly IConnectionProvider $dbProvider,
		private readonly StatsFactory $statsFactory,
		private readonly CheckUserPermissionManager $checkUserPermissionManager,
		private readonly UserFactory $userFactory,
		private readonly UserEditTracker $userEditTracker,
		private readonly CheckUserTemporaryAccountsByIPLookup $checkUserTemporaryAccountsByIPLookup,
		private readonly IContextSource $context,
		private readonly TitleFactory $titleFactory,
		private readonly GenderCache $genderCache,
		private readonly TempUserConfig $tempUserConfig,
		private readonly ServiceOptions $options,
		private readonly CentralIdLookup $centralIdLookup
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Check if the user's local or global page is known
	 *
	 * @param UserIdentity $user
	 * @return bool
	 */
	private function userPageIsKnown( UserIdentity $user ): bool {
		$userPageTitle = $this->titleFactory->makeTitle( NS_USER, $user->getName() );
		return $userPageTitle->isKnown();
	}

	/**
	 * This function is a light shim for UserImpactLookup->getUserImpact.
	 *
	 * @param UserIdentity $user
	 * @return array Array of data points related to the user pulled from the UserImpact
	 * 				 or an empty array if no user impact data can be found
	 */
	private function getDataFromUserImpact( UserIdentity $user ): array {
		$userData = [];
		// GrowthExperiments UserImpactLookup service is unavailable, don't attempt to
		// retrieve data from it (T394070)
		if ( !$this->userImpactLookup ) {
			return [];
		}

		$userImpact = $this->userImpactLookup->getUserImpact( $user );
		// Function is not guaranteed to return a UserImpact
		if ( !$userImpact ) {
			return $userData;
		}

		$userData['totalEditCount'] = $userImpact->getTotalEditsCount();
		$userData['thanksGiven'] = $userImpact->getGivenThanksCount();
		$userData['thanksReceived'] = $userImpact->getReceivedThanksCount();
		$userData['editCountByDay'] = $userImpact->getEditCountByDay();
		$userData['revertedEditCount'] = $userImpact->getRevertedEditCount();
		// If the number of edits is at or over GEUserImpactMaxEdits, then don't attempt to show
		// reverted edit count (T401466)
		if ( $userData['totalEditCount'] >= 1_000 ) {
			$userData['revertedEditCount'] = null;
		}
		$userData['newArticlesCount'] = $userImpact->getTotalArticlesCreatedCount();
		if ( !$this->shouldShowNewArticlesCount( $user, $userImpact->getTotalEditsCount() ) ) {
			// FIXME: Temporary workaround for T399096
			$userData['newArticlesCount'] = null;
		}
		$userData['lastEditTimestamp'] = wfTimestampOrNull(
			TS_MW,
			$userImpact->getLastEditTimestamp()
		);

		return $userData;
	}

	private function shouldShowNewArticlesCount( UserIdentity $userIdentity, int $editCount ): bool {
		$user = $this->userFactory->newFromUserIdentity( $userIdentity );
		if ( !$user->getRegistration() ) {
			// Old account, no registration date, hide the new articles count
			return false;
		}
		if ( $user->getRegistration() <= '20180701000000' ) {
			// Account registered before July 2018, when page creations were first logged,
			// hide the new articles count
			return false;
		}
		if ( $editCount >= 1_000 ) {
			// Assume the user has more than 1K edits/log entries, which can make
			// the GrowthExperiments UserImpact query inaccurate.
			return false;
		}
		return true;
	}

	/**
	 * @param Authority $authority
	 * @param UserIdentity $user
	 * @return array array containing aggregated user information
	 */
	public function getUserInfo( Authority $authority, UserIdentity $user ): array {
		if ( !$user->isRegistered() ) {
			return [];
		}
		// If the authority can't view hidden user, and the user is hidden,
		// don't return anything.
		if ( !$authority->isAllowed( 'hideuser' ) &&
			$this->userFactory->newFromUserIdentity( $user )->isHidden()
		) {
			return [];
		}
		$start = microtime( true );
		$userInfo = $this->getDataFromUserImpact( $user );
		$hasUserImpactData = count( $userInfo );

		$userInfo['name'] = $user->getName();
		$userInfo['gender'] = $this->genderCache->getGenderOf( $user );
		$userInfo['localRegistration'] = $this->userRegistrationLookup->getRegistration( $user );
		$userInfo['firstRegistration'] = $this->userRegistrationLookup->getFirstRegistration( $user );
		$userInfo['userPageIsKnown'] = $this->userPageIsKnown( $user );

		$groups = $this->userGroupManager->getUserGroups( $user );
		sort( $groups );
		$groupMessages = [];
		foreach ( $groups as $group ) {
			if ( $this->context->msg( "group-$group" )->exists() ) {
				$groupMessages[] = $this->context->msg( "group-$group" )->escaped();
			}
		}
		$userInfo['groups'] = '';
		if ( $groupMessages ) {
			$userInfo['groups'] = $this->context->msg( 'checkuser-userinfocard-groups' )
				->params( Message::listParam( $groupMessages, ListType::COMMA ) )
				->parse();
		}

		if ( !isset( $userInfo['totalEditCount'] ) ) {
			$userInfo['totalEditCount'] = $this->userEditTracker->getUserEditCount( $user );
		}

		if ( $this->shouldIncludeIPRevealLogData( $authority, $user ) ) {
			$userInfo += $this->getIPRevealLogData( $authority, $user );
		}

		$userInfo['globalRestrictions'] = null;
		$userInfo['globalRestrictionsTimestamp'] = null;
		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$centralAuthUser = CentralAuthUser::getInstance( $user );
			$userInfo['globalEditCount'] = $centralAuthUser->isAttached() ? $centralAuthUser->getGlobalEditCount() : 0;
			$globalGroups = $centralAuthUser->getGlobalGroups();
			sort( $globalGroups );
			$globalGroupMessages = [];
			foreach ( $globalGroups as $group ) {
				if ( $this->context->msg( "group-$group" )->exists() ) {
					$globalGroupMessages[] = $this->context->msg( "group-$group" )
						->escaped();
				}
			}
			$userInfo['globalGroups'] = '';
			if ( $globalGroupMessages ) {
				$userInfo['globalGroups'] = $this->context->msg( 'checkuser-userinfocard-global-groups' )
					->params( Message::listParam( $globalGroupMessages, ListType::COMMA ) )
					->parse();
			}

			if ( $centralAuthUser->isLocked() ) {
				$userInfo['globalRestrictions'] = self::GLOBAL_RESTRICTIONS_LOCKED;

				// We don't know what permissions the user has on the central wiki,
				// so we check whether this is the central wiki. If not, assume we don't
				// have any special permissions to view hidden parts of the log entries.
				$localWikiId = WikiMap::getCurrentWikiId();
				$centralWikiId = $this->options->get( 'CheckUserUserInfoCardCentralWikiId' );

				$userStatusLookup = CentralAuthServices::getUserStatusLookupFactory()
					->getLookupService( $centralWikiId );

				$lookupAuthority = $authority;
				if ( $localWikiId !== $centralWikiId && $centralWikiId !== false ) {
					$lookupAuthority = new SimpleAuthority( $authority->getUser(), [], $authority->isTemp() );
				}
				$userInfo['globalRestrictionsTimestamp'] =
					$userStatusLookup->getUserLockedTimestamp( $centralAuthUser->getName(), $lookupAuthority );
			}
		}

		if (
			// Locked users can't log in, so blocks have no effect on them
			$userInfo['globalRestrictions'] === null
			&& $this->extensionRegistry->isLoaded( 'GlobalBlocking' )
		) {
			$centralId = $this->centralIdLookup->centralIdFromLocalUser( $user, $authority );
			$globalBlockingServices = GlobalBlockingServices::wrap( MediaWikiServices::getInstance() );
			$globalBlockRow = $globalBlockingServices->getGlobalBlockLookup()
				->getGlobalBlockingBlock( null, $centralId, GlobalBlockLookup::SKIP_LOCAL_DISABLE_CHECK );

			// By default, trust `centralIdFromLocalUser` to take care of permissions
			$canSee = true;
			if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
				// However, CentralAuth ignores authority when performing central id lookup, so ensure that
				// we don't leak information about the user if it's hidden and authority doesn't have the right
				// to see hidden users
				// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
				$canSee = !$centralAuthUser->isHidden() || $authority->isAllowed( 'centralauth-suppress' );
			}

			if ( $globalBlockRow && $canSee ) {
				$localBlockStatus = $globalBlockingServices->getGlobalBlockLocalStatusLookup()
					->getLocalStatusInfo( $globalBlockRow->gb_id );
				$isLocallyDisabled = $localBlockStatus !== false;

				$userInfo['globalRestrictions'] = $isLocallyDisabled ?
					self::GLOBAL_RESTRICTIONS_BLOCKED_DISABLED :
					self::GLOBAL_RESTRICTIONS_BLOCKED;
				$userInfo['globalRestrictionsTimestamp'] = $globalBlockRow->gb_timestamp;
			}
		}

		$userInfo['activeWikis'] = [];
		if ( $this->globalContributionsLookup instanceof CheckUserGlobalContributionsLookup ) {
			$activeWikisStart = microtime( true );

			// Be consistent with what's displayed on Special:GlobalContributions and use the same
			// cutoff for user activity
			$checkUserDataCutoff = ConvertibleTimestamp::time() - $this->options->get( 'CUDMaxAge' );
			$checkUserDataCutoff = ConvertibleTimestamp::convert( TS_MW, $checkUserDataCutoff );

			try {
				$activeWikiIds = $this->globalContributionsLookup->getActiveWikisVisibleToUser(
					$user->getName(), $authority, $this->context->getRequest(), $checkUserDataCutoff
				);
			} catch ( InvalidArgumentException ) {
				// No central user found or viewable, assume that the user is not active on any wiki
				$activeWikiIds = [];
			}

			sort( $activeWikiIds );
			foreach ( $activeWikiIds as $wikiId ) {
				$wiki = WikiMap::getWiki( $wikiId );
				if ( !$wiki ) {
					continue;
				}
				$userInfo['activeWikis'][$wikiId] = $wiki->getUrl(
					'Special:Contributions/' . $this->getUserTitleKey( $user )
				);
			}
			$this->statsFactory->withComponent( 'CheckUser' )
				->getTiming( 'userinfocardservice_active_wikis' )
				->observe( ( microtime( true ) - $activeWikisStart ) * 1000 );
		}

		$dbr = $this->dbProvider->getReplicaDatabase();
		if ( $authority->isAllowed( 'checkuser-log' ) ) {
			$row = $dbr->newSelectQueryBuilder()
				->select( [
					'count' => 'COUNT(*)',
					'recent_ts' => 'MAX(cul_timestamp)',
				] )
				->from( 'cu_log' )
				->where( [ 'cul_target_id' => $user->getId() ] )
				->caller( __METHOD__ )
				->fetchRow();

			$numChecks = (int)$row->count;
			$userInfo['checkUserChecks'] = $numChecks;
			if ( $numChecks > 0 ) {
				$userInfo['checkUserLastCheck'] = $row->recent_ts;
			}
		}

		$blocks = [];
		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			try {
				$centralAuthUser = CentralAuthUser::getInstance( $user );
				$blocks = $centralAuthUser->getBlocks();
			} catch ( LocalUserNotFoundException ) {
				LoggerFactory::getInstance( 'CheckUser' )->info(
					'Unable to get CentralAuthUser for user {user}', [
						'user' => $user->getName(),
					]
				);
			}
		}
		$userInfo['activeLocalBlocksAllWikis'] = array_sum( array_map( 'count', $blocks ) );

		$logActionRestrictions = $this->getLogActionRestrictions( $authority, $dbr );
		$blockLogEntriesCount = $dbr->newSelectQueryBuilder()
			->select( 'log_id' )
			->from( 'logging' )
			->where(
				array_merge( [
					'log_type' => 'block',
					'log_action' => 'block',
					'log_namespace' => NS_USER,
					'log_title' => $this->getUserTitleKey( $user ),
				], $logActionRestrictions )
			)
			->caller( __METHOD__ )
			->fetchRowCount();
		if ( $authority->isAllowed( 'suppressionlog' ) ) {
			$blockLogEntriesCount += $dbr->newSelectQueryBuilder()
				->select( 'log_id' )
				->from( 'logging' )
				->where(
					array_merge( [
						'log_type' => 'suppress',
						'log_action' => 'block',
						'log_namespace' => NS_USER,
						'log_title' => $this->getUserTitleKey( $user ),
					], $logActionRestrictions )
				)
				->caller( __METHOD__ )
				->fetchRowCount();
		}
		// Subtract the count of active local blocks (local blocks are on the 0 index, set by CentralAuthUser) to get
		// the past blocks count.
		// In case the user doesn't have suppressionlog rights, ensure that the value displayed here is at least 0.
		$userInfo['pastBlocksOnLocalWiki'] = max( 0, $blockLogEntriesCount - count( $blocks[0] ?? [] ) );

		$authorityPermissionStatus =
			$this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses( $authority );
		$userPermissionStatus = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses(
			$this->userFactory->newFromUserIdentity( $user )
		);

		$userInfo['canAccessTemporaryAccountIpAddresses'] = $authorityPermissionStatus->isGood() &&
			$userPermissionStatus->isGood();

		// Generate a URL to the Special:CentralAuth page for the user being viewed, preferring to have the
		// URL be on a central wiki if one is defined.
		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$centralWikiId = $this->options->get( 'CheckUserUserInfoCardCentralWikiId' );
			if ( $centralWikiId ) {
				$centralWiki = WikiMap::getWiki( $centralWikiId );
				if ( $centralWiki ) {
					$userInfo['specialCentralAuthUrl'] = $centralWiki->getURL(
						'Special:CentralAuth/' . $this->getUserTitleKey( $user )
					);
				}
			}

			$userInfo['specialCentralAuthUrl'] ??= SpecialPage::getTitleFor(
				'CentralAuth', $this->getUserTitleKey( $user )
			)->getLinkURL();
		}

		// If the user is a temporary account, get the number of accounts active on the same IPs/ranges
		$userInfo['tempAccountsOnIPCount'] = [];
		if ( $this->tempUserConfig->isTempName( $user->getName() ) ) {
			// 11 is the maximum number of accounts we care about as defined by T388718
			$bucketCount = $this->checkUserTemporaryAccountsByIPLookup->getBucketedCount(
				$this->checkUserTemporaryAccountsByIPLookup
					->getAggregateActiveTempAccountCount( $user, 11 )
			);
			$userInfo['tempAccountsOnIPCount'] = $bucketCount;
		}

		$this->statsFactory->withComponent( 'CheckUser' )
			->getTiming( 'userinfocardservice_get_user_info' )
			->setLabel( 'with_user_impact', $hasUserImpactData ? '1' : '0' )
			->observe( ( microtime( true ) - $start ) * 1000 );

		return $userInfo;
	}

	/**
	 * Checks if the Info Card should include the IP Reveals count and the
	 * timestamp of the last check for a given user.
	 *
	 * IP Reveal data will be included if the wiki has temporary accounts
	 * enabled, the target user is a temporary account and the performing
	 * authority can access temporary account IP Addresses or has the
	 * 'checkuser-temporary-account-log' permission.
	 *
	 * @param Authority $performer The user the Info Card is being shown to.
	 * @param UserIdentity $target The user the Info Card shows info for.
	 *
	 * @return bool True if IP Reveal data should be included, false otherwise.
	 */
	private function shouldIncludeIPRevealLogData(
		Authority $performer,
		UserIdentity $target
	): bool {
		if ( !$this->tempUserConfig->isEnabled() ||
			 !$this->tempUserConfig->isTempName( $target->getName() ) ) {
			return false;
		}

		$status = $this->checkUserPermissionManager
			->canAccessTemporaryAccountIPAddresses( $performer );

		if ( !$status->isGood() ) {
			// Users with access to the IP Reveal log can list who accessed the
			// user's IP and when, so the IP Reveal count is also shown for them.
			return $performer->isAllowed( 'checkuser-temporary-account-log' );
		}

		return true;
	}

	/**
	 * Returns the number of times a user had its IP revealed, and the timestamp
	 * when it was last revealed, ignoring deleted log entries if the user
	 * performing the action is not allowed to access them.
	 *
	 * @param Authority $performer
	 * @param UserIdentity $userIdentity
	 * @return array
	 */
	private function getIPRevealLogData(
		Authority $performer,
		UserIdentity $userIdentity
	): array {
		$dbr = $this->dbProvider->getReplicaDatabase();
		$conditions = [
			'log_type' => TemporaryAccountLogger::LOG_TYPE,
			'log_action' => TemporaryAccountLogger::ACTION_VIEW_IPS,
			'log_namespace' => NS_USER,
			'log_title' => $this->getUserTitleKey( $userIdentity ),
		];

		if ( !$performer->isAllowed( 'deletedhistory' ) ) {
			$hiddenBits = LogPage::DELETED_ACTION;
		} elseif ( !$performer->isAllowedAny( 'suppressrevision', 'viewsuppressed' ) ) {
			$hiddenBits = LogPage::SUPPRESSED_ACTION;
		} else {
			$hiddenBits = 0x0;
		}

		if ( $hiddenBits !== 0x0 ) {
			// If the user is *not* allowed to see some data once hidden, skip
			// applicable log entries. See onSpecialLogAddLogSearchRelations
			// in CentralAuth LogHookHandler.
			$bitfield = $dbr->bitAnd( 'log_deleted', $hiddenBits );
			$conditions[] = "{$bitfield} != {$hiddenBits}";
		}

		$row = $dbr->newSelectQueryBuilder()
			->select( [
				'count' => 'COUNT(*)',
				'last' => 'MAX(log_timestamp)',
			] )
			->from( 'logging' )
			->where( $conditions )
			->caller( __METHOD__ )
			->fetchRow();

		if ( $row === false ) {
			return [];
		}

		return [
			'numberOfIpReveals' => intval( $row->count ),
			'ipRevealLastCheck' => $row->last ?
				ConvertibleTimestamp::convert( TS_MW, $row->last ) :
				null,
		];
	}

	/**
	 * Get the username in a form that can be used in a DB query
	 *
	 * This performs the same transformation on the username as done in
	 * User::getTitleKey().
	 *
	 * @param UserIdentity $userIdentity
	 * @return string
	 */
	private function getUserTitleKey( UserIdentity $userIdentity ): string {
		return str_replace( ' ', '_', $userIdentity->getName() );
	}

	/**
	 * Get the conditions to fetch only the log entries that the user is allowed to see
	 *
	 * @param Authority $authority
	 * @param IReadableDatabase $dbr
	 * @return array
	 */
	private function getLogActionRestrictions( Authority $authority, IReadableDatabase $dbr ): array {
		$conditions = [];

		if ( !$authority->isAllowed( 'deletedhistory' ) ) {
			$conditions[] = $dbr->bitAnd( 'log_deleted', LogPage::DELETED_ACTION ) . ' = 0';
		} elseif ( !$authority->isAllowedAny( 'suppressrevision', 'viewsuppressed' ) ) {
			$conditions[] = $dbr->bitAnd( 'log_deleted', LogPage::SUPPRESSED_ACTION ) .
				' != ' . LogPage::SUPPRESSED_ACTION;
		}

		return $conditions;
	}
}
