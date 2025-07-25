<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\CheckUser;

use InvalidArgumentException;
use MediaWiki\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\CheckUser\Api\CheckUser\ApiQueryCheckUserAbstractResponse;
use MediaWiki\Context\RequestContext;
use MediaWikiIntegrationTestCase;
use RuntimeException;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\Api\CheckUser\ApiQueryCheckUserAbstractResponse
 * @group Database
 */
class ApiQueryCheckUserAbstractResponseTest extends MediaWikiIntegrationTestCase {

	/** @dataProvider provideConstructor */
	public function testConstructor( $requestParameters, $expectedProperties ) {
		ConvertibleTimestamp::setFakeTime( '20230405060708' );
		$mockApiQueryCheckUser = $this->createMock( ApiQueryCheckUser::class );
		$mockApiQueryCheckUser->method( 'extractRequestParams' )
			->willReturn( $requestParameters );
		$mockApiQueryCheckUser->method( 'dieWithError' )
			->willThrowException( new RuntimeException( 'dieWithError was called' ) );
		$objectUnderTest = $this->getMockBuilder( ApiQueryCheckUserAbstractResponse::class )
			->setConstructorArgs( [
				$mockApiQueryCheckUser,
				$this->getServiceContainer()->getConnectionProvider(),
				$this->getServiceContainer()->getMainConfig(),
				RequestContext::getMain(),
				$this->getServiceContainer()->get( 'CheckUserLogService' ),
				$this->getServiceContainer()->getUserNameUtils(),
				$this->getServiceContainer()->get( 'CheckUserLookupUtils' )
			] )->getMockForAbstractClass();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		foreach ( $expectedProperties as $property => $value ) {
			if ( $property === 'timeCutoff' ) {
				// Support running the tests on a postgres database which uses a different timestamp format.
				$value = $this->getDb()->timestamp( $value );
			}
			$this->assertSame(
				$value,
				$objectUnderTest->$property,
				"The property $property was not as expected."
			);
		}
	}

	public static function provideConstructor() {
		return [
			"Test that 'API: ' is added as a prefix to the reason" => [
				[ 'reason' => 'test', 'timecond' => '-3 months', 'limit' => 1, 'target' => 'Test' ],
				[ 'reason' => 'API: test' ],
			],
			'Target is un-normalised IP address' => [
				[ 'reason' => 'test', 'timecond' => '-2 months', 'limit' => 1, 'target' => '127.0.0.001' ],
				[ 'target' => '127.0.0.1' ],
			],
			'Target is un-normalised IP range' => [
				[ 'reason' => 'test', 'timecond' => '-1 month', 'limit' => 1, 'target' => '127.0.0.1/24' ],
				[ 'target' => '127.0.0.0/24' ],
			],
			'Target is XFF IP address' => [
				[ 'reason' => 'test', 'timecond' => '-1 week', 'limit' => 1, 'target' => '1.2.3.4', 'xff' => true ],
				[ 'target' => '1.2.3.4', 'xff' => true ],
			],
			'Target is a username but XFF is set' => [
				[ 'reason' => 'test', 'timecond' => '-1 day', 'limit' => 1, 'target' => 'Test', 'xff' => false ],
				[ 'target' => 'Test', 'xff' => null ],
			],
			'Target is an invalid username' => [
				[ 'reason' => 'test', 'timecond' => '-1 hour', 'limit' => 1, 'target' => '#test' ],
				[ 'target' => '' ],
			],
			'Test that timecond is correctly parsed' => [
				[ 'reason' => 'test', 'timecond' => '-1 hour', 'limit' => 1, 'target' => 'Test' ],
				[ 'timeCutoff' => '20230405050708' ],
			],
			'Test that limit is correctly parsed' => [
				[ 'reason' => 'test', 'timecond' => '-1 hour', 'limit' => 5, 'target' => 'Test' ],
				[ 'limit' => 5 ],
			],
		];
	}

	/** @dataProvider provideInvalidRequestParameters */
	public function testConstructorOnInvalidParameters( $requestParameters ) {
		$this->overrideConfigValue( 'CheckUserForceSummary', true );
		$this->expectException( RuntimeException::class );
		$this->testConstructor( $requestParameters, [] );
	}

	public static function provideInvalidRequestParameters() {
		return [
			'Reason field is empty' => [
				[ 'reason' => '', 'timecond' => '-1 hour', 'limit' => 1, 'target' => '127.0.0.256' ],
			],
			'Timecond is invalid' => [
				[ 'reason' => 'test', 'timecond' => 'invalid', 'limit' => 1, 'target' => 'Test' ],
			],
		];
	}

	public function testGetQueryBuilderForTableOnInvalidTable() {
		$this->expectException( InvalidArgumentException::class );
		$mockApiQueryCheckUser = $this->createMock( ApiQueryCheckUser::class );
		$mockApiQueryCheckUser->method( 'extractRequestParams' )
			->willReturn( [
				'request' => 'actions', 'target' => 'Test', 'reason' => '', 'timecond' => '-3 months', 'limit' => '50'
			] );
		$objectUnderTest = $this->getMockBuilder( ApiQueryCheckUserAbstractResponse::class )
			->setConstructorArgs( [
				$mockApiQueryCheckUser,
				$this->getServiceContainer()->getConnectionProvider(),
				$this->getServiceContainer()->getMainConfig(),
				RequestContext::getMain(),
				$this->getServiceContainer()->get( 'CheckUserLogService' ),
				$this->getServiceContainer()->getUserNameUtils(),
				$this->getServiceContainer()->get( 'CheckUserLookupUtils' )
			] )->getMockForAbstractClass();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$objectUnderTest->getQueryBuilderForTable( 'invalid_table' );
	}
}
