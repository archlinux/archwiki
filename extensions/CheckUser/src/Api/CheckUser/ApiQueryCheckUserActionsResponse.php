<?php

namespace MediaWiki\CheckUser\Api\CheckUser;

use MediaWiki\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Config\Config;
use MediaWiki\Logging\LogEventsList;
use MediaWiki\Logging\LogFormatter;
use MediaWiki\Logging\LogFormatterFactory;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNameUtils;
use MessageLocalizer;
use stdClass;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class ApiQueryCheckUserActionsResponse extends ApiQueryCheckUserAbstractResponse {

	private MessageLocalizer $messageLocalizer;
	private UserIdentityLookup $userIdentityLookup;
	private CommentStore $commentStore;
	private UserFactory $userFactory;
	private LogFormatterFactory $logFormatterFactory;

	/**
	 * @param ApiQueryCheckUser $module
	 * @param IConnectionProvider $dbProvider
	 * @param Config $config
	 * @param MessageLocalizer $messageLocalizer
	 * @param CheckUserLogService $checkUserLogService
	 * @param UserNameUtils $userNameUtils
	 * @param CheckUserLookupUtils $checkUserLookupUtils
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param CommentStore $commentStore
	 * @param UserFactory $userFactory
	 * @param LogFormatterFactory $logFormatterFactory
	 *
	 * @internal Use CheckUserApiResponseFactory::newFromRequest() instead
	 */
	public function __construct(
		ApiQueryCheckUser $module,
		IConnectionProvider $dbProvider,
		Config $config,
		MessageLocalizer $messageLocalizer,
		CheckUserLogService $checkUserLogService,
		UserNameUtils $userNameUtils,
		CheckUserLookupUtils $checkUserLookupUtils,
		UserIdentityLookup $userIdentityLookup,
		CommentStore $commentStore,
		UserFactory $userFactory,
		LogFormatterFactory $logFormatterFactory
	) {
		parent::__construct(
			$module, $dbProvider, $config, $messageLocalizer,
			$checkUserLogService, $userNameUtils, $checkUserLookupUtils
		);
		$this->messageLocalizer = $messageLocalizer;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->commentStore = $commentStore;
		$this->userFactory = $userFactory;
		$this->logFormatterFactory = $logFormatterFactory;
	}

	/** @inheritDoc */
	public function getRequestType(): string {
		return 'actions';
	}

	/** @inheritDoc */
	public function getResponseData(): array {
		$res = $this->performQuery( __METHOD__ );

		$actions = [];
		foreach ( $res as $row ) {
			// Use the IP as the $row->user_text if the actor ID is NULL and the IP is not NULL (T353953).
			if ( $row->actor === null && $row->ip ) {
				$row->user_text = $row->ip;
			}
			$action = [
				'timestamp' => ConvertibleTimestamp::convert( TS_ISO_8601, $row->timestamp ),
				'ns'        => intval( $row->namespace ),
				'title'     => $row->title,
				'user'      => $row->user_text,
				'ip'        => $row->ip,
				'agent'     => $row->agent,
			];

			$user = $this->userFactory->newFromUserIdentity(
				new UserIdentityValue( $row->user ?? 0, $row->user_text )
			);

			// Get either the RevisionRecord or DatabaseLogEntry associated with this row.
			$revRecord = null;
			$logEntry = null;
			if ( ( $row->type == RC_EDIT || $row->type == RC_NEW ) && $row->this_oldid != 0 ) {
				$revRecord = $this->checkUserLookupUtils->getRevisionRecordFromRow( $row );
			} elseif ( $row->type == RC_LOG && $row->log_type ) {
				$logEntry = $this->checkUserLookupUtils->getManualLogEntryFromRow( $row, $user );
			}

			// If the 'user' key is a username which the current authority cannot see, then replace it with the
			// 'rev-deleted-user' message.
			$userIsHidden = $user->isHidden() && !$this->module->getUser()->isAllowed( 'hideuser' );
			if ( $revRecord !== null && !$userIsHidden ) {
				$userIsHidden = !$revRecord->userCan( RevisionRecord::DELETED_USER, $this->module->getAuthority() );
			} elseif ( $logEntry !== null && !$userIsHidden ) {
				// Specifically using LogEventsList::userCanBitfield here instead of ::userCan because we still want
				// to show the username if the authority cannot see logs from this log type but the user is otherwise
				// visible.
				$userIsHidden = !LogEventsList::userCanBitfield(
					$row->log_deleted,
					LogPage::DELETED_USER,
					$this->module->getAuthority()
				);
			}
			if ( $userIsHidden ) {
				$action['user'] = $this->messageLocalizer->msg( 'rev-deleted-user' )->text();
			}

			// If the title is a user page and the username in this user page link is hidden from the current authority,
			// then replace the title with the 'rev-deleted-user' message.
			$title = Title::makeTitle( $row->namespace, $row->title );
			if ( $title->getNamespace() === NS_USER ) {
				$titleUser = $this->userFactory->newFromName( $title->getBaseText() );
				if (
					$titleUser &&
					$titleUser->isHidden() &&
					!$this->module->getUser()->isAllowed( 'hideuser' )
				) {
					$action['title'] = $this->messageLocalizer->msg( 'rev-deleted-user' )->text();
				}
			}

			$summary = $this->getSummary( $row, $revRecord, $logEntry );
			if ( $summary !== null ) {
				$action['summary'] = $summary;
			}

			if ( ( $row->type == RC_EDIT || $row->type == RC_NEW ) && $row->minor ) {
				$action['minor'] = 'm';
			}
			if ( $row->xff ) {
				$action['xff'] = $row->xff;
			}
			$actions[] = $action;
		}

		if ( IPUtils::isIPAddress( $this->target ) ) {
			if ( $this->xff ) {
				$logType = 'ipedits-xff';
			} else {
				$logType = 'ipedits';
			}
			$targetType = 'ip';
			$userId = 0;
		} else {
			$logType = 'useredits';
			$targetType = 'user';
			$userId = $this->userIdentityLookup->getUserIdentityByName( $this->target )->getId();
		}
		$this->checkUserLogService->addLogEntry(
			$this->module->getUser(), $logType, $targetType, $this->target, $this->reason, $userId
		);
		return $actions;
	}

	/**
	 * Gets the summary text associated with the given $row. This is a combination of the actiontext and any comment
	 * left for the action.
	 *
	 * This will also appropriately hide the action text and any set comment if the current authority cannot see them.
	 *
	 * @param stdClass $row The database row
	 * @param RevisionRecord|null $revRecord The RevisionRecord associated with this row, if it exists.
	 * @param ManualLogEntry|null $logEntry The ManualLogEntry associated with this row, if it exists.
	 * @return ?string The comment and action text combined together into a plaintext string, or null if there is no
	 *   comment or action text.
	 */
	private function getSummary( stdClass $row, ?RevisionRecord $revRecord, ?ManualLogEntry $logEntry ): ?string {
		// Generate the action text if possible.
		if ( $logEntry !== null ) {
			// Log action text taken from the LogFormatter for the entry being displayed.
			$logFormatter = $this->logFormatterFactory->newFromEntry( $logEntry );
			$logFormatter->setAudience( LogFormatter::FOR_THIS_USER );
			$actionText = $logFormatter->getPlainActionText();
		} else {
			$actionText = '';
		}

		// Get the comment if there is one and only show it if the current authority can see it.
		$commentVisible = true;
		if ( $revRecord !== null ) {
			$commentVisible = $revRecord->userCan( RevisionRecord::DELETED_COMMENT, $this->module->getAuthority() );
		} elseif ( $logEntry !== null ) {
			$commentVisible = LogEventsList::userCan( $row, LogPage::DELETED_COMMENT, $this->module->getAuthority() );
		}
		if ( $commentVisible ) {
			$comment = $this->commentStore->getComment( 'comment', $row )->text;
		} else {
			$comment = '';
		}

		if ( $comment !== '' && $actionText !== '' ) {
			// If there is both an actiontext and a comment, we need to make it clear that the comment
			// is separate from the actiontext. We do this by adding parentheses around the comment.
			return $actionText . ' ' . $this->messageLocalizer->msg( 'parentheses', $comment )->text();
		} elseif ( $comment !== '' || $actionText !== '' ) {
			// One of these will be empty, so we can just concatenate them together to return one of them.
			return $actionText . $comment;
		} else {
			return null;
		}
	}

	/** @inheritDoc */
	protected function validateTargetAndGenerateTargetConditions( string $table ): IExpression {
		if ( IPUtils::isIPAddress( $this->target ) ) {
			$targetExpr = $this->checkUserLookupUtils->getIPTargetExpr( $this->target, $this->xff ?? false, $table );
			if ( $targetExpr === null ) {
				$this->module->dieWithError( 'apierror-badip', 'invalidip' );
			}
		} else {
			$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $this->target );
			if ( !$userIdentity || !$userIdentity->getId() ) {
				$this->module->dieWithError( [ 'nosuchusershort', wfEscapeWikiText( $this->target ) ], 'nosuchuser' );
			}
			$targetExpr = $this->dbr->expr( 'actor_user', '=', $userIdentity->getId() );
		}
		return $targetExpr;
	}

	/** @inheritDoc */
	protected function getPartialQueryBuilderForCuChanges(): SelectQueryBuilder {
		$queryBuilder = $this->dbr->newSelectQueryBuilder()
			->select( [
				'namespace' => 'cuc_namespace', 'title' => 'cuc_title',
				'page' => 'cuc_page_id', 'timestamp' => 'cuc_timestamp',
				'minor' => 'cuc_minor', 'type' => 'cuc_type', 'this_oldid' => 'cuc_this_oldid',
				'ip' => 'cuc_ip', 'xff' => 'cuc_xff', 'agent' => 'cuc_agent',
				'user' => 'actor_user', 'user_text' => 'actor_name', 'actor' => 'cuc_actor',
				'comment_text', 'comment_data',
			] )
			->from( 'cu_changes' )
			->join( 'actor', null, 'actor_id=cuc_actor' )
			->join( 'comment', null, 'comment_id=cuc_comment_id' )
			->where( $this->dbr->expr( 'cuc_timestamp', '>', $this->timeCutoff ) );
		return $queryBuilder;
	}

	/** @inheritDoc */
	protected function getPartialQueryBuilderForCuLogEvent(): SelectQueryBuilder {
		if ( $this->dbr->getType() === 'postgres' ) {
			// On postgres the cuc_type type is a smallint.
			$typeValue = 'CAST(' . RC_LOG . ' AS smallint)';
		} else {
			// Other DBs can handle converting RC_LOG to the correct type.
			$typeValue = (string)RC_LOG;
		}
		return $this->dbr->newSelectQueryBuilder()
			->select( [
				'namespace' => 'log_namespace', 'title' => 'log_title',
				'page_id' => 'log_page', 'timestamp' => 'cule_timestamp', 'type' => $typeValue,
				'ip' => 'cule_ip', 'xff' => 'cule_xff', 'agent' => 'cule_agent',
				'user' => 'actor_user', 'user_text' => 'actor_name', 'actor' => 'cule_actor',
				'comment_text', 'comment_data',
				'log_type' => 'log_type', 'log_action' => 'log_action',
				'log_params' => 'log_params', 'log_deleted' => 'log_deleted', 'log_id' => 'cule_log_id',
			] )
			->from( 'cu_log_event' )
			->join( 'actor', null, 'actor_id=cule_actor' )
			->join( 'logging', null, 'log_id=cule_log_id' )
			->join( 'comment', null, 'comment_id=log_comment_id' )
			->where( $this->dbr->expr( 'cule_timestamp', '>', $this->timeCutoff ) );
	}

	/** @inheritDoc */
	protected function getPartialQueryBuilderForCuPrivateEvent(): SelectQueryBuilder {
		if ( $this->dbr->getType() === 'postgres' ) {
			// On postgres the cuc_type type is a smallint.
			$typeValue = 'CAST(' . RC_LOG . ' AS smallint)';
		} else {
			// Other DBs can handle converting RC_LOG to the correct type.
			$typeValue = (string)RC_LOG;
		}
		$queryBuilder = $this->dbr->newSelectQueryBuilder()
			->select( [
				'namespace' => 'cupe_namespace', 'title' => 'cupe_title',
				'page_id' => 'cupe_page', 'timestamp' => 'cupe_timestamp', 'type' => $typeValue,
				'ip' => 'cupe_ip', 'xff' => 'cupe_xff', 'agent' => 'cupe_agent',
				'user' => 'actor_user', 'user_text' => 'actor_name', 'actor' => 'cupe_actor',
				'comment_text', 'comment_data',
				'log_type' => 'cupe_log_type', 'log_action' => 'cupe_log_action',
				'log_params' => 'cupe_params',
				// cu_private_event log events cannot be deleted or suppressed.
				'log_deleted' => '0',
			] )
			->from( 'cu_private_event' )
			->join( 'comment', null, 'comment_id=cupe_comment_id' )
			->where( $this->dbr->expr( 'cupe_timestamp', '>', $this->timeCutoff ) );
		if ( $this->xff === null ) {
			// We only need a JOIN if the target of the check is a username because the username will have a valid
			// actor ID.
			$queryBuilder->join( 'actor', null, 'actor_id=cupe_actor' );
		} else {
			// A LEFT JOIN is required because cupe_actor can be NULL if the performer was an IP address and temporary
			// accounts were enabled.
			$queryBuilder->leftJoin( 'actor', null, 'actor_id=cupe_actor' );
		}
		return $queryBuilder;
	}
}
