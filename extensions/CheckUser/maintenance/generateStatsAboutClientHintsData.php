<?php

namespace MediaWiki\CheckUser\Maintenance;

use MediaWiki\Json\FormatJson;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\WikiMap\WikiMap;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Generates statistics about the Client Hints data in cu_useragent_clienthints
 * in an anonymised form and then send this data to the standard output.
 */
class GenerateStatsAboutClientHintsData extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addOption(
			'averages-accuracy',
			'How many rows should be used to generate averages in the results. Default 25000.'
		);

		$this->requireExtension( 'CheckUser' );
	}

	/** @inheritDoc */
	public function execute() {
		$wiki = $this->getOption( 'wiki', WikiMap::getCurrentWikiDbDomain() );
		$averagesAccuracy = $this->getOption( 'averages-accuracy', 25000 );
		$this->output( FormatJson::encode( $this->generateCounts( $wiki, $averagesAccuracy ) ) . "\n" );
	}

	/**
	 * Generates and returns an array of data about the Client Hints
	 * stored in the database.
	 *
	 * @param string $wiki The wiki to use.
	 * @param int $averagesAccuracy How many rows to use to generate averages.
	 * @return array
	 */
	protected function generateCounts( string $wiki, int $averagesAccuracy ): array {
		$dbr = $this->getDB( DB_REPLICA, [ 'vslow' ], $wiki );
		$resultArray = [];
		// The average number of cu_useragent_clienthints_map rows for a
		// given reference ID.
		$resultArray['averageMapRowsPerReferenceId'] = $dbr->newSelectQueryBuilder()
			->select( 'AVG(count)' )
			->table(
				$dbr->newSelectQueryBuilder()
					->select( [ 'count' => 'COUNT(*)' ] )
					->from( 'cu_useragent_clienthints_map' )
					->groupBy( [ 'uachm_reference_id', 'uachm_reference_type' ] )
					->limit( $averagesAccuracy )
			)
			->caller( __METHOD__ )
			->fetchField();
		// Get the total row counts for the tables. Allows estimation as to how
		// many unique reference IDs there are in the mapping table by dividing
		// this count by averageMapRowsPerReferenceId.
		$resultArray['totalRowCount'] = [];
		$resultArray['totalRowCount']['cu_useragent_clienthints_map'] = $dbr->newSelectQueryBuilder()
			->from( 'cu_useragent_clienthints_map' )
			->caller( __METHOD__ )
			->fetchRowCount();
		$resultArray['totalRowCount']['cu_useragent_clienthints'] = $dbr->newSelectQueryBuilder()
			->from( 'cu_useragent_clienthints' )
			->caller( __METHOD__ )
			->fetchRowCount();
		// Get the number of cu_useragent_clienthints_map rows grouped by which
		// row they reference in cu_useragent_clienthints.
		$mapTableRowCountPerUachId = $dbr->newSelectQueryBuilder()
			->select( [ 'uachm_uach_id', 'count' => 'COUNT(*)' ] )
			->from( 'cu_useragent_clienthints_map' )
			->groupBy( 'uachm_uach_id' )
			->caller( __METHOD__ )
			->fetchResultSet();
		$mapTableRowCountBreakdown = [];
		$mapTableRowCountPerNamePerReferenceId = [];
		$invalidMapRowsCount = 0;
		foreach ( $mapTableRowCountPerUachId as $breakdown ) {
			// Get the name and value for output by the script
			// instead of the uach_id for the breakdown of
			// row count by uach_id.
			$nameAndValue = $dbr->newSelectQueryBuilder()
				->select( [ 'uach_name', 'uach_value' ] )
				->from( 'cu_useragent_clienthints' )
				->where( [ 'uach_id' => $breakdown->uachm_uach_id ] )
				->caller( __METHOD__ )
				->fetchRow();
			if ( $nameAndValue === false ) {
				// If no row in cu_useragent_clienthints has this uach_id,
				// then mark it as invalid and add the count to a invalid
				// map rows count to be returned to the caller.
				$invalidMapRowsCount += $breakdown->count;
				continue;
			}
			$nameForBreakdown = $nameAndValue->uach_name;
			$valueForBreakdown = $nameAndValue->uach_value;
			if ( !array_key_exists( $nameForBreakdown, $mapTableRowCountBreakdown ) ) {
				$mapTableRowCountBreakdown[$nameForBreakdown] = [];
				// Get the average number of rows with this uach_name
				// for each reference ID.
				$idsWithThisName = $dbr->newSelectQueryBuilder()
					->select( 'uach_id' )
					->from( 'cu_useragent_clienthints' )
					->where( [ 'uach_name' => $nameForBreakdown ] )
					->caller( __METHOD__ )
					->fetchFieldValues();
				$mapTableRowCountPerNamePerReferenceId[$nameForBreakdown] = $dbr->newSelectQueryBuilder()
					->select( 'AVG(count)' )
					->table(
						$dbr->newSelectQueryBuilder()
							->select( [ 'count' => 'COUNT(*)' ] )
							->from( 'cu_useragent_clienthints_map' )
							->where( [ 'uachm_uach_id' => $idsWithThisName ] )
							->groupBy( [ 'uachm_reference_id', 'uachm_reference_type' ] )
							->limit( $averagesAccuracy )
					)
					->caller( __METHOD__ )
					->fetchField();
			}
			$mapTableRowCountBreakdown[$nameForBreakdown][$valueForBreakdown] = $breakdown->count;
		}
		$resultArray['mapTableRowCountBreakdown'] = $mapTableRowCountBreakdown;
		$resultArray['averageItemsPerNamePerReferenceId'] = $mapTableRowCountPerNamePerReferenceId;
		$resultArray['invalidMapRowsCount'] = $invalidMapRowsCount;
		return $resultArray;
	}
}

$maintClass = GenerateStatsAboutClientHintsData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
