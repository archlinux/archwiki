<?php

namespace MediaWiki\CheckUser\GlobalContributions;

use GlobalPreferences\GlobalPreferencesFactory;
use HtmlArmor;
use InvalidArgumentException;
use LogicException;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Html\Html;
use MediaWiki\Html\TemplateParser;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Pager\ContributionsPager;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\RecentChanges\ChangesList;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\SpecialPage\ContributionsRangeTrait;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use OOUI\HtmlSnippet;
use OOUI\MessageWidget;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Query for all edits from one of:
 *  - an IP, revealing what temporary accounts are using that IP
 *  - a username (temporary or registered), revealing all edits from the account
 *
 * This pager uses data from the CheckUser table as it can reveal IP activity
 * which only CU should have knowledge of. Therefore, data is limited to 90 days.
 *
 */
class GlobalContributionsPager extends ContributionsPager implements CheckUserQueryInterface {

	use ContributionsRangeTrait;

	/**
	 * Prometheus counter metric name for API lookup errors.
	 */
	public const API_LOOKUP_ERROR_METRIC_NAME = 'checkuser_globalcontributions_api_lookup_error';

	private TempUserConfig $tempUserConfig;
	private CheckUserLookupUtils $checkUserLookupUtils;
	private CentralIdLookup $centralIdLookup;
	private CheckUserApiRequestAggregator $apiRequestAggregator;
	private CheckUserGlobalContributionsLookup $globalContributionsLookup;
	private PermissionManager $permissionManager;
	private GlobalPreferencesFactory $globalPreferencesFactory;
	private IConnectionProvider $dbProvider;
	private JobQueueGroup $jobQueueGroup;
	private StatsFactory $statsFactory;
	private array $permissions = [];
	private int $wikisWithPermissionsCount;
	private string $needsToEnableGlobalPreferenceAtWiki;
	private bool $externalApiLookupError = false;
	private bool $centralUserExists = true;

	/**
	 * @var int Number of revisions to return per wiki
	 */
	public const REVISION_COUNT_LIMIT = 20;

	public function __construct(
		LinkRenderer $linkRenderer,
		LinkBatchFactory $linkBatchFactory,
		HookContainer $hookContainer,
		RevisionStore $revisionStore,
		NamespaceInfo $namespaceInfo,
		CommentFormatter $commentFormatter,
		UserFactory $userFactory,
		TempUserConfig $tempUserConfig,
		CheckUserLookupUtils $checkUserLookupUtils,
		CentralIdLookup $centralIdLookup,
		CheckUserApiRequestAggregator $apiRequestAggregator,
		CheckUserGlobalContributionsLookup $globalContributionsLookup,
		PermissionManager $permissionManager,
		GlobalPreferencesFactory $globalPreferencesFactory,
		IConnectionProvider $dbProvider,
		JobQueueGroup $jobQueueGroup,
		StatsFactory $statsFactory,
		IContextSource $context,
		array $options,
		?UserIdentity $target = null
	) {
		$options['runHooks'] = false;

		parent::__construct(
			$linkRenderer,
			$linkBatchFactory,
			$hookContainer,
			$revisionStore,
			$namespaceInfo,
			$commentFormatter,
			$userFactory,
			$context,
			$options,
			$target
		);
		$this->tempUserConfig = $tempUserConfig;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
		$this->centralIdLookup = $centralIdLookup;
		$this->apiRequestAggregator = $apiRequestAggregator;
		$this->globalContributionsLookup = $globalContributionsLookup;
		$this->permissionManager = $permissionManager;
		$this->globalPreferencesFactory = $globalPreferencesFactory;
		$this->dbProvider = $dbProvider;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->templateParser = new TemplateParser( __DIR__ . '/../../templates' );
		$this->statsFactory = $statsFactory;
	}

