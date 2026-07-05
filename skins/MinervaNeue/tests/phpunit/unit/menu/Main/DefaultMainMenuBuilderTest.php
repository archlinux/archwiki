<?php

namespace MediaWiki\Minerva;

use MediaWiki\Context\IContextSource;
use MediaWiki\Message\Message;
use MediaWiki\Minerva\Menu\Definitions;
use MediaWiki\Minerva\Menu\Main\DefaultMainMenuBuilder;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityUtils;

/**
 * These are tests for the hamburger menu.
 *
 * @group MinervaNeue
 * @coversDefaultClass \MediaWiki\Minerva\Menu\Main\DefaultMainMenuBuilder
 */
class DefaultMainMenuBuilderTest extends \MediaWikiUnitTestCase {
	private const PREFERENCES_ITEM = [
		'href' => '/wiki/Special:Preferences'
	];

	private const AUTH_PERSONAL_TOOLS = [
		'createaccount' => [ 'href' => '/wiki/Special:CreateAccount',
			'text' => 'Create Account',
		],
		'login' => [ 'href' => '/wiki/Special:Login',
			'text' => 'Login',
		],
	];

	private function makeBuilder(
		bool $isRegistered = true,
		bool $isPersonalModeEnabled = false,
		bool $showMobileOptions = true,
		bool $showDonateLink = true
	) {
		$title = $this->createMock( Title::class );
		$userMock = $this->createMock( User::class );
		$identityToolsMock = $this->createMock( UserIdentityUtils::class );
		$specialPageMock = $this->createMock( Title::class );
		$factoryMock = $this->createMock( SpecialPageFactory::class );
		$contextMock = $this->createMock( IContextSource::class );
		$messageMock = $this->createMock( Message::class );
		$registryMock = $this->createMock( ExtensionRegistry::class );
		$specialPageMock->expects( $this->any() )
			->method( 'getLocalURL' )
			->willReturn( '/wiki/Special:DummyTest' );
		$factoryMock->expects( $this->any() )
			->method( 'getTitleForAlias' )
			->willReturn( $specialPageMock );
		$messageMock->expects( $this->any() )
			->method( 'text' )
			->willReturn( 'msg-text' );
		$contextMock->expects( $this->any() )
			->method( 'msg' )
			->willReturn( $messageMock );
		$contextMock->expects( $this->once() )
			->method( 'getUser' )
			->willReturn( $userMock );
		$contextMock->expects( $this->any() )
			->method( 'getTitle' )
			->willReturn( $title );
		$userMock->expects( $this->any() )
			->method( 'isRegistered' )
			->willReturn( $isRegistered );
		$registryMock->method( 'isLoaded' )->willReturn( true );

		$definitions = new Definitions( $factoryMock, $registryMock );
		$definitions->setContext( $contextMock );
		return new DefaultMainMenuBuilder(
			$showMobileOptions,
			$showDonateLink,
			$userMock,
			$definitions,
			$identityToolsMock,
			$isPersonalModeEnabled
		);
	}

	/**
	 * @covers ::getPersonalToolsGroup
	 */
	public function testGetPersonalToolsGroupWithPersonalModeAnon() {
		$builder = $this->makeBuilder(
			// anon
			false,
			// $wgMinervaPersonalMenu = true
			true
		);
		$group = $builder->getPersonalToolsGroup( [], false );
		$personalToolsGroup = $builder->getPersonalToolsGroup( [
			'preferences' => self::PREFERENCES_ITEM,
		], false );
		$settingsGroup = $builder->getSettingsGroup();
		$this->assertSame( 'settings', $settingsGroup->getEntries()[0]['name'],
			'If logged out and personal tools is enabled, the mobile options page should be shown' );
		$this->assertCount( 0, $personalToolsGroup->getEntries(),
			'personal tools group should be empty' );
	}

	/**
	 * @covers ::getPersonalToolsGroup
	 */
	public function testGetPersonalToolsGroupWithPersonalModeRegistered() {
		$builder = $this->makeBuilder(
			// registered user.
			true,
			// $wgMinervaPersonalMenu = true
			true
		);
		$personalToolsGroup = $builder->getPersonalToolsGroup( [
			'preferences' => self::PREFERENCES_ITEM,
		], false );
		$settingsGroup = $builder->getSettingsGroup();
		$this->assertSame( 'settings', $settingsGroup->getEntries()[0]['name'] );
		$this->assertCount( 0, $personalToolsGroup->getEntries(),
			'personal group should be empty - handled in separate menu' );
	}

	/**
	 * @covers ::getPersonalToolsGroup
	 */
	public function testGetPersonalToolsGroupWithoutPersonalModeAnon() {
		$builder = $this->makeBuilder(
			// anon user.
			false,
			// $wgMinervaPersonalMenu = false
			false
		);
		$personalToolsGroup = $builder->getPersonalToolsGroup( [
			'preferences' => self::PREFERENCES_ITEM,
		], false );
		$settingsGroup = $builder->getSettingsGroup();

		$this->assertCount( 0, $personalToolsGroup->getEntries(),
			'personal tools group is empty if MinervaPersonalMenu is disabled.' );
		$this->assertCount( 1, $settingsGroup->getEntries(),
			'the settings has its own dedicated menu.' );
	}

	/**
	 * @covers ::getPersonalToolsGroup
	 */
	public function testGetPersonalToolsGroupWithoutPersonalModeRegistered() {
		$builder = $this->makeBuilder(
			// registered user
			true,
			// $wgMinervaPersonalMenu = false
			false
		);
		$personalToolsGroup = $builder->getPersonalToolsGroup( [
			'preferences' => self::PREFERENCES_ITEM
		], false );
		$settingsGroup = $builder->getSettingsGroup();

		$this->assertCount( 1, $personalToolsGroup->getEntries(),
			'for logged in users the `settings` option replaces the `preferences` option' );
		$this->assertCount( 0, $settingsGroup->getEntries(),
			'the settings menu is empty  in this case.' );
	}

	/**
	 * @covers ::getPersonalToolsGroup
	 */
	public function testGetPersonalToolsGroupLoggedOutWhenShouldShowAccountMenuItems() {
		$builder = $this->makeBuilder( false, false, true, true );
		$personalToolsGroup = $builder->getPersonalToolsGroup( self::AUTH_PERSONAL_TOOLS, true );

		$this->assertCount( 2, $personalToolsGroup->getEntries(),
		'personal tools group should include two entries' );
		$this->assertSame( 'createaccount',
			$personalToolsGroup->getEntries()[0]['name'] );
		$this->assertSame( 'login',
			$personalToolsGroup->getEntries()[1]['name'] );
	}
}
