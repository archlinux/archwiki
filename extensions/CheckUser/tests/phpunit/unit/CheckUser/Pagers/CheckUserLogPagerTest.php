<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Pagers;

use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserLogPager;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\User\ActorStore;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserLogPager
 */
class CheckUserLogPagerTest extends MediaWikiUnitTestCase {
	private function commonGetPerformerSearchConds( string $initiatorName, $mockReturnValue ) {
		$objectUnderTest = $this->getMockBuilder( CheckUserLogPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$mockActorStore = $this->createMock( ActorStore::class );
		$mockDbr = $this->createMock( IReadableDatabase::class );
		$mockActorStore->expects( $this->once() )
			->method( 'findActorIdByName' )
			->with( $initiatorName, $mockDbr )
			->willReturn( $mockReturnValue );
		$objectUnderTest->actorStore = $mockActorStore;
		$objectUnderTest->mDb = $mockDbr;
		return $objectUnderTest;
	}

	/** @dataProvider provideInitiatorNames */
	public function testGetPerformerSearchCondsHasActorId( $initiatorName ) {
		$objectUnderTest = $this->commonGetPerformerSearchConds( $initiatorName, 1 );
		$this->assertArrayEquals(
			[ 'cul_actor' => 1 ],
			$objectUnderTest->getPerformerSearchConds( $initiatorName ),
			false,
			true,
			'If an IP or user has an actor ID, the actor ID should be returned.'
		);
	}

	/** @dataProvider provideInitiatorNames */
	public function testGetPerformerSearchCondsHasNoActorId( $initiatorName ) {
		$objectUnderTest = $this->commonGetPerformerSearchConds( $initiatorName, null );
		$this->assertSame(
			null,
			$objectUnderTest->getPerformerSearchConds( $initiatorName ),
			'If an IP or user has no actor ID, null should be returned.'
		);
	}

	public static function provideInitiatorNames() {
		return [
			'IP' => [ '127.0.0.1' ],
			'User' => [ 'TestAccount' ]
		];
	}

	/** @dataProvider provideGetQueryInfo */
	public function testGetQueryInfo( $opts, $expectedOptions ) {
		$objectUnderTest = $this->getMockBuilder( CheckUserLogPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		// Use a mock CommentStore in the test that returns an array of empty
		// arrays for the call to ::getJoin
		$mockCommentStore = $this->createMock( CommentStore::class );
		$mockCommentStore->method( 'getJoin' )
			->willReturn( [
				'tables' => [],
				'fields' => [],
				'joins' => [],
			] );
		// Use a mock CheckUserLogService in the test that a mock ::getTargetSearchConds result
		$mockCheckUserLogService = $this->createMock( CheckUserLogService::class );
		$mockCheckUserLogService->method( 'getTargetSearchConds' )
			->willReturn( [] );
		// Assign the mock services to the object under test
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$objectUnderTest->checkUserLogService = $mockCheckUserLogService;
		$objectUnderTest->commentStore = $mockCommentStore;
		// Assign $opts to the object under test
		$objectUnderTest->opts = $opts;
		// Call the method under test
		$actualQueryInfo = $objectUnderTest->getQueryInfo();
		$this->assertArrayEquals(
			$objectUnderTest->selectFields(),
			$actualQueryInfo['fields'],
			'::getQueryInfo should use the fields from ::selectFields.'
		);
		$this->assertArrayEquals(
			[ 'cu_log', 'cu_log_actor' => 'actor' ],
			$actualQueryInfo['tables'],
			'::getQueryInfo did not return the expected tables.'
		);
		$this->assertArrayEquals(
			$expectedOptions,
			$actualQueryInfo['options'],
			'::getQueryInfo did not return the expected options array.'
		);
	}

	public static function provideGetQueryInfo() {
		return [
			'IP specified as the target' => [
				// $objectUnderTest->opts value for the test.
				[ 'target' => '1.2.3.4', 'initiator' => '', 'reason' => '' ],
				// The expected array for the 'options' array returned by ::getQueryInfo
				[ 'USE INDEX' => [ 'cu_log' => 'cul_target_hex' ] ]
			],
			'IP range specified as the target' => [
				[ 'target' => '1.2.3.4/22', 'initiator' => '', 'reason' => '' ],
				[ 'USE INDEX' => [ 'cu_log' => 'cul_target_hex' ] ]
			],
			'User specified as the target' => [
				[ 'target' => 'Testinguser', 'initiator' => '', 'reason' => '' ],
				[]
			],
		];
	}
}
