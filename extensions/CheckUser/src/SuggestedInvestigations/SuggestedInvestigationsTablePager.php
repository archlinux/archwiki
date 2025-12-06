<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\CheckUser\SuggestedInvestigations;

use InvalidArgumentException;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Investigate\SpecialInvestigate;
use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\UserLinkRenderer;
use MediaWiki\Pager\CodexTablePager;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Codex\Utility\Codex;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

class SuggestedInvestigationsTablePager extends CodexTablePager {

	/**
	 * The unique sort fields for the sort options for unique paginate
	 */
	private const INDEX_FIELDS = [
		'sic_created_timestamp' => [ 'sic_created_timestamp', 'sic_id' ],
		'sic_status' => [ 'sic_status', 'sic_created_timestamp', 'sic_id' ],
	];

	/** Database with the users table */
	private IReadableDatabase $userDb;

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly UserLinkRenderer $userLinkRenderer,
		?IContextSource $context = null,
		?LinkRenderer $linkRenderer = null
	) {
		// If we didn't set mDb here, the parent constructor would set it to the database replica
		// for the default database domain
		$this->mDb = $this->connectionProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		parent::__construct(
			$this->msg( 'checkuser-suggestedinvestigations-table-caption' )->text(),
			$context,
			$linkRenderer
		);
		// $this->mDefaultLimit does *not* set the actual default limit in the superclass, it's used to constructURLs
		$this->mDefaultLimit = 10;
		$this->mLimit = $this->mRequest->getInt( 'limit', $this->mDefaultLimit );
		if ( $this->mLimit <= 0 ) {
			$this->mLimit = $this->mDefaultLimit;
		}
		if ( $this->mLimit > 100 ) {
			$this->mLimit = 100;
		}
		$this->mLimitsShown = [ 10, 20, 50 ];

		$this->userDb = $this->connectionProvider->getReplicaDatabase();
	}

	/**
	 * @inheritDoc
	 * @param string $name
	 * @param null|string|array $value
	 * @return string
	 */
	public function formatValue( $name, $value ) {
		return match ( $name ) {
			'users' => $this->formatUsersCell( $value ),
			'signals' => $this->formatSignalsCell( $value ),
			'sic_created_timestamp' => $this->formatTimestampCell( $value ),
			'sic_status' => $this->formatStatusCell( CaseStatus::from( (int)$value ), $this->mCurrentRow->sic_id ),
			'sic_status_reason' => $this->formatStatusReasonCell(
				$value,
				CaseStatus::from( (int)$this->mCurrentRow->sic_status ),
				$this->mCurrentRow->sic_id
			),
			'actions' => $this->formatActionsCell(
				$this->mCurrentRow->sic_id,
				CaseStatus::from( (int)$this->mCurrentRow->sic_status ),
				$this->mCurrentRow->sic_status_reason
			),
			default => throw new InvalidArgumentException( 'Unknown field name: ' . $name ),
		};
	}

	/**
	 * @param UserIdentity[] $users
	 * @return string
	 */
	private function formatUsersCell( array $users ): string {
		$formattedUsers = Html::openElement( 'ul', [ 'class' => 'mw-checkuser-suggestedinvestigations-users' ] );

		// Hide users after the third by default, but only if we'd be hiding at least two users
		$userHideThreshold = 3;
		if ( count( $users ) <= $userHideThreshold + 1 ) {
			$userHideThreshold++;
		}

		foreach ( $users as $i => $user ) {
			$userLink = $this->userLinkRenderer->userLink( $user, $this->getContext() );

			$formattedUsers .= Html::rawElement(
				'li', [
					'class' => $i >= $userHideThreshold ?
						'mw-checkuser-suggestedinvestigations-user-defaulthide'
						: '',
				],
				$this->msg( 'checkuser-suggestedinvestigations-user-check' )
					->rawParams( $userLink )
					->params(
						SpecialPage::getTitleFor( 'CheckUser', $user->getName() )->getFullText(),
						$user->getName()
					)
					->parse()
			);
		}
		$formattedUsers .= Html::closeElement( 'ul' );
		return $formattedUsers;
	}

	private function formatSignalsCell( array $signals ): string {
		// For grepping, the currently known signal messages are:
		// * checkuser-suggestedinvestigations-signal-sharedemail
		// * checkuser-suggestedinvestigations-signal-hcaptcha
		$signalLabels = array_map(
			fn ( $signal ) =>
				$this->msg( 'checkuser-suggestedinvestigations-signal-' . $signal['name'] ),
			$signals
		);

		return $this->getLanguage()->commaList( $signalLabels );
	}

	private function formatTimestampCell( string $timestamp ): string {
		$lang = $this->getLanguage();
		$user = $this->getContext()->getUser();
		return htmlspecialchars( $lang->userTimeAndDate( $timestamp, $user ) );
	}

	private function formatStatusCell( CaseStatus $status, int $caseId ): string {
		$statusKey = match ( $status ) {
			CaseStatus::Open => 'checkuser-suggestedinvestigations-status-open',
			CaseStatus::Resolved => 'checkuser-suggestedinvestigations-status-resolved',
			CaseStatus::Invalid => 'checkuser-suggestedinvestigations-status-invalid',
		};
		$statusText = $this->msg( $statusKey )->text();

		$chipType = match ( $status ) {
			CaseStatus::Resolved => 'success',
			CaseStatus::Invalid => 'warning',
			default => 'notice',
		};

		$codex = new Codex();
		$statusChip = $codex->infoChip()
			->setText( $statusText )
			->setStatus( $chipType )
			->setIcon( 'cdx-info-chip__icon' )
			->build()
			->getHtml();

		return Html::rawElement(
			'div',
			[ 'data-case-id' => $caseId, 'class' => 'mw-checkuser-suggestedinvestigations-status' ],
			$statusChip
		);
	}

	private function formatStatusReasonCell( string $reason, CaseStatus $status, int $caseId ): string {
		if ( $status === CaseStatus::Invalid && $reason === '' ) {
			$reason = $this->msg( 'checkuser-suggestedinvestigations-status-reason-default-invalid' )->text();
		}
		return Html::element(
			'span',
			[ 'data-case-id' => $caseId, 'class' => 'mw-checkuser-suggestedinvestigations-status-reason' ],
			$reason
		);
	}

	private function formatActionsCell( int $caseId, CaseStatus $status, string $reason ): string {
		$actionsHtml = Html::openElement( 'div', [
			'class' => 'mw-checkuser-suggestedinvestigations-actions',
		] );

		/** @var UserIdentity[] $users */
		$users = $this->mCurrentRow->users;

		$investigateEnabled = false;
		$investigateUrl = null;

		// Enable the "Investigate" button only if there are not too many targets
		if ( count( $users ) <= SpecialInvestigate::MAX_TARGETS ) {
			$investigateEnabled = true;
			$investigateUrl = SpecialPage::getTitleFor( 'Investigate' )->getFullURL( [
				// Special:Investigate expects a list of usernames separated by newlines
				'targets' => implode( "\n", array_map( static fn ( $u ) => $u->getName(), $users ) ),
			] );
		}

		// Render the "Investigate" button as a link, because it will make it more natural: it supports by default
		// opening in a new tab, the user won't need to wait for the JS to load, and it works even if JS is disabled.
		// HTML structure as defined on
		// https://doc.wikimedia.org/codex/main/components/demos/button.html#link-buttons-and-other-elements
		$investigateButtonClasses = [
			'cdx-button',
			'cdx-button--fake-button',
			$investigateEnabled ? 'cdx-button--fake-button--enabled' : 'cdx-button--fake-button--disabled',
			'cdx-button--weight-quiet',
			'cdx-button--icon-only',
		];

		$investigateButtonTitle = $this->msg( 'checkuser-suggestedinvestigations-action-investigate' )->text();
		if ( !$investigateEnabled ) {
			$investigateButtonTitle = $this->msg( 'checkuser-suggestedinvestigations-action-investigate-disabled' )
				->numParams( SpecialInvestigate::MAX_TARGETS )->text();
		}

		$actionsHtml .= Html::openElement(
			'a', [
				'role' => 'button',
				'class' => $investigateButtonClasses,
				'title' => $investigateButtonTitle,
				'href' => $investigateUrl,
			]
		);
		$actionsHtml .= Html::element( 'span', [
			'class' => 'cdx-button__icon mw-checkuser-suggestedinvestigations-icon--investigate',
		] );
		$actionsHtml .= Html::closeElement( 'a' );

		$codex = new Codex();
		$actionsHtml .= $codex->button()
			->setIconOnly( true )
			->setIconClass( 'mw-checkuser-suggestedinvestigations-icon--edit' )
			->setAttributes( [
				'title' => $this->msg( 'checkuser-suggestedinvestigations-action-change-status' )->text(),
				'data-case-id' => $caseId,
				'data-case-status' => strtolower( $status->name ),
				'data-case-status-reason' => $reason,
				'class' => 'mw-checkuser-suggestedinvestigations-change-status-button',
			] )
			->setWeight( 'quiet' )
			->build()
			->getHtml();

		$actionsHtml .= Html::closeElement( 'div' );
		return $actionsHtml;
	}

	/** @inheritDoc */
	public function reallyDoQuery( $offset, $limit, $order ) {
		$cases = parent::reallyDoQuery( $offset, $limit, $order );

		$caseIds = [];
		foreach ( $cases as $case ) {
			$caseIds[] = $case->sic_id;
		}

		$signals = $this->querySignalsForCases( $caseIds );
		$caseUsers = $this->queryUsersForCases( $caseIds );

		$result = [];

		foreach ( $cases as $caseRow ) {
			$caseRow->signals = $signals[$caseRow->sic_id] ?? [];
			$caseRow->users = $caseUsers[$caseRow->sic_id] ?? [];
			$result[] = $caseRow;
		}

		return new FakeResultWrapper( $result );
	}

	/** @inheritDoc */
	public function getQueryInfo() {
		return [
			'tables' => [
				'cusi_case',
			],
			'fields' => [
				'sic_id',
				'sic_status',
				'sic_created_timestamp',
				'sic_status_reason',
			],
		];
	}

	/**
	 * Returns an array that maps each case ID to an array of signals. The signals are returned
	 * as arrays with 'name' and 'value' keys.
	 */
	private function querySignalsForCases( array $caseIds ): array {
		if ( count( $caseIds ) === 0 ) {
			return [];
		}

		$dbr = $this->getDatabase();
		$result = $dbr->newSelectQueryBuilder()
			->select( [ 'sis_sic_id', 'sis_name', 'sis_value' ] )
			->from( 'cusi_signal' )
			->where( [
				'sis_sic_id' => $caseIds,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$signalsForCases = [];
		foreach ( $result as $row ) {
			$caseId = $row->sis_sic_id;
			if ( !isset( $signalsForCases[$caseId] ) ) {
				$signalsForCases[$caseId] = [];
			}

			$signalsForCases[$caseId][] = [
				'name' => $row->sis_name,
				'value' => $row->sis_value,
			];
		}

		return $signalsForCases;
	}

	/**
	 * Returns an array that maps each case ID to an array of user identities associated with that case.
	 * @return UserIdentity[][]
	 */
	private function queryUsersForCases( array $caseIds ): array {
		if ( count( $caseIds ) === 0 ) {
			return [];
		}

		$dbr = $this->getDatabase();
		$resultCaseUserId = $dbr->newSelectQueryBuilder()
			->select( [ 'siu_sic_id', 'siu_user_id' ] )
			->from( 'cusi_user' )
			->where( [
				'siu_sic_id' => $caseIds,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$userIds = [];
		foreach ( $resultCaseUserId as $row ) {
			$userIds[] = $row->siu_user_id;
		}
		$userIds = array_unique( $userIds );

		$dbrUsers = $this->userDb;
		$userIdToName = [];
		foreach ( array_chunk( $userIds, 100 ) as $userIdChunk ) {
			$resultUsers = $dbrUsers->newSelectQueryBuilder()
				->select( [ 'user_id', 'user_name' ] )
				->from( 'user' )
				->where( [
					'user_id' => $userIdChunk,
				] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $resultUsers as $row ) {
				$userIdToName[$row->user_id] = $row->user_name;
			}
		}

		$usersForCases = [];
		foreach ( $resultCaseUserId as $row ) {
			$caseId = $row->siu_sic_id;
			if ( !isset( $usersForCases[$caseId] ) ) {
				$usersForCases[$caseId] = [];
			}

			$userId = $row->siu_user_id;
			$userName = $userIdToName[$userId];
			$usersForCases[$caseId][] = UserIdentityValue::newRegistered( $userId, $userName );
		}

		return $usersForCases;
	}

	/** @inheritDoc */
	public function getFullOutput(): ParserOutput {
		$pout = parent::getFullOutput();
		$pout->addModules( [ 'ext.checkUser.suggestedInvestigations' ] );
		return $pout;
	}

	/** @inheritDoc */
	protected function isFieldSortable( $field ) {
		return $field === 'sic_created_timestamp'
			|| $field === 'sic_status';
	}

	/** @inheritDoc */
	public function getDefaultSort() {
		return 'sic_created_timestamp';
	}

	/** @inheritDoc */
	protected function getDefaultDirections() {
		return self::DIR_DESCENDING;
	}

	/** @inheritDoc */
	public function getIndexField() {
		return [ self::INDEX_FIELDS[$this->mSort] ];
	}

	/** @inheritDoc */
	protected function getFieldNames() {
		return [
			'users' => $this->msg( 'checkuser-suggestedinvestigations-header-users' )->text(),
			'signals' => $this->msg( 'checkuser-suggestedinvestigations-header-signals' )->text(),
			'sic_created_timestamp' => $this->msg( 'checkuser-suggestedinvestigations-header-created' )->text(),
			'sic_status' => $this->msg( 'checkuser-suggestedinvestigations-header-status' )->text(),
			'sic_status_reason' => $this->msg( 'checkuser-suggestedinvestigations-header-notes' )->text(),
			'actions' => $this->msg( 'checkuser-suggestedinvestigations-header-actions' )->text(),
		];
	}

	/** @inheritDoc */
	public function getModuleStyles(): array {
		return array_merge( parent::getModuleStyles(), [
			'ext.checkUser.suggestedInvestigations.styles',
		] );
	}
}
