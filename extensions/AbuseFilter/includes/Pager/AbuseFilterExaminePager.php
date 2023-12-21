<?php

namespace MediaWiki\Extension\AbuseFilter\Pager;

use MediaWiki\Extension\AbuseFilter\AbuseFilterChangesList;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\Title;
use RecentChange;
use ReverseChronologicalPager;
use stdClass;
use Wikimedia\Rdbms\IDatabase;

class AbuseFilterExaminePager extends ReverseChronologicalPager {
	/**
	 * @var AbuseFilterChangesList Our changes list
	 */
	private $changesList;
	/**
	 * @var Title
	 */
	private $title;
	/**
	 * @var array Query conditions
	 */
	private $conds;
	/**
	 * @var int Line number of the row, see RecentChange::$counter
	 */
	private $rcCounter;

	/**
	 * @param AbuseFilterChangesList $changesList
	 * @param LinkRenderer $linkRenderer
	 * @param IDatabase $dbr
	 * @param Title $title
	 * @param array $conds
	 */
	public function __construct(
		AbuseFilterChangesList $changesList,
		LinkRenderer $linkRenderer,
		IDatabase $dbr,
		Title $title,
		array $conds
	) {
		// Set database before parent constructor to avoid setting it there with wfGetDB
		$this->mDb = $dbr;
		parent::__construct( $changesList, $linkRenderer );
		$this->changesList = $changesList;
		$this->title = $title;
		$this->conds = $conds;
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
		$rc = RecentChange::newFromRow( $row );
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
