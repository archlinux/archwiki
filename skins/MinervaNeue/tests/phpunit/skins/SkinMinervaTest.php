<?php

namespace MediaWiki\Minerva;

use MediaWiki\Context\RequestContext;
use MediaWiki\Minerva\Skins\SkinMinerva;
use MediaWiki\Output\OutputPage;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \MediaWiki\Minerva\Skins\SkinMinerva
 * @group MinervaNeue
 * @group Database
 */
class SkinMinervaTest extends MediaWikiIntegrationTestCase {
	use MockAuthorityTrait;

	private const MAIN_MENU_HOME_ENTRY = [
		'tag-name' => 'a',
		'label' => 'Home',
		'array-attributes' => [
			[
				'key' => 'href',
				'value' => '/wiki/Main_Page',
			],
			[
				'key' => 'data-mw',
				'value' => 'interface',
			]
		],
		'classes' => 'menu__item--home',
		'data-icon' => [
			'icon' => 'home',
		],
		'isButton' => true,
	];
	private const MAIN_MENU_HOME = [
		'name' => 'home',
		'components' => [
			self::MAIN_MENU_HOME_ENTRY
		]
	];
	private const PAGE_ACTIONS = [
		'toolbar' => [],
		'overflowMenu' => [
			'item-id' => 'page-actions-overflow',
			'checkboxID' => 'page-actions-overflow-checkbox',
			'toggleID' => 'page-actions-overflow-toggle',
			'event' => 'ui.overflowmenu',
			'data-btn' => [
				'tag-name' => 'label',
				'data-icon' => [
					'icon' => 'ellipsis'
				],
				'classes' => 'toggle-list__toggle',
				'array-attributes' => [
					[
						 'key' => 'id',
						 'value' => 'page-actions-overflow-toggle',
					],
					[
						'key' => 'for',
						'value' => 'page-actions-overflow-checkbox',
					],
					[
						'key' => 'aria-hidden',
						'value' => 'true',
					],
				],
				'label' => '(minerva-page-actions-overflow)',
			],
			'listID' => 'p-tb',
			'listClass' => 'page-actions-overflow-list toggle-list__list--drop-down',
		]
	];
	private const ATTRIBUTE_NOTIFICATION_HREF = [
		'key' => 'href',
		'value' => '/wiki/Special:Notifications',
	];
	private const ATTRIBUTE_NOTIFICATION_DATA_EVENT_NAME = [
		'key' => 'data-event-name',
		'value' => 'ui.notifications',
	];

	private const ATTRIBUTE_NOTIFICATION_DATA_COUNTER_TEXT = [
		'key' => 'data-counter-text',
		'value' => "13",
	];
	private const ATTRIBUTE_NOTIFICATION_DATA_COUNTER_NUM = [
		'key' => 'data-counter-num',
		'value' => 13,
	];
	private const ATTRIBUTE_NOTIFICATION_TITLE = [
		'key' => 'title',
		'value' => "Your alerts",
	];

	/**
	 * @param RequestContext|null $context
	 * @return SkinMinerva
	 */
	private function newSkinMinerva( $context = null ): SkinMinerva {
		$services = $this->getServiceContainer();
		$permissions = $services->getService( 'Minerva.Permissions' );
		if ( $context ) {
			$permissions->setContext( $context );
		}
		return new SkinMinerva(
			$services->getGenderCache(),
			$services->getLinkRenderer(),
			$services->getService( 'Minerva.LanguagesHelper' ),
			$services->getService( 'Minerva.Menu.Definitions' ),
			$services->getService( 'Minerva.Menu.PageActions' ),
			$permissions,
			$services->getService( 'Minerva.SkinOptions' ),
			$services->getService( 'Minerva.SkinUserPageHelper' ),
			$services->getNamespaceInfo(),
			$services->getRevisionLookup(),
			$services->getUserIdentityUtils(),
			$services->getUserOptionsManager()
		);
	}

	private function overrideSkinOptions( array $options ): void {
		$services = $this->getServiceContainer();
		$mockOptions = new SkinOptions(
			$services->getHookContainer(),
			$services->getService( 'Minerva.SkinUserPageHelper' )
		);
		$mockOptions->setMultiple( $options );
		$this->setService( 'Minerva.SkinOptions', $mockOptions );
	}

