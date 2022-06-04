<?php
/*
 * @file
 * @ingroup skins
 */

namespace MediaWiki\Skins\Vector\Tests\Integration;

use HashConfig;
use HTMLForm;
use MediaWiki\User\UserOptionsManager;
use MediaWikiIntegrationTestCase;
use ReflectionMethod;
use ResourceLoaderContext;
use RuntimeException;
use Title;
use User;
use Vector\Constants;
use Vector\FeatureManagement\FeatureManager;
use Vector\Hooks;
use Vector\HTMLForm\Fields\HTMLLegacySkinVersionField;
use Vector\SkinVector;
use Vector\SkinVector22;

/**
 * Integration tests for Vector Hooks.
 *
 * @group Vector
 * @coversDefaultClass \Vector\Hooks
 */
class VectorHooksTest extends MediaWikiIntegrationTestCase {
	private const HIDE_IF = [ '!==', 'skin', Constants::SKIN_NAME_LEGACY ];

	private const SKIN_PREFS_SECTION = 'rendering/skin/skin-prefs';

	/**
	 * @param bool $excludeMainPage
	 * @param array $excludeNamespaces
	 * @param array $include
	 * @param array $querystring
	 * @return array
	 */
	private static function makeMaxWidthConfig(
		$excludeMainPage,
		$excludeNamespaces = [],
		$include = [],
		$querystring = []
	) {
		return [
			'exclude' => [
				'mainpage' => $excludeMainPage,
				'namespaces' => $excludeNamespaces,
				'querystring' => $querystring,
			],
			'include' => $include
		];
	}

	public function provideGetVectorResourceLoaderConfig() {
		return [
			[
				[
					'VectorWebABTestEnrollment' => [],
					'VectorSearchHost' => 'en.wikipedia.org'
				],
				[
					'wgVectorSearchHost' => 'en.wikipedia.org',
					'wgVectorWebABTestEnrollment' => [],
				]
			],
			[
				[
					'VectorWebABTestEnrollment' => [
						'name' => 'vector.sticky_header',
						'enabled' => true,
						'buckets' => [
								'unsampled' => [
										'samplingRate' => 1,
								],
								'control' => [
										'samplingRate' => 0
								],
								'stickyHeaderEnabled' => [
										'samplingRate' => 0
								],
								'stickyHeaderDisabled' => [
										'samplingRate' => 0
								],
						],
					],
					'VectorSearchHost' => 'en.wikipedia.org'
				],
				[
					'wgVectorSearchHost' => 'en.wikipedia.org',
					'wgVectorWebABTestEnrollment' => [
						'name' => 'vector.sticky_header',
						'enabled' => true,
						'buckets' => [
								'unsampled' => [
										'samplingRate' => 1,
								],
								'control' => [
										'samplingRate' => 0
								],
								'stickyHeaderEnabled' => [
										'samplingRate' => 0
								],
								'stickyHeaderDisabled' => [
										'samplingRate' => 0
								],
						],
					],
				]
			],
		];
	}

	public function provideGetVectorResourceLoaderConfigWithExceptions() {
		return [
			# Bad experiment (no buckets)
			[
				[
					'VectorSearchHost' => 'en.wikipedia.org',
					'VectorWebABTestEnrollment' => [
						'name' => 'vector.sticky_header',
						'enabled' => true,
					],
				]
			],
			# Bad experiment (no unsampled bucket)
			[
				[
					'VectorSearchHost' => 'en.wikipedia.org',
					'VectorWebABTestEnrollment' => [
						'name' => 'vector.sticky_header',
						'enabled' => true,
						'buckets' => [
							'a' => [
								'samplingRate' => 0
							],
						]
					],
				]
			],
			# Bad experiment (wrong format)
			[
				[
					'VectorSearchHost' => 'en.wikipedia.org',
					'VectorWebABTestEnrollment' => [
						'name' => 'vector.sticky_header',
						'enabled' => true,
						'buckets' => [
							'unsampled' => 1,
						]
					],
				]
			],
			# Bad experiment (samplingRate defined as string)
			[
				[
					'VectorSearchHost' => 'en.wikipedia.org',
					'VectorWebABTestEnrollment' => [
						'name' => 'vector.sticky_header',
						'enabled' => true,
						'buckets' => [
							'unsampled' => [
								'samplingRate' => '1'
							],
						]
					],
				]
			],
		];
	}