	/**
	 * Fetch the permissions needed by this pager from a list of external wikis. Store them
	 * in the $permissions property.
	 *
	 * @param string[] $wikiIds
	 */
	private function getExternalWikiPermissions( $wikiIds ) {
		$permissions = $this->apiRequestAggregator->execute(
			$this->getUser(),
			[
				'action' => 'query',
				'prop' => 'info',
				'intestactions' => 'checkuser-temporary-account|checkuser-temporary-account-no-preference' .
					'|deletedtext|deletedhistory|suppressrevision|viewsuppressed',
				// We need to check against a title, but it doesn't actually matter if the title exists
				'titles' => 'Test Title',
				// Using `full` level checks blocks as well
				'intestactionsdetail' => 'full',
				'format' => 'json',
			],
			$wikiIds,
			$this->getRequest(),
			CheckUserApiRequestAggregator::AUTHENTICATE_CENTRAL_AUTH
		);
		foreach ( $wikiIds as $wikiId ) {
			if ( !isset( $permissions[$wikiId]['query']['pages'][0]['actions'] ) ) {
				// The API lookup failed, so assume the user does not have IP reveal rights.
				$this->externalApiLookupError = true;

				$this->statsFactory->getCounter( self::API_LOOKUP_ERROR_METRIC_NAME )
					->increment();

				continue;
			}
			$this->permissions[$wikiId] = $permissions[$wikiId]['query']['pages'][0]['actions'];
		}
	}

	/**
	 * Query the central index tables to find wikis that have recently been edited
	 * from by temporary accounts using the target IP.
	 *
	 * Also sets some properties:
	 * - $permissions, which holds information about external wiki permissions
	 * - $wikisWithPermissionsCount, the number of wikis where the user can reveal IPs
	 * - $needsToEnableGlobalPreferenceAtWiki, the wiki to enable a global preference at
	 *
	 * @return array
	 */
	private function fetchWikisToQuery() {
		$this->needsToEnableGlobalPreferenceAtWiki = '';

		if ( $this->isValidIPOrQueryableRange( $this->target, $this->getConfig() ) ) {
			$activeWikis = $this->globalContributionsLookup->getActiveWikis(
				$this->target,
				$this->getAuthority()
			);

			// Look up external permissions
			$this->getExternalWikiPermissions(
				array_filter( $activeWikis, static function ( $wikiId ) {
					// No need to do an API call to the local wiki
					return !WikiMap::isCurrentWikiDbDomain( $wikiId );
				} )
			);

			// Look up local permissions
			$isBlocked = $this->getAuthority()->getBlock();
			$canRevealIp = !$isBlocked && $this->permissionManager->userHasRight(
				$this->getAuthority()->getUser(),
				'checkuser-temporary-account'
			);
			$canRevealIpNoPreference = !$isBlocked && $this->permissionManager->userHasRight(
				$this->getAuthority()->getUser(),
				'checkuser-temporary-account-no-preference'
			);

			// Look up the global preference status
			$globalPreferences = $this->globalPreferencesFactory->getGlobalPreferencesValues(
				$this->getAuthority()->getUser(),
				// Load from the database, not the cache, since we're using it for access.
				true
			);
			$hasEnabledGlobalPreference = $globalPreferences &&
				isset( $globalPreferences['checkuser-temporary-account-enable'] ) &&
				$globalPreferences['checkuser-temporary-account-enable'];

			$wikisToQuery = [];
			foreach ( $activeWikis as $wikiId ) {
				// If an IP is being queried, check against the permissions obtained from the external wikis.
				// Otherwise, a temporary account or registered account is being queried, which can be broadly
				// accessed without restriction (revision/IP reveal restrictions will still apply).
				if ( $this->isValidIPOrQueryableRange( $this->target, $this->getConfig() ) ) {
					if (
						( WikiMap::isCurrentWikiDbDomain( $wikiId ) && $canRevealIpNoPreference ) ||
						$this->userHasExternalPermission( 'checkuser-temporary-account-no-preference', $wikiId )
					) {
						$wikisToQuery[] = $wikiId;
					} elseif (
						( WikiMap::isCurrentWikiDbDomain( $wikiId ) && $canRevealIp ) ||
						$this->userHasExternalPermission( 'checkuser-temporary-account', $wikiId )
					) {
						if ( $hasEnabledGlobalPreference ) {
							$wikisToQuery[] = $wikiId;
						} else {
							$this->needsToEnableGlobalPreferenceAtWiki = $wikiId;
						}
					}
				}
			}
		} else {
			try {
				$wikisToQuery = $this->globalContributionsLookup->getActiveWikis(
					$this->target,
					$this->getAuthority()
				);
			} catch ( InvalidArgumentException $e ) {
				// No central user found or viewable, flag it and then return an empty array for active wikis
				// which will eventually return an empty results set
				$this->centralUserExists = false;
				$this->wikisWithPermissionsCount = 0;
				return [];
			}
		}

		$this->wikisWithPermissionsCount = count( $wikisToQuery );

		return $wikisToQuery;
	}

