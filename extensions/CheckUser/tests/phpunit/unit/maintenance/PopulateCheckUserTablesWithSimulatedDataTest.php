<?php

namespace MediaWiki\CheckUser\Tests\Unit\Maintenance;

use MediaWiki\CheckUser\Maintenance\PopulateCheckUserTablesWithSimulatedData;
use MediaWiki\Config\HashConfig;
use MediaWikiUnitTestCase;
use ReflectionClass;
use Wikimedia\IPUtils;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Maintenance\PopulateCheckUserTablesWithSimulatedData
 */
class PopulateCheckUserTablesWithSimulatedDataTest extends MediaWikiUnitTestCase {
	public function setUpObjectUnderTest() {
		return TestingAccessWrapper::newFromObject( new PopulateCheckUserTablesWithSimulatedData() );
	}

	/** @dataProvider provideValidIntArguments */
	public function testEnsureArgumentIsIntWithValidArgument( $argument, $expectedInteger ) {
		$mockedObject = $this->createPartialMock( PopulateCheckUserTablesWithSimulatedData::class, [ 'fatalError' ] );
		$mockedObject->expects( $this->never() )
			->method( 'fatalError' );
		$this->assertSame(
			$expectedInteger,
			TestingAccessWrapper::newFromObject( $mockedObject )->ensureArgumentIsInt( $argument, 'Argument' ),
			'Return value of ::ensureArgumentIsInt not as expected.'
		);
	}

	public static function provideValidIntArguments() {
		return [
			'Argument of the integer 1' => [ 1, 1 ],
			'Argument of the integer 250 as a string' => [ '250', 250 ],
			'Floating point number' => [ '25.34', 25 ],
		];
	}

	/** @dataProvider provideInvalidIntArguments */
	public function testEnsureArgumentIsIntWithInvalidArgument( $invalidArgument ) {
		$mockedObject = $this->createPartialMock( PopulateCheckUserTablesWithSimulatedData::class, [ 'fatalError' ] );
		$mockedObject->expects( $this->once() )
			->method( 'fatalError' );
		TestingAccessWrapper::newFromObject( $mockedObject )->ensureArgumentIsInt( $invalidArgument, 'Argument' );
	}

	public static function provideInvalidIntArguments() {
		return [
			'Argument of a string with no numbers' => [ 'abc' ],
		];
	}

	/** @dataProvider provideReturnRandomIpExceptExcluded */
	public function testReturnRandomIpExceptExcluded( $ipsToUse, $ipsToExclude, $includedIps ) {
		$objectUnderTest = $this->setUpObjectUnderTest();
		$objectUnderTest->ipsToUse = $ipsToUse;
		$this->assertContains(
			$objectUnderTest->returnRandomIpExceptExcluded( $ipsToExclude ),
			$includedIps,
			'IP returned was not in list or was excluded.'
		);
	}

	public static function provideReturnRandomIpExceptExcluded() {
		return [
			'Three IPs, one excluded' => [
				[ '127.0.0.1', '127.0.0.2', '127.0.0.3' ],
				[ '127.0.0.2' ],
				[ '127.0.0.1', '127.0.0.3' ]
			],
		];
	}

	/** @dataProvider provideReturnRandomIpExceptExcludedNoIpsToChoose */
	public function testReturnRandomIpExceptExcludedNoIpsToChoose( $ipsToUse, $ipsToExclude ) {
		$objectUnderTest = $this->setUpObjectUnderTest();
		$objectUnderTest->ipsToUse = $ipsToUse;
		$this->assertNull(
			$objectUnderTest->returnRandomIpExceptExcluded( $ipsToExclude ),
			'IP returned was not in list or was excluded.'
		);
	}

	public static function provideReturnRandomIpExceptExcludedNoIpsToChoose() {
		return [
			'Three IPs, three excluded' => [
				[ '127.0.0.1', '127.0.0.2', '127.0.0.3' ],
				[ '127.0.0.1', '127.0.0.2', '127.0.0.3' ],
			]
		];
	}