	/**
	 * @covers ::shouldDisableMaxWidth
	 */
	public function providerShouldDisableMaxWidth() {
		$excludeTalkFooConfig = self::makeMaxWidthConfig(
			false,
			[ NS_TALK ],
			[ 'Talk:Foo' ],
			[]
		);

		return [
			[
				'No options, nothing disables max width',
				[],
				Title::makeTitle( NS_MAIN, 'Foo' ),
				[],
				false
			],
			[
				'Main page disables max width if exclude.mainpage set',
				self::makeMaxWidthConfig( true ),
				Title::newMainPage(),
				[],
				true
			],
			[
				'Namespaces can be excluded',
				self::makeMaxWidthConfig( false, [ NS_CATEGORY ] ),
				Title::makeTitle( NS_CATEGORY, 'Category' ),
				[],
				true
			],
			[
				'Namespaces are included if not excluded',
				self::makeMaxWidthConfig( false, [ NS_CATEGORY ] ),
				Title::makeTitle( NS_SPECIAL, 'SpecialPages' ),
				[],
				false
			],
			[
				'More than one namespace can be included',
				self::makeMaxWidthConfig( false, [ NS_CATEGORY, NS_SPECIAL ] ),
				Title::makeTitle( NS_SPECIAL, 'Specialpages' ),
				[],
				true
			],
			[
				'Can be disabled on history page',
				self::makeMaxWidthConfig(
					false,
					[
						/* no namespaces excluded */
					],
					[
						/* no includes */
					],
					[ 'action' => 'history' ]
				),
				Title::makeTitle( NS_MAIN, 'History page' ),
				[ 'action' => 'history' ],
				true
			],
			[
				'Include can override exclusions',
				self::makeMaxWidthConfig(
					false,
					[ NS_CATEGORY, NS_SPECIAL ],
					[ 'Special:Specialpages' ],
					[ 'action' => 'history' ]
				),
				Title::makeTitle( NS_SPECIAL, 'Specialpages' ),
				[ 'action' => 'history' ],
				false
			],
			[
				'Max width can be disabled on talk pages',
				$excludeTalkFooConfig,
				Title::makeTitle( NS_TALK, 'A talk page' ),
				[],
				true
			],
			[
				'includes can be used to override any page in a disabled namespace',
				$excludeTalkFooConfig,
				Title::makeTitle( NS_TALK, 'Foo' ),
				[],
				false
			],
			[
				'Excludes/includes are based on root title so should apply to subpages',
				$excludeTalkFooConfig,
				Title::makeTitle( NS_TALK, 'Foo/subpage' ),
				[],
				false
			]
		];
	}

	/**
	 * @covers ::shouldDisableMaxWidth
	 * @dataProvider providerShouldDisableMaxWidth
	 */
	public function testShouldDisableMaxWidth(
		$msg,
		$options,
		$title,
		$requestValues,
		$shouldDisableMaxWidth
	) {
		$this->assertSame(
			$shouldDisableMaxWidth,
			Hooks::shouldDisableMaxWidth( $options, $title, $requestValues ),
			$msg
		);
	}

	/**
	 * @covers ::onGetPreferences
	 */
	public function testOnGetPreferencesShowPreferencesDisabled() {
		$config = new HashConfig( [
			'VectorSkinMigrationMode' => false,
			'VectorShowSkinPreferences' => false,
		] );
		$this->setService( 'Vector.Config', $config );

		$prefs = [];
		Hooks::onGetPreferences( $this->getTestUser()->getUser(), $prefs );
		$this->assertSame( [], $prefs, 'No preferences are added.' );
	}

	private function setFeatureLatestSkinVersionIsEnabled( $isEnabled ) {
		$featureManager = new FeatureManager();
		$featureManager->registerSimpleRequirement( Constants::REQUIREMENT_LATEST_SKIN_VERSION, $isEnabled );
		$featureManager->registerFeature( Constants::FEATURE_LATEST_SKIN, [
			Constants::REQUIREMENT_LATEST_SKIN_VERSION
		] );

		$this->setService( Constants::SERVICE_FEATURE_MANAGER, $featureManager );
	}

