<?php
namespace MediaWiki\Skins\Vector\Tests\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Page\LinkCache;
use MediaWiki\Skins\Vector\SkinVectorLegacy;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\TalkPageNotificationManager;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * Class VectorTemplateTest
 * @group Vector
 * @group Skins
 */
class SkinVectorTest extends MediaWikiIntegrationTestCase {
	use MockAuthorityTrait;
	use TempUserTestTrait;

	private const ASSOCIATED_PAGE = [
		'text' => 'Associated page 1',
		'href' => '/url/to/associated/page/1',
	];

	protected function setUp(): void {
		parent::setUp();
		// Mock TalkPageNotificationManager to avoid DB queries
		$this->setService( 'TalkPageNotificationManager', $this->createMock( TalkPageNotificationManager::class ) );
		$this->clearHooks();
	}

	/**
	 * @return SkinVectorLegacy
	 */
	private function createVectorTemplateObject() {
		$skinFactory = $this->getServiceContainer()->getSkinFactory();
		$template = $skinFactory->makeSkin( 'vector' );
		return $template;
	}

	/**
	 * @covers \MediaWiki\Skins\Vector\SkinVectorLegacy::getTemplateData
	 */
	public function testGetTemplateData() {
		$this->setService( 'LinkCache', $this->createMock( LinkCache::class ) );
		$title = Title::makeTitle( NS_MAIN, 'SkinVector' );
		$title->resetArticleID( 0 );
		$context = new RequestContext();
		$context->setTitle( $title );
		$context->setAuthority( $this->mockAnonNullAuthority() );
		$context->setLanguage( 'fr' );
		$context->setActionName( 'view' );
		$vectorTemplate = $this->createVectorTemplateObject();
		$vectorTemplate->setContext( $context );
		$this->setTemporaryHook( 'SkinTemplateNavigation::Universal',
			static function ( &$skinTemplate, &$content_navigation ) {
				$content_navigation['actions'] = [
					'action-1' => [
						'href' => '/action/',
						'text' => 'action 1'
					]
				];
				$content_navigation['associated-pages'] = [
					'ns-1' => self::ASSOCIATED_PAGE,
				];
				$content_navigation['variants'] = [
					[
						'class' => 'selected',
						'text' => 'Language variant',
						'href' => '/url/to/variant',
						'lang' => 'zh-hant',
						'hreflang' => 'zh-hant',
					]
				];
				$content_navigation['views'] = [];
				$content_navigation['user-menu'] = [
					'pt-1' => [
						'href' => '/wiki/',
						'text' => 'pt1'
					],
				];
			}
		);
		$openVectorTemplate = TestingAccessWrapper::newFromObject( $vectorTemplate );

		$props = $openVectorTemplate->getTemplateData()['data-portlets'];
		$views = $props['data-views'];
		$namespaces = $props['data-associated-pages'];

		// The mediawiki core specification might change at any time
		// so let's limit the values we test to those we are aware of.
		$keysToTest = [
			'id', 'class', 'html-tooltip', 'html-items',
			'html-after-portal', 'html-before-portal',
			'label', 'heading-class', 'is-dropdown'
		];
		foreach ( $views as $key => $value ) {
			if ( !in_array( $key, $keysToTest ) ) {
				unset( $views[ $key] );
			}
		}
		$this->assertSame(
			[
				// Provided by core
				'id' => 'p-views',
				'class' => 'mw-portlet mw-portlet-views emptyPortlet ' .
					'vector-menu-tabs vector-menu-tabs-legacy',
				'html-tooltip' => '',
				'html-items' => '',
				'html-after-portal' => '',
				'html-before-portal' => '',
				'label' => $context->msg( 'views' )->text(),
				'heading-class' => '',
				'is-dropdown' => false,
			],
			$views
		);

		$variants = $props['data-variants'];
		$actions = $props['data-actions'];
		$this->assertSame(
			'mw-portlet mw-portlet-associated-pages vector-menu-tabs vector-menu-tabs-legacy',
			$namespaces['class']
		);
		$this->assertSame(
			'mw-portlet mw-portlet-variants vector-menu-dropdown',
			$variants['class']
		);
		$this->assertSame(
			'mw-portlet mw-portlet-cactions vector-menu-dropdown',
			$actions['class']
		);
		$this->assertSame(
			'mw-portlet mw-portlet-personal vector-user-menu-legacy',
			$props['data-user-menu']['class']
		);
	}