	public function testMoveFakeTimeForward() {
		ConvertibleTimestamp::setFakeTime( ConvertibleTimestamp::time() );
		$timeAtStart = ConvertibleTimestamp::time();
		$this->setUpObjectUnderTest()->moveFakeTimeForward();
		$this->assertGreaterThan(
			$timeAtStart,
			ConvertibleTimestamp::time(),
			'::moveFakeTimeForward did not move the time forward by a random amount.'
		);
		$this->assertLessThan(
			$timeAtStart + 400,
			ConvertibleTimestamp::time(),
			':moveFakeTimeForward moved forward the time by too much.'
		);
	}

	public function testSetNewRandomFakeTime() {
		$mockedObject = $this->createPartialMock( PopulateCheckUserTablesWithSimulatedData::class, [ 'getConfig' ] );
		$mockedObject->expects( $this->once() )
			->method( 'getConfig' )
			->willReturn( new HashConfig( [ 'CUDMaxAge' => 7776000 ] ) );
		TestingAccessWrapper::newFromObject( $mockedObject )->setNewRandomFakeTime();
		$timeAfterCall = ConvertibleTimestamp::time();
		// Clear the fake time to allow comparison to the real time.
		ConvertibleTimestamp::setFakeTime( false );
		$this->assertLessThan(
			ConvertibleTimestamp::time(),
			$timeAfterCall,
			'::setNewRandomFakeTime should set a time an hour before the current time at least.'
		);
		$this->assertGreaterThan(
			ConvertibleTimestamp::time() - 7776000 - 3600,
			$timeAfterCall,
			'::setNewRandomFakeTime should set a time after or at CUDMaxAge seconds ago.'
		);
	}

	public function testInitUserAgentAndClientHintsCombos() {
		$objectUnderTest = $this->setUpObjectUnderTest();
		$objectUnderTest->initUserAgentAndClientHintsCombos();
		$this->assertIsArray(
			$objectUnderTest->userAgentsToClientHintsMap,
			'Calling ::initUserAgentAndClientHintsCombos should create an array in ' .
			'$this->userAgentsToClientHintsMap.'
		);
	}

	/** @dataProvider provideGenerateNewIp */
	public function testGenerateNewIp( $randomFloat, $validationCallback ) {
		// TODO: Maybe mock mt_rand to avoid random failures?
		$mockObject = $this->createPartialMock( PopulateCheckUserTablesWithSimulatedData::class, [ 'getRandomFloat' ] );
		$mockObject->expects( $this->once() )
			->method( 'getRandomFloat' )
			->willReturn( $randomFloat );
		$mockObject = TestingAccessWrapper::newFromObject( $mockObject );
		$mockObject->ipv4Ranges = [ '127.0.0.1/24', '1.2.3.4/28' ];
		$mockObject->ipv6Ranges = [ 'fd12:3456:789a:1::/40', '::/64' ];
		// Assert that an IPv4 or IPv6 is returned.
		$generatedIp = $mockObject->generateNewIp();
		$this->assertTrue(
			$validationCallback( $generatedIp ),
			'::generateNewIp did not generate the correct type of IP.'
		);
		foreach ( array_merge( $mockObject->ipv4Ranges, $mockObject->ipv6Ranges ) as $range ) {
			if ( IPUtils::isInRange( $generatedIp, $range ) ) {
				// IP is in this range, so all tests successful. Therefore return.
				return;
			}
		}
		// IP was not in any ranges defined in $ipv4Ranges, so fail.
		$this->fail( 'Generated IP address was not in the valid ranges list.' );
	}

	public static function provideGenerateNewIp() {
		return [
			'Gets IPv4' => [ 0.3, [ IPUtils::class, 'isValidIPv4' ] ],
			'Gets IPv6' => [ 0.7, [ IPUtils::class, 'isValidIPv6' ] ],
		];
	}

