<?php

namespace MediaWiki\Extension\AbuseFilter\Pager;

use MediaWiki\Extension\AbuseFilter\AbuseFilterChangesList;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Pager\ReverseChronologicalPager;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\RecentChanges\RecentChangeFactory;
use MediaWiki\Title\Title;
use stdClass;
use Wikimedia\Rdbms\IReadableDatabase;

class AbuseFilterExaminePager extends ReverseChronologicalPager {
	/**
	 * @var int Line number of the row, see RecentChange::$counter
	 */
	private $rcCounter;

	public function __construct(
		private readonly AbuseFilterChangesList $changesList,
		LinkRenderer $linkRenderer,
		private readonly RecentChangeFactory $recentChangeFactory,
		IReadableDatabase $dbr,
		private readonly Title $title,
		private readonly array $conds
	) {
		// Set database before parent constructor to avoid setting it there
		$this->mDb = $dbr;
		parent::__construct( $changesList, $linkRenderer );
		$this->rcCounter = 1;
	}

	/**
	 * @return array
	 */
	public function getQueryInfo() {
		$rcQuery = RecentChange::getQueryInfo();
		return [
			'tables' => $rcQuery['tables'],
			'fields' => $rcQuery['fields'],
			'conds' => $this->conds,
			'join_conds' => $rcQuery['joins'],
		];
	}

	/**
	 * @param stdClass $row
	 * @return string
	 */
	public function formatRow( $row ) {
		$rc = $this->recentChangeFactory->newRecentChangeFromRow( $row );
		$rc->counter = $this->rcCounter++;
		return $this->changesList->recentChangesLine( $rc, false );
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	public function getIndexField() {
		return 'rc_id';
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @return Title
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * @return string
	 */
	public function getEmptyBody() {
		return $this->msg( 'abusefilter-examine-noresults' )->parseAsBlock();
	}
}
