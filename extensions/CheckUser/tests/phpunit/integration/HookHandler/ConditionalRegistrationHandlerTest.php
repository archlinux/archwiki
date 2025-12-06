<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Api\ApiModuleManager;
use MediaWiki\CheckUser\HookHandler\ConditionalRegistrationHandler;
use MediaWiki\Config\Config;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\ConditionalRegistrationHandler
 */
class ConditionalRegistrationHandlerTest extends MediaWikiIntegrationTestCase {
	private Config $config;
	private TempUserConfig $tempUserConfig;
	private ExtensionRegistry $extensionRegistry;
	private ConditionalRegistrationHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock( Config::class );
		$this->tempUserConfig = $this->createMock( TempUserConfig::class );
		$this->extensionRegistry = $this->createMock( ExtensionRegistry::class );

		$this->handler = new ConditionalRegistrationHandler(
			$this->config,
			$this->tempUserConfig,
			$this->extensionRegistry
		);
	}

	public function testShouldDoNothingWhenTempUsersAreNotKnown(): void {
		$this->tempUserConfig->method( 'isKnown' )
			->willReturn( false );

		$list = [];

		$this->handler->onSpecialPage_initList( $list );

		$this->assertSame( [], $list );
	}

	public function testShouldRegisterSpecialIPContributionsIfTempUsersAreKnown(): void {
		$this->tempUserConfig->method( 'isKnown' )
			->willReturn( true );

		$list = [];

		$this->handler->onSpecialPage_initList( $list );

		$this->assertArrayHasKey( 'IPContributions', $list );
	}

	/**
	 * @dataProvider provideRegisterSpecialGlobalContributions
	 */
	public function testRegisterSpecialGlobalContributions(
		$extensionsAreLoaded,
		$tempAccountsAreKnown,
		$centralWiki,
		$expectLoaded
	): void {
		$this->tempUserConfig->method( 'isKnown' )
			->willReturn( $tempAccountsAreKnown );
		$this->extensionRegistry->method( 'isLoaded' )
			->willReturn( $extensionsAreLoaded );

		$this->config->method( 'get' )
			->willReturn( $centralWiki );

		$list = [];

		$this->handler->onSpecialPage_initList( $list );

		if ( $expectLoaded ) {
			$this->assertArrayHasKey( 'GlobalContributions', $list );
		} else {
			$this->assertArrayNotHasKey( 'GlobalContributions', $list );
		}
	}

	public static function provideRegisterSpecialGlobalContributions(): array {
		return [
			'Page is added when dependencies are loaded and temp accounts are known' => [
				true, true, false, true,
			],
			'Page is added when dependencies are loaded and central wiki is defined' => [
				true, false, 'somewiki', true,
			],
			'Page is not added when temp accounts are unknown and central wiki is not defined' => [
				true, false, false, false,
			],
			'Page is not added when dependencies are not loaded' => [
				false, true, 'somewiki', false,
			],
		];
	}

	/**
	 * @dataProvider provideRegisterGlobalContributionsApi
	 */
	public function testRegisterGlobalContributionsApi(
		bool $extensionsAreLoaded,
		bool $isOnCentralWiki,
		bool $expectLoaded
	): void {
		$this->extensionRegistry->method( 'isLoaded' )
			->willReturn( $extensionsAreLoaded );
		$this->config->method( 'get' )
			->with( 'CheckUserGlobalContributionsCentralWikiId' )
			->willReturn( $isOnCentralWiki ? WikiMap::getCurrentWikiId() : false );

		if ( $expectLoaded ) {
			$moduleManager = $this->createMock( ApiModuleManager::class );
			$moduleManager->expects( $this->once() )
				->method( 'addModule' )
				->with( 'globalcontributions', 'list', $this->isType( 'array' ) );
		} else {
			$moduleManager = $this->createNoOpMock( ApiModuleManager::class );
		}

		$this->handler->onApiQuery__moduleManager( $moduleManager );
	}

	public static function provideRegisterGlobalContributionsApi(): iterable {
		yield 'module is added when dependencies are loaded and on central wiki' => [
			true, true, true,
		];
		yield 'module is not added when dependencies are not loaded' => [
			false, true, false,
		];
		yield 'module is not added when not on central wiki' => [
			true, false, false,
		];
	}

	/** @dataProvider provideSuggestedInvestigationsRegistered */
	public function testSuggestedInvestigationsRegistered(
		bool $enabled, bool $hidden, bool $siExpected
	) {
		// Normally we could switch these using methods from SuggestedInvestigationsTestTrait, but
		// these tests create mock of the config object, so we need to set the values this way.
		$this->config->method( 'get' )
			->willReturnCallback( static fn ( $key ) => match ( $key ) {
				'CheckUserSuggestedInvestigationsEnabled' => $enabled,
				'CheckUserSuggestedInvestigationsHidden' => $hidden,
				default => null,
			} );

		$list = [];
		$this->handler->onSpecialPage_initList( $list );

		if ( $siExpected ) {
			$this->assertArrayHasKey( 'SuggestedInvestigations', $list );
		} else {
			$this->assertArrayNotHasKey( 'SuggestedInvestigations', $list );
		}
	}

	public static function provideSuggestedInvestigationsRegistered() {
		return [
			'Feature disabled, not hidden' => [
				'enabled' => false,
				'hidden' => false,
				'siExpected' => false,
			],
			'Feature enabled, not hidden' => [
				'enabled' => true,
				'hidden' => false,
				'siExpected' => true,
			],
			'Feature disabled, hidden' => [
				'enabled' => false,
				'hidden' => true,
				'siExpected' => false,
			],
			'Feature enabled, hidden' => [
				'enabled' => true,
				'hidden' => true,
				'siExpected' => false,
			],
		];
	}
}
