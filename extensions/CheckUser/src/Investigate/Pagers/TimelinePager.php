<?php

namespace MediaWiki\Extension\CheckUser\Investigate\Pagers;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\Hook\CheckUserFormatRowHook;
use MediaWiki\Extension\CheckUser\Investigate\Services\TimelineService;
use MediaWiki\Extension\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\Extension\CheckUser\Services\TokenQueryManager;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Pager\ReverseChronologicalPager;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\User\UserIdentityValue;
use Psr\Log\LoggerInterface;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\FakeResultWrapper;

class TimelinePager extends ReverseChronologicalPager {
	/** @var string */
	private $start;

	/** @var string|null */
	private $lastDateHeader;

	/**
	 * Targets whose results should not be included in the investigation.
	 * Targets in this list may or may not also be in the $targets list.
	 * Either way, no activity related to these targets will appear in the
	 * results.
	 *
	 * @var string[]
	 */
	private $excludeTargets;

	/**
	 * If set, makes the pager to not show temporary accounts in the results.
	 */
	private bool $excludeTempAccounts;

	/**
	 * Targets that have been added to the investigation but that are not
	 * present in $excludeTargets. These are the targets that will actually
	 * be investigated.
	 *
	 * @var string[]
	 */
	private $filteredTargets;

	public function __construct(
		IContextSource $context,
		LinkRenderer $linkRenderer,
		private readonly CheckUserFormatRowHook $formatRowHookRunner,
		private readonly TokenQueryManager $tokenQueryManager,
		DurationManager $durationManager,
		private TimelineService $timelineService,
		private TimelineRowFormatter $timelineRowFormatter,
		private readonly LinkBatchFactory $linkBatchFactory,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct( $context, $linkRenderer );

		$tokenData = $tokenQueryManager->getDataFromRequest( $context->getRequest() );
		$this->mOffset = $tokenData['offset'] ?? '';
		$this->excludeTempAccounts = $tokenData['FilterTempAccounts'] ?? false;
		$this->excludeTargets = $tokenData['exclude-targets'] ?? [];
		$this->filteredTargets = array_diff(
			$tokenData['targets'] ?? [],
			$this->excludeTargets
		);
		$this->start = $durationManager->getTimestampFromRequest( $context->getRequest() );
	}

	/**
	 * @inheritDoc
	 *
	 * Handle special case where all targets are filtered.
	 */
	public function reallyDoQuery( $offset, $limit, $order ) {
		// If there are no targets, there is no need to run the query and an empty result can be used.
		if ( $this->filteredTargets === [] ) {
			return new FakeResultWrapper( [] );
		}
		return parent::reallyDoQuery( $offset, $limit, $order );
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		return $this->timelineService->getQueryInfo(
			$this->filteredTargets,
			$this->excludeTargets,
			$this->excludeTempAccounts,
			$this->start,
			$this->mLimit
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function doBatchLookups() {
		$lb = $this->linkBatchFactory->newLinkBatch();
		$lb->setCaller( __METHOD__ );

		foreach ( $this->mResult as $row ) {
			$username = $row->user_text;
			if ( $username === null && $row->ip_hex !== null ) {
				$username = IPUtils::formatHex( $row->ip_hex );
			}
			$lb->addUser( new UserIdentityValue( $row->user ?? 0, $username ?? '' ) );
		}

		$lb->execute();
	}

	/**
	 * @inheritDoc
	 */
	public function getIndexField() {
		return [ [ 'timestamp', 'id' ] ];
	}

	/**
	 * @inheritDoc
	 */
	public function formatRow( $row ) {
		$line = '';
		$dateHeader = $this->getLanguage()->userDate( wfTimestamp( TS_MW, $row->timestamp ), $this->getUser() );
		if ( $this->lastDateHeader === null ) {
			$this->lastDateHeader = $dateHeader;
			$line .= Html::element( 'h4', [], $dateHeader );
			$line .= Html::openElement( 'ul' );
		} elseif ( $this->lastDateHeader !== $dateHeader ) {
			$this->lastDateHeader = $dateHeader;

			// Start a new list with a new date header
			$line .= Html::closeElement( 'ul' );
			$line .= Html::element( 'h4', [], $dateHeader );
			$line .= Html::openElement( 'ul' );
		}

		$rowItems = $this->timelineRowFormatter->getFormattedRowItems( $row );

		$this->formatRowHookRunner->onCheckUserFormatRow( $this->getContext(), $row, $rowItems );

		if ( !is_array( $rowItems ) || !isset( $rowItems['links'] ) || !isset( $rowItems['info'] ) ) {
			$this->logger->warning(
				__METHOD__ . ': Expected array with keys \'links\' and \'info\''
					. ' from CheckUserFormatRow $rowItems param'
			);
			return '';
		}

		$formattedLinks = implode( ' ', array_filter( $rowItems['links'], 'strlen' ) );

		$formatted = implode(
			' . . ',
			array_filter( [ $formattedLinks, ...$rowItems['info'] ], 'strlen' )
		);

		$line .= Html::rawElement(
			'li',
			[],
			$formatted
		);

		return $line;
	}

	/**
	 * @inheritDoc
	 *
	 * Conceal the offset which may reveal private data.
	 */
	public function getPagingQueries() {
		return $this->tokenQueryManager->getPagingQueries(
			$this->getRequest(),
			parent::getPagingQueries()
		);
	}

	/**
	 * Get the formatted result list, with navigation bars.
	 */
	public function getFullOutput(): ParserOutput {
		return new ParserOutput(
			$this->getNavigationBar() . $this->getBody() . $this->getNavigationBar()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getEndBody() {
		return $this->getNumRows() ? Html::closeElement( 'ul' ) : '';
	}
}
