<?php

namespace MediaWiki\CheckUser\Investigate\Pagers;

use HtmlArmor;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Logging\LogEventsList;
use MediaWiki\Logging\LogFormatter;
use MediaWiki\Logging\LogFormatterFactory;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Message\Message;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserRigorOptions;
use Wikimedia\IPUtils;

class TimelineRowFormatter {
	private LinkRenderer $linkRenderer;
	private CheckUserLookupUtils $checkUserLookupUtils;
	private TitleFormatter $titleFormatter;
	private SpecialPageFactory $specialPageFactory;
	private UserFactory $userFactory;
	private CommentFormatter $commentFormatter;
	private CommentStore $commentStore;
	private LogFormatterFactory $logFormatterFactory;

	private array $message = [];

	private Language $language;

	private User $user;

	public function __construct(
		LinkRenderer $linkRenderer,
		CheckUserLookupUtils $checkUserLookupUtils,
		TitleFormatter $titleFormatter,
		SpecialPageFactory $specialPageFactory,
		CommentFormatter $commentFormatter,
		UserFactory $userFactory,
		CommentStore $commentStore,
		LogFormatterFactory $logFormatterFactory,
		User $user,
		Language $language
	) {
		$this->linkRenderer = $linkRenderer;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
		$this->titleFormatter = $titleFormatter;
		$this->specialPageFactory = $specialPageFactory;
		$this->commentFormatter = $commentFormatter;
		$this->userFactory = $userFactory;
		$this->commentStore = $commentStore;
		$this->logFormatterFactory = $logFormatterFactory;
		$this->user = $user;
		$this->language = $language;

		$this->preCacheMessages();
	}

	/**
	 * Format change, log event or private event record and display appropriate
	 * information depending on user privileges
	 *
	 * @param \stdClass $row
	 * @return string[][]
	 */
	public function getFormattedRowItems( \stdClass $row ): array {
		// Use the IP as the $row->user_text if the actor ID is NULL and the IP is not NULL (T353953).
		if ( $row->actor === null && $row->ip ) {
			$row->user_text = $row->ip;
		}

		$user = $this->userFactory->newFromUserIdentity(
			new UserIdentityValue( $row->user ?? 0, $row->user_text )
		);

		// Get either the RevisionRecord or ManualLogEntry associated with this row.
		$revRecord = null;
		$logEntry = null;
		if ( ( $row->type == RC_EDIT || $row->type == RC_NEW ) && $row->this_oldid != 0 ) {
			$revRecord = $this->checkUserLookupUtils->getRevisionRecordFromRow( $row );
		} elseif ( $row->type == RC_LOG && $row->log_type ) {
			$logEntry = $this->checkUserLookupUtils->getManualLogEntryFromRow( $row, $user );
		}

		return [
			'links' => [
				'logLink' => $this->getLogLink( $row ),
				'logsLink' => $this->getLogsLink( $row, $logEntry ),
				'diffLink' => $this->getDiffLink( $row ),
				'historyLink' => $this->getHistoryLink( $row ),
				'newPageFlag' => $this->getNewPageFlag( (int)$row->type ),
				'minorFlag' => $this->getMinorFlag( (bool)$row->minor ),
			],
			'info' => [
				'title' => $this->getTitleLink( $row ),
				'time' => $this->getTime( $row->timestamp ),
				'userLinks' => $this->getUserLinks( $row, $revRecord, $logEntry ),
				'actionText' => $this->getActionText( $logEntry ),
				'ipInfo' => $this->getIpInfo( $row->ip ),
				'userAgent' => $this->getUserAgent( $row->agent ?? '' ),
				'comment' => $this->getComment( $row, $revRecord, $logEntry ),
			],
		];
	}

	/**
	 * Show the comment, or redact if appropriate. If the revision is not found,
	 * show nothing.
	 *
	 * @param \stdClass $row
	 * @param RevisionRecord|null $revRecord
	 * @param ManualLogEntry|null $logEntry
	 * @return string
	 */
	private function getComment( \stdClass $row, ?RevisionRecord $revRecord, ?ManualLogEntry $logEntry ): string {
		// Get the comment if there is one and only show it if the current authority can see it.
		$commentVisible = true;
		if ( $revRecord !== null ) {
			$commentVisible = $revRecord->userCan( RevisionRecord::DELETED_COMMENT, $this->user );
		} elseif ( $logEntry !== null ) {
			$commentVisible = LogEventsList::userCan( $row, LogPage::DELETED_COMMENT, $this->user );
		}
		if ( $commentVisible ) {
			$comment = $this->commentStore->getComment( 'comment', $row )->text;
		} else {
			$comment = $this->msg( 'rev-deleted-comment' )->text();
		}

		return $this->commentFormatter->formatBlock( $comment, null, false, null, false );
	}

