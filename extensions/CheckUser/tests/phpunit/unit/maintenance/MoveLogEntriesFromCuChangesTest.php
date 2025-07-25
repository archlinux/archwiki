<?php

namespace MediaWiki\CheckUser\Tests\Unit\Maintenance;

use MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges
 */
class MoveLogEntriesFromCuChangesTest extends MediaWikiUnitTestCase {

	public function testGetUpdateKey() {
		$objectUnderTest = TestingAccessWrapper::newFromObject( new MoveLogEntriesFromCuChanges() );
		$this->assertSame(
			'MediaWiki\\CheckUser\\Maintenance\\MoveLogEntriesFromCuChanges',
			$objectUnderTest->getUpdateKey(),
			'::getUpdateKey did not return the expected value.'
		);
	}

	public function testDoDBUpdatesWithNoRowsInCuChanges() {
		// Mock the primary DB
		$dbwMock = $this->createMock( IDatabase::class );
		$dbwMock->method( 'newSelectQueryBuilder' )
			->willReturnCallback( static fn () => new SelectQueryBuilder( $dbwMock ) );
		// Expect that a query for the number of rows in cu_changes is made.
		$dbwMock->method( 'selectRowCount' )
			->with(
				[ 'cu_changes' ],
				'*',
				[],
				'MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges::doDBUpdates',
				[],
				[]
			)
			->willReturn( 0 );
		// Get the object under test and make the getServiceContainer return the mock MediaWikiServices.
		$objectUnderTest = $this->getMockBuilder( MoveLogEntriesFromCuChanges::class )
			->onlyMethods( [ 'getDB', 'output' ] )
			->getMock();
		$objectUnderTest
			->method( 'getDB' )
			->with( DB_PRIMARY )
			->willReturn( $dbwMock );
		// Expect that doDBUpdates returns true.
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertTrue(
			$objectUnderTest->doDBUpdates(),
			"::doDBUpdates should return true when the no rows are in cu_changes."
		);
	}
}