	/**
	 * @covers \MediaWiki\Skins\Vector\SkinVectorLegacy::runOnSkinTemplateNavigationHooks
	 */
	public function testTempUserCreateAccountLink() {
		$this->enableAutoCreateTempUser();

		$title = Title::makeTitle( NS_MAIN, 'SkinVector' );
		$title->resetArticleID( 0 );

		$tempUser = $this->createMock( User::class );
		$tempUser->method( 'isTemp' )->willReturn( true );
		$tempUser->method( 'isRegistered' )->willReturn( true );

		$context = new RequestContext();
		$context->setTitle( $title );
		$context->setLanguage( 'en' );
		$context->setActionName( 'view' );

		$skin = $this->createVectorTemplateObject();
		$skin->setContext( $context );

		// Create a second skin instance to pass as $skin parameter.
		// Set context with the temp user so isTemp() returns true.
		$innerSkin = $this->createVectorTemplateObject();
		$tempContext = new RequestContext();
		$tempContext->setTitle( $title );
		$tempContext->setUser( $tempUser );
		$tempContext->setLanguage( 'en' );
		$tempContext->setActionName( 'view' );
		$innerSkin->setContext( $tempContext );

		$content_navigation = [
			'user-interface-preferences' => [],
			'user-page' => [
				'userpage' => [
					'href' => '/wiki/User:Example',
					'text' => '~2026-1'
				]
			],
			'notifications' => [],
			'user-menu' => [],
			'associated-pages' => [
				'ns-1' => self::ASSOCIATED_PAGE,
			],
			'actions' => [],
			'views' => [],
			'variants' => [],
		];

		$method = new \ReflectionMethod( $skin, 'runOnSkinTemplateNavigationHooks' );
		$args = [ $innerSkin, &$content_navigation ];
		$method->invokeArgs( $skin, $args );

		$this->assertArrayHasKey(
			'createaccount',
			$content_navigation['user-menu'],
			'Temp user menu should include a createaccount item'
		);
		$this->assertSame(
			'pt-createaccount',
			$content_navigation['user-menu']['createaccount']['single-id']
		);
	}

	/**
	 * Standard config for Language Alert in Sidebar
	 */
	private static function enableLanguageInHeaderFeatureConfig(): array {
		return [
			'VectorLanguageInHeader' => [
				'logged_in' => true,
				'logged_out' => true
			],
			'VectorLanguageInMainPageHeader' => [
				'logged_in' => false,
				'logged_out' => false
			],
		];
	}

	public static function providerLanguageAlertRequirements() {
		$testTitle = Title::makeTitle( NS_MAIN, 'Test' );
		$testTitleMainPage = Title::makeTitle( NS_MAIN, 'MAIN_PAGE' );
		return [
			'When none of the requirements are present, do not show alert' => [
				// Configuration requirements for language in header and alert in sidebar
				[],
				// Title instance
				$testTitle,
				// Cached languages
				[],
				// Is the language selector at the top of the content?
				false,
				// Should the language button be hidden?
				false,
				// Expected
				false
			],
			'When the feature is enabled and languages should be hidden, do not show alert' => [
				self::enableLanguageInHeaderFeatureConfig(),
				$testTitle,
				[], true, true, false
			],
			'When the language in header feature is disabled, do not show alert' => [
				[
					'VectorLanguageInHeader' => [
						'logged_in' => false,
						'logged_out' => false
					],
				],
				$testTitle,
				[ 'fr', 'en', 'ko' ], true, false, false
			],
			'When it is a main page, feature is enabled, and there are no languages, do not show alert' => [
				self::enableLanguageInHeaderFeatureConfig(),
				$testTitleMainPage,
				[], true, true, false
			],
			'When it is a non-main page, feature is enabled, and there are no languages, do not show alert' => [
				self::enableLanguageInHeaderFeatureConfig(),
				$testTitle,
				[], true, true, false
			],
			'When it is a main page, header feature is disabled, and there are languages, do not show alert' => [
				[
					'VectorLanguageInHeader' => [
						'logged_in' => false,
						'logged_out' => false
					],
				],
				$testTitleMainPage,
				[ 'fr', 'en', 'ko' ], true, true, false
			],
			'When most requirements are present but languages are not at the top, do not show alert' => [
				self::enableLanguageInHeaderFeatureConfig(),
				$testTitle,
				[ 'fr', 'en', 'ko' ], false, false, false
			],
			'When most requirements are present but languages should be hidden, do not show alert' => [
				self::enableLanguageInHeaderFeatureConfig(),
				$testTitle,
				[ 'fr', 'en', 'ko' ], true, true, false
			],
			'When it is a main page, features are enabled, and there are languages, show alert' => [
				self::enableLanguageInHeaderFeatureConfig(),
				$testTitleMainPage,
				[ 'fr', 'en', 'ko' ], true, false, true
			],
			'When all the requirements are present on a non-main page, show alert' => [
				self::enableLanguageInHeaderFeatureConfig(),
				$testTitle,
				[ 'fr', 'en', 'ko' ], true, false, true
			],
		];
	}
}
