<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser;

use MediaWiki\CheckUser\CheckUser\SpecialCheckUserLog;
use MediaWiki\User\ActorStore;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUser
 */
class SpecialCheckUserLogTest extends MediaWikiUnitTestCase {
	private function commonVerifyInitiator( string $initiatorName, $mockReturnValue ) {
		$objectUnderTest = $this->getMockBuilder( SpecialCheckUserLog::class )
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
		$objectUnderTest->dbr = $mockDbr;
		return $objectUnderTest;
	}

	/** @dataProvider provideInitiatorNames */
	public function testVerifyInitiatorInitiatorHasActorId( $initiatorName ) {
		$objectUnderTest = $this->commonVerifyInitiator( $initiatorName, 1 );
		$this->assertSame(
			1,
			$objectUnderTest->verifyInitiator( $initiatorName ),
			'If an IP or user has an actor ID, the actor ID should be returned.'
		);
	}

	/** @dataProvider provideInitiatorNames */
	public function testVerifyInitiatorInitiatorHasNoActorId( $initiatorName ) {
		$objectUnderTest = $this->commonVerifyInitiator( $initiatorName, null );
		$this->assertSame(
			false,
			$objectUnderTest->verifyInitiator( $initiatorName ),
			'If an IP or user has no actor ID, false should be returned.'
		);
	}

	public static function provideInitiatorNames() {
		return [
			'IP' => [ '127.0.0.1' ],
			'User' => [ 'TestAccount' ]
		];
	}
}
