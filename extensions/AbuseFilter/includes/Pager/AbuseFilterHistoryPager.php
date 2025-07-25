<?php

namespace MediaWiki\Extension\AbuseFilter\Pager;

use HtmlArmor;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\AbuseFilter\AbuseFilter;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseFilter;
use MediaWiki\Extension\AbuseFilter\SpecsFormatter;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Pager\TablePager;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityValue;
use UnexpectedValueException;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IResultWrapper;

class AbuseFilterHistoryPager extends TablePager {

	private LinkBatchFactory $linkBatchFactory;
	private FilterLookup $filterLookup;
	private SpecsFormatter $specsFormatter;
	private AbuseFilterPermissionManager $afPermManager;

	/** @var int|null The filter ID */
	private $filter;

	/** @var string|null The user whose changes we're looking up for */
	private $user;

	/** @var bool */
	private $canViewPrivateFilters;

	/**
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param FilterLookup $filterLookup
	 * @param SpecsFormatter $specsFormatter
	 * @param AbuseFilterPermissionManager $afPermManager
	 * @param ?int $filter
	 * @param ?string $user User name
	 * @param bool $canViewPrivateFilters
	 */
	public function __construct(
		IContextSource $context,
		LinkRenderer $linkRenderer,
		LinkBatchFactory $linkBatchFactory,
		FilterLookup $filterLookup,
		SpecsFormatter $specsFormatter,
		AbuseFilterPermissionManager $afPermManager,
		?int $filter,
		?string $user,
		bool $canViewPrivateFilters = false
	) {
		// needed by parent's constructor call
		$this->filter = $filter;
		parent::__construct( $context, $linkRenderer );
		$this->linkBatchFactory = $linkBatchFactory;
		$this->filterLookup = $filterLookup;
		$this->specsFormatter = $specsFormatter;
		$this->afPermManager = $afPermManager;
		$this->user = $user;
		$this->canViewPrivateFilters = $canViewPrivateFilters;
		$this->mDefaultDirection = true;
	}

	/**
	 * Note: this method is called by parent::__construct
	 * @return array
	 * @see MediaWiki\Pager\Pager::getFieldNames()
	 */
	public function getFieldNames() {
		static $headers = null;

		if ( $headers !== null ) {
			return $headers;
		}

		$headers = [
			'afh_timestamp' => 'abusefilter-history-timestamp',
			'afh_user_text' => 'abusefilter-history-user',
			'afh_public_comments' => 'abusefilter-history-public',
			'afh_flags' => 'abusefilter-history-flags',
			'afh_actions' => 'abusefilter-history-actions',
			'afh_id' => 'abusefilter-history-diff',
		];

		if ( !$this->filter ) {
			// awful hack
			$headers = [ 'afh_filter' => 'abusefilter-history-filterid' ] + $headers;
		}

		foreach ( $headers as &$msg ) {
			$msg = $this->msg( $msg )->text();
		}

		return $headers;
	}