	/**
	 * @covers ::onGetPreferences
	 */
	public function testOnGetPreferencesShowPreferencesEnabledSkinSectionFoundLegacy() {
		$isLegacy = true;
		$this->setFeatureLatestSkinVersionIsEnabled( !$isLegacy );
		$config = new HashConfig( [
			'VectorSkinMigrationMode' => false,
			'VectorShowSkinPreferences' => true,
			'VectorDefaultSidebarVisibleForAuthorisedUser' => true,
		] );
		$this->setService( 'Vector.Config', $config );

		$prefs = [
			'foo' => [],
			'skin' => [],
			'bar' => []
		];
		Hooks::onGetPreferences( $this->getTestUser()->getUser(), $prefs );
		$this->assertEquals(
			[
				'foo' => [],
				'skin' => [],
				'VectorSkinVersion' => [
					'class' => HTMLLegacySkinVersionField::class,
					'label-message' => 'prefs-vector-enable-vector-1-label',
					'help-message' => 'prefs-vector-enable-vector-1-help',
					'section' => self::SKIN_PREFS_SECTION,
					'default' => $isLegacy,
					'hide-if' => self::HIDE_IF,
				],
				'VectorSidebarVisible' => [
					'type' => 'api',
					'default' => true
				],
				'bar' => [],
			],
			$prefs,
			'Preferences are inserted directly after skin.'
		);
	}

	/**
	 * @covers ::getVectorResourceLoaderConfig
	 * @dataProvider provideGetVectorResourceLoaderConfig
	 */
	public function testGetVectorResourceLoaderConfig( $configData, $expected ) {
		$config = new HashConfig( $configData );
		$vectorConfig = Hooks::getVectorResourceLoaderConfig(
			$this->createMock( ResourceLoaderContext::class ),
			$config
		);

		$this->assertSame(
			$vectorConfig,
			$expected
		);
	}

	/**
	 * @covers ::getVectorResourceLoaderConfig
	 * @dataProvider provideGetVectorResourceLoaderConfigWithExceptions
	 */
	public function testGetVectorResourceLoaderConfigWithExceptions( $configData ) {
		$config = new HashConfig( $configData );
		$this->expectException( RuntimeException::class );
		$vectorConfig = Hooks::getVectorResourceLoaderConfig(
			$this->createMock( ResourceLoaderContext::class ),
			$config
		);
	}

	/**
	 * @covers ::onGetPreferences
	 */
	public function testOnGetPreferencesShowPreferencesEnabledSkinSectionMissingLegacy() {
		$isLegacy = false;
		$this->setFeatureLatestSkinVersionIsEnabled( !$isLegacy );
		$config = new HashConfig( [
			'VectorSkinMigrationMode' => false,
			'VectorDefaultSidebarVisibleForAuthorisedUser' => true,
			'VectorShowSkinPreferences' => true,
		] );
		$this->setService( 'Vector.Config', $config );

		$prefs = [
			'foo' => [],
			'bar' => []
		];
		Hooks::onGetPreferences( $this->getTestUser()->getUser(), $prefs );
		$this->assertEquals(
			[
				'foo' => [],
				'bar' => [],
				'VectorSkinVersion' => [
					'class' => HTMLLegacySkinVersionField::class,
					'label-message' => 'prefs-vector-enable-vector-1-label',
					'help-message' => 'prefs-vector-enable-vector-1-help',
					'section' => self::SKIN_PREFS_SECTION,
					'default' => $isLegacy,
					'hide-if' => self::HIDE_IF,
				],
				'VectorSidebarVisible' => [
					'type' => 'api',
					'default' => true
				],
			],
			$prefs,
			'Preferences are appended.'
		);
	}

	/**
	 * @covers ::onPreferencesFormPreSave
	 */
	public function testOnPreferencesFormPreSaveVectorEnabledLegacyNewPreference() {
		$formData = [
			'skin' => 'vector',
			'VectorSkinVersion' => Constants::SKIN_VERSION_LEGACY,
		];
		$form = $this->createMock( HTMLForm::class );
		$user = $this->createMock( User::class );
		$userOptionsManager = $this->createMock( UserOptionsManager::class );
		$userOptionsManager->expects( $this->never() )
			->method( 'setOption' );
		$this->setService( 'UserOptionsManager', $userOptionsManager );
		$result = true;
		$oldPreferences = [];

		Hooks::onPreferencesFormPreSave( $formData, $form, $user, $result, $oldPreferences );
	}

	/**
	 * @covers ::onPreferencesFormPreSave
	 */
	public function testOnPreferencesFormPreSaveVectorDisabledNoOldPreference() {
		$formData = [
			'VectorSkinVersion' => Constants::SKIN_VERSION_LATEST,
		];
		$form = $this->createMock( HTMLForm::class );
		$user = $this->createMock( User::class );
		$userOptionsManager = $this->createMock( UserOptionsManager::class );
		$userOptionsManager->expects( $this->never() )
			->method( 'setOption' );
		$this->setService( 'UserOptionsManager', $userOptionsManager );
		$result = true;
		$oldPreferences = [];

		Hooks::onPreferencesFormPreSave( $formData, $form, $user, $result, $oldPreferences );
	}

