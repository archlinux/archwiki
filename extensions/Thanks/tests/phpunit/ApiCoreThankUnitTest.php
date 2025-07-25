<?php

use MediaWiki\Api\ApiMain;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\UserBlockTarget;
use MediaWiki\Extension\Thanks\Api\ApiCoreThank;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;

/**
 * Unit tests for the Thanks API module
 *
 * @group Thanks
 * @group API
 * @group Database
 *
 * @author Addshore
 */
class ApiCoreThankUnitTest extends ApiTestCase {

	protected function getModule() {
		$services = $this->getServiceContainer();
		return new ApiCoreThank(
			new ApiMain(),
			'thank',
			$services->getPermissionManager(),
			$services->getService( 'ThanksLogStore' ),
			$services->getNotificationService(),
			$services->getRevisionStore(),
			$services->getUserFactory()
		);
	}

	private static function makeBlockParams( $options ) {
		$options = array_merge( [
			'target' => new UserBlockTarget( new UserIdentityValue( 2, 'Test user' ) ),
			'by' => new UserIdentityValue( 1, 'TestUser' ),
			'reason' => __METHOD__,
			'timestamp' => wfTimestamp( TS_MW ),
			'expiry' => 'infinity',
		], $options );
		return $options;
	}

	/**
	 * @dataProvider provideDieOnBadUser
	 * @covers \MediaWiki\Extension\Thanks\Api\ApiThank::dieOnBadUser
	 * @covers \MediaWiki\Extension\Thanks\Api\ApiThank::dieOnUserBlockedFromThanks
	 */
	public function testDieOnBadUser(
		$mockisNamed,
		$mockPingLimited,
		$mockBlockParams,
		$dieMethod,
		$expectedError
	) {
		$user = $this->createMock( User::class );
		if ( $mockisNamed !== null ) {
			$user->expects( $this->once() )
				->method( 'isNamed' )
				->willReturn( $mockisNamed );
		}
		if ( $mockPingLimited !== null ) {
			$user->expects( $this->once() )
				->method( 'pingLimiter' )
				->willReturn( $mockPingLimited );
		}
		if ( $mockBlockParams !== null ) {
			$mockBlock = new DatabaseBlock( $mockBlockParams );
			$user->expects( $this->once() )
				->method( 'getBlock' )
				->willReturn( $mockBlock );
		}

		$module = $this->getModule();
		$method = new ReflectionMethod( $module, $dieMethod );
		$method->setAccessible( true );

		if ( $expectedError ) {
			$this->expectApiErrorCode( $expectedError );
		}

		$method->invoke( $module, $user );
		// perhaps the method should return true.. For now we must do this
		$this->assertTrue( true );
	}

	public static function provideDieOnBadUser() {
		return [
			'anon' => [
				false,
				null,
				null,
				'dieOnBadUser',
				'notloggedin'
			],
			'ping' => [
				true,
				true,
				null,
				'dieOnBadUser',
				'ratelimited'
			],
			'sitewide blocked' => [
				null,
				null,
				self::makeBlockParams( [] ),
				'dieOnUserBlockedFromThanks',
				'blocked'
			],
			'partial blocked' => [
				null,
				null,
				self::makeBlockParams( [ 'sitewide' => false ] ),
				'dieOnUserBlockedFromThanks',
				false
			],
		];
	}

	// @todo test userAlreadySentThanksForRevision
	// @todo test getRevisionFromParams
	// @todo test getTitleFromRevision
	// @todo test getSourceFromParams
	// @todo test getUserIdFromRevision
	// @todo test markResultSuccess
	// @todo test sendThanks

}