	/**
	 * @param string $name
	 * @param string|null $value
	 * @return string
	 */
	public function formatValue( $name, $value ) {
		$lang = $this->getLanguage();
		$linkRenderer = $this->getLinkRenderer();

		$row = $this->mCurrentRow;

		switch ( $name ) {
			case 'afh_filter':
				$formatted = $linkRenderer->makeLink(
					SpecialAbuseFilter::getTitleForSubpage( $row->afh_filter ),
					$lang->formatNum( $row->afh_filter )
				);
				break;
			case 'afh_timestamp':
				$title = SpecialAbuseFilter::getTitleForSubpage(
					'history/' . $row->afh_filter . '/item/' . $row->afh_id );
				$formatted = $linkRenderer->makeLink(
					$title,
					$lang->userTimeAndDate( $row->afh_timestamp, $this->getUser() )
				);
				break;
			case 'afh_user_text':
				$formatted =
					Linker::userLink( $row->afh_user ?? 0, $row->afh_user_text ) . ' ' .
					Linker::userToolLinks( $row->afh_user ?? 0, $row->afh_user_text );
				break;
			case 'afh_public_comments':
				$formatted = htmlspecialchars( $value, ENT_QUOTES, 'UTF-8', false );
				break;
			case 'afh_flags':
				$formatted = $this->specsFormatter->formatFlags( $value, $lang );
				break;
			case 'afh_actions':
				$actions = unserialize( $value );

				$display_actions = '';

				foreach ( $actions as $action => $parameters ) {
					$displayAction = $this->specsFormatter->formatAction( $action, $parameters, $lang );
					$display_actions .= Html::rawElement( 'li', [], $displayAction );
				}
				$display_actions = Html::rawElement( 'ul', [], $display_actions );

				$formatted = $display_actions;
				break;
			case 'afh_id':
				// Set a link to a diff with the previous version if this isn't the first edit to the filter.
				// Like in AbuseFilterViewDiff, don't show it if:
				// - the user cannot see private filters and any of the versions is hidden
				// - the user cannot see protected variables and any of the versions is protected
				$formatted = '';
				if ( $this->filterLookup->getFirstFilterVersionID( $row->afh_filter ) !== (int)$value ) {
					// @todo Should we also hide actions?
					$prevFilter = $this->filterLookup->getClosestVersion(
						$row->afh_id, $row->afh_filter, FilterLookup::DIR_PREV );
					$filter = $this->filterLookup->filterFromHistoryRow( $row );
					$userCanSeeFilterDiff = true;

					if ( $filter->isProtected() ) {
						$userCanSeeFilterDiff = $this->afPermManager
							->canViewProtectedVariablesInFilter( $this->getAuthority(), $filter )
							->isGood();
					}

					if ( $prevFilter->isProtected() && $userCanSeeFilterDiff ) {
						$userCanSeeFilterDiff = $this->afPermManager
							->canViewProtectedVariablesInFilter( $this->getAuthority(), $prevFilter )
							->isGood();
					}

					if ( !$this->canViewPrivateFilters && $userCanSeeFilterDiff ) {
						$userCanSeeFilterDiff = !$filter->isHidden() && !$prevFilter->isHidden();
					}

					if ( $userCanSeeFilterDiff ) {
						$title = SpecialAbuseFilter::getTitleForSubpage(
							'history/' . $row->afh_filter . "/diff/prev/$value" );
						$formatted = $linkRenderer->makeLink(
							$title,
							new HtmlArmor( $this->msg( 'abusefilter-history-diff' )->parse() )
						);
					}
				}
				break;
			default:
				throw new UnexpectedValueException( "Unknown row type $name!" );
		}

		return $formatted;
	}

	/**
	 * @return array
	 */
	public function getQueryInfo() {
		$queryBuilder = $this->filterLookup->getAbuseFilterHistoryQueryBuilder( $this->getDatabase() )
			->fields( [ 'af_hidden', 'afh_changed_fields' ] )
			->leftJoin( 'abuse_filter', null, 'afh_filter=af_id' );

		if ( $this->user !== null ) {
			$queryBuilder->andWhere( [ 'actor_name' => $this->user ] );
		}

		if ( $this->filter ) {
			$queryBuilder->andWhere( [ 'afh_filter' => $this->filter ] );
		}

		if ( !$this->canViewPrivateFilters ) {
			// Hide data the user can't see.
			$queryBuilder->andWhere( $this->mDb->bitAnd( 'af_hidden', Flags::FILTER_HIDDEN ) . ' = 0' );
		}

		// We cannot know the variables used in the filters when we are running the SQL query, so
		// assume no variables are used and the filter is just protected. We will filter out
		// any filters which the user cannot see due to a specific variable later.
		if ( !$this->afPermManager->canViewProtectedVariables( $this->getAuthority(), [] )->isGood() ) {
			// Hide data the user can't see.
			$queryBuilder->andWhere( $this->mDb->bitAnd( 'af_hidden', Flags::FILTER_USES_PROTECTED_VARS ) . ' = 0' );
		}

		return $queryBuilder->getQueryInfo();
	}