	/**
	 * @param string $ip
	 * @return string
	 */
	private function getIpInfo( string $ip ): string {
		// Note: in the old check user this links to self with ip as target. Can't do now
		// because of token. We could prefill a new investigation tab
		return IPUtils::prettifyIP( $ip );
	}

	/**
	 * @param string $userAgent
	 * @return string
	 */
	private function getUserAgent( string $userAgent ): string {
		return htmlspecialchars( $userAgent );
	}

	/**
	 * @param ManualLogEntry|null $logEntry
	 * @return string
	 */
	private function getActionText( ?ManualLogEntry $logEntry ): string {
		// If there is no associated ManualLogEntry, then this is not a log event and by extension there is no action
		// text.
		if ( $logEntry === null ) {
			return '';
		}

		// Log action text taken from the LogFormatter for the entry being displayed.
		$logFormatter = $this->logFormatterFactory->newFromEntry( $logEntry );
		$logFormatter->setAudience( LogFormatter::FOR_THIS_USER );
		return $logFormatter->getActionText();
	}

	/**
	 * @param \stdClass $row
	 * @return string
	 */
	private function getTitleLink( \stdClass $row ): string {
		if ( $row->type == RC_LOG ) {
			return '';
		}

		$title = TitleValue::tryNew( (int)$row->namespace, $row->title );

		if ( !$title ) {
			return '';
		}

		// Hide the title link if the title for a user page of a user which the current user cannot see.
		if ( $title->getNamespace() === NS_USER && $this->isUserHidden( $title->getText() ) ) {
			return '';
		}

		return $this->linkRenderer->makeLink(
			$title,
			null,
			[ 'class' => 'ext-checkuser-investigate-timeline-row-title' ]
		);
	}

	/**
	 * @param \stdClass $row
	 * @param ManualLogEntry|null $logEntry
	 * @return string
	 */
	private function getLogsLink( \stdClass $row, ?ManualLogEntry $logEntry ): string {
		if ( $row->type != RC_LOG ) {
			return '';
		}

		$title = TitleValue::tryNew( (int)$row->namespace, $row->title );

		if ( !$title ) {
			return '';
		}

		// Hide the 'logs' link if the title is a user page of a user which the current user cannot see.
		if ( $title->getNamespace() === NS_USER && $this->isUserHidden( $title->getText() ) ) {
			return '';
		}

		// Hide the 'logs' link if the log entry details are hidden from the current user, as the title (which is
		// included in the URL) will be hidden for this log entry.
		if (
			$logEntry &&
			!LogEventsList::userCanBitfield( $logEntry->getDeleted(), LogPage::DELETED_ACTION, $this->user )
		) {
			return '';
		}

		return $this->msg( 'parentheses' )
			->rawParams(
				$this->linkRenderer->makeKnownLink(
					new TitleValue( NS_SPECIAL, $this->specialPageFactory->getLocalNameFor( 'Log' ) ),
					new HtmlArmor( $this->message['checkuser-logs-link-text'] ),
					[],
					[ 'page' => $this->titleFormatter->getPrefixedText( $title ) ]
				)
			)->escaped();
	}

	/**
	 * @param \stdClass $row
	 * @return string
	 */
	private function getLogLink( \stdClass $row ): string {
		// Return no link if the row is not a log entry or if the log ID is not set.
		if ( $row->type != RC_LOG || !$row->log_id ) {
			return '';
		}

		return $this->msg( 'parentheses' )
			->rawParams(
				$this->linkRenderer->makeKnownLink(
					new TitleValue( NS_SPECIAL, $this->specialPageFactory->getLocalNameFor( 'Log' ) ),
					new HtmlArmor( $this->message['checkuser-log-link-text'] ),
					[],
					[ 'logid' => $row->log_id ]
				)
			)->escaped();
	}

	/**
	 * @param \stdClass $row
	 * @return string
	 */
	private function getDiffLink( \stdClass $row ): string {
		if ( $row->type == RC_NEW || $row->type == RC_LOG ) {
			return '';
		}

		$title = TitleValue::tryNew( (int)$row->namespace, $row->title );

		if ( !$title ) {
			return '';
		}

		// Hide the diff link if the title for a user page of a user which the current user cannot see.
		if ( $title->getNamespace() === NS_USER && $this->isUserHidden( $title->getText() ) ) {
			return '';
		}

		return $this->msg( 'parentheses' )
			->rawParams(
				$this->linkRenderer->makeKnownLink(
					$title,
					new HtmlArmor( $this->message['diff'] ),
					[],
					[
						'curid' => $row->page_id,
						'diff' => $row->this_oldid,
						'oldid' => $row->last_oldid
					]
				)
			)->escaped();
	}

