<?php

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Extension\Thanks\ApiCoreThank;
use MediaWiki\User\UserIdentityValue;

/**
 * Unit tests for the Thanks API module
 *
 * @group Thanks
 * @group API
 *
 * @author Addshore
 */
class ApiCoreThankUnitTest extends MediaWikiIntegrationTestCase {

	protected function getModule() {
		return new ApiCoreThank( new ApiMain(), 'thank' );
	}

	private function createBlock( $options ) {
		$options = array_merge( [
			'address' => 'Test user',
			'by' => new UserIdentityValue( 1, 'TestUser' ),
			'reason' => __METHOD__,
			'timestamp' => wfTimestamp( TS_MW ),
			'expiry' => 'infinity',
		], $options );
		return new DatabaseBlock( $options );
	}

	/**
	 * @dataProvider provideDieOnBadUser
	 * @covers \MediaWiki\Extension\Thanks\ApiThank::dieOnBadUser
	 * @covers \MediaWiki\Extension\Thanks\ApiThank::dieOnUserBlockedFromThanks
	 */
	public function testDieOnBadUser( $user, $dieMethod, $expectedError ) {
		$module = $this->getModule();
		$method = new ReflectionMethod( $module, $dieMethod );
		$method->setAccessible( true );

		if ( $expectedError ) {
			$this->expectException( ApiUsageException::class );
			$this->expectExceptionMessage( $expectedError );
		}

		$method->invoke( $module, $user );
		// perhaps the method should return true.. For now we must do this
		$this->assertTrue( true );
	}

	public function provideDieOnBadUser() {
		$testCases = [];

		$mockUser = $this->createMock( User::class );
		$mockUser->expects( $this->once() )
			->method( 'isAnon' )
			->willReturn( true );

		$testCases[ 'anon' ] = [
			$mockUser,
			'dieOnBadUser',
			'Anonymous users cannot send thanks'
		];

		$mockUser = $this->createMock( User::class );
		$mockUser->expects( $this->once() )
			->method( 'isAnon' )
			->willReturn( false );
		$mockUser->expects( $this->once() )
			->method( 'pingLimiter' )
			->willReturn( true );

		$testCases[ 'ping' ] = [
			$mockUser,
			'dieOnBadUser',
			"You've exceeded your rate limit. Please wait some time and try again"
		];

		$mockUser = $this->createMock( User::class );
		$mockUser->expects( $this->once() )
			->method( 'isAnon' )
			->willReturn( false );
		$mockUser->expects( $this->once() )
			->method( 'pingLimiter' )
			->willReturn( false );

		$mockUser = $this->createMock( User::class );
		$mockUser->expects( $this->once() )
			->method( 'getBlock' )
			->willReturn( $this->createBlock( [] ) );

		$testCases[ 'sitewide blocked' ] = [
			$mockUser,
			'dieOnUserBlockedFromThanks',
			'You have been blocked from editing'
		];

		$mockUser = $this->createMock( User::class );
		$mockUser->expects( $this->once() )
			->method( 'getBlock' )
			->willReturn(
				$this->createBlock( [ 'sitewide' => false ] )
			);

		$testCases[ 'partial blocked' ] = [
			$mockUser,
			'dieOnUserBlockedFromThanks',
			false
		];

		return $testCases;
	}

	// @todo test userAlreadySentThanksForRevision
	// @todo test getRevisionFromParams
	// @todo test getTitleFromRevision
	// @todo test getSourceFromParams
	// @todo test getUserIdFromRevision
	// @todo test markResultSuccess
	// @todo test sendThanks

}
