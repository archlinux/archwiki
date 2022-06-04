<?php

namespace MediaWiki\Extension\AbuseFilter\Pager;

use MediaWiki\Extension\AbuseFilter\AbuseFilterChangesList;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewExamine;
use RecentChange;
use ReverseChronologicalPager;
use stdClass;
use Title;

class AbuseFilterExaminePager extends ReverseChronologicalPager {
	/**
	 * @var AbuseFilterChangesList Our changes list
	 */
	public $mChangesList;
	/**
	 * @var AbuseFilterViewExamine The associated view
	 */
	public $mPage;
	/**
	 * @var int Line number of the row, see RecentChange::$counter
	 */
	public $rcCounter;

	/**
	 * @param AbuseFilterViewExamine $page
	 * @param AbuseFilterChangesList $changesList
	 */
	public function __construct( AbuseFilterViewExamine $page, AbuseFilterChangesList $changesList ) {
		parent::__construct();
		$this->mChangesList = $changesList;
		$this->mPage = $page;
		$this->rcCounter = 1;
	}

	/**
	 * @return array
	 */
	public function getQueryInfo() {
		$dbr = wfGetDB( DB_REPLICA );
		$conds = [];
		$rcQuery = RecentChange::getQueryInfo();

		if ( (string)$this->mPage->mSearchUser !== '' ) {
			$conds[$rcQuery['fields']['rc_user_text']] = $this->mPage->mSearchUser;
		}

		$startTS = strtotime( $this->mPage->mSearchPeriodStart );
		if ( $startTS ) {
			$conds[] = 'rc_timestamp>=' . $dbr->addQuotes( $dbr->timestamp( $startTS ) );
		}
		$endTS = strtotime( $this->mPage->mSearchPeriodEnd );
		if ( $endTS ) {
			$conds[] = 'rc_timestamp<=' . $dbr->addQuotes( $dbr->timestamp( $endTS ) );
		}

		$conds[] = $this->mPage->buildTestConditions( $dbr );
		$conds = array_merge( $conds, $this->mPage->buildVisibilityConditions( $dbr, $this->getAuthority() ) );

		$info = [
			'tables' => $rcQuery['tables'],
			'fields' => $rcQuery['fields'],
			'conds' => $conds,
			'join_conds' => $rcQuery['joins'],
		];

		return $info;
	}

	/**
	 * @param stdClass $row
	 * @return string
	 */
	public function formatRow( $row ) {
		$rc = RecentChange::newFromRow( $row );
		$rc->counter = $this->rcCounter++;
		return $this->mChangesList->recentChangesLine( $rc, false );
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	public function getIndexField() {
		return 'rc_id';
	}

	/**
	 * @return Title
	 */
	public function getTitle() {
		return $this->mPage->getTitle( 'examine' );
	}

	/**
	 * @return string
	 */
	public function getEmptyBody() {
		return $this->msg( 'abusefilter-examine-noresults' )->parseAsBlock();
	}
}