	/**
	 * @covers ::onPreferencesFormPreSave
	 */
	public function testOnPreferencesFormPreSaveVectorDisabledOldPreference() {
		$formData = [
			'VectorSkinVersion' => Constants::SKIN_VERSION_LATEST,
		];
		$config = new HashConfig( [
			'VectorSkinMigrationMode' => false,
			'VectorShowSkinPreferences' => false,
		] );
		$form = $this->createMock( HTMLForm::class );
		$user = $this->createMock( User::class );
		$userOptionsManager = $this->createMock( UserOptionsManager::class );
		$userOptionsManager->expects( $this->once() )
			->method( 'setOption' )
			->with( $user, 'VectorSkinVersion', 'old' );
		$this->setService( 'Vector.Config', $config );
		$this->setService( 'UserOptionsManager', $userOptionsManager );
		$result = true;
		$oldPreferences = [
			'VectorSkinVersion' => 'old',
		];

		Hooks::onPreferencesFormPreSave( $formData, $form, $user, $result, $oldPreferences );
	}

	/**
	 * @covers ::onLocalUserCreated
	 */
	public function testOnLocalUserCreatedLegacy() {
		$config = new HashConfig( [
			'VectorSkinMigrationMode' => false,
			'VectorDefaultSkinVersionForNewAccounts' => Constants::SKIN_VERSION_LEGACY,
		] );
		$this->setService( 'Vector.Config', $config );

		$user = $this->createMock( User::class );
		$userOptionsManager = $this->createMock( UserOptionsManager::class );
		$userOptionsManager->expects( $this->once() )
			->method( 'setOption' )
			->with( $user, 'VectorSkinVersion', Constants::SKIN_VERSION_LEGACY );
		$this->setService( 'UserOptionsManager', $userOptionsManager );
		$isAutoCreated = false;
		Hooks::onLocalUserCreated( $user, $isAutoCreated );
	}

	/**
	 * @covers ::onLocalUserCreated
	 */
	public function testOnLocalUserCreatedLatest() {
		$config = new HashConfig( [
			'VectorSkinMigrationMode' => false,
			'VectorDefaultSkinVersionForNewAccounts' => Constants::SKIN_VERSION_LATEST,
		] );
		$this->setService( 'Vector.Config', $config );

		$user = $this->createMock( User::class );
		$userOptionsManager = $this->createMock( UserOptionsManager::class );
		$userOptionsManager->expects( $this->once() )
			->method( 'setOption' )
			->with( $user, 'VectorSkinVersion', Constants::SKIN_VERSION_LATEST );
		$this->setService( 'UserOptionsManager', $userOptionsManager );
		$isAutoCreated = false;
		Hooks::onLocalUserCreated( $user, $isAutoCreated );
	}

	/**
	 * @covers ::onSkinTemplateNavigation
	 */
	public function testOnSkinTemplateNavigation() {
		$this->setMwGlobals( [
			'wgVectorUseIconWatch' => true
		] );
		$skin = new SkinVector( [ 'name' => 'vector' ] );
		$skin->getContext()->setTitle( Title::newFromText( 'Foo' ) );
		$contentNavWatch = [
			'actions' => [
				'watch' => [ 'class' => 'watch' ],
			]
		];
		$contentNavUnWatch = [
			'actions' => [
				'move' => [ 'class' => 'move' ],
				'unwatch' => [],
			],
		];

		Hooks::onSkinTemplateNavigation( $skin, $contentNavUnWatch );
		Hooks::onSkinTemplateNavigation( $skin, $contentNavWatch );

		$this->assertTrue(
			in_array( 'icon', $contentNavWatch['views']['watch']['class'] ) !== false,
			'Watch list items require an "icon" class'
		);
		$this->assertTrue(
			in_array( 'icon', $contentNavUnWatch['views']['unwatch']['class'] ) !== false,
			'Unwatch list items require an "icon" class'
		);
		$this->assertFalse(
			strpos( $contentNavUnWatch['actions']['move']['class'], 'icon' ) !== false,
			'List item other than watch or unwatch should not have an "icon" class'
		);
	}