	public function doQuery() {
		parent::doQuery();

		// If we reach here query has been made for a valid IP or range or a valid username
		// and if there are rows to display they will be displayed.
		// Check if the target is an IP or range and only if so, log that the user has globally
		// viewed the temporary accounts editing on the target IP/range.
		if ( $this->isValidIPOrQueryableRange( $this->target, $this->getConfig() ) ) {
			$this->jobQueueGroup->push(
				LogTemporaryAccountAccessJob::newSpec(
					$this->getAuthority()->getUser(),
					$this->target,
					TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP_GLOBAL,
				)
			);
		}
	}

	/** @inheritDoc */
	public function getTimestampField() {
		return 'rev_timestamp';
	}

	/**
	 * @inheritDoc
	 */
	public function reallyDoQuery( $offset, $limit, $order ) {
		$wikisToQuery = $this->fetchWikisToQuery();
		$results = [];

		if ( $offset ) {
			$offsets = explode( '|', $offset );
			$indexColumns = array_slice( (array)$this->mIndexField, 0, count( $offsets ) );
			$offsetConds = array_combine( $indexColumns, $offsets );
		} else {
			$offsetConds = [];
		}

		// Apply start and end timestamp limits. Code taken from
		// RangeChronologicalPager::buildQueryInfo and ReverseChronologicalPager::buildQueryInfo
		$timestampConds = [];
		if ( $this->endOffset ) {
			$timestampConds[] = $this->mDb->expr( $this->getTimestampField(), '<', $this->endOffset );
		}

		// Don't display revisions older than the CUDMaxAge to be consistent with what is displayed when
		// searching for temporary account contributions on an IP address (T388667).
		$checkUserDataCutoff = ConvertibleTimestamp::time() - $this->getConfig()->get( 'CUDMaxAge' );
		if ( $this->startOffset ) {
			$startOffset = max(
				ConvertibleTimestamp::convert( TS_MW, $this->startOffset ),
				ConvertibleTimestamp::convert( TS_MW, $checkUserDataCutoff )
			);
		} else {
			$startOffset = $checkUserDataCutoff;
		}
		$timestampConds[] = $this->mDb->expr(
			$this->getTimestampField(), '>=', $this->mDb->timestamp( $startOffset )
		);

		// Compute a synthetic sequence number for each wiki 0 ... -N,
		// where 0 is the most recently edited wiki and -N is the least recently edited wiki.
		// This is useful for tie-breaking where two revisions between different wikis
		// share the same timestamp.
		$wikiSeqNo = 0;

		foreach ( $wikisToQuery as $wikiId ) {
			$dbr = $this->dbProvider->getReplicaDatabase( $wikiId );
			$wikiConds = [];
			if ( !WikiMap::isCurrentWikiDbDomain( $wikiId ) ) {
				// Filter out results with hidden authors from external wikis, if the user shouldn't see
				// them. These filters are added for the local query in ContributionsPager::getQueryInfo.
				// Since external permissions are not looked up if the target is a registered user,
				// these will not be shown to anyone for a registered user target, until T389187.
				if ( !$this->userHasExternalPermission( 'deletedhistory', $wikiId ) ) {
					$wikiConds[] = $dbr->bitAnd(
						$this->revisionDeletedField, RevisionRecord::DELETED_USER
					) . ' = 0';
				}
				if (
					!$this->userHasExternalPermission( 'suppressrevision', $wikiId ) &&
					!$this->userHasExternalPermission( 'viewsuppressed', $wikiId )
				) {
					$wikiConds[] = $dbr->bitAnd(
						$this->revisionDeletedField, RevisionRecord::SUPPRESSED_USER
					) . ' != ' . RevisionRecord::SUPPRESSED_USER;
				}
			}

			$resultSet = $dbr->newSelectQueryBuilder()
				->caller( __METHOD__ )
				->queryInfo( $this->getQueryInfo() )
				->andWhere( $wikiConds )
				->andWhere( $timestampConds )
				->orderBy( [ 'rev_timestamp', 'rev_id' ], SelectQueryBuilder::SORT_DESC )
				// Use a limit for each wiki (specified in T356292), rather than the page limit.
				->limit( self::REVISION_COUNT_LIMIT )
				->fetchResultSet();

			foreach ( $resultSet as $row ) {
				$row->sourcewiki = $wikiId;
				$row->wiki_seq_no = $wikiSeqNo;

				if ( !$offsetConds ) {
					$results[] = $row;
					continue;
				}

				// Check whether this revision fits into the current pagination window.
				foreach ( $offsetConds as $field => $pivotValue ) {
					if ( $this->mIncludeOffset && $row->$field === $pivotValue ) {
						$results[] = $row;
						break;
					}

					if ( $order === self::QUERY_ASCENDING ) {
						if ( $row->$field < $pivotValue ) {
							break;
						}

						if ( $row->$field > $pivotValue ) {
							$results[] = $row;
							break;
						}
					}

					if ( $order === self::QUERY_DESCENDING ) {
						if ( $row->$field > $pivotValue ) {
							break;
						}

						if ( $row->$field < $pivotValue ) {
							$results[] = $row;
							break;
						}
					}
				}
			}

			$wikiSeqNo--;
		}

		// Sort the entire results set by timestamp, wiki sequence number
		// and finally revision ID as a tie-breaker, then apply the limit.
		usort( $results, static function ( $a, $b ) use ( $order ) {
			$aTimestamp = $a->rev_timestamp;
			$bTimestamp = $b->rev_timestamp;

			if ( $aTimestamp !== $bTimestamp ) {
				if ( $order === self::QUERY_DESCENDING ) {
					return $bTimestamp <=> $aTimestamp;
				}

				return $aTimestamp <=> $bTimestamp;
			}

			if ( $a->wiki_seq_no !== $b->wiki_seq_no ) {
				if ( $order === self::QUERY_DESCENDING ) {
					return $b->wiki_seq_no <=> $a->wiki_seq_no;
				}

				return $a->wiki_seq_no <=> $b->wiki_seq_no;
			}

			if ( $order === self::QUERY_DESCENDING ) {
				return $b->rev_id <=> $a->rev_id;
			}

			return $a->rev_id <=> $b->rev_id;
		} );

		return new FakeResultWrapper( array_slice( $results, 0, $limit ) );
	}

