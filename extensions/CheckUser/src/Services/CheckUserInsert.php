<?php

namespace MediaWiki\CheckUser\Services;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\ClientHints\UserAgentClientHintsManagerHelperTrait;
use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Language\Language;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\Logging\LogEntryBase;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\ActorStore;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use Psr\Log\LoggerInterface;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * This service provides methods that can be used
 * to insert data into the CheckUser result tables.
 *
 * Extensions other than CheckUser should not use
 * the methods marked as internal.
 */
class CheckUserInsert {

	use UserAgentClientHintsManagerHelperTrait;

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserClientHintsEnabled',
	];

	private ActorStore $actorStore;
	private CheckUserUtilityService $checkUserUtilityService;
	private CommentStore $commentStore;
	private HookRunner $hookRunner;
	private IConnectionProvider $connectionProvider;
	private Language $contentLanguage;
	private TempUserConfig $tempUserConfig;
	private CheckUserCentralIndexManager $checkUserCentralIndexManager;
	private UserAgentClientHintsManager $userAgentClientHintsManager;
	private JobQueueGroup $jobQueueGroup;
	private ServiceOptions $options;
	private LoggerInterface $logger;

	/**
	 * The maximum number of bytes that fit in CheckUser's text fields,
	 * specifically user agent, XFF strings and action text.
	 */
	public const TEXT_FIELD_LENGTH = 255;

	public function __construct(
		ServiceOptions $options,
		ActorStore $actorStore,
		CheckUserUtilityService $checkUserUtilityService,
		CommentStore $commentStore,
		HookContainer $hookContainer,
		IConnectionProvider $connectionProvider,
		Language $contentLanguage,
		TempUserConfig $tempUserConfig,
		CheckUserCentralIndexManager $checkUserCentralIndexManager,
		UserAgentClientHintsManager $userAgentClientHintsManager,
		JobQueueGroup $jobQueueGroup,
		LoggerInterface $logger
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->actorStore = $actorStore;
		$this->checkUserUtilityService = $checkUserUtilityService;
		$this->commentStore = $commentStore;
		$this->hookRunner = new HookRunner( $hookContainer );
		$this->connectionProvider = $connectionProvider;
		$this->contentLanguage = $contentLanguage;
		$this->tempUserConfig = $tempUserConfig;
		$this->checkUserCentralIndexManager = $checkUserCentralIndexManager;
		$this->userAgentClientHintsManager = $userAgentClientHintsManager;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->logger = $logger;
	}

	/**
	 * Hook function for RecentChange_save. Saves data about the RecentChange object, along with private user data
	 * (such as their IP address and user agent string) from the main request, in the CheckUser result tables
	 * so that it can be queried by a CheckUser if they run a check.
	 *
	 * Note that other extensions (like AbuseFilter) may call this function directly
	 * if they want to send data to CU without creating a recentchanges entry.
	 *
	 * @param RecentChange $rc
	 */
	public function updateCheckUserData( RecentChange $rc ) {
		// RC_CATEGORIZE recent changes are generally triggered by other edits, so there is no reason to store
		// checkuser data about them (T125209).
		if ( $rc->getAttribute( 'rc_type' ) == RC_CATEGORIZE ) {
			return;
		}

		// RC_EXTERNAL recent changes are not triggered by actions on the local wiki, so there is no reason to store
		// checkuser data about them (T125664).
		if ( $rc->getAttribute( 'rc_type' ) == RC_EXTERNAL ) {
			return;
		}

		$attribs = $rc->getAttributes();
		$dbw = $this->connectionProvider->getPrimaryDatabase();

		if ( $rc->getAttribute( 'rc_type' ) == RC_LOG ) {
			// Write to either cu_log_event or cu_private_event if this is a log event
			$logId = $rc->getAttribute( 'rc_logid' );
			$logEntry = null;
			if ( $logId != 0 ) {
				$logEntry = DatabaseLogEntry::newFromId( $logId, $dbw );
				if ( $logEntry === null ) {
					LoggerFactory::getInstance( 'CheckUser' )->warning(
						'RecentChange with id {rc_id} has non-existing rc_logid {rc_logid}',
						[
							'rc_id' => $rc->getAttribute( 'rc_id' ),
							'rc_logid' => $rc->getAttribute( 'rc_logid' ),
							'exception' => new \RuntimeException()
						]
					);
				}
			}
			// In some rare cases the LogEntry for this rc_logid may not exist even if
			// rc_logid is not zero (T343983). If this occurs, consider rc_logid to be zero
			// and therefore save the entry in cu_private_event
			if ( $logEntry === null ) {
				$rcRow = [
					'cupe_namespace'  => $attribs['rc_namespace'],
					'cupe_title'      => $attribs['rc_title'],
					'cupe_log_type'   => $attribs['rc_log_type'],
					'cupe_log_action' => $attribs['rc_log_action'],
					'cupe_params'     => $attribs['rc_params'],
					'cupe_timestamp'  => $dbw->timestamp( $attribs['rc_timestamp'] ),
				];

				// If rc_comment_id is set, then use it. Instead, get the comment id by a lookup
				if ( isset( $attribs['rc_comment_id'] ) ) {
					$rcRow['cupe_comment_id'] = $attribs['rc_comment_id'];
				} else {
					$rcRow['cupe_comment_id'] = $this->commentStore
						->createComment( $dbw, $attribs['rc_comment'], $attribs['rc_comment_data'] )->id;
				}

				// On PG, MW unsets cur_id due to schema incompatibilities. So it may not be set!
				if ( isset( $attribs['rc_cur_id'] ) ) {
					$rcRow['cupe_page'] = $attribs['rc_cur_id'];
				}

				$this->insertIntoCuPrivateEventTable(
					$rcRow,
					__METHOD__,
					$rc->getPerformerIdentity(),
					$rc
				);
			} else {
				$this->insertIntoCuLogEventTable(
					$logEntry,
					__METHOD__,
					$rc->getPerformerIdentity(),
					$rc
				);
				if ( $this->options->get( 'CheckUserClientHintsEnabled' ) &&
					$rc->getAttribute( 'rc_log_type' ) === 'newusers' ) {
					$this->storeClientHintsDataFromHeaders(
						$logId,
						'log',
						RequestContext::getMain()->getRequest()
					);
				}
			}
		} else {
			// Log to cu_changes if this isn't a log entry.
			$rcRow = [
				'cuc_namespace'  => $attribs['rc_namespace'],
				'cuc_title'      => $attribs['rc_title'],
				'cuc_minor'      => $attribs['rc_minor'],
				'cuc_comment'    => $rc->getAttribute( 'rc_comment' ),
				'cuc_this_oldid' => $attribs['rc_this_oldid'],
				'cuc_last_oldid' => $attribs['rc_last_oldid'],
				'cuc_type'       => $attribs['rc_type'],
				'cuc_timestamp'  => $dbw->timestamp( $attribs['rc_timestamp'] ),
			];

			// On PG, MW unsets cur_id due to schema incompatibilities. So it may not be set!
			if ( isset( $attribs['rc_cur_id'] ) ) {
				$rcRow['cuc_page_id'] = $attribs['rc_cur_id'];
			}

			$this->insertIntoCuChangesTable(
				$rcRow,
				__METHOD__,
				new UserIdentityValue( $attribs['rc_user'], $attribs['rc_user_text'] ),
				$rc
			);
		}
	}

	/**
	 * Performs a call to CheckUserCentralIndexManager::recordActionInCentralIndexes inside a DeferredUpdate that
	 * is run on POST_SEND.
	 *
	 * @param UserIdentity $performer
	 * @param string $ip
	 * @param string $timestamp
	 * @param bool $hasRevisionId
	 * @see CheckUserCentralIndexManager::recordActionInCentralIndexes for documentation on the parameters
	 */
	private function recordActionInCentralTablesOnDeferredUpdate(
		UserIdentity $performer, string $ip, string $timestamp, bool $hasRevisionId
	) {
		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$domainID = $dbw->getDomainID();

		DeferredUpdates::addCallableUpdate(
			fn () => $this->checkUserCentralIndexManager->recordActionInCentralIndexes(
				$performer, $ip, $domainID, $timestamp, $hasRevisionId
			),
			DeferredUpdates::POSTSEND,
			// Cancel this update if the main transaction round is rolled back (T385734).
			$dbw
		);
	}

	/**
	 * Inserts a row into cu_log_event based on provided log ID and performer.
	 *
	 * The $user parameter is used to fill the column values about the performer of the log action.
	 * The log ID is stored in the table and used to get information to show the CheckUser when
	 * running a check.
	 *
	 * @param DatabaseLogEntry $logEntry the log entry to add to cu_log_event
	 * @param string $method the method name that called this, used for the insertion into the DB.
	 * @param UserIdentity $user the user who made the request.
	 * @param ?RecentChange $rc If triggered by a RecentChange, then this is the associated
	 *  RecentChange object. Null if not triggered by a RecentChange.
	 * @return void
	 * @internal Only for use by the CheckUser extension
	 */
	public function insertIntoCuLogEventTable(
		DatabaseLogEntry $logEntry,
		string $method,
		UserIdentity $user,
		?RecentChange $rc = null
	): void {
		$request = RequestContext::getMain()->getRequest();

		$ip = $request->getIP();
		$xff = $request->getHeader( 'X-Forwarded-For' );

		$row = [
			'cule_log_id' => $logEntry->getId()
		];

		// Provide the ip, xff and row to code that hooks onto this so that they can modify the row before
		//  it's inserted. The ip and xff are provided separately so that the caller doesn't have to set
		//  the hex versions of the IP and XFF and can therefore leave that to this function.
		$this->hookRunner->onCheckUserInsertLogEventRow( $ip, $xff, $row, $user, $logEntry->getId(), $rc );
		[ $xff_ip, $isSquidOnly, $xff ] = $this->checkUserUtilityService->getClientIPfromXFF( $xff );

		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$row = array_merge( [
			'cule_actor'     => $this->acquireActorId( $user, CheckUserQueryInterface::LOG_EVENT_TABLE ),
			'cule_timestamp' => $dbw->timestamp( $logEntry->getTimestamp() ),
			'cule_ip'        => IPUtils::sanitizeIP( $ip ),
			'cule_ip_hex'    => $ip ? IPUtils::toHex( $ip ) : null,
			'cule_xff'       => !$isSquidOnly ? $xff : '',
			'cule_xff_hex'   => ( $xff_ip && !$isSquidOnly ) ? IPUtils::toHex( $xff_ip ) : null,
			'cule_agent'     => $this->getAgent( $request ),
		], $row );

		// (T199323) Truncate text fields prior to database insertion
		// Attempting to insert too long text will cause an error in MariaDB/MySQL strict mode
		$row['cule_xff'] = $this->contentLanguage->truncateForDatabase( $row['cule_xff'], self::TEXT_FIELD_LENGTH );

		$dbw->newInsertQueryBuilder()
			->insertInto( 'cu_log_event' )
			->row( $row )
			->caller( $method )
			->execute();

		// Update the central index for this newly inserted row.
		$this->recordActionInCentralTablesOnDeferredUpdate(
			$user, $ip, $row['cule_timestamp'], false
		);
	}

	/**
	 * Inserts a row to cu_private_event based on a provided row and performer of the action.
	 *
	 * The $row has defaults applied, truncation performed and comment table insertion performed.
	 * The $user parameter is used to fill the default for the actor ID column.
	 *
	 * Provide cupe_comment_id if you have generated a comment table ID for this action, or provide
	 * cupe_comment if you want this method to deal with the comment table.
	 *
	 * @param array $row an array of cu_private_event table column names to their values. Changeable by a hook
	 *  and for any needed truncation.
	 * @param string $method the method name that called this, used for the insertion into the DB.
	 * @param UserIdentity $user the user associated with the event
	 * @param ?RecentChange $rc If triggered by a RecentChange, then this is the associated
	 *  RecentChange object. Null if not triggered by a RecentChange.
	 * @return int The ID of the newly created row in the cu_private_event table
	 * @internal Only for use by the CheckUser extension
	 */
	public function insertIntoCuPrivateEventTable(
		array $row,
		string $method,
		UserIdentity $user,
		?RecentChange $rc = null
	): int {
		$request = RequestContext::getMain()->getRequest();

		$ip = $request->getIP();
		$xff = $request->getHeader( 'X-Forwarded-For' );

		// Provide the ip, xff and row to code that hooks onto this so that they can modify the row before
		//  it's inserted. The ip and xff are provided separately so that the caller doesn't have to set
		//  the hex versions of the IP and XFF and can therefore leave that to this function.
		$this->hookRunner->onCheckUserInsertPrivateEventRow( $ip, $xff, $row, $user, $rc );
		[ $xff_ip, $isSquidOnly, $xff ] = $this->checkUserUtilityService->getClientIPfromXFF( $xff );

		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$row = array_merge(
			[
				'cupe_namespace'  => 0,
				'cupe_title'      => '',
				'cupe_log_type'   => 'checkuser-private-event',
				'cupe_log_action' => '',
				'cupe_params'     => LogEntryBase::makeParamBlob( [] ),
				'cupe_page'       => 0,
				'cupe_actor'      => $this->acquireActorId( $user, CheckUserQueryInterface::PRIVATE_LOG_EVENT_TABLE ),
				'cupe_timestamp'  => $dbw->timestamp( wfTimestampNow() ),
				'cupe_ip'         => IPUtils::sanitizeIP( $ip ),
				'cupe_ip_hex'     => $ip ? IPUtils::toHex( $ip ) : null,
				'cupe_xff'        => !$isSquidOnly ? $xff : '',
				'cupe_xff_hex'    => ( $xff_ip && !$isSquidOnly ) ? IPUtils::toHex( $xff_ip ) : null,
				'cupe_agent'      => $this->getAgent( $request ),
			],
			$row
		);

		// (T199323) Truncate text fields prior to database insertion
		// Attempting to insert too long text will cause an error in MariaDB/MySQL strict mode
		$row['cupe_xff'] = $this->contentLanguage->truncateForDatabase( $row['cupe_xff'], self::TEXT_FIELD_LENGTH );

		if ( !isset( $row['cupe_comment_id'] ) ) {
			$row += $this->commentStore->insert(
				$dbw,
				'cupe_comment',
				$row['cupe_comment'] ?? ''
			);
		}

		// Remove any defined cupe_comment as this is not a valid column name.
		unset( $row['cupe_comment'] );

		$dbw->newInsertQueryBuilder()
			->insertInto( 'cu_private_event' )
			->row( $row )
			->caller( $method )
			->execute();
		$insertedId = $dbw->insertId();

		// Update the central index for this newly inserted row.
		$this->recordActionInCentralTablesOnDeferredUpdate(
			$user, $ip, $row['cupe_timestamp'], false
		);

		return $insertedId;
	}

	/**
	 * Inserts a row in cu_changes based on the provided $row.
	 *
	 * The $user parameter is used to generate the default value for cuc_actor.
	 *
	 * @param array $row an array of cu_change table column names to their values. Overridable by a hook
	 *  and for any necessary truncation.
	 * @param string $method the method name that called this, used for the insertion into the DB.
	 * @param UserIdentity $user the user who made the change
	 * @param ?RecentChange $rc If triggered by a RecentChange, then this is the associated
	 *  RecentChange object. Null if not triggered by a RecentChange.
	 * @internal Only for use by the CheckUser extension
	 */
	public function insertIntoCuChangesTable(
		array $row,
		string $method,
		UserIdentity $user,
		?RecentChange $rc = null
	): void {
		$request = RequestContext::getMain()->getRequest();

		$ip = $request->getIP();
		$xff = $request->getHeader( 'X-Forwarded-For' );
		// Provide the ip, xff and row to code that hooks onto this so that they can modify the row before
		//  it's inserted. The ip and xff are provided separately so that the caller doesn't have to set
		//  the hex versions of the IP and XFF and can therefore leave that to this function.
		$this->hookRunner->onCheckUserInsertChangesRow( $ip, $xff, $row, $user, $rc );
		[ $xff_ip, $isSquidOnly, $xff ] = $this->checkUserUtilityService->getClientIPfromXFF( $xff );

		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$row = array_merge(
			[
				'cuc_page_id'    => 0,
				'cuc_namespace'  => 0,
				'cuc_minor'      => 0,
				'cuc_title'      => '',
				'cuc_comment'    => '',
				'cuc_actor'      => $this->acquireActorId( $user, CheckUserQueryInterface::CHANGES_TABLE ),
				'cuc_this_oldid' => 0,
				'cuc_last_oldid' => 0,
				'cuc_type'       => RC_LOG,
				'cuc_timestamp'  => $dbw->timestamp( wfTimestampNow() ),
				'cuc_ip'         => IPUtils::sanitizeIP( $ip ),
				'cuc_ip_hex'     => $ip ? IPUtils::toHex( $ip ) : null,
				'cuc_xff'        => !$isSquidOnly ? $xff : '',
				'cuc_xff_hex'    => ( $xff_ip && !$isSquidOnly ) ? IPUtils::toHex( $xff_ip ) : null,
				'cuc_agent'      => $this->getAgent( $request ),
			],
			$row
		);

		// (T199323) Truncate text fields prior to database insertion
		// Attempting to insert too long text will cause an error in MariaDB/MySQL strict mode
		$row['cuc_xff'] = $this->contentLanguage->truncateForDatabase( $row['cuc_xff'], self::TEXT_FIELD_LENGTH );

		if ( !isset( $row['cuc_comment_id'] ) ) {
			$row += $this->commentStore->insert(
				$dbw,
				'cuc_comment',
				$row['cuc_comment']
			);
		}
		unset( $row['cuc_comment'] );

		$dbw->newInsertQueryBuilder()
			->insertInto( 'cu_changes' )
			->row( $row )
			->caller( $method )
			->execute();

		// Update the central index for this newly inserted row.
		$this->recordActionInCentralTablesOnDeferredUpdate(
			$user, $ip, $row['cuc_timestamp'], $row['cuc_this_oldid'] !== 0
		);
	}

	/**
	 * Get user agent for the given request.
	 *
	 * @param WebRequest $request
	 * @return string
	 */
	private function getAgent( WebRequest $request ): string {
		$agent = $request->getHeader( 'User-Agent' );
		if ( $agent === false ) {
			// no agent was present, store as an empty string (otherwise, it would
			// end up stored as a zero due to boolean casting done by the DB layer).
			return '';
		}
		return $this->contentLanguage->truncateForDatabase( $agent, self::TEXT_FIELD_LENGTH );
	}

	/**
	 * Generates an integer for insertion into cuc_actor, cule_actor, or cupe_actor.
	 *
	 * This integer will be an actor ID for the $user unless all the following are true:
	 * * The $user is an IP address
	 * * $wgAutoCreateTempUser['enabled'] is true
	 * * The $table is 'cu_private_event'
	 *
	 * In all of the above are true, this method will return null as when the first two are true, trying to create an
	 * actor ID will cause a CannotCreateActorException exception to be thrown.
	 *
	 * If the first two are true but the last is not, then the code will try to find an existing actor ID for the IP
	 * address (to allow imports) and if this fails then will throw a CannotCreateActorException.
	 *
	 * @param UserIdentity $user
	 * @param string $table The table that the actor ID will be inserted into.
	 * @return ?int The value to insert into the actor column (can be null if the table is cu_private_event).
	 */
	private function acquireActorId( UserIdentity $user, string $table ): ?int {
		$dbw = $this->connectionProvider->getPrimaryDatabase();
		if ( IPUtils::isIPAddress( $user->getName() ) && $this->tempUserConfig->isEnabled() ) {
			if ( $table === CheckUserQueryInterface::PRIVATE_LOG_EVENT_TABLE ) {
				return null;
			}
			$actorId = $this->actorStore->findActorId( $user, $dbw );
			if ( $actorId !== null ) {
				return $actorId;
			}
		}
		return $this->actorStore->acquireActorId( $user, $dbw );
	}
}
