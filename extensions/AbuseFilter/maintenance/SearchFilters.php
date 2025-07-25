<?php

namespace MediaWiki\Extension\AbuseFilter\Maintenance;

use MediaWiki\Extension\AbuseFilter\AbuseFilter;
use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;

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
			'Find all filters matching a regular expression pattern and/or that have a given ' .
			'consequence and/or privacy level'
		);
		$this->addOption( 'pattern', 'Regular expression pattern', false, true );
		$this->addOption( 'consequence', 'The consequence that the filter should have', false, true );
		$this->addOption(
			'privacy',
			'The privacy level that the filter should include (a constant from Flags)',
			false,
			true
		);

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

		if (
			!$this->getOption( 'pattern' ) &&
			!$this->getOption( 'consequence' ) &&
			$this->getOption( 'privacy' ) === null
		) {
			$this->fatalError( 'One of --consequence, --pattern or --privacy should be specified.' );
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
		$privacy = $this->getOption( 'privacy' );

		if ( $dbr->tableExists( 'abuse_filter', __METHOD__ ) ) {
			$queryBuilder = $dbr->newSelectQueryBuilder()
				->select( [ 'dbname' => 'DATABASE()', 'af_id' ] )
				->from( 'abuse_filter' );
			if ( $this->getOption( 'pattern' ) ) {
				$queryBuilder->where( "af_pattern RLIKE $pattern" );
			}
			if ( $consequence ) {
				$queryBuilder->where( AbuseFilter::findInSet( $dbr, 'af_actions', $consequence ) );
			}
			if ( $privacy !== '' ) {
				if ( $privacy === '0' ) {
					$queryBuilder->where( $dbr->expr(
						'af_hidden',
						'=',
						0
					) );
				} else {
					$privacy = (int)$privacy;
					$queryBuilder->where( $dbr->bitAnd(
						'af_hidden',
						$privacy
					) . " = $privacy" );
				}
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