	/**
	 * @inheritDoc
	 */
	protected function getRevisionQuery() {
		$revQueryBuilder = $this->revisionStore->newSelectQueryBuilder( $this->getDatabase() )
			->joinPage()
			->joinComment()
			->fields( [
				'page_is_new',
				// Set our synthetic wiki sequence number field to an initial value.
				// This will be overridden in reallyDoQuery().
				'wiki_seq_no' => '1'
			] );

		if ( $this->isValidIPOrQueryableRange( $this->target, $this->getConfig() ) ) {
			$ipConditions = $this->checkUserLookupUtils->getIPTargetExpr(
				$this->target,
				false,
				self::CHANGES_TABLE
			);
			if ( $ipConditions === null ) {
				// Invalid IPs are treated as usernames so we should only ever reach
				// this condition if the IP range is out of limits
				throw new LogicException(
					"Attempted IP range lookup with a range outside of the limit: $this->target\n
					Check if your RangeContributionsCIDRLimit and CheckUserCIDRLimit configs are compatible."
				);
			}
			$tempUserConditions = $this->tempUserConfig->getMatchCondition(
				$this->getDatabase(),
				'actor_name',
				IExpression::LIKE
			);

			$revQueryBuilder->join( self::CHANGES_TABLE, null, 'cuc_this_oldid=rev_id' )
				->where( $ipConditions )
				->andWhere( $tempUserConditions );
		} else {
			$revQueryBuilder->where( [ 'actor_name' => $this->target ] );
		}

		return $revQueryBuilder->getQueryInfo();
	}

