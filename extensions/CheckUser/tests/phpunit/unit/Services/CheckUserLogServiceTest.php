<?php

namespace MediaWiki\CheckUser\Tests\Unit\Services;

use LogicException;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\Services\CheckUserLogService
 * @group CheckUser
 */
class CheckUserLogServiceTest extends MediaWikiUnitTestCase {
	use MockServiceDependenciesTrait;

	/** @dataProvider provideVerifyTargetIP */
	public function testVerifyTargetIP( $target, $expected ) {
		$objectUnderTest = $this->newServiceInstance( CheckUserLogService::class, [] );
		$this->assertArrayEquals(
			$expected,
			$objectUnderTest->verifyTarget( $target ),
			true,
			false,
			'Valid IP addresses should be seen as valid targets and parsed as an IP or IP range.'
		);
	}

	public static function provideVerifyTargetIP() {
		return [
			'Single IP' => [ '124.0.0.0', [ '7C000000' ] ],
			'/24 IP range' => [ '124.0.0.0/24', [ '7C000000', '7C0000FF' ] ],
			'/16 IP range' => [ '124.0.0.0/16', [ '7C000000', '7C00FFFF' ] ],
			'Single IP notated as a /32 range' => [ '1.2.3.4/32', [ '01020304' ] ],
			'Single IPv6' => [ '::e:f:2001', [ 'v6-00000000000000000000000E000F2001' ] ],
			'/96 IPv6 range' => [ '::e:f:2001/96', [
				'v6-00000000000000000000000E00000000',
				'v6-00000000000000000000000EFFFFFFFF'
			]
			],
		];
	}

	public function testGetTargetSearchCondsOnInvalidTarget() {
		// Mock CheckUserLogService::verifyTarget to return false
		$objectUnderTest = $this->getMockBuilder( CheckUserLogService::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'verifyTarget' ] )
			->getMock();
		$objectUnderTest->method( 'verifyTarget' )
			->willReturn( false );
		// Expect that the method under test (::getTargetSearchConds) returns null
		$this->assertNull(
			$objectUnderTest->getTargetSearchConds( '/' ),
			'::getTargetSearchConds should return null if ::verifyTarget returns false'
		);
	}

	public function testGetTargetSearchCondsOnInvalidArrayFromVerifyTarget() {
		// Mock CheckUserLogService::verifyTarget to return an invalid array to deliberately
		// cause a LogicException.
		$objectUnderTest = $this->getMockBuilder( CheckUserLogService::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'verifyTarget' ] )
			->getMock();
		$objectUnderTest->method( 'verifyTarget' )
			->willReturn( [ 'a', 'b', 'c' ] );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$objectUnderTest->dbProvider = $this->createMock( IConnectionProvider::class );
		// Call the method under test, expecting it to throw a LogicException.
		$this->expectException( LogicException::class );
		$objectUnderTest->getTargetSearchConds( '/' );
	}
}