	/**
	 * @param \stdClass $row
	 * @return string
	 */
	private function getHistoryLink( \stdClass $row ): string {
		if ( $row->type == RC_NEW || $row->type == RC_LOG ) {
			return '';
		}

		$title = TitleValue::tryNew( (int)$row->namespace, $row->title );

		if ( !$title ) {
			return '';
		}

		// Hide the history link if the title for a user page of a user which the current user cannot see.
		if ( $title->getNamespace() === NS_USER && $this->isUserHidden( $title->getText() ) ) {
			return '';
		}

		return $this->msg( 'parentheses' )
			->rawParams(
				$this->linkRenderer->makeKnownLink(
					$title,
					new HtmlArmor( $this->message['hist'] ),
					[],
					[
						'curid' => $row->page_id,
						'action' => 'history'
					]
				)
			)->escaped();
	}

	/**
	 * @param int $type
	 * @return string
	 */
	private function getNewPageFlag( int $type ): string {
		if ( $type == RC_NEW ) {
			return Html::rawElement( 'span',
				[ 'class' => 'newpage' ],
				$this->message['newpageletter']
			);
		}
		return '';
	}

	/**
	 * @param bool $minor
	 * @return string
	 */
	private function getMinorFlag( bool $minor ): string {
		if ( $minor ) {
			return Html::rawElement(
				'span',
				[ 'class' => 'minor' ],
				$this->message['minoreditletter']
			);
		}
		return '';
	}

	/**
	 * @param string $timestamp
	 * @return string
	 */
	private function getTime( string $timestamp ): string {
		return htmlspecialchars(
			$this->language->userTime( wfTimestamp( TS_MW, $timestamp ), $this->user )
		);
	}

	/**
	 * @param \stdClass $row
	 * @param RevisionRecord|null $revRecord
	 * @param ManualLogEntry|null $logEntry
	 * @return string
	 */
	private function getUserLinks( \stdClass $row, ?RevisionRecord $revRecord, ?ManualLogEntry $logEntry ): string {
		$userIsHidden = $this->isUserHidden( $row->user_text );
		$userHiddenClass = '';
		if ( $userIsHidden ) {
			$userHiddenClass = 'history-deleted mw-history-suppressed';
		}
		// Check if the RevisionRecord says that the user is hidden.
		if (
			!$userIsHidden &&
			$revRecord instanceof RevisionRecord
		) {
			$userIsHidden = !RevisionRecord::userCanBitfield(
				$revRecord->getVisibility(),
				RevisionRecord::DELETED_USER,
				$this->user
			);
			if ( $userIsHidden ) {
				$userHiddenClass = Linker::getRevisionDeletedClass( $revRecord );
			}
		}
		// Check if the ManualLogEntry says that the user is hidden.
		if (
			!$userIsHidden &&
			$logEntry instanceof ManualLogEntry
		) {
			$userIsHidden = !LogEventsList::userCanBitfield(
				$logEntry->getDeleted(),
				LogPage::DELETED_USER,
				$this->user
			);
			if ( $userIsHidden ) {
				$userHiddenClass = 'history-deleted';
				if ( $logEntry->isDeleted( LogPage::DELETED_RESTRICTED ) ) {
					$userHiddenClass .= ' mw-history-suppressed';
				}
			}
		}
		if ( $userIsHidden ) {
			return Html::element(
				'span',
				[ 'class' => $userHiddenClass ],
				$this->msg( 'rev-deleted-user' )->text()
			);
		} else {
			$userId = $row->user ?? 0;
			if ( $userId > 0 ) {
				$user = $this->userFactory->newFromId( $userId );
			} else {
				// This is an IP
				$user = $this->userFactory->newFromName( $row->user_text, UserRigorOptions::RIGOR_NONE );
			}

			$links = Html::rawElement(
				'span', [], Linker::userLink( $userId, $user->getName() )
			);

			$links .= Linker::userToolLinksRedContribs(
				$userId,
				$user->getName(),
				$user->getEditCount()
			);

			return $links;
		}
	}

	/**
	 * As we use the same small set of messages in various methods and that
	 * they are called often, we call them once and save them in $this->message
	 */
	private function preCacheMessages() {
		$msgKeys = [
			'diff', 'hist', 'minoreditletter', 'newpageletter', 'blocklink',
			'checkuser-logs-link-text', 'checkuser-log-link-text',
		];
		foreach ( $msgKeys as $msg ) {
			$this->message[$msg] = $this->msg( $msg )->escaped();
		}
	}

	/**
	 * @param string $key
	 * @param array $params
	 * @return Message
	 */
	private function msg( string $key, array $params = [] ): Message {
		return new Message( $key, $params, $this->language );
	}

	/**
	 * Should a given username should be hidden from the current user.
	 *
	 * @param string $username
	 * @return bool
	 */
	private function isUserHidden( string $username ): bool {
		$user = $this->userFactory->newFromName( $username );
		return $user !== null && $user->isHidden() && !$this->user->isAllowed( 'hideuser' );
	}
}