	public static function provideHasPageActions() {
		return [
			[ NS_MAIN, 'test', 'view', true ],
			[ NS_SPECIAL, 'test', 'view', false ],
			[ NS_MAIN, 'test', 'history', false ]
		];
	}

	/**
	 * @dataProvider provideHasPageActions
	 * @covers ::hasPageActions
	 */
	public function testHasPageActions( int $namespace, string $title, string $action, bool $expected ) {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( $namespace, $title ) );
		$context->setActionName( $action );

		$skin = $this->newSkinMinerva();
		$skin->setContext( $context );

		$this->assertEquals( $expected, TestingAccessWrapper::newFromObject( $skin )->hasPageActions() );
	}

	public static function provideHasPageTabs() {
		return [
			[ [ SkinOptions::TABS_ON_SPECIALS => false ], NS_MAIN, 'test', 'view', true ],
			[ [ SkinOptions::TABS_ON_SPECIALS => false ], NS_MAIN, 'Main Page', 'view', false ],
			[ [ SkinOptions::TABS_ON_SPECIALS => false ], NS_TALK, 'Main Page', 'view', false ],
			[ [ SkinOptions::TALK_AT_TOP => false ], NS_MAIN, 'test', 'view', false ],
			[ [ SkinOptions::TALK_AT_TOP => false ], NS_SPECIAL, 'test', 'view', true ],
			[ [ SkinOptions::TALK_AT_TOP => false ], NS_MAIN, 'test', 'history', true ],
		];
	}

	/**
	 * @covers ::getDefaultModules
	 */
	public function testGetDefaultModules() {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( 0, 'Hello' ) );
		$context->setActionName( 'view' );

		$skinFactory = $this->getServiceContainer()->getSkinFactory();
		$skin = $skinFactory->makeSkin( 'minerva' );

		$skin->setContext( $context );

		$this->assertContains( 'skins.minerva.styles', $skin->getDefaultModules()['styles']['skin'],
								'Check entry point' );
	}

	/**
	 * @dataProvider provideHasPageTabs
	 * @covers ::hasPageTabs
	 */
	public function testHasPageTabs( array $options, int $namespace, string $title, string $action, bool $expected ) {
		// both tabs on specials and talk at top default to true
		$this->overrideSkinOptions( $options );

		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( $namespace, $title ) );
		$context->setActionName( $action );

		// hasPageTabs gets the action directly from the request rather than the context so we set it here as well
		$context->getRequest()->setVal( 'action', $action );

		$skin = $this->newSkinMinerva();
		$skin->setContext( $context );

		$this->assertEquals( $expected, TestingAccessWrapper::newFromObject( $skin )->hasPageTabs() );
	}

	/**
	 * @covers ::getTabsData
	 */
	public function testGetTabsData() {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_MAIN, 'test' ) );

		$skin = $this->newSkinMinerva();
		$skin->setContext( $context );

		$contentNavigationUrls = [
			'main' => [
				'class' => 'hi',
				'text' => 'article'
			],
			'talk' => [
				'class' => 'selected',
				'rel' => 'discussion',
				'text' => 'talk'
			],
		];
		$data = TestingAccessWrapper::newFromObject( $skin )->getTabsData(
			$contentNavigationUrls, 'test'
		);

		$this->assertEquals( [ 'items' => [
			[
				'class' => 'hi',
				'text' => 'article'
			],
			[
				'class' => 'selected',
				'rel' => 'discussion',
				'text' => 'talk'
			]
		], 'id' => 'test' ], $data );
	}

	/**
	 * @covers ::getTabsData when hasPageTabs is false
	 */
	public function testGetTabsDataNoPageTabs() {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_MAIN, 'Main Page' ) );
		$context->setActionName( 'view' );

		$skin = $this->newSkinMinerva();
		$skin->setContext( $context );

		$contentNavigationUrls = [ 'associated-pages' => [ 'testkey' => 'testvalue' ] ];
		$associatedPages = [ 'id' => 'test' ];
		$data = TestingAccessWrapper::newFromObject( $skin )->getTabsData( $contentNavigationUrls, $associatedPages );

		$this->assertEquals( [], $data );
	}

	/**
	 * @covers ::getTabsData when contentNavigationUrls is empty
	 */
	public function testGetTabsDataNoContentNavigationUrls() {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_MAIN, 'test' ) );

		$skin = $this->newSkinMinerva();
		$skin->setContext( $context );

		$contentNavigationUrls = [];
		$associatedPages = [ 'id' => 'test' ];
		$data = TestingAccessWrapper::newFromObject( $skin )->getTabsData( $contentNavigationUrls, $associatedPages );

		$this->assertEquals( [], $data );
	}

	/**
	 * @covers ::getTabsData when associatedPages has no id
	 */
	public function testGetTabsDataNoId() {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_MAIN, 'test' ) );

		$skin = $this->newSkinMinerva();
		$skin->setContext( $context );

		$item = [
			'class' => 'hi',
			'text' => 'article'
		];
		$contentNavigationUrls = [
			$item
		];
		$associatedPages = [];
		$data = TestingAccessWrapper::newFromObject( $skin )->getTabsData( $contentNavigationUrls );

		$this->assertEquals( [ 'items' => [ $item ], 'id' => null ], $data );
	}

	/**
	 * @covers ::mapPortletData
	 */
	public function testMapPortletData() {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_MAIN, 'test' ) );

		$skin = $this->newSkinMinerva();
		$skin->setContext( $context );

		$portletData = [
			'id' => 'nav',
			'array-items' => [
				[
					'name' => 'h',
					'class' => 'foo',
					'array-links' => [
						[
							'text' => 'Home',
							'icon' => 'home',
							'array-attributes' => [
								[
									'key' => 'href',
									'value' => '/wiki/Home',
								],
								[
									'key' => 'rel',
									'value' => 'main',
								],
							]
						],
					]
				]
			]
		];
		$data = TestingAccessWrapper::newFromObject( $skin )->mapPortletData( $portletData );

		$this->assertEquals( [
			'h' => [
				'class' => 'foo',
				'text' => 'Home',
				'context' => 'h',
				'icon' => 'home',
				'href' => '/wiki/Home',
				'rel' => 'main',
			]
		], $data );
	}

	/**
	 * @covers ::getHtmlElementAttributes when night mode is not enabled via feature flag or query params
	 */
	public function testGetHtmlElementAttributesNoNightMode() {
		$skin = $this->newSkinMinerva();

		$classes = $skin->getHtmlElementAttributes()['class'];
		$this->assertStringNotContainsString( 'skin-theme-clientpref-', $classes );
	}

	/**
	 * @covers ::getHtmlElementAttributes when night mode is enabled via feature flag
	 */
	public function testGetHtmlElementAttributesNightMode() {
		$this->overrideSkinOptions( [ SkinOptions::NIGHT_MODE => true ] );

		$skin = $this->newSkinMinerva();

		$classes = $skin->getHtmlElementAttributes()['class'];
		$this->assertStringContainsString( 'skin-theme-clientpref-day', $classes );
	}

	/**
	 * @covers ::getHtmlElementAttributes when night mode is set via query params
	 */
	public function testGetHtmlElementAttributesNightModeQueryParam() {
		$context = new RequestContext();
		$request = $context->getRequest();
		$request->setVal( 'minervanightmode', '1' );

		$skin = $this->newSkinMinerva();
		$skin->setContext( $context );

		$classes = $skin->getHtmlElementAttributes()['class'];
		$this->assertStringContainsString( 'skin-theme-clientpref-night', $classes );
	}

	/**
	 * @covers ::getHtmlElementAttributes when night mode is set via query params to an invalid option
	 */
	public function testGetHtmlElementAttributesNightModeQueryParamInvalid() {
		$context = new RequestContext();
		$request = $context->getRequest();
		$request->setVal( 'minervanightmode', '3' );

		$skin = $this->newSkinMinerva();
		$skin->setContext( $context );

		$classes = $skin->getHtmlElementAttributes()['class'];
		$this->assertStringContainsString( 'skin-theme-clientpref-day', $classes );
	}

	/**
	 * @covers ::getHtmlElementAttributes when night mode is enabled and the value is not default
	 */
	public function testGetHtmlElementAttributesNightModeUserOption() {
		$this->overrideSkinOptions( [ SkinOptions::NIGHT_MODE => true ] );

		$skin = $this->newSkinMinerva();

		$user = $skin->getUser();
		$this->getServiceContainer()->getUserOptionsManager()->setOption( $user, 'minerva-theme', 'day' );

		$classes = $skin->getHtmlElementAttributes()['class'];
		$this->assertStringContainsString( 'skin-theme-clientpref-day', $classes );
	}

	/**
	 * @covers ::getHtmlElementAttributes when night mode is enabled with non-default, and query param is invalid
	 */
	public function testGetHtmlElementAttributesNightModeUserOptionQueryParamInvalid() {
		$this->overrideSkinOptions( [ SkinOptions::NIGHT_MODE => true ] );

		$context = new RequestContext();
		$request = $context->getRequest();
		$request->setVal( 'minervanightmode', '3' );

		$skin = $this->newSkinMinerva();
		$skin->setContext( $context );

		$user = $skin->getUser();
		$this->getServiceContainer()->getUserOptionsManager()->setOption( $user, 'minerva-theme', 'day' );

		$classes = $skin->getHtmlElementAttributes()['class'];
		$this->assertStringContainsString( 'skin-theme-clientpref-day', $classes );
	}

	/**
	 * @covers ::setContext
	 * @covers ::hasCategoryLinks
	 */
	public function testHasCategoryLinksWhenOptionIsOff() {
		$outputPage = $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->getMock();
		$outputPage->expects( $this->never() )
			->method( 'getCategoryLinks' );

		$this->overrideSkinOptions( [ SkinOptions::CATEGORIES => false ] );
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_MAIN, 'Test' ) );
		$context->setOutput( $outputPage );

		$skin = $this->newSkinMinerva();
		$skin->setContext( $context );
		$skin = TestingAccessWrapper::newFromObject( $skin );

		$this->assertFalse( $skin->hasCategoryLinks() );
	}

	/**
	 * @dataProvider provideHasCategoryLinks
	 * @covers ::setContext
	 * @covers ::hasCategoryLinks
	 */
	public function testHasCategoryLinks( array $categoryLinks, $expected ) {
		$outputPage = $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->getMock();
		$outputPage->expects( $this->once() )
			->method( 'getCategoryLinks' )
			->willReturn( $categoryLinks );

		$this->overrideSkinOptions( [ SkinOptions::CATEGORIES => true ] );

		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_MAIN, 'Test' ) );
		$context->setOutput( $outputPage );

		$skin = $this->newSkinMinerva();
		$skin->setContext( $context );

		$skin = TestingAccessWrapper::newFromObject( $skin );

		$this->assertEquals( $expected, $skin->hasCategoryLinks() );
	}

	public static function provideHasCategoryLinks() {
		return [
			[ [], false ],
			[
				[
					'normal' => '<ul><li><a href="/wiki/Category:1">1</a></li></ul>'
				],
				true
			],
			[
				[
					'hidden' => '<ul><li><a href="/wiki/Category:Hidden">Hidden</a></li></ul>'
				],
				true
			],
			[
				[
					'normal' => '<ul><li><a href="/wiki/Category:1">1</a></li></ul>',
					'hidden' => '<ul><li><a href="/wiki/Category:Hidden">Hidden</a></li></ul>'
				],
				true
			],
			[
				[
					'unexpected' => '<ul><li><a href="/wiki/Category:1">1</a></li></ul>'
				],
				false
			],
		];
	}

	public static function provideGetNotificationButtons() {
		return [
			[
				[],
				[]
			],
			//
			// CIRCLE
			//
			[
				[
					'tag-name' => 'a',
					'classes' => 'mw-echo-notifications-badge mw-echo-notification-badge-nojs '
						. ' mw-echo-unseen-notifications',
					'array-attributes' => [
						self::ATTRIBUTE_NOTIFICATION_HREF,
						self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_TEXT,
						self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_NUM,
						self::ATTRIBUTE_NOTIFICATION_TITLE,
						[
							'key' => 'id',
							'value' => 'pt-notifications-alert',
						],
					],
					'data-icon' => [
						'icon' => 'circle'
					],
					'label' => 'Alerts (13)',
				],
				[
					[
						'name' => 'notifications-alert',
						'id' => 'pt-notifications-alert',
						'class' => 'notification-count notification-unseen mw-echo-unseen-notifications mw-list-item',
						'array-links' => [
							[
								'icon' => 'circle',
								'array-attributes' => [
									self::ATTRIBUTE_NOTIFICATION_HREF,
									self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_TEXT,
									self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_NUM,
									self::ATTRIBUTE_NOTIFICATION_TITLE,
									[
										'key' => 'class',
										'value' => 'mw-echo-notifications-badge '
											. 'mw-echo-notification-badge-nojs oo-ui-icon-bellOutline '
											. 'mw-echo-unseen-notifications',
									],
								],
								'text' => 'Alerts (13)'
							]
						]
					]
				]

			],
			//
			// BELL
			//
			[
				[
					'tag-name' => 'a',
					'classes' => 'mw-echo-notifications-badge mw-echo-notification-badge-nojs',
					'array-attributes' => [
						self::ATTRIBUTE_NOTIFICATION_HREF,
						self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_TEXT,
						self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_NUM,
						self::ATTRIBUTE_NOTIFICATION_TITLE,
						[
							'key' => 'id',
							'value' => 'pt-notifications-alert',
						],
					],
					'data-icon' => [
						'icon' => 'bellOutline'
					],
					'label' => 'Alerts (13)',
				],
				[
					[
						'html-item' => 'n/a',
						'name' => 'notifications-alert',
						'html' => 'HTML',
						'id' => 'pt-notifications-alert',
						'class' => 'mw-list-item',
						'array-links' => [
							[
								'icon' => 'bellOutline',
								'array-attributes' => [
									self::ATTRIBUTE_NOTIFICATION_HREF,
									self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_TEXT,
									self::ATTRIBUTE_NOTIFICATION_DATA_COUNTER_NUM,
									self::ATTRIBUTE_NOTIFICATION_TITLE,
									[
										'key' => 'class',
										'value' => 'mw-echo-notifications-badge mw-echo-notification-badge-nojs',
									],
								],
								'text' => 'Alerts (13)'
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider provideGetNotificationButtons
	 * @param array $expected
	 * @param array $from
	 * @covers ::getNotificationButtons
	 */
	public function testGetNotificationButtons( $expected, $from ) {
		$btns = SkinMinerva::getNotificationButtons( $from );
		$this->assertEquals( $expected['classes'] ?? '', $btns[0]['classes'] ?? '' );
		$this->assertEquals( $expected['data-attributes'] ?? [], $btns[0]['data-attributes'] ?? [] );
		$this->assertEquals( $expected['data-icon'] ?? [], $btns[0]['data-icon'] ?? [] );
		$this->assertEquals( $expected['data-label'] ?? '', $btns[0]['data-label'] ?? '' );
	}

	/**
	 * @covers ::getTemplateData
	 */
	public function testGetTemplateData() {
		$context = new RequestContext();
		$title = $this->getExistingTestPage()->getTitle();
		$context->setTitle( $title );
		$context->setUser( $this->getTestUser()->getUser() );
		$context->setLanguage( 'qqx' );

		$skin = $this->newSkinMinerva();
		$skin->setContext( $context );
		$skin->getOutput()->setProperty( 'wgMFDescription', 'A description' );
		$data = $skin->getTemplateData();

		// Unset items which can vary depending on what extensions are installed
		// and what hooks run
		unset( $data['data-minerva-page-actions']['overflowMenu']['items'] );

		$this->assertTrue( $data['has-minerva-languages'] );
		$this->assertEquals( [
			'<div id="siteNotice"></div>'
		], $data['array-minerva-banners'] );
		$this->assertEquals(
			[
				'data-icon' => [
					'icon' => 'search',
				],
				'label' => '(searchbutton)',
				'classes' => 'skin-minerva-search-trigger',
				'array-attributes' => [
					[
						'key' => 'id',
						'value' => 'searchIcon',
					]
				]
			],
			$data['data-minerva-search-box']['data-btn']
		);
		$this->assertEquals(
			[
				'data-icon' => [
					'icon' => 'menu',
				],
				'tag-name' => 'label',
				'classes' => 'toggle-list__toggle',
				'array-attributes' => [
					[
						'key' => 'for',
						'value' => 'main-menu-input',
					],
					[
						'key' => 'id',
						'value' => 'mw-mf-main-menu-button',
					],
					[
						'key' => 'aria-hidden',
						'value' => 'true',
					],
				],
				'text' => '(mobile-frontend-main-menu-button-tooltip)',
			],
			$data['data-minerva-main-menu-btn']
		);
		$this->assertTrue( isset( $data['data-minerva-main-menu']['sitelinks'] ) );
		$this->assertEquals( 'p-navigation', $data['data-minerva-main-menu']['groups'][0]['id'] );
		$this->assertEquals( self::MAIN_MENU_HOME, $data['data-minerva-main-menu']['groups'][0]['entries'][0] );
		$this->assertEquals( 'p-interaction', $data['data-minerva-main-menu']['groups'][1]['id'] );
		$this->assertEquals( 'pt-preferences', $data['data-minerva-main-menu']['groups'][2]['id'] );
		$this->assertEquals( '<div class="tagline">A description</div>', $data['html-minerva-tagline'] );
		$this->assertTrue( isset( $data['html-minerva-user-menu'] ) );
		$this->assertFalse( $data['is-minerva-beta'] );
		$this->assertEquals( [
			'id' => 'p-associated-pages',
			'items' => [
				[
					'class' => 'selected mw-list-item',
					'text' => '(nstab-main)',
					'icon' => null,
					'context' => 'main',
					'href' => $title->getLocalURL(),
				],
				[
					'class' => 'new mw-list-item',
					'text' => '(nstab-talk / talk)',
					'icon' => null,
					'context' => 'talk',
					'href' => $title->getTalkPage()->getLocalURL(
						'action=edit&redlink=1'
					),
					'rel' => 'discussion'
				]
			],
		], $data['data-minerva-tabs'] );
		$this->assertEquals( self::PAGE_ACTIONS, $data['data-minerva-page-actions'] );
		$this->assertEquals( [], $data['data-minerva-secondary-actions'] );
		$this->assertSame( '', $data['html-minerva-subject-link'] );
		$this->assertEquals( [
			'href' => $title->getLocalURL(
				'action=history'
			),
			'text' => '(mobile-frontend-history)',
			'historyIcon' => [
				'icon' => 'modified-history',
				'size' => 'medium',
			],
			'arrowIcon' => [
				'icon' => 'expand',
				'size' => 'small'
			],
		], $data['data-minerva-history-link'] );
	}

	/**
	 * @covers ::getSecondaryActions
	 */
	public function testMainPageTalkButton() {
		$mainPageTitle = Title::makeTitle( NS_MAIN, 'Main Page' );
		$mainPageTitle->setContentModel( CONTENT_MODEL_WIKITEXT );

		$authority = $this->mockRegisteredUltimateAuthority();
		$context = RequestContext::getMain();
		$context->setTitle( $mainPageTitle );
		$context->setActionName( 'view' );
		$context->setAuthority( $authority );
		$context->setUser( $this->getTestUser()->getUser() );
		$skin = $this->newSkinMinerva( $context );
		$context->setSkin( $skin );
		$skin->setContext( $context );
		$skin = TestingAccessWrapper::newFromObject( $context->getSkin() );
		$contentNavigationUrls = [
			'talk' => [
				'text' => 'discuss',
			],
		];

		// Registered users have talk button on mainpage
		$actions = $skin->getSecondaryActions( $contentNavigationUrls );
		$this->assertArrayHasKey( 'talk', $actions );
		$this->assertSame( [ 'array-attributes', 'tag-name',
			'isButton', 'classes', 'label' ], array_keys( $actions['talk'] ), );

		// Unregistered users do not have talk button on mainpage
		$context->setAuthority( $this->mockAnonUltimateAuthority() );
		$actions = $skin->getSecondaryActions( $contentNavigationUrls );
		$this->assertArrayNotHasKey( 'talk', $actions );
	}
}
