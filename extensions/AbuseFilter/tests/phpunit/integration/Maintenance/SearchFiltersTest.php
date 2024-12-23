<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use Generator;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Maintenance\SearchFilters;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @group Test
 * @group AbuseFilter
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\Maintenance\SearchFilters
 */
class SearchFiltersTest extends MaintenanceBaseTestCase {
	/**
	 * @inheritDoc
	 */
	protected function getMaintenanceClass() {
		return SearchFilters::class;
	}

	/**
	 * @inheritDoc
	 */
	public function addDBData() {
		$defaultRow = [
			'af_actor' => 1,
			'af_timestamp' => $this->getDb()->timestamp( '20190826000000' ),
			'af_enabled' => 1,
			'af_comments' => '',
			'af_public_comments' => 'Test filter',
			'af_hidden' => Flags::FILTER_PUBLIC,
			'af_hit_count' => 0,
			'af_throttled' => 0,
			'af_deleted' => 0,
			'af_global' => 0,
			'af_group' => 'default'
		];
		$rows = [
			[ 'af_id' => 1, 'af_pattern' => '', 'af_actions' => '' ] + $defaultRow,
			[ 'af_id' => 2, 'af_pattern' => 'rmspecials(page_title) === "foo"', 'af_actions' => 'warn' ] + $defaultRow,
			[ 'af_id' => 3, 'af_pattern' => 'user_editcount % 3 !== 1', 'af_actions' => 'warn,block' ] + $defaultRow,
			[
				'af_id' => 4, 'af_pattern' => 'rmspecials(added_lines_pst) !== ""', 'af_actions' => 'block',
			] + $defaultRow
		];
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'abuse_filter' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	/** @dataProvider provideNonMySQLDatabaseTypes */
	public function testExecuteForNonMySQLDatabaseType( $dbType ) {
		$this->expectCallToFatalError();
		$this->expectOutputString( "This maintenance script only works with MySQL databases\n" );
		$this->overrideConfigValue( MainConfigNames::DBtype, $dbType );
		$this->maintenance->execute();
	}

	public static function provideNonMySQLDatabaseTypes() {
		return [
			'PostgresSQL' => [ 'postgres' ],
			'SQLite' => [ 'sqlite' ],
		];
	}

	public function testExecuteWhenNeitherPatternOrConsequenceProvided() {
		// It is safe to mock the DB type here, as the script should exit before any queries are made
		$this->overrideConfigValue( MainConfigNames::DBtype, 'mysql' );
		$this->expectCallToFatalError();
		$this->expectOutputString( "One of --consequence or --pattern should be specified.\n" );
		$this->maintenance->execute();
	}

	private function getExpectedOutput( array $ids, bool $withHeader = true ): string {
		global $wgDBname;
		$expected = $withHeader ? "wiki\tfilter\n" : '';
		foreach ( $ids as $id ) {
			$expected .= "$wgDBname\t$id\n";
		}
		return $expected;
	}

	public static function provideSearches(): Generator {
		yield 'single filter for pattern search' => [ 'page_title', '', [ 2 ] ];
		yield 'multiple filters for pattern search' => [ 'rmspecials', '', [ 2, 4 ] ];
		yield 'single filter when consequence specified' => [ 'rmspecials', 'block', [ 4 ] ];
		yield 'regex for pattern' => [ '[a-z]\(', '', [ 2, 4 ] ];
	}

	/**
	 * @param string $pattern
	 * @param string $consequence
	 * @param array $expectedIDs
	 * @dataProvider provideSearches
	 */
	public function testExecute_singleWiki( string $pattern, string $consequence, array $expectedIDs ) {
		if ( $this->getDb()->getType() !== 'mysql' ) {
			$this->markTestSkipped( 'The script only works on MySQL' );
		}
		$this->setMwGlobals( [ 'wgConf' => (object)[ 'wikis' => [] ] ] );
		$this->maintenance->loadParamsAndArgs( null, [ 'pattern' => $pattern, 'consequence' => $consequence ] );
		$this->expectOutputString( $this->getExpectedOutput( $expectedIDs ) );
		$this->maintenance->execute();
	}

	/**
	 * @param string $pattern
	 * @param string $consequence
	 * @param array $expectedIDs
	 * @dataProvider provideSearches
	 */
	public function testExecute_multipleWikis( string $pattern, string $consequence, array $expectedIDs ) {
		if ( $this->getDb()->getType() !== 'mysql' ) {
			$this->markTestSkipped( 'The script only works on MySQL' );
		}
		global $wgDBname;
		$this->setMwGlobals( [ 'wgConf' => (object)[ 'wikis' => [ $wgDBname, $wgDBname ] ] ] );
		$this->maintenance->loadParamsAndArgs( null, [ 'pattern' => $pattern, 'consequence' => $consequence ] );
		$expectedText = $this->getExpectedOutput( $expectedIDs ) . $this->getExpectedOutput( $expectedIDs, false );
		$this->expectOutputString( $expectedText );
		$this->maintenance->execute();
	}
}
