<?php

namespace MediaWiki\Extension\AbuseFilter\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class SearchFilters extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Find all filters matching a regular expression pattern and/or that have given consequence'
		);
		$this->addOption( 'pattern', 'Regular expression pattern', false, true );
		$this->addOption( 'consequence', 'The consequence that the filter should have', false, true );

		$this->requireExtension( 'Abuse Filter' );
	}

	/**
	 * @see Maintenance:execute()
	 */
	public function execute() {
		global $wgConf;

		if ( $this->getConfig()->get( MainConfigNames::DBtype ) !== 'mysql' ) {
			$this->fatalError( 'This maintenance script only works with MySQL databases' );
		}

		if ( !$this->getOption( 'pattern' ) && !$this->getOption( 'consequence' ) ) {
			$this->fatalError( 'One of --consequence or --pattern should be specified.' );
		}

		$this->output( "wiki\tfilter\n" );

		if ( count( $wgConf->wikis ) > 0 ) {
			foreach ( $wgConf->wikis as $dbname ) {
				$this->getMatchingFilters( $dbname );
			}
		} else {
			$this->getMatchingFilters();
		}
	}

	/**
	 * @param string|false $dbname Name of database, or false if the wiki is not part of a wikifarm
	 */
	private function getMatchingFilters( $dbname = false ) {
		$dbr = $this->getDB( DB_REPLICA, [], $dbname );
		$pattern = $dbr->addQuotes( $this->getOption( 'pattern' ) );
		$consequence = $this->getOption( 'consequence' );

		if ( $dbr->tableExists( 'abuse_filter', __METHOD__ ) ) {
			$queryBuilder = $dbr->newSelectQueryBuilder()
				->select( [ 'dbname' => 'DATABASE()', 'af_id' ] )
				->from( 'abuse_filter' );
			if ( $this->getOption( 'pattern' ) ) {
				$queryBuilder->where( "af_pattern RLIKE $pattern" );
			}
			if ( $consequence ) {
				$queryBuilder->where( $dbr->expr(
					'af_actions',
					IExpression::LIKE,
					new LikeValue( $dbr->anyString(), $consequence, $dbr->anyString() )
				) );
			}
			$rows = $queryBuilder->caller( __METHOD__ )->fetchResultSet();

			foreach ( $rows as $row ) {
				$this->output( $row->dbname . "\t" . $row->af_id . "\n" );
			}
		}
	}
}

$maintClass = SearchFilters::class;
require_once RUN_MAINTENANCE_IF_MAIN;