	/**
	 * @inheritDoc
	 */
	public function getIndexField() {
		return [
			[ 'rev_timestamp', 'wiki_seq_no', 'rev_id' ]
		];
	}

	/**
	 * Wrapper method for testing. See documentation for WikiMap::getForeignURL.
	 *
	 * @param string $wikiID
	 * @param string $page
	 * @param string|null $fragmentId
	 *
	 * @return string|false
	 */
	protected function getForeignURL( $wikiID, $page, $fragmentId = null ) {
		return WikiMap::getForeignURL( $wikiID, $page, $fragmentId );
	}

	/**
	 * @inheritDoc
	 */
	protected function populateAttributes( $row, &$attributes ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			parent::populateAttributes( $row, $attributes );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getStartBody() {
		$startBody = "<section class=\"mw-pager-body plainlinks\">\n";

		if ( $this->needsToEnableGlobalPreferenceAtWiki && $this->getNumRows() > 0 ) {
			$startBody .= new MessageWidget( [
				'type' => 'info',
				'label' => new HtmlSnippet(
					$this->msg(
						'checkuser-global-contributions-no-global-preference',
						$this->getForeignURL(
							$this->needsToEnableGlobalPreferenceAtWiki,
							'Special:GlobalPreferences'
						)
					)->parse()
				)
			] );
		}

		if ( $this->externalApiLookupError ) {
			$startBody .= new MessageWidget( [
				'type' => 'error',
				'label' => new HtmlSnippet(
					$this->msg( 'checkuser-global-contributions-api-lookup-error' )->parse()
				)
			] );
		}

		return $startBody;
	}

	/**
	 * @inheritDoc
	 */
	protected function getEndBody() {
		return "</section>\n";
	}

	/**
	 * @inheritDoc
	 */
	protected function getEmptyBody() {
		if ( $this->needsToEnableGlobalPreferenceAtWiki ) {
			return new MessageWidget( [
				'type' => 'info',
				'label' => new HtmlSnippet(
					$this->msg(
						'checkuser-global-contributions-no-results-no-global-preference',
						$this->getForeignURL(
							$this->needsToEnableGlobalPreferenceAtWiki,
							'Special:GlobalPreferences'
						)
					)->parse()
				)
			] );
		}

		// Username or temporary account queried for and no central id for the user was found
		if ( !$this->centralUserExists ) {
			return new MessageWidget( [
				'type' => 'warning',
				'label' => new HtmlSnippet(
					$this->msg(
						'checkuser-global-contributions-no-results-no-central-user',
						$this->target
					)->parse()
				)
			] );
		}

		// An IP target was searched for and the user doesn't have view permissions
		if (
			$this->wikisWithPermissionsCount === 0 &&
			$this->isValidIPOrQueryableRange( $this->target, $this->getConfig() )
		) {
			return new MessageWidget( [
				'type' => 'info',
				'label' => new HtmlSnippet(
					$this->msg( 'checkuser-global-contributions-no-results-no-permissions' )
						->params( $this->target )
						->parse()
				)
			] );
		}

		// No results, but filters have been applied which may cause some results to be hidden for the target
		// so show a message that suggests removing these filters.
		if ( $this->hasAppliedFilters() ) {
			return new MessageWidget( [
				'type' => 'info',
				'label' => new HtmlSnippet( $this->msg(
					'checkuser-global-contributions-no-results-when-filters-applied'
				)->parse() ),
			] );
		}

		// No visible contributions for an existing user
		return new MessageWidget( [
			'type' => 'info',
			'label' => new HtmlSnippet(
				$this->msg(
					'checkuser-global-contributions-no-results-no-visible-contribs',
					$this->target
				)->parse()
			)
		] );
	}

	/**
	 * @inheritDoc
	 */
	protected function formatArticleLink( $row ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatArticleLink( $row );
		}

		if ( !$this->currentPage ) {
			return '';
		}

		// If the namespace ID is not one of the common namespaces that are the same
		// across all wikis then use that. In the other case then we should make the
		// namespace be "Namespace<id>" where "id" is the number of the namespace
		// to prevent these pages being seen as in the mainspace (which has no namespace prefix).
		$namespaceIsKnown = in_array(
			$row->{$this->pageNamespaceField},
			$this->namespaceInfo->getCommonNamespaces()
		);

		if ( $namespaceIsKnown ) {
			$linkText = $this->currentPage->getPrefixedText();
		} else {
			$linkText = $this->msg( 'checkuser-global-contributions-page-when-no-namespace-translation-available' )
				->numParams( $row->page_namespace )
				->params( $this->currentPage->getText() )
				->text();
		}

		$dir = $this->getLanguage()->getDir();
		$link = $this->getLinkRenderer()->makeExternalLink(
			$this->getForeignURL(
				$row->sourcewiki,
				'Special:Redirect/page/' . $row->rev_page
			),
			$linkText,
			$this->currentPage,
			'',
			[ 'class' => 'mw-contributions-title' ],
		);
		return Html::rawElement( 'bdi', [ 'dir' => $dir ], $link );
	}

