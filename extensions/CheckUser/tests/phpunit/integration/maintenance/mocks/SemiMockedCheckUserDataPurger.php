<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance\Mocks;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\CheckUserDataPurger;
use PHPUnit\Framework\Assert;
use Wikimedia\Rdbms\IDatabase;

/**
 * A partly mocked CheckUserDataPurger service that verifies that the calls to
 * ::purgeDataFromLocalTable are as expected and returns mock values from that method.
 */
class SemiMockedCheckUserDataPurger extends CheckUserDataPurger {

	public const MOCKED_PURGED_ROW_COUNTS_PER_TABLE = [
		CheckUserQueryInterface::CHANGES_TABLE => 150,
		CheckUserQueryInterface::PRIVATE_LOG_EVENT_TABLE => 170,
		CheckUserQueryInterface::LOG_EVENT_TABLE => 160,
	];

	/** @var array<string,int> How many times a call has been made to ::purgeDataFromLocalTable for a given table */
	private array $seenTables = [];

	public function purgeDataFromLocalTable(
		IDatabase $dbw, string $table, string $cutoff, ClientHintsReferenceIds $deletedReferenceIds,
		string $fname, int $totalRowsToPurge = 500
	): int {
		// Keep a track of how many times a call to this method has been made, grouped by the $table argument value.
		// Mock the return value by returning a unique integer on the first call, and then 0 on all subsequent calls
		// to simulate that on the second call the maintenance script finds no more rows to purge.
		$returnValue = 0;
		if ( !array_key_exists( $table, $this->seenTables ) ) {
			$this->seenTables[$table] = 0;
			$returnValue = self::MOCKED_PURGED_ROW_COUNTS_PER_TABLE[$table] ?? 200;
		}
		$this->seenTables[$table]++;
		// Check that the arguments are as expected. The arguments with object types are tested because the
		// type is specified.
		Assert::assertSame( '20230405060638', $cutoff, 'The cutoff value is not as expected' );
		Assert::assertSame( 200, $totalRowsToPurge );
		return $returnValue;
	}

	public function checkThatExpectedCallsHaveBeenMade() {
		// Check that a call has been made twice for each table
		foreach ( self::RESULT_TABLES as $table ) {
			Assert::assertSame( 2, $this->seenTables[$table] );
		}
		// Check that no unexpected tables are present
		Assert::assertSame( count( self::RESULT_TABLES ) * 2, array_sum( $this->seenTables ) );
	}
}