	/**
	 * Excludes rows which are for protected filters where the filter currently uses protected variables
	 * the user cannot see, to be consistent with how we exclude access to see the history of filters
	 * the user cannot currently see.
	 *
	 * This method repeats the query to get $limit rows that the user can see, so that we do not expose
	 * how many versions have been hidden.
	 *
	 * @inheritDoc
	 */
	public function reallyDoQuery( $offset, $limit, $order ) {
		$foundRows = [];
		$currentOffset = $offset;

		do {
			$result = parent::reallyDoQuery( $currentOffset, $limit, $order );

			// Loop over each row in the result, and check that the user can see the the current version of
			// the filter which this is associated with.
			foreach ( $result as $row ) {
				$historyFilter = $this->filterLookup->filterFromHistoryRow( $row );
				$currentFilterVersion = $this->filterLookup->getFilter(
					$historyFilter->getID(), $historyFilter->isGlobal()
				);
				if (
					!$currentFilterVersion->isProtected() ||
					$this->afPermManager
						->canViewProtectedVariablesInFilter( $this->getAuthority(), $currentFilterVersion )
						->isGood()
				) {
					$foundRows[] = $row;
				}
			}

			// If we excluded rows in the above foreach, we will need to perform another query to get more rows so
			// that the page contains a full list of results and does not expose the number of versions that
			// the user cannot see.
			// To do this we need to get a new offset value, which will be used to get rows we have not checked yet
			// and is the timestamp of the last row we fetched.
			$numRows = $result->numRows();

			if ( $numRows ) {
				$result->seek( $numRows - 1 );
				$row = $result->fetchRow();
				$currentOffset = $row['afh_timestamp'];
			}
		} while ( count( $foundRows ) <= $limit && $numRows );

		$foundRows = array_slice( $foundRows, 0, $limit );
		return new FakeResultWrapper( $foundRows );
	}

	/**
	 * @param IResultWrapper $result
	 */
	protected function preprocessResults( $result ) {
		if ( $this->getNumRows() === 0 ) {
			return;
		}

		$lb = $this->linkBatchFactory->newLinkBatch();
		$lb->setCaller( __METHOD__ );
		foreach ( $result as $row ) {
			$lb->addUser( new UserIdentityValue( $row->afh_user ?? 0, $row->afh_user_text ) );
		}
		$lb->execute();
		$result->seek( 0 );
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	public function getDefaultSort() {
		return 'afh_timestamp';
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	public function isFieldSortable( $field ) {
		return $field === 'afh_timestamp';
	}

	/**
	 * @param string $field
	 * @param string $value
	 * @return array
	 * @see TablePager::getCellAttrs
	 */
	public function getCellAttrs( $field, $value ) {
		$row = $this->mCurrentRow;
		$mappings = array_flip( AbuseFilter::HISTORY_MAPPINGS ) +
			[ 'afh_actions' => 'actions', 'afh_id' => 'id' ];
		$changed = explode( ',', $row->afh_changed_fields );

		$fieldChanged = false;
		if ( $field === 'afh_flags' ) {
			// The field is changed if any of these filters are in the $changed array.
			$filters = [ 'af_enabled', 'af_hidden', 'af_deleted', 'af_global' ];
			if ( count( array_intersect( $filters, $changed ) ) ) {
				$fieldChanged = true;
			}
		} elseif ( in_array( $mappings[$field], $changed ) ) {
			$fieldChanged = true;
		}

		$class = $fieldChanged ? ' mw-abusefilter-history-changed' : '';
		$attrs = parent::getCellAttrs( $field, $value );
		$attrs['class'] .= $class;
		return $attrs;
	}

	/** @inheritDoc */
	protected function getRowClass( $row ) {
		return 'mw-abusefilter-history-id-' . $row->afh_id;
	}

	/**
	 * Title used for self-links.
	 *
	 * @return Title
	 */
	public function getTitle() {
		$subpage = $this->filter ? ( 'history/' . $this->filter ) : 'history';
		return SpecialAbuseFilter::getTitleForSubpage( $subpage );
	}
}