	/**
	 * @inheritDoc
	 */
	protected function formatDiffHistLinks( $row ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatDiffHistLinks( $row );
		}

		if ( !$this->currentPage ) {
			return '';
		}

		if (
			$row->{$this->revisionParentIdField} != 0 &&
			$this->userCanSeeExternalRevision( $row )
		) {
			$difftext = $this->getLinkRenderer()->makeExternalLink(
				wfAppendQuery(
					$this->getForeignURL(
						$row->sourcewiki,
						$row->{$this->pageTitleField}
					),
					[
						'diff' => 'prev',
						'oldid' => $row->{$this->revisionIdField},
					]
				),
				new HtmlArmor( $this->messages['diff'] ),
				// The page is only used for its title and namespace,
				// so this is safe.
				$this->currentPage,
				'',
				[ 'class' => 'mw-changeslist-diff' ]
			);
		} else {
			$difftext = $this->messages['diff'];
		}

		$histlink = $this->getLinkRenderer()->makeExternalLink(
			wfAppendQuery(
				$this->getForeignURL(
					$row->sourcewiki,
					'',
				),
				[
					'curid' => $row->rev_page,
					'action' => 'history'
				]
			),
			new HtmlArmor( $this->messages['hist'] ),
			// The page is only used for its title and namespace,
			// so this is safe.
			$this->currentPage,
			'',
			[ 'class' => 'mw-changeslist-history' ]
		);

		return Html::rawElement( 'span',
			[ 'class' => 'mw-changeslist-links' ],
			// The spans are needed to ensure the dividing '|' elements are not
			// themselves styled as links.
			Html::rawElement( 'span', [], $difftext ) . ' ' .
			// Space needed for separating two words.
			Html::rawElement( 'span', [], $histlink )
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function formatDateLink( $row ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatDateLink( $row );
		}

		if ( !$this->currentPage ) {
			return '';
		}

		// Re-implemented from ChangesList::revDateLink so we can inject
		// a foreign URL here instead of a local one.
		$ts = $row->{$this->revisionTimestampField};
		$time = $this->getLanguage()->userTime( $ts, $this->getAuthority()->getUser() );
		$date = $this->getLanguage()->userTimeAndDate( $ts, $this->getAuthority()->getUser() );
		$class = 'mw-changeslist-date';
		if ( $this->userCanSeeExternalRevision( $row ) ) {
			$dateLink = $this->getLinkRenderer()->makeExternalLink(
				wfAppendQuery(
					$this->getForeignURL(
						$row->sourcewiki,
						$row->{$this->pageTitleField}
					),
					[ 'oldid' => $row->{$this->revisionIdField} ]
				),
				$date,
				$this->currentPage,
				'',
				[ 'class' => $class ]
			);
			$dateLink = Html::rawElement( 'bdi', [ 'dir' => $this->getLanguage()->getDir() ], $dateLink );
		} else {
			$dateLink = htmlspecialchars( $date );
		}

		if ( $this->externalRevisionIsDeleted( $row ) ) {
			// Logic from Linker::getRevisionDeletedClass
			$class .= ' history-deleted';
			if ( (bool)( $row->{$this->revisionDeletedField} & RevisionRecord::DELETED_RESTRICTED ) ) {
				$class .= ' mw-history-suppressed';
			}
			$dateLink = Html::rawElement(
				'span',
				[ 'class' => $class ],
				$dateLink
			);
		}

		return Html::element( 'span', [
			'class' => 'mw-changeslist-time'
		], $time ) . $dateLink;
	}

