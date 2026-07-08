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

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Pagers;

use InvalidArgumentException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CentralAuth\CentralAuthEditCounter;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\CheckUser\Investigate\SpecialInvestigate;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Formatters\StatusReasonFormatter;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Navigation\SuggestedInvestigationsPagerNavigationBuilder;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\CompositeBlockChecker;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsMessageRenderer;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Navigation\CodexPagerNavigationBuilder;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Pager\CodexTablePager;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use Psr\Log\LoggerInterface;
use Wikimedia\Codex\Utility\Codex;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class SuggestedInvestigationsCasesPager extends CodexTablePager {

	/**
	 * @var array An array describing what filters are currently applied, passed to the
	 *   client using a JS config var and used for instrumentation.
	 * @internal Only public for use by {@link SpecialSuggestedInvestigations}
	 */
	public array $appliedFilters;

	/**
	 * @var CaseStatus[] The list of case statuses to be shown in the table. Empty array means all statuses.
	 */
	private array $statusFilter = [];

	/**
	 * @var string[] If not an empty array, then only show cases with these users in
	 */
	private array $userNamesFilter = [];

	/**
	 * @var bool If true, hide cases where all of the accounts in the case have no edits
	 */
	private bool $hideCasesWithNoUserEdits = false;

	/**
	 * @var bool If true, hide cases where all of the accounts in the case are unblocked
	 */
	private bool $hideCasesWithNoBlockedUsers = false;

	/**
	 * @var string[] If not an empty array, then filter for these signal database names
	 */
	private array $signalsFilter = [];

	/** @var int|null Number of days to filter by case updated timestamp; null = all time */
	private ?int $lastUpdatedDaysFilter = null;

	/**
	 * @var int The number of filters applied (counting all filters present in the filters dialog)
	 */
	private int $numberOfFiltersApplied = 0;

	/**
	 * @var array An array of database signal names to the display name for that signal
	 */
	private array $signalsToDisplayNames = [];

	/**
	 * @var int[] User IDs of users who have at least one check on them recorded in the cu_log table
	 */
	private array $usersWhoHaveBeenChecked = [];

	/**
	 * @var bool Whether to use Special:GlobalContributions over Special:Contributions for
	 *   the user contributions link.
	 */
	private bool $useGlobalContribs;

	/**
	 * @var bool Whether the filters implemented using PHP have scanned too many rows and have
	 *   returned only partial results
	 */
	private bool $phpFiltersLimitReached = false;

	/**
	 * Allowed values for the lastUpdated URL param, in days.
	 * Must match the numeric values in lastUpdatedOptions in Constants.js.
	 * The empty-string "all time" option in Constants.js is intentionally not listed here.
	 */
	private const ALLOWED_LAST_UPDATED_DAYS = [ 1, 3, 7, 90 ];

	/**
	 * The unique sort fields for the sort options for unique paginate
	 */
	private const INDEX_FIELDS = [
		'sic_updated_timestamp' => [ 'sic_updated_timestamp', 'sic_id' ],
		'sic_status' => [ 'sic_status', 'sic_updated_timestamp', 'sic_id' ],
	];

	/** Database with the users and cu_log table */
	private readonly IReadableDatabase $localDb;

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly UserEditTracker $userEditTracker,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly StatusReasonFormatter $statusReasonFormatter,
		private readonly ?CentralAuthEditCounter $centralAuthEditCounter,
		private readonly LinkBatchFactory $linkBatchFactory,
		private readonly UserFactory $userFactory,
		private readonly CompositeBlockChecker $compositeBlockChecker,
		private readonly LoggerInterface $logger,
		private readonly SuggestedInvestigationsMessageRenderer $messageRenderer,
		LinkRenderer $linkRenderer,
		IContextSource $context,
		array $signals,
		private readonly ?int $caseIdFilter,
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

		$this->localDb = $this->connectionProvider->getReplicaDatabase();

		$urlNamesToSignals = [];
		foreach ( $signals as $signal ) {
			if ( is_array( $signal ) ) {
				if ( array_key_exists( 'urlName', $signal ) ) {
					$urlNamesToSignals[$signal['urlName']] = $signal['name'];
				}

				if ( array_key_exists( 'displayName', $signal ) ) {
					$this->signalsToDisplayNames[$signal['name']] = $signal['displayName'];
					continue;
				}

				$signal = $signal['name'];
			}

			// For grepping, the currently known signal messages are:
			// * checkuser-suggestedinvestigations-signal-dev-signal-1
			// * checkuser-suggestedinvestigations-signal-dev-signal-2
			$this->signalsToDisplayNames[$signal] = $this->msg(
				'checkuser-suggestedinvestigations-signal-' . $signal
			)->parse();
		}

		$this->useGlobalContribs = $this->centralAuthEditCounter !== null &&
			$this->specialPageFactory->exists( 'GlobalContributions' ) &&
			$this->getConfig()->get( 'CheckUserSuggestedInvestigationsUseGlobalContributionsLink' );

		$this->parseFilters( $urlNamesToSignals );
	}

	/**
	 * Parses the filters set in the request and applies them to the pager.
	 * Intended for calling during execution of {@link self::__construct}
	 */
	private function parseFilters( array $urlNamesToSignals ): void {
		if ( $this->caseIdFilter === null ) {
			$this->parseFiltersForMainView( $urlNamesToSignals );
		}

		$this->appliedFilters = [
			'status' => array_map(
				static fn ( CaseStatus $status ) => strtolower( $status->name ),
				$this->statusFilter
			),
			'username' => $this->userNamesFilter,
			'hideCasesWithNoUserEdits' => $this->hideCasesWithNoUserEdits,
			'hideCasesWithNoBlockedUsers' => $this->hideCasesWithNoBlockedUsers,
			'signal' => $this->signalsFilter,
			'lastUpdated' => $this->lastUpdatedDaysFilter,
		];
	}

	/**
	 * Parses the filters in the request when the request is for the main
	 * table view of the special page (i.e. not the detail view).
	 */
	private function parseFiltersForMainView( array $urlNamesToSignals ): void {
		$this->statusFilter = array_filter( array_map(
			CaseStatus::newFromStringName( ... ),
			$this->mRequest->getArray( 'status', [] )
		) );
		if ( count( $this->statusFilter ) !== 0 ) {
			$this->numberOfFiltersApplied += count( $this->statusFilter );
		}

		$this->userNamesFilter = array_filter( $this->mRequest->getArray( 'username', [] ) );
		if ( $this->userNamesFilter ) {
			$this->numberOfFiltersApplied += count( $this->userNamesFilter );
		}

		// Default to true; the JS sends hideCasesWithNoUserEdits=0 to explicitly opt out
		$this->hideCasesWithNoUserEdits = $this->mRequest->getBool( 'hideCasesWithNoUserEdits', true );

		// Only count as an applied filter when set to non-default (i.e. =0)
		if ( !$this->hideCasesWithNoUserEdits ) {
			$this->numberOfFiltersApplied++;
		}

		$this->hideCasesWithNoBlockedUsers = $this->mRequest->getBool( 'hideCasesWithNoBlockedUsers' );
		if ( $this->hideCasesWithNoBlockedUsers ) {
			$this->numberOfFiltersApplied++;
		}

		$filteredSignals = $this->mRequest->getArray( 'signal', [] );
		foreach ( $filteredSignals as $signal ) {
			// Decode the URL name into the database name for the signal,
			// treating it as the signal database name if no matching
			// URL signal found
			if ( array_key_exists( $signal, $urlNamesToSignals ) ) {
				$signal = $urlNamesToSignals[$signal];
			}

			$this->signalsFilter[] = $signal;
		}
		if ( $this->signalsFilter ) {
			$this->numberOfFiltersApplied += count( $this->signalsFilter );
		}

		$lastUpdatedDays = $this->mRequest->getIntOrNull( 'lastUpdated' );
		$this->lastUpdatedDaysFilter = in_array( $lastUpdatedDays, self::ALLOWED_LAST_UPDATED_DAYS, true )
			? $lastUpdatedDays : null;
		if ( $this->lastUpdatedDaysFilter !== null ) {
			$this->numberOfFiltersApplied++;
		}
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
			'sic_updated_timestamp' => $this->formatTimestampCell( $value ),
			'sic_status' => $this->formatStatusCell( CaseStatus::from( (int)$value ), $this->mCurrentRow->sic_id ),
			'sic_status_reason' => $this->formatStatusReasonCell(
				$value,
				CaseStatus::from( (int)$this->mCurrentRow->sic_status ),
				$this->mCurrentRow->sic_id,
				$this->mCurrentRow->sic_status_changed_by_user_name ?? null
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

		$detailViewLink = $this->getDetailViewTitle( $this->mCurrentRow->sic_url_identifier )->getFullText();

		$contributionsSpecialPage = $this->useGlobalContribs ? 'GlobalContributions' : 'Contributions';

		foreach ( $users as $i => $user ) {
			$userVisible = $this->getAuthority()->isAllowed( 'hideuser' ) ||
				!$this->userFactory->newFromUserIdentity( $user )->isHidden();
			if ( $userVisible ) {
				$userLink = $this->getLinkRenderer()->makeUserLink( $user, $this->getContext() );

				// Generate a link to Special:CheckUser with a prefilled 'reason' input field that links back to the
				// case that this user is in.
				$checkUserPrefilledReason = $this->msg( 'checkuser-suggestedinvestigations-user-check-reason-prefill' )
					->params( $detailViewLink )
					->numParams( $this->mCurrentRow->sic_id )
					->params( $user->getName() )
					->inContentLanguage()
					->text();

				// Generate the link class for the "contribs" tool link
				$userContribsLinkClass = 'mw-usertoollinks-contribs';

				if ( $this->useGlobalContribs ) {
					$editCount = $this->centralAuthEditCounter->getCount(
						CentralAuthUser::getInstance( $user )
					);
				} else {
					$editCount = $this->userEditTracker->getUserEditCount( $user );
				}
				if ( $editCount === 0 ) {
					// Use same CSS classes as Linker::userToolLinkArray to get a red link when no contribs
					$userContribsLinkClass .= ' mw-usertoollinks-contribs-no-edits';
				}

				// Add link to either Special:Contributions or Special:GlobalContributions
				$userToolLinks = [];
				$userToolLinks[] = $this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( $contributionsSpecialPage, $user->getName() ),
					$this->msg( 'contribslink' )
						->params( $user->getName() )
						->text(),
					[ 'class' => $userContribsLinkClass ]
				);

				// Add link to Special:CheckUserLog if the user has been checked before and the
				// viewing authority has the 'checkuser-log' right
				if (
					in_array( $user->getId(), $this->usersWhoHaveBeenChecked ) &&
					$this->getAuthority()->isAllowed( 'checkuser-log' )
				) {
					$userToolLinks[] = $this->getLinkRenderer()->makeKnownLink(
						SpecialPage::getTitleFor( 'CheckUserLog', $user->getName() ),
						$this->msg( 'checkuser-suggestedinvestigations-user-past-checks-link-text' )
							->params( $user->getName() )
							->text()
					);
				}

				// Add link to Special:CheckUser if the user has the 'checkuser' right
				if ( $this->getAuthority()->isAllowed( 'checkuser' ) ) {
					$userToolLinks[] = $this->getLinkRenderer()->makeKnownLink(
						SpecialPage::getTitleFor( 'CheckUser', $user->getName() ),
						$this->msg( 'checkuser-suggestedinvestigations-user-check-link-text' )
							->params( $user->getName() )
							->text(),
						[],
						[ 'reason' => $checkUserPrefilledReason ]
					);
				}
			} else {
				$userLink = Html::element(
					'span',
					[ 'class' => 'history-deleted' ],
					$this->msg( 'rev-deleted-user' )->text()
				);
				$userToolLinks = [];
			}

			$userToolLinksHtml = '';
			if ( $userToolLinks !== [] ) {
				$userToolLinksHtml = $this->msg( 'parentheses' )
					->rawParams( $this->getLanguage()->pipeList( $userToolLinks ) )
					->escaped();
			}

			$formattedUsers .= Html::rawElement(
				'li',
				[
					'class' => $i >= $userHideThreshold ?
						'mw-checkuser-suggestedinvestigations-user-defaulthide'
						: '',
				],
				$this->msg( 'checkuser-suggestedinvestigations-user' )
					->rawParams( $userLink, $userToolLinksHtml )
					->parse()
			);
		}
		$formattedUsers .= Html::closeElement( 'ul' );
		return $formattedUsers;
	}

	private function formatSignalsCell( array $signals ): string {
		$signalLabels = array_map( $this->getSignalDisplayNameForSignal( ... ), $signals );

		return $this->getLanguage()->commaList( $signalLabels );
	}

	private function getSignalDisplayNameForSignal( string $signal ): string {
		if ( !array_key_exists( $signal, $this->signalsToDisplayNames ) ) {
			// This can happen when a signal was once defined, but is no longer
			// defined. This can also happen for the local dev signals when creating fake cases.
			// In both cases, fallback to trying the i18n message constructed from the signal name.
			return $this->msg( 'checkuser-suggestedinvestigations-signal-' . $signal )->parse();
		}
		return $this->signalsToDisplayNames[$signal];
	}

	private function formatTimestampCell( string $timestamp ): string {
		$lang = $this->getLanguage();
		$user = $this->getContext()->getUser();

		// If in the detail view (which is when the case ID filter is set),
		// don't show the link as it will be a link to the current page.
		if ( $this->caseIdFilter ) {
			return htmlspecialchars( $lang->userTimeAndDate( $timestamp, $user ) );
		}

		return $this->getLinkRenderer()->makeKnownLink(
			$this->getDetailViewTitle( $this->mCurrentRow->sic_url_identifier ),
			$lang->userTimeAndDate( $timestamp, $user )
		);
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

	private function formatStatusReasonCell(
		string $reason,
		CaseStatus $status,
		int $caseId,
		string|null $closedByUserName
	): string {
		$html = $this->statusReasonFormatter->format(
			$reason,
			$status,
			$closedByUserName,
			$this->getContext()
		);

		return Html::rawElement(
			'span',
			[ 'data-case-id' => $caseId, 'class' => 'mw-checkuser-suggestedinvestigations-status-reason' ],
			$html
		);
	}

	private function formatActionsCell( int $caseId, CaseStatus $status, string $reason ): string {
		$actionsHtml = Html::openElement( 'div', [
			'class' => 'mw-checkuser-suggestedinvestigations-actions',
		] );

		/** @var UserIdentity[] $users */
		$users = array_filter(
			$this->mCurrentRow->users,
			fn ( UserIdentity $user ) => $this->getAuthority()->isAllowed( 'hideuser' ) ||
				!$this->userFactory->newFromUserIdentity( $user )->isHidden()
		);

		$investigateEnabled = false;
		$investigateUrl = null;

		// Enable the "Investigate" button only if there are not too many targets and at least one
		// not suppressed username target
		if ( count( $users ) <= SpecialInvestigate::MAX_TARGETS && count( $users ) !== 0 ) {
			$investigateEnabled = true;

			$prefilledReason = $this->msg( 'checkuser-suggestedinvestigations-user-investigate-reason-prefill' )
				->params( $this->getDetailViewTitle( $this->mCurrentRow->sic_url_identifier )->getFullText() )
				->numParams( $this->mCurrentRow->sic_id )
				->inContentLanguage()
				->text();

			$investigateUrl = SpecialPage::getTitleFor( 'Investigate' )->getFullURL( [
				// Special:Investigate expects a list of usernames separated by newlines
				'targets' => implode( "\n", array_map( static fn ( $u ) => $u->getName(), $users ) ),
				'reason' => $prefilledReason,
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
			'mw-checkuser-suggestedinvestigations-investigate-action',
		];

		$investigateButtonTitle = $this->msg( 'checkuser-suggestedinvestigations-action-investigate' )->text();
		if ( !$investigateEnabled && count( $users ) !== 0 ) {
			$investigateButtonTitle = $this->msg( 'checkuser-suggestedinvestigations-action-investigate-disabled' )
				->numParams( SpecialInvestigate::MAX_TARGETS )->text();
		}

		$actionsHtml .= Html::openElement(
			'a',
			[
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
				'data-case-signals' => implode( ' ', $this->mCurrentRow->signals ),
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
		$cases = [];
		$batchOffset = $offset;
		$queryLimit = $limit;
		$loopsPerformed = 0;
		do {
			// Each time we perform a new loop, double the number of rows fetched per query up to 512 rows (2^9).
			// This is done to balance the number of queries needed to fetch results against selecting
			// rows which are then discarded without use.
			// This also avoids an indefinite loop that can be triggered by the IndexPager::isFirst call
			// of this method (where the same single row is selected in a loop).
			if ( $loopsPerformed !== 0 && $queryLimit < 512 ) {
				$queryLimit *= 2;
			}

			$batchOfCases = iterator_to_array( parent::reallyDoQuery( $batchOffset, $queryLimit, $order ) );

			if ( count( $batchOfCases ) ) {
				$batchOffset = implode( '|', array_map(
					static fn ( $indexColumn ) => $batchOfCases[array_key_last( $batchOfCases )]->$indexColumn,
					(array)$this->mIndexField
				) );
			}

			$caseIdsBatch = [];
			foreach ( $batchOfCases as $case ) {
				$caseIdsBatch[] = $case->sic_id;
			}

			// Query the users for each case row and add them to the case rows. Case rows are filtered
			// out if no users are found for the case (as this indicates that the row has been
			// filtered out by a filter on the users in the case)
			$caseUsers = $this->queryUsersForCases( $caseIdsBatch );
			foreach ( $batchOfCases as $i => $caseRow ) {
				if ( array_key_exists( $caseRow->sic_id, $caseUsers ) ) {
					$caseRow->users = $caseUsers[$caseRow->sic_id];
				} else {
					unset( $batchOfCases[$i] );
				}
			}

			$cases = array_merge( $cases, $batchOfCases );

			// We have a safeguard against too many queries that stops looking for rows after 10 loops
			// to avoid this being a DDoS vector. This condition is reached once at least 1,023 cases
			// and at most 6,300 cases are checked using PHP filters (cases are those returned by
			// parent::reallyDoQuery, so does not include those excluded using SQL filters).
			$loopsPerformed++;
		} while ( count( $cases ) < $limit && count( $caseIdsBatch ) === $queryLimit && $loopsPerformed < 10 );

		if ( !$this->phpFiltersLimitReached ) {
			$this->phpFiltersLimitReached = $loopsPerformed >= 10;
		}

		$cases = array_slice( $cases, 0, $limit );

		$caseIds = [];
		foreach ( $cases as $case ) {
			$caseIds[] = $case->sic_id;
		}

		$signals = $this->querySignalsForCases( $caseIds );
		foreach ( $cases as $caseRow ) {
			$caseRow->signals = $signals[$caseRow->sic_id] ?? [];
		}

		$performerIds = [];
		foreach ( $cases as $case ) {
			if ( $case->sic_status_changed_by !== null && (int)$case->sic_status_changed_by > 0 ) {
				$performerIds[] = (int)$case->sic_status_changed_by;
			}
		}
		$performerUserNames = $this->queryPerformerUserNames( array_unique( $performerIds ) );
		foreach ( $cases as $caseRow ) {
			$changedBy = $caseRow->sic_status_changed_by !== null ? (int)$caseRow->sic_status_changed_by : 0;
			$caseRow->sic_status_changed_by_user_name = $performerUserNames[$changedBy] ?? null;
		}

		return new FakeResultWrapper( $cases );
	}

	/** @inheritDoc */
	public function getQueryInfo(): array {
		$queryInfo = [
			'tables' => [
				'cusi_case',
			],
			'fields' => [
				'sic_id',
				'sic_status',
				'sic_updated_timestamp',
				'sic_status_reason',
				'sic_url_identifier',
				'sic_status_changed_by',
			],
			'conds' => [],
		];

		if ( $this->caseIdFilter !== null ) {
			$queryInfo['conds']['sic_id'] = $this->caseIdFilter;
		}

		if ( count( $this->statusFilter ) ) {
			$queryInfo['conds']['sic_status'] = array_map(
				static fn ( $status ) => $status->value,
				$this->statusFilter
			);
		}

		$queryInfo = $this->applyUsernameFilter( $queryInfo );
		$queryInfo = $this->applySignalsFilter( $queryInfo );
		$queryInfo = $this->applyLastUpdatedFilter( $queryInfo );

		return $queryInfo;
	}

	private function applyUsernameFilter( array $queryInfo ): array {
		if ( !$this->userNamesFilter ) {
			return $queryInfo;
		}

		$userIdentitiesQueryBuilder = $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserNames( $this->userNamesFilter )
			->caller( __METHOD__ );

		// Filter out local hidden usernames
		if ( !$this->getAuthority()->isAllowed( 'hideuser' ) ) {
			$userIdentitiesQueryBuilder->hidden( false );
		}

		$userIdentities = $userIdentitiesQueryBuilder->fetchUserIdentities();
		$userIds = array_map(
			static fn ( UserIdentity $user ) => $user->getId(),
			iterator_to_array( $userIdentities )
		);

		// If there are user IDs, then join on the query.
		// If none of the usernames equate to user IDs, then make the query return nothing
		// to indicate these usernames are not in any case.
		if ( $userIds ) {
			$queryInfo['tables'][] = 'cusi_user';
			$queryInfo['join_conds']['cusi_user'] = [ 'JOIN', 'sic_id = siu_sic_id' ];
			$queryInfo['conds']['siu_user_id'] = $userIds;
		} else {
			$queryInfo['conds'][] = '1=0';
		}

		return $queryInfo;
	}

	private function applySignalsFilter( array $queryInfo ): array {
		if ( !$this->signalsFilter ) {
			return $queryInfo;
		}
		$signalsFilterSubquerySql = $this->getDatabase()->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cusi_signal' )
			->where( [ 'sis_name' => $this->signalsFilter, 'sic_id = sis_sic_id' ] )
			->caller( __METHOD__ )
			->getSQL();

		$queryInfo['conds'][] = "EXISTS ($signalsFilterSubquerySql)";

		return $queryInfo;
	}

	private function applyLastUpdatedFilter( array $queryInfo ): array {
		if ( $this->lastUpdatedDaysFilter === null ) {
			return $queryInfo;
		}

		$db = $this->getDatabase();
		$cutoff = $db->timestamp(
			ConvertibleTimestamp::time() - $this->lastUpdatedDaysFilter * 86400
		);
		$queryInfo['conds'][] = $db->expr(
			'sic_updated_timestamp',
			'>=',
			$cutoff
		);

		return $queryInfo;
	}

	/** @inheritDoc */
	protected function doBatchLookups(): void {
		$users = [];
		$lb = $this->linkBatchFactory->newLinkBatch()
			->setCaller( __METHOD__ );

		foreach ( $this->mResult as $row ) {
			foreach ( $row->users as $user ) {
				$users[] = $user;
				$lb->addUser( $user );
			}
		}

		$lb->execute();

		foreach ( array_chunk( $users, 500 ) as $usersBatch ) {
			$this->usersWhoHaveBeenChecked = array_merge(
				$this->localDb->newSelectQueryBuilder()
					->select( 'cul_target_id' )
					->from( 'cu_log' )
					->where( [ 'cul_target_id' => array_map(
						static fn ( UserIdentity $user ) => $user->getId(),
						$usersBatch
					) ] )
					->caller( __METHOD__ )
					->fetchFieldValues(),
				$this->usersWhoHaveBeenChecked
			);
		}
	}

	/**
	 * Returns an array that maps each case ID to an array of signals. Only the name of the signals are returned.
	 * @return string[][]
	 */
	private function querySignalsForCases( array $caseIds ): array {
		if ( count( $caseIds ) === 0 ) {
			return [];
		}

		$dbr = $this->getDatabase();
		$result = $dbr->newSelectQueryBuilder()
			->select( [ 'sis_sic_id', 'sis_name' ] )
			->distinct()
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

			$signalsForCases[$caseId][] = $row->sis_name;
		}

		return $signalsForCases;
	}

	/**
	 * Returns an array mapping user IDs to user names for the given set of user IDs,
	 * queried from the local wiki database.
	 *
	 * @param int[] $userIds
	 * @return string[]
	 */
	private function queryPerformerUserNames( array $userIds ): array {
		if ( !$userIds ) {
			return [];
		}

		$userIdentities = $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $userIds )
			->caller( __METHOD__ )
			->fetchUserIdentities();

		$names = [];
		foreach ( $userIdentities as $user ) {
			$names[$user->getId()] = $user->getName();
		}

		return $names;
	}

	/**
	 * Returns an array that maps each case ID to an array of user identities
	 * associated with that case, ordered by account ID descending for each case.
	 *
	 * If no key exists for a case ID, then the case should be excluded as
	 * the case was filtered out by the “Hide cases where no accounts have edits” or
	 * "Hide cases where no accounts are blocked" filter.
	 *
	 * @return UserIdentity[][]
	 */
	private function queryUsersForCases( array $caseIds ): array {
		if ( count( $caseIds ) === 0 ) {
			return [];
		}

		$userIds = [];
		$caseIdsToUserIds = [];

		$dbr = $this->getDatabase();
		$lastUserId = null;
		$lastCaseId = null;
		do {
			$caseUsersQueryBuilder = $dbr->newSelectQueryBuilder()
				->select( [ 'siu_sic_id', 'siu_user_id' ] )
				->from( 'cusi_user' )
				->where( [
					'siu_sic_id' => $caseIds,
				] );
			if ( $lastCaseId !== null && $lastUserId !== null ) {
				$caseUsersQueryBuilder->where(
					$dbr->buildComparison( '<', [ 'siu_sic_id' => $lastCaseId, 'siu_user_id' => $lastUserId ] )
				);
			}
			$batchOfCaseUsers = $caseUsersQueryBuilder
				->orderBy( [ 'siu_sic_id', 'siu_user_id' ], SelectQueryBuilder::SORT_DESC )
				->limit( 500 )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $batchOfCaseUsers as $row ) {
				$caseId = (int)$row->siu_sic_id;
				$userId = (int)$row->siu_user_id;

				if ( !isset( $caseIdsToUserIds[$caseId] ) ) {
					$caseIdsToUserIds[$caseId] = [];
				}

				$userIds[] = $userId;
				$caseIdsToUserIds[$caseId][] = $userId;

				$lastUserId = $userId;
				$lastCaseId = $caseId;
			}
		} while ( $batchOfCaseUsers->numRows() > 0 );

		$userIds = array_unique( $userIds );

		$dbrUsers = $this->localDb;
		$userIdToUserIdentity = [];
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
				$userIdToUserIdentity[$row->user_id] = UserIdentityValue::newRegistered(
					$row->user_id,
					$row->user_name
				);
			}
		}

		// Preload the local or global user edit counts (as appropriate). Needed
		// for the $this->hideCasesWithNoUserEdits filter, but also for the "contribs"
		// link colour check in ::formatUsersCell
		if ( $this->useGlobalContribs ) {
			$this->centralAuthEditCounter->preloadGetCountCache( array_map(
				static fn ( UserIdentity $user ) => CentralAuthUser::getInstance( $user ),
				$userIdToUserIdentity
			) );
		} else {
			$this->userEditTracker->preloadUserEditCountCache( $userIdToUserIdentity );
		}

		$unblockedUserIds = [];
		if ( $this->hideCasesWithNoBlockedUsers ) {
			$unblockedUserIds = $this->compositeBlockChecker->getUserIdsNotBlocked(
				array_keys( $userIdToUserIdentity )
			);
		}

		// Group the UserIdentity objects by case IDs, while also excluding case IDs
		// which do not meet the hideCasesWithNoUserEdits filter (if enabled)
		$usersForCases = [];
		foreach ( $caseIdsToUserIds as $caseId => $userIds ) {
			if ( $this->hideCasesWithNoUserEdits ) {
				$caseHasNoEdits = true;

				foreach ( $userIds as $userId ) {
					$userIdentity = $userIdToUserIdentity[$userId];

					if ( $caseHasNoEdits ) {
						if ( $this->useGlobalContribs ) {
							$editCount = $this->centralAuthEditCounter->getCount(
								CentralAuthUser::getInstance( $userIdentity )
							);
						} else {
							$editCount = $this->userEditTracker->getUserEditCount( $userIdentity );
						}

						$caseHasNoEdits = $editCount === 0;
					}
				}

				if ( $caseHasNoEdits ) {
					continue;
				}
			}

			if ( $this->hideCasesWithNoBlockedUsers ) {
				if ( array_diff( $userIds, $unblockedUserIds ) === [] ) {
					continue;
				}
			}

			foreach ( $userIds as $userId ) {
				$usersForCases[$caseId][] = $userIdToUserIdentity[$userId];
			}
		}

		return $usersForCases;
	}

	/**
	 * Gets the Title for the detail view page for a case identified by it's URL identifier.
	 */
	private function getDetailViewTitle( int $urlIdentifier ): Title {
		return SpecialPage::getTitleFor( 'SuggestedInvestigations', 'detail/' . dechex( $urlIdentifier ) );
	}

	/**
	 * Renders the button used to open the filters dialog
	 * along with the table caption
	 *
	 * @inheritDoc
	 */
	protected function getHeader(): string {
		if ( !$this->mQueryDone ) {
			$this->doQuery();
		}

		// If the query has processed and filtered out too many rows in PHP, then display
		// a warning message about this to the user
		if ( $this->phpFiltersLimitReached ) {
			$this->getOutput()->addHTML( $this->messageRenderer->getUserDismissableWarning(
				$this->msg(
					'checkuser-suggestedinvestigations-filter-too-many-results-filtered-in-php'
				)->escaped(),
				'ext-checkuser-suggestedinvestigations-too-many-results-warning'
			) );

			$this->logger->warning(
				'PHP filter limits reached, so SI did not show a full page of results',
				[ 'applied_filters' => $this->appliedFilters, 'limit' => $this->mLimit ]
			);
		}

		if ( !$this->shouldShowVisibleCaption() ) {
			return '';
		}

		$tableCaption = Html::element(
			'div',
			[ 'class' => 'cdx-table__header__caption', 'aria-hidden' => 'true' ],
			$this->mCaption
		);

		return Html::rawElement(
			'div',
			[ 'class' => 'cdx-table__header' ],
			$tableCaption . $this->getNavigationBuilder()->getFilterButton()
		);
	}

	/**
	 * Only show the filter button and table caption when not
	 * in detailed view, as detailed view does not need filters.
	 *
	 * @inheritDoc
	 */
	protected function shouldShowVisibleCaption(): bool {
		return $this->caseIdFilter === null;
	}

	/** @inheritDoc */
	protected function getTableClass(): string {
		$tableClasses = [ 'ext-checkuser-suggestedinvestigations-table' ];
		if ( $this->isNavigationBarShown() ) {
			$tableClasses[] = 'ext-checkuser-suggestedinvestigations-table-with-navigation-bar';
		}
		return parent::getTableClass() . ' ' . implode( ' ', $tableClasses );
	}

	/** @inheritDoc */
	public function getFullOutput(): ParserOutput {
		$pout = parent::getFullOutput();
		$pout->addModules( [ 'ext.checkUser.suggestedInvestigations' ] );
		$pout->setJsConfigVar(
			'wgCheckUserSuggestedInvestigationsActiveFilters',
			$this->appliedFilters
		);
		$pout->setJsConfigVar(
			'wgCheckUserSuggestedInvestigationsGlobalEditCountsUsed',
			$this->useGlobalContribs
		);
		return $pout;
	}

	/** @inheritDoc */
	protected function isFieldSortable( $field ) {
		// When only one row could appear, there is no need
		// to allow sorting that one row
		if ( $this->caseIdFilter !== null ) {
			return false;
		}

		return $field === 'sic_updated_timestamp'
			|| $field === 'sic_status';
	}

	/** @inheritDoc */
	public function getDefaultSort() {
		return 'sic_updated_timestamp';
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
			'sic_updated_timestamp' => $this->msg( 'checkuser-suggestedinvestigations-header-updated' )->text(),
			'sic_status' => $this->msg( 'checkuser-suggestedinvestigations-header-status' )->text(),
			'sic_status_reason' => $this->msg( 'checkuser-suggestedinvestigations-header-notes' )->text(),
			'actions' => $this->msg( 'checkuser-suggestedinvestigations-header-actions' )->text(),
		];
	}

	/** @inheritDoc */
	public function getModuleStyles(): array {
		return array_merge( parent::getModuleStyles(), [
			'ext.checkUser.suggestedInvestigations.styles',
			'mediawiki.interface.helpers.styles',
		] );
	}

	protected function createNavigationBuilder(): CodexPagerNavigationBuilder {
		$builder = new SuggestedInvestigationsPagerNavigationBuilder(
			$this->getContext(),
			$this->prepareQueryValuesForNavigationBuilder( $this->getRequest()->getValues() ),
			$this->numberOfFiltersApplied
		);
		$builder->setNavClass( $this->getNavClass() );
		return $builder;
	}

	private function prepareQueryValuesForNavigationBuilder( array $queryValues ): array {
		// Flatten all known arrays with integer keys; skip unknown arrays
		$knownArrays = [ 'signal', 'status', 'username' ];
		$outputQueryValues = [];
		foreach ( $queryValues as $key => $value ) {
			if ( !is_array( $value ) ) {
				$outputQueryValues[$key] = $value;
				continue;
			}
			if ( !in_array( $key, $knownArrays, true ) ) {
				continue;
			}

			foreach ( $value as $subKey => $subValue ) {
				if ( !is_int( $subKey ) ) {
					continue;
				}
				$newKey = $key . '[' . $subKey . ']';
				$outputQueryValues[$newKey] = $subValue;
			}
		}
		return $outputQueryValues;
	}

	/**
	 * @return SuggestedInvestigationsPagerNavigationBuilder
	 */
	public function getNavigationBuilder(): SuggestedInvestigationsPagerNavigationBuilder {
		// @phan-suppress-next-line PhanTypeMismatchReturnSuperType
		return parent::getNavigationBuilder();
	}
}