	/**
	 * @covers ::updateUserLinksDropdownItems
	 */
	public function testUpdateUserLinksDropdownItems() {
		$updateUserLinksDropdownItems = new ReflectionMethod(
			Hooks::class,
			'updateUserLinksDropdownItems'
		);
		$updateUserLinksDropdownItems->setAccessible( true );
		$skin = new SkinVector22( [ 'name' => 'vector-2022' ] );
		// Anon user
		$skin->getUser()->setId( '1' );
		$contentAnon = [
			'user-menu' => [
				'anonuserpage' => [ 'class' => [], 'icon' => 'anonuserpage' ],
				'createaccount' => [ 'class' => [], 'icon' => 'createaccount' ],
				'login' => [ 'class' => [], 'icon' => 'login' ],
				'login-private' => [ 'class' => [], 'icon' => 'login-private' ],
			],
		];
		$updateUserLinksDropdownItems->invokeArgs( null, [ $skin, &$contentAnon ] );
		$this->assertTrue(
			count( $contentAnon['user-menu'] ) === 0,
			'Anon user page, create account, login, and login private links are removed from anon user links dropdown'
		);
		// Registered user
		$skin->getUser()->setId( '1' );
		$contentRegistered = [
			'user-menu' => [
				'userpage' => [ 'class' => [], 'icon' => 'userpage' ],
				'watchlist' => [ 'class' => [], 'icon' => 'watchlist' ],
				'logout' => [ 'class' => [], 'icon' => 'logout' ],
			],
		];
		$updateUserLinksDropdownItems->invokeArgs( null, [ $skin, &$contentRegistered ] );
		$this->assertContains( 'user-links-collapsible-item', $contentRegistered['user-menu']['userpage']['class'],
			'User page link in user links dropdown requires collapsible class'
		);
		$this->assertContains( 'mw-ui-icon-before', $contentRegistered['user-menu']['userpage']['link-class'],
			'User page link in user links dropdown requires before icon classes'
		);
		$this->assertContains( 'user-links-collapsible-item', $contentRegistered['user-menu']['watchlist']['class'],
			'Watchlist link in user links dropdown requires collapsible class'
		);
		$this->assertContains( 'mw-ui-icon-before', $contentRegistered['user-menu']['watchlist']['link-class'],
			'Watchlist link in user links dropdown requires before icon classes'
		);
		$this->assertFalse( isset( $contentRegistered['user-menu']['logout'] ),
			'Logout link in user links dropdown is not set'
		);
	}

	/**
	 * @covers ::updateUserLinksOverflowItems
	 */
	public function testUpdateUserLinksOverflowItems() {
		$updateUserLinksOverflowItems = new ReflectionMethod(
			Hooks::class,
			'updateUserLinksOverflowItems'
		);
		$updateUserLinksOverflowItems->setAccessible( true );
		$content = [
			'notifications' => [
				'alert' => [ 'class' => [], 'icon' => 'alert' ],
			],
			'user-interface-preferences' => [
				'uls' => [ 'class' => [], 'icon' => 'uls' ],
			],
			'user-page' => [
				'userpage' => [ 'class' => [], 'icon' => 'userpage' ],
			],
			'vector-user-menu-overflow' => [
				'watchlist' => [ 'class' => [], 'icon' => 'watchlist' ],
			],
		];
		$updateUserLinksOverflowItems->invokeArgs( null, [ &$content ] );
		$this->assertContains( 'user-links-collapsible-item',
			$content['user-interface-preferences']['uls']['class'],
			'ULS link in user links overflow requires collapsible class'
		);
		$this->assertContains( 'user-links-collapsible-item',
			$content['user-page']['userpage']['class'],
			'User page link in user links overflow requires collapsible class'
		);
		$this->assertContains( 'mw-ui-button',
			$content['user-page']['userpage']['link-class'],
			'User page link in user links overflow requires button classes'
		);
		$this->assertContains( 'mw-ui-quiet',
			$content['user-page']['userpage']['link-class'],
			'User page link in user links overflow requires quiet button classes'
		);
		$this->assertNotContains( 'mw-ui-icon',
			$content['user-page']['userpage']['class'],
			'User page link in user links overflow does not have icon classes'
		);
		$this->assertContains( 'user-links-collapsible-item',
			$content['vector-user-menu-overflow']['watchlist']['class'],
			'Watchlist link in user links overflow requires collapsible class'
		);
		$this->assertContains( 'mw-ui-button',
			$content['vector-user-menu-overflow']['watchlist']['link-class'],
			'Watchlist link in user links overflow requires button classes'
		);
		$this->assertContains( 'mw-ui-quiet',
			$content['vector-user-menu-overflow']['watchlist']['link-class'],
			'Watchlist link in user links overflow requires quiet button classes'
		);
		$this->assertContains( 'mw-ui-icon-element',
			$content['vector-user-menu-overflow']['watchlist']['link-class'],
			'Watchlist link in user links overflow hides text'
		);
		$this->assertTrue(
			$content['vector-user-menu-overflow']['watchlist']['id'] === 'pt-watchlist-2',
			'Watchlist link in user links has unique id'
		);
	}
}
