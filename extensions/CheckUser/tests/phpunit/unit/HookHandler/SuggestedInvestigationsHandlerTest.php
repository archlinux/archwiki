<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\SuggestedInvestigationsHandler;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\SuggestedInvestigationsHandler
 */
class SuggestedInvestigationsHandlerTest extends MediaWikiUnitTestCase {

	private SuggestedInvestigationsSignalMatchService $suggestedInvestigationsSignalMatchService;
	private SuggestedInvestigationsHandler $sut;

	protected function setUp(): void {
		parent::setUp();

		$this->suggestedInvestigationsSignalMatchService = $this->createMock(
			SuggestedInvestigationsSignalMatchService::class
		);

		$this->sut = new SuggestedInvestigationsHandler( $this->suggestedInvestigationsSignalMatchService );
	}

	/** @dataProvider provideOnLocalUserCreated */
	public function testOnLocalUserCreated( bool $autocreated, string $expectedEventType ) {
		$mockUser = $this->createMock( User::class );

		$this->suggestedInvestigationsSignalMatchService->expects( $this->once() )
			->method( 'matchSignalsAgainstUser' )
			->with( $mockUser, $expectedEventType );

		$this->sut->onLocalUserCreated( $mockUser, $autocreated );
		DeferredUpdates::doUpdates();
	}

	public static function provideOnLocalUserCreated(): array {
		return [
			'User is autocreated' => [ true, 'autocreateaccount' ],
			'User is not autocreated' => [ false, 'createaccount' ],
		];
	}

	public function testOnUserSetEmail() {
		$mockUser = $this->createMock( User::class );

		$this->suggestedInvestigationsSignalMatchService->expects( $this->once() )
			->method( 'matchSignalsAgainstUser' )
			->with( $mockUser, 'setemail' );

		$email = 'test@test.com';
		$this->sut->onUserSetEmail( $mockUser, $email );
		DeferredUpdates::doUpdates();
	}

	public function testOnUserSetEmailAuthenticationTimestamp() {
		$mockUser = $this->createMock( User::class );

		$this->suggestedInvestigationsSignalMatchService->expects( $this->once() )
			->method( 'matchSignalsAgainstUser' )
			->with( $mockUser, 'confirmemail' );

		$timestamp = '20250405060708';
		$this->sut->onUserSetEmailAuthenticationTimestamp( $mockUser, $timestamp );
		DeferredUpdates::doUpdates();
	}
}
