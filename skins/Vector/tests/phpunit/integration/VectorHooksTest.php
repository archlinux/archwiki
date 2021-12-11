<?php
/*
 * @file
 * @ingroup skins
 */

use Vector\Constants;
use Vector\FeatureManagement\FeatureManager;
use Vector\Hooks;
use Vector\HTMLForm\Fields\HTMLLegacySkinVersionField;

/**
 * Integration tests for Vector Hooks.
 *
 * @group Vector
 * @coversDefaultClass \Vector\Hooks
 */
class VectorHooksTest extends MediaWikiIntegrationTestCase {

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
					'hide-if' => [ '!==', 'wpskin', Constants::SKIN_NAME ]
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
	 * @covers ::onGetPreferences
	 */
	public function testOnGetPreferencesShowPreferencesEnabledSkinSectionMissingLegacy() {
		$isLegacy = false;
		$this->setFeatureLatestSkinVersionIsEnabled( !$isLegacy );

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
					'hide-if' => [ '!==', 'wpskin', Constants::SKIN_NAME ]
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
		$user->expects( $this->never() )
			->method( 'setOption' );
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
		$user->expects( $this->never() )
			->method( 'setOption' );
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
		$form = $this->createMock( HTMLForm::class );
		$user = $this->createMock( User::class );
		$user->expects( $this->once() )
			->method( 'setOption' )
			->with( 'VectorSkinVersion', 'old' );
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
			'VectorDefaultSkinVersionForNewAccounts' => Constants::SKIN_VERSION_LEGACY,
		] );
		$this->setService( 'Vector.Config', $config );

		$user = $this->createMock( User::class );
		$user->expects( $this->once() )
			->method( 'setOption' )
			->with( 'VectorSkinVersion', Constants::SKIN_VERSION_LEGACY );
		$isAutoCreated = false;
		Hooks::onLocalUserCreated( $user, $isAutoCreated );
	}

	/**
	 * @covers ::onLocalUserCreated
	 */
	public function testOnLocalUserCreatedLatest() {
		$config = new HashConfig( [
			'VectorDefaultSkinVersionForNewAccounts' => Constants::SKIN_VERSION_LATEST,
		] );
		$this->setService( 'Vector.Config', $config );

		$user = $this->createMock( User::class );
		$user->expects( $this->once() )
			->method( 'setOption' )
			->with( 'VectorSkinVersion', Constants::SKIN_VERSION_LATEST );
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
			strpos( $contentNavWatch['views']['watch']['class'], 'icon' ) !== false,
			'Watch list items require an "icon" class'
		);
		$this->assertTrue(
			strpos( $contentNavUnWatch['views']['unwatch']['class'], 'icon' ) !== false,
			'Unwatch list items require an "icon" class'
		);
		$this->assertFalse(
			strpos( $contentNavUnWatch['actions']['move']['class'], 'icon' ) !== false,
			'List item other than watch or unwatch should not have an "icon" class'
		);
	}
}