	/** @dataProvider provideIPv6 */
	public function testGenerateNewIpv6( $ipv6Ranges, $mtRandValue ) {
		if ( $mtRandValue !== null ) {
			$objectUnderTest = $this->createPartialMock(
				PopulateCheckUserTablesWithSimulatedData::class, [ 'mtRand' ]
			);
			$objectUnderTest->method( 'mtRand' )
				->willReturnCallback( static function ( $min, $max ) use ( $mtRandValue ) {
					if ( $mtRandValue === 'min' ) {
						return $min;
					} elseif ( $mtRandValue === 'max' ) {
						return $max;
					} else {
						return $mtRandValue;
					}
				} );
			$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		} else {
			$objectUnderTest = $this->setUpObjectUnderTest();
		}
		$objectUnderTest->ipv6Ranges = $ipv6Ranges;
		$generatedIpv6 = $objectUnderTest->generateNewIPv6();
		$this->assertTrue(
			IPUtils::isValidIPv6( $generatedIpv6 ),
			'Generated IP was not a valid IPv6'
		);
	}

	public static function provideIPv6() {
		return [
			'One /64 range' => [ [ '::/64' ], null ],
			'One /47 range' => [ [ '::/47' ], null ],
			'One /64 range with mt_rand always choosing minimum' => [ [ '::/64' ], 'min' ],
			'One /47 range with mt_rand always choosing end' => [ [ '::/47' ], 'max' ],
			'::/64 range with mt_rand always returning 0' => [ [ '::/64' ], 0 ],
		];
	}

	/** @dataProvider provideIncrementAndCheck */
	public function testIncrementAndCheck( $actionsPerformed, $actionsLeft, $expectedReturnValue ) {
		$objectUnderTest = $this->setUpObjectUnderTest();
		// T287318 - TestingAccessWrapper::__call does not support pass-by-reference
		$classReflection = new ReflectionClass( $objectUnderTest->object );
		$methodReflection = $classReflection->getMethod( 'incrementAndCheck' );
		$methodReflection->setAccessible( true );
		$this->assertSame(
			$expectedReturnValue,
			$methodReflection->invokeArgs( $objectUnderTest->object, [ &$actionsPerformed, $actionsLeft ] ),
			'Expected return value of ::incrementAndCheck not found.'
		);
	}

	public static function provideIncrementAndCheck() {
		return [
			'No actions performed with 5 left' => [ 0, 5, true ],
			'1 action performed with 3 left' => [ 1, 3, true ],
			'2 actions performed with 3 left' => [ 2, 3, false ],
			'3 actions performed with 3 left' => [ 3, 3, false ],
		];
	}

	/** @dataProvider provideApplyRemainderAction */
	public function testApplyRemainderAction(
		$actionsLeft, $remainderActions, $expectedActionsLeftAfterCall, $expectedRemainderActionsAfterCall
	) {
		$objectUnderTest = $this->setUpObjectUnderTest();
		// T287318 - TestingAccessWrapper::__call does not support pass-by-reference
		$classReflection = new ReflectionClass( $objectUnderTest->object );
		$methodReflection = $classReflection->getMethod( 'applyRemainderAction' );
		$methodReflection->setAccessible( true );
		$methodReflection->invokeArgs( $objectUnderTest->object, [ &$actionsLeft, &$remainderActions ] );
		$this->assertSame(
			$expectedActionsLeftAfterCall,
			$actionsLeft,
			'Actions left was not set correctly by ::applyRemainderAction'
		);
		$this->assertSame(
			$expectedRemainderActionsAfterCall,
			$remainderActions,
			'Remainder actions was not set correctly by ::applyRemainderAction'
		);
	}

	public static function provideApplyRemainderAction() {
		return [
			'No remainder actions' => [ 3, 0, 3, 0 ],
			'One remainder action' => [ 3, 1, 4, 0 ],
			'10 remainder actions' => [ 5, 10, 6, 9 ],
			'Negative remainder actions' => [ 2, -1, 2, -1 ],
		];
	}

	public function testGetPrefix() {
		$this->assertSame(
			'CheckUserSimulated-',
			$this->setUpObjectUnderTest()->getPrefix(),
			'Prefix was not as expected.'
		);
	}
}