	/**
	 * @inheritDoc
	 */
	protected function formatTopMarkText( $row, &$classes ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatTopMarkText( $row, $classes );
		}

		// PagerTools are omitted here because they require cross-wiki
		// permission checks.
		$topmarktext = '';
		if ( $row->{$this->revisionIdField} === $row->page_latest ) {
			$topmarktext .= '<span class="mw-uctop">' . $this->messages['uctop'] . '</span>';
			$classes[] = 'mw-contributions-current';
		}
		return $topmarktext;
	}

	/**
	 * @inheritDoc
	 */
	protected function formatComment( $row ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatComment( $row );
		}

		// Show a generic message instead of the actual comment for external
		// revisions, since determining their visibility involves cross-wiki
		// permission checks. Showing the message is needed to prevent breaking
		// skins that expect to have the comment there (T388392).
		return Html::element(
			'span',
			[ 'class' => 'comment mw-comment-none' ],
			$this->msg( 'checkuser-global-contributions-no-summary-available' )
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function formatUserLink( $row ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatUserLink( $row );
		}

		$dir = $this->getLanguage()->getDir();

		if ( $this->revisionUserIsDeleted( $row ) ) {
			$userPageLink = $this->msg( 'empty-username' )->parse();
			$userTalkLink = $this->msg( 'empty-username' )->parse();
		} else {
			$userTitle = Title::makeTitle( NS_USER, $row->{$this->userNameField} );
			$userTalkTitle = Title::makeTitle( NS_USER_TALK, $row->{$this->userNameField} );

			$classes = 'mw-userlink mw-extuserlink';

			if ( $this->tempUserConfig->isTempName( $row->{$this->userNameField} ) ) {
				// If the contribution is from a temporary user, add the appropriate class
				// and link to their Special:Contributions page
				$classes .= ' mw-tempuserlink';
				$userPageLink = $this->getLinkRenderer()->makeExternalLink(
					$this->getForeignURL(
						$row->sourcewiki,
						'Special:Contributions/' . $row->{$this->userNameField}
					),
					$row->{$this->userNameField},
					$userTitle,
					'',
					[ 'class' => $classes ]
				);
			} else {
				// Otherwise, the contribution is from a registered account
				// and should link to their user page
				$userPageLink = $this->getLinkRenderer()->makeExternalLink(
					$this->getForeignURL(
						$row->sourcewiki,
						'User:' . $row->{$this->userNameField}
					),
					$row->{$this->userNameField},
					$userTitle,
					'',
					[ 'class' => $classes ]
				);
			}

			$userTalkLink = $this->getLinkRenderer()->makeExternalLink(
				$this->getForeignURL(
					$row->sourcewiki,
					$userTalkTitle->getPrefixedText()
				),
				$this->msg( 'talkpagelinktext' ),
				$userTalkTitle,
				'',
				[ 'class' => 'mw-usertoollinks-talk' ]
			);
		}

		return ' <span class="mw-changeslist-separator"></span> ' .
			Html::rawElement( 'bdi', [ 'dir' => $dir ], $userPageLink ) .
			' <span class="mw-usertoollinks mw-changeslist-links">' .
			"<span class=\"mw-usertoollinks-talk\">{$userTalkLink}</span>" .
			'</span>';
	}

	/**
	 * @inheritDoc
	 */
	protected function formatFlags( $row ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatFlags( $row );
		}

		// This is similar to ContributionsPager::formatFlags, but uses the
		// row since the RevisionRecord is not available for external rows.
		$flags = [];
		if ( $row->{$this->revisionParentIdField} == 0 ) {
			$flags[] = ChangesList::flag( 'newpage' );
		}

		if ( $row->{$this->revisionMinorField} ) {
			$flags[] = ChangesList::flag( 'minor' );
		}
		return $flags;
	}

	/**
	 * @inheritDoc
	 */
	protected function formatVisibilityLink( $row ) {
		// Don't show visibility links if the row is for an external wiki, since
		// determining their usability involves cross-wiki permission checks.
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatVisibilityLink( $row );
		}

		return '';
	}

	/**
	 * @inheritDoc
	 */
	protected function formatTags( $row, &$classes ) {
		// Note that for an external tag that is not translated on this wiki,
		// the raw tag name will be displayed. This is because external tags
		// are not supported by ChangeTags::formatSummaryRow.
		return parent::formatTags( $row, $classes );
	}

	/**
	 * Format a link to the source wiki.
	 *
	 * @param mixed $row
	 * @return string
	 */
	protected function formatSourceWiki( $row ) {
		$link = $this->getLinkRenderer()->makeExternalLink(
			$this->getForeignURL(
				$row->sourcewiki,
				''
			),
			WikiMap::getWikiName( $row->sourcewiki ),
			Title::newMainPage(),
			'',
			[ 'class' => 'mw-changeslist-sourcewiki' ],
		);
		return $link . ' <span class="mw-changeslist-separator"></span> ';
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateParams( $row, &$classes ) {
		$templateParams = parent::getTemplateParams( $row, $classes );
		$sourceWiki = $this->formatSourceWiki( $row );
		$templateParams['sourceWiki'] = $sourceWiki;
		return $templateParams;
	}

	/**
	 * @inheritDoc
	 */
	public function getProcessedTemplate( $templateParams ) {
		return $this->templateParser->processTemplate( 'SpecialGlobalContributionsLine', $templateParams );
	}

	/**
	 * Check whether the revision author is deleted. This re-implements
	 * RevisionRecord::isDeleted, since the RevisionRecord is not
	 * available for external rows.
	 *
	 * @param mixed $row
	 * @return bool
	 */
	public function revisionUserIsDeleted( $row ) {
		return ( $row->{$this->revisionDeletedField} & RevisionRecord::DELETED_USER ) ==
			RevisionRecord::DELETED_USER;
	}

	/**
	 * @inheritDoc
	 */
	public function tryCreatingRevisionRecord( $row, $title = null ) {
		if ( $this->isFromExternalWiki( $row ) ) {
			// RevisionRecord doesn't fully support external revision rows.
			return null;
		}
		return parent::tryCreatingRevisionRecord( $row, $title );
	}

	/**
	 * Bool representing whether or not the revision comes from an external wiki
	 *
	 * @param mixed $row
	 * @return bool
	 */
	protected function isFromExternalWiki( $row ) {
		return isset( $row->sourcewiki ) &&
			!WikiMap::isCurrentWikiDbDomain( $row->sourcewiki );
	}

	/**
	 * Check for errors, which includes permission errors if they don't have the right
	 * and also block errors if they are blocked.
	 *
	 * Must be called after ::getExternalWikiPermissions.
	 *
	 * @param string $right
	 * @param string $wikiId
	 * @return bool The user has the permission on the wiki
	 */
	private function userHasExternalPermission( $right, $wikiId ) {
		return isset( $this->permissions[$wikiId] ) &&
			isset( $this->permissions[$wikiId][$right] ) &&
			count( $this->permissions[$wikiId][$right] ) === 0;
	}

	/**
	 * @param mixed $row
	 * @return bool
	 */
	private function externalRevisionIsDeleted( $row ) {
		return (bool)( $row->{$this->revisionDeletedField} & RevisionRecord::DELETED_TEXT );
	}

	/**
	 * @param mixed $row
	 * @return bool
	 */
	private function userCanSeeExternalRevision( $row ) {
		if ( !$this->externalRevisionIsDeleted( $row ) ) {
			return true;
		}
		return $this->userHasExternalPermission( 'deletedtext', $row->sourcewiki );
	}

	/**
	 * Whether any errors occurred while looking up external wiki permissions during the last query.
	 * @return bool
	 */
	public function hasExternalApiLookupError(): bool {
		return $this->externalApiLookupError;
	}
}
