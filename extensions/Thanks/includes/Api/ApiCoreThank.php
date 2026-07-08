<?php

namespace MediaWiki\Extension\Thanks\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Extension\Notifications\DiscussionParser;
use MediaWiki\Extension\Thanks\Hooks;
use MediaWiki\Extension\Thanks\Storage\Exceptions\InvalidLogType;
use MediaWiki\Extension\Thanks\Storage\Exceptions\LogDeleted;
use MediaWiki\Extension\Thanks\Storage\LogStore;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\Logging\LogEntry;
use MediaWiki\Notification\NotificationService;
use MediaWiki\Notification\RecipientSet;
use MediaWiki\Notification\Types\WikiNotification;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * API module to send thanks notifications for revisions and log entries.
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiCoreThank extends ApiThank {

	public function __construct(
		ApiMain $main,
		string $action,
		PermissionManager $permissionManager,
		LogStore $storage,
		private readonly NotificationService $notifications,
		protected readonly RevisionStore $revisionStore,
		protected readonly UserFactory $userFactory,
	) {
		parent::__construct(
			$main,
			$action,
			$permissionManager,
			$storage
		);
	}

	/**
	 * Perform the API request.
	 * @suppress PhanPossiblyUndeclaredVariable Phan get's confused by the badly arranged code
	 */
	public function execute() {
		// Initial setup.
		$user = $this->getUser();
		$this->dieOnBadUser( $user );
		$this->dieOnUserBlockedFromThanks( $user );
		$params = $this->extractRequestParams();
		$revcreation = false;

		$this->requireOnlyOneParameter( $params, 'rev', 'log' );

		// Extract type and ID from the parameters.
		if ( $params['rev'] !== null ) {
			$type = 'rev';
			$id = $params['rev'];
		} elseif ( $params['log'] !== null ) {
			$type = 'log';
			$id = $params['log'];
		} else {
			ApiBase::dieDebug( __METHOD__, 'Unhandled parameter' );
		}

		// Determine thanks parameters.
		if ( $type === 'log' ) {
			$logEntry = $this->getLogEntryFromId( $id );
			// If there's an associated revision, thank for that instead.
			if ( $logEntry->getAssociatedRevId() ) {
				$type = 'rev';
				$id = $logEntry->getAssociatedRevId();
			} else {
				// If there's no associated revision, die if the user is sitewide blocked
				$excerpt = '';
				$page = $logEntry->getTarget();
				$recipient = $this->getUserFromLog( $logEntry );
			}
		}
		// Type may change from log to rev when associated revision exists
		if ( $type === 'rev' ) {
			$revision = $this->getRevisionFromId( $id );
			$excerpt = DiscussionParser::getEditExcerpt( $revision, $this->getLanguage() );
			$page = $revision->getPage();
			$this->dieOnUserBlockedFromPage( $user, $page );

			$recipient = $this->getUserFromRevision( $revision );

			// If there is no parent revid of this revision, it's a page creation.
			if ( !$this->revisionStore->getPreviousRevision( $revision ) ) {
				$revcreation = true;
			}
		}

		// Send thanks.
		if ( $this->userAlreadySentThanks( $user, $type, $id ) ) {
			$this->markResultSuccess( $recipient->getName() );
		} else {
			$this->dieOnBadRecipient( $user, $recipient );
			$this->sendThanks(
				$user,
				$type,
				$id,
				$excerpt,
				$recipient,
				$this->getSourceFromParams( $params ),
				$page,
				$revcreation
			);
		}
	}

	/**
	 * Check the session data for an indication of whether this user has already sent this thanks.
	 * @param User $user The user being thanked.
	 * @param string $type Either 'rev' or 'log'.
	 * @param int $id The revision or log ID.
	 * @return bool
	 */
	protected function userAlreadySentThanks( User $user, $type, $id ) {
		return (bool)$user->getRequest()->getSessionData( Hooks::getSessionKey( $type, $id ) );
	}

	private function getRevisionFromId( int $revId ): RevisionRecord {
		$revision = $this->revisionStore->getRevisionById( $revId );
		// Revision ID 1 means an invalid argument was passed in.
		// FIXME Get rid of this limitation! T344475
		if ( !$revision || $revision->getId() === 1 ) {
			$this->dieWithError( 'thanks-error-invalidrevision', 'invalidrevision' );
		} elseif ( $revision->isDeleted( RevisionRecord::DELETED_TEXT ) ) {
			$this->dieWithError( 'thanks-error-revdeleted', 'revdeleted' );
		}
		return $revision;
	}

	/**
	 * Get the log entry from the ID.
	 * @param int $logId The log entry ID.
	 * @return DatabaseLogEntry
	 */
	protected function getLogEntryFromId( $logId ): DatabaseLogEntry {
		$logEntry = null;
		try {
			$logEntry = $this->storage->getLogEntryFromId( $logId );
		} catch ( InvalidLogType $e ) {
			$err = $this->msg( 'thanks-error-invalid-log-type', $e->getLogType() );
			$this->dieWithError( $err, 'thanks-error-invalid-log-type' );
		} catch ( LogDeleted ) {
			$this->dieWithError( 'thanks-error-log-deleted', 'thanks-error-log-deleted' );
		}

		if ( !$logEntry ) {
			$this->dieWithError( 'thanks-error-invalid-log-id', 'thanks-error-invalid-log-id' );
		}
		return $logEntry;
	}

	/**
	 * Set the source of the thanks, e.g. 'diff' or 'history'
	 * @param string[] $params Incoming API parameters, with a 'source' key.
	 * @return string The source, or 'undefined' if not provided.
	 */
	private function getSourceFromParams( $params ) {
		if ( $params['source'] ) {
			return trim( $params['source'] );
		} else {
			return 'undefined';
		}
	}

	/**
	 * @param RevisionRecord $revision
	 * @return User
	 */
	private function getUserFromRevision( RevisionRecord $revision ) {
		$recipient = $revision->getUser();
		if ( !$recipient ) {
			$this->dieWithError( 'thanks-error-invalidrecipient', 'invalidrecipient' );
		}
		return $this->userFactory->newFromUserIdentity( $recipient );
	}

	/**
	 * @param LogEntry $logEntry
	 * @return UserIdentity
	 */
	private function getUserFromLog( LogEntry $logEntry ) {
		$recipient = $logEntry->getPerformerIdentity();
		return $this->userFactory->newFromUserIdentity( $recipient );
	}

	/**
	 * Create the thanks notification event, and log the thanks.
	 * @param User $user The thanks-sending user.
	 * @param string $type The thanks type ('rev' or 'log').
	 * @param int $id The log or revision ID.
	 * @param string $excerpt The excerpt to display as the thanks notification. This will only
	 * be used if it is not possible to retrieve the relevant excerpt at the time the
	 * notification is displayed (in order to account for changing visibility in the meantime).
	 * @param User $recipient The recipient of the thanks.
	 * @param string $source Where the thanks was given.
	 * @param PageIdentity $page The page for which thanks is given.
	 * @param bool $revcreation True if the linked revision is a page creation.
	 */
	protected function sendThanks(
		User $user, $type, $id, $excerpt, User $recipient, $source, PageIdentity $page, $revcreation
	) {
		$uniqueId = $type . '-' . $id;
		// Do one last check to make sure we haven't sent Thanks before
		if ( $this->haveAlreadyThanked( $user, $uniqueId ) ) {
			// Pretend the thanks were sent
			$this->markResultSuccess( $recipient->getName() );
			return;
		}

		// Create the notification
		$this->notifications->notify(
			new WikiNotification( 'edit-thank', $page, $user, [
				$type . 'id' => $id,
				'source' => $source,
				'excerpt' => $excerpt,
				'revcreation' => $revcreation,
			] ),
			new RecipientSet( $recipient )
		);

		// And mark the thank in session for a cheaper check to prevent duplicates (T48690).
		$user->getRequest()->setSessionData( Hooks::getSessionKey( $type, $id ), true );
		// Set success message
		$this->markResultSuccess( $recipient->getName() );
		$this->logThanks( $user, $recipient, $uniqueId );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'rev' => [
				ParamValidator::PARAM_TYPE => 'integer',
				IntegerDef::PARAM_MIN => 1,
				ParamValidator::PARAM_REQUIRED => false,
			],
			'log' => [
				ParamValidator::PARAM_TYPE => 'integer',
				IntegerDef::PARAM_MIN => 1,
				ParamValidator::PARAM_REQUIRED => false,
			],
			'token' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'source' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return [
			'https://www.mediawiki.org/wiki/Extension:Thanks#API_Documentation',
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=thank&revid=456&source=diff&token=123ABC'
				=> 'apihelp-thank-example-1',
		];
	}
}
