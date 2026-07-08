<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CheckUser\HookHandler\UserInfoCardVariablesHandler;
use MediaWiki\Output\OutputPage;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\UserInfoCardVariablesHandler
 */
class UserInfoCardVariablesHandlerTest extends MediaWikiUnitTestCase {
	private UserOptionsLookup $optionsMock;
	private HashConfig $configMock;
	private OutputPage $outputMock;

	private UserInfoCardVariablesHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->optionsMock = $this->createMock( UserOptionsLookup::class );

		$this->configMock = new HashConfig();
		$this->configMock->set( 'CheckUserEnableUserInfoCardInstrumentation', true );
		$this->configMock->set( 'CheckUserUserInfoCardShowXToolsLink', false );

		$userMock = $this->createMock( User::class );

		$this->outputMock = $this->createMock( OutputPage::class );
		$this->outputMock->method( 'getConfig' )->willReturn( $this->configMock );
		$this->outputMock->method( 'getUser' )->willReturn( $userMock );

		$this->handler = new UserInfoCardVariablesHandler( $this->optionsMock );
	}

	public function testSkipIfDisabled() {
		$this->optionsMock->expects( $this->once() )
			->method( 'getBoolOption' )
			->willReturn( false );

		$vars = [];
		$this->handler->onMakeGlobalVariablesScript( $vars, $this->outputMock );

		$this->assertArrayEquals( [], $vars );
	}

	public function testCheckUser() {
		$this->optionsMock->expects( $this->once() )
			->method( 'getBoolOption' )
			->willReturn( true );

		$vars = [];
		$this->handler->onMakeGlobalVariablesScript( $vars, $this->outputMock );

		$this->assertArrayEquals( [
			'wgCheckUserEnableUserInfoCardInstrumentation' => true,
			'wgCheckUserUserInfoCardShowXToolsLink' => false,
		], $vars );
	}

	public function testGrowthExperiments() {
		$this->optionsMock->expects( $this->once() )
			->method( 'getBoolOption' )
			->willReturn( true );

		$this->configMock->set( 'GEUserImpactMaxEdits', 1234 );
		$this->configMock->set( 'GEUserImpactMaxThanks', 4321 );

		$vars = [];
		$this->handler->onMakeGlobalVariablesScript( $vars, $this->outputMock );

		$this->assertArrayEquals( [
			'wgCheckUserGEUserImpactMaxEdits' => 1234,
			'wgCheckUserGEUserImpactMaxThanks' => 4321,
			'wgCheckUserEnableUserInfoCardInstrumentation' => true,
			'wgCheckUserUserInfoCardShowXToolsLink' => false,
		], $vars );
	}
}
