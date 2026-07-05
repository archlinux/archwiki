<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthGlobalUserLockStatusChangedHook;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnGlobalLockHandler;
// phpcs:ignore Generic.Files.LineLength
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnGlobalLockHandler
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\AbstractSuggestedInvestigationsAutoCloseHandler
 * @group CheckUser
 */
class SuggestedInvestigationsAutoCloseOnGlobalLockHandlerTest extends MediaWikiUnitTestCase {

	private SuggestedInvestigationsCaseLookupService&MockObject $caseLookup;
	private JobQueueGroup&MockObject $jobQueueGroup;
	private UserIdentityLookup&MockObject $userIdentityLookup;
	private SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher&MockObject $crossWikiJobDispatcher;
	private SuggestedInvestigationsAutoCloseOnGlobalLockHandler $handler;

	/**
	 * @return void
	 */
	private function mockGetUserIdentityByName( ?UserIdentityValue $user ): void {
		$this->userIdentityLookup->expects( $this->once() )
			->method( 'getUserIdentityByName' )
			->with( 'TestUser' )
			->willReturn( $user );
	}

	protected function setUp(): void {
		parent::setUp();

		if ( !interface_exists( CentralAuthGlobalUserLockStatusChangedHook::class ) ) {
			$this->markTestSkipped( 'CentralAuth extension is not available' );
		}

		$this->caseLookup = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$this->jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$this->userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$this->crossWikiJobDispatcher = $this->createMock(
			SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher::class
		);
		$this->handler = new SuggestedInvestigationsAutoCloseOnGlobalLockHandler(
			$this->caseLookup,
			$this->jobQueueGroup,
			new NullLogger(),
			$this->userIdentityLookup,
			$this->crossWikiJobDispatcher
		);
	}

	public static function provideEarlyReturnCases(): iterable {
		yield 'Suggested Investigations feature disabled' => [
			'isLocked' => true,
			'isExtensionEnabled' => false,
			'localUserExists' => true,
			'localUserRegistered' => true,
			'expectsDispatch' => false,
		];
		yield 'local user not found' => [
			'isLocked' => true,
			'isExtensionEnabled' => true,
			'localUserExists' => false,
			'localUserRegistered' => false,
			'expectsDispatch' => true,
		];
		yield 'local user not registered' => [
			'isLocked' => true,
			'isExtensionEnabled' => true,
			'localUserExists' => true,
			'localUserRegistered' => false,
			'expectsDispatch' => true,
		];
		yield 'user is unlocked (isLocked=false)' => [
			'isLocked' => false,
			'isExtensionEnabled' => true,
			'localUserExists' => true,
			'localUserRegistered' => true,
			'expectsDispatch' => false,
		];
	}

	/**
	 * @dataProvider provideEarlyReturnCases
	 */
	public function testEarlyReturn(
		bool $isLocked,
		bool $isExtensionEnabled,
		bool $localUserExists,
		bool $localUserRegistered,
		bool $expectsDispatch
	): void {
		$this->caseLookup->expects( $this->never() )
			->method( 'getOpenCaseIdsForUser' );
		$this->jobQueueGroup->expects( $this->never() )
			->method( 'lazyPush' );
		$this->crossWikiJobDispatcher->expects( $expectsDispatch ? $this->once() : $this->never() )
			->method( 'dispatch' )
			->with( 'TestUser' );

		if ( $isLocked ) {
			$this->mockSuggestedInvestigationEnabled( $isExtensionEnabled );

			if ( $isExtensionEnabled ) {
				$localUser = null;
				if ( $localUserExists ) {
					$localUser = new UserIdentityValue( $localUserRegistered ? 1 : 0, 'TestUser' );
				}
				$this->mockGetUserIdentityByName( $localUser );
			} else {
				$this->userIdentityLookup->expects( $this->never() )
					->method( 'getUserIdentityByName' );
			}
		} else {
			$this->caseLookup->expects( $this->never() )
				->method( 'areSuggestedInvestigationsEnabled' );
			$this->userIdentityLookup->expects( $this->never() )
				->method( 'getUserIdentityByName' );
		}

		$this->handler->onCentralAuthGlobalUserLockStatusChanged(
			$this->getCentralAuthUserMock( 'TestUser' ),
			$isLocked
		);
	}

	private function getCentralAuthUserMock( string $name ): CentralAuthUser {
		$centralAuthUser = $this->createMock( CentralAuthUser::class );
		$centralAuthUser->expects( $this->any() )
			->method( 'getName' )
			->willReturn( $name );

		return $centralAuthUser;
	}

	private function mockSuggestedInvestigationEnabled( bool $enabled ): void {
		$this->caseLookup->expects( $this->once() )
			->method( 'areSuggestedInvestigationsEnabled' )
			->willReturn( $enabled );
	}
}
