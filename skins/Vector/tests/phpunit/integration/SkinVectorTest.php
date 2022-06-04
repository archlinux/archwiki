<?php
namespace MediaWiki\Skins\Vector\Tests\Integration;

use Exception;
use HashConfig;
use MediaWikiIntegrationTestCase;
use ReflectionMethod;
use RequestContext;
use Title;
use Vector\SkinVector;
use Wikimedia\TestingAccessWrapper;

/**
 * Class VectorTemplateTest
 * @package MediaWiki\Skins\Vector\Tests\Unit
 * @group Vector
 * @group Skins
 *
 * @coversDefaultClass \Vector\SkinVector
 */
class SkinVectorTest extends MediaWikiIntegrationTestCase {

	/**
	 * @return SkinVector
	 */
	private function provideVectorTemplateObject() {
		return new SkinVector( [ 'name' => 'vector' ] );
	}

	/**
	 * @param string $nodeString an HTML of the node we want to verify
	 * @param string $tag Tag of the element we want to check
	 * @param string $attribute Attribute of the element we want to check
	 * @param string $search Value of the attribute we want to verify
	 * @return bool
	 */
	private function expectNodeAttribute( $nodeString, $tag, $attribute, $search ) {
		$node = new \DOMDocument();
		$node->loadHTML( $nodeString );
		$element = $node->getElementsByTagName( $tag )->item( 0 );
		if ( !$element ) {
			return false;
		}

		$values = explode( ' ', $element->getAttribute( $attribute ) );
		return in_array( $search, $values );
	}

	public function provideGetTocData() {
		$tocData = [
			'number-section-count' => 2,
			'array-sections' => [
				[
					'toclevel' => 1,
					'level' => '2',
					'line' => 'A',
					'number' => '1',
					'index' => '1',
					'fromtitle' => 'Test',
					'byteoffset' => 231,
					'anchor' => 'A',
					'array-sections' => [
						[
							'toclevel' => 2,
							'level' => '4',
							'line' => 'A1',
							'number' => '1.1',
							'index' => '2',
							'fromtitle' => 'Test',
							'byteoffset' => 245,
							'anchor' => 'A1'
						]
					]
				],
			]
		];

		return [
			// When zero sections
			[
				// $tocData
				[],
				// wgVectorTableOfContentsCollapseAtCount
				1,
				// expected 'vector-is-collapse-sections-enabled' value
				false
			],
			// When number of multiple sections is lower than configured value
			[
				// $tocData
				$tocData,
				// wgVectorTableOfContentsCollapseAtCount
				3,
				// expected 'vector-is-collapse-sections-enabled' value
				false
			],
			// When number of multiple sections is equal to the configured value
			[
				// $tocData
				$tocData,
				// wgVectorTableOfContentsCollapseAtCount
				2,
				// expected 'vector-is-collapse-sections-enabled' value
				true
			],
			// When number of multiple sections is higher than configured value
			[
				// $tocData
				$tocData,
				// wgVectorTableOfContentsCollapseAtCount
				1,
				// expected 'vector-is-collapse-sections-enabled' value
				true
			],
		];
	}

	/**
	 * @covers ::getTocData
	 * @dataProvider provideGetTOCData
	 */
	public function testGetTocData(
		array $tocData,
		int $configValue,
		bool $expected
	) {
		$this->setMwGlobals( [
			'wgVectorTableOfContentsCollapseAtCount' => $configValue
		] );

		$skinVector = new SkinVector( [ 'name' => 'vector-2022' ] );
		$openSkinVector = TestingAccessWrapper::newFromObject( $skinVector );
		$data = $openSkinVector->getTocData( $tocData );

		if ( empty( $tocData ) ) {
			$this->assertEquals( [], $data, 'toc data is empty when given an empty array' );
			return;
		}
		$this->assertArrayHasKey( 'vector-is-collapse-sections-enabled', $data );
		$this->assertEquals(
			$expected,
			$data['vector-is-collapse-sections-enabled'],
			'vector-is-collapse-sections-enabled has correct value'
		);
		$this->assertArrayHasKey( 'array-sections', $data );
	}

	/**
	 * @covers ::getTemplateData
	 */
	public function testGetTemplateData() {
		$title = Title::newFromText( 'SkinVector' );
		$context = RequestContext::getMain();
		$context->setTitle( $title );
		$context->setLanguage( 'fr' );
		$vectorTemplate = $this->provideVectorTemplateObject();
		$this->setTemporaryHook( 'PersonalUrls', [
			static function ( &$personal_urls, &$title, $skin ) {
				$personal_urls = [
					'pt-1' => [ 'text' => 'pt1' ],
				];
			}
		] );
		$this->setTemporaryHook( 'SkinTemplateNavigation::Universal', [
			static function ( &$skinTemplate, &$content_navigation ) {
				$content_navigation['actions'] = [
					'action-1' => []
				];
				$content_navigation['namespaces'] = [
					'ns-1' => []
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
			}
		] );
		$openVectorTemplate = TestingAccessWrapper::newFromObject( $vectorTemplate );

		$props = $openVectorTemplate->getTemplateData()['data-portlets'];
		$views = $props['data-views'];
		$namespaces = $props['data-namespaces'];

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
				'class' => 'mw-portlet mw-portlet-views emptyPortlet vector-menu vector-menu-tabs',
				'html-tooltip' => '',
				'html-items' => '',
				'html-after-portal' => '',
				'html-before-portal' => '',
				'label' => $context->msg( 'views' )->text(),
				'heading-class' => 'vector-menu-heading',
				'is-dropdown' => false,
			],
			$views
		);

		$variants = $props['data-variants'];
		$actions = $props['data-actions'];
		$this->assertSame(
			'mw-portlet mw-portlet-namespaces vector-menu vector-menu-tabs',
			$namespaces['class']
		);
		$this->assertSame(
			'mw-portlet mw-portlet-variants vector-menu-dropdown-noicon vector-menu vector-menu-dropdown',
			$variants['class']
		);
		$this->assertSame(
			'mw-portlet mw-portlet-cactions vector-menu-dropdown-noicon vector-menu vector-menu-dropdown',
			$actions['class']
		);
		$this->assertSame(
			'mw-portlet mw-portlet-personal vector-user-menu-legacy vector-menu',
			$props['data-personal']['class']
		);
	}

	/**
	 * Standard config for Language Alert in Sidebar
	 * @return array
	 */
	private function enableLanguageAlertFeatureConfig(): array {
		return [
			'VectorLanguageInHeader' => [
				'logged_in' => true,
				'logged_out' => true
			],
			'VectorLanguageInMainPageHeader' => [
				'logged_in' => false,
				'logged_out' => false
			],
			'VectorLanguageAlertInSidebar' => [
				'logged_in' => true,
				'logged_out' => true
			],
		];
	}

	public function providerLanguageAlertRequirements() {
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
				$this->enableLanguageAlertFeatureConfig(),
				$testTitle,
				[], true, true, false
			],
			'When the language alert feature is disabled, do not show alert' => [
				[
					'VectorLanguageInHeader' => [
						'logged_in' => true,
						'logged_out' => true
					],
					'VectorLanguageAlertInSidebar' => [
						'logged_in' => false,
						'logged_out' => false
					]
				],
				$testTitle,
				[ 'fr', 'en', 'ko' ], true, false, false
			],
			'When the language in header feature is disabled, do not show alert' => [
				[
					'VectorLanguageInHeader' => [
						'logged_in' => false,
						'logged_out' => false
					],
					'VectorLanguageAlertInSidebar' => [
						'logged_in' => true,
						'logged_out' => true
					]
				],
				$testTitle,
				[ 'fr', 'en', 'ko' ], true, false, false
			],
			'When it is a main page, feature is enabled, and there are no languages, do not show alert' => [
				$this->enableLanguageAlertFeatureConfig(),
				$testTitleMainPage,
				[], true, true, false
			],
			'When it is a non-main page, feature is enabled, and there are no languages, do not show alert' => [
				$this->enableLanguageAlertFeatureConfig(),
				$testTitle,
				[], true, true, false
			],
			'When it is a main page, header feature is disabled, and there are languages, do not show alert' => [
				[
					'VectorLanguageInHeader' => [
						'logged_in' => false,
						'logged_out' => false
					],
					'VectorLanguageAlertInSidebar' => [
						'logged_in' => true,
						'logged_out' => true
					]
				],
				$testTitleMainPage,
				[ 'fr', 'en', 'ko' ], true, true, false
			],
			'When it is a non-main page, alert feature is disabled, there are languages, do not show alert' => [
				[
					'VectorLanguageInHeader' => [
						'logged_in' => true,
						'logged_out' => true
					],
					'VectorLanguageAlertInSidebar' => [
						'logged_in' => false,
						'logged_out' => false
					]
				],
				$testTitle,
				[ 'fr', 'en', 'ko' ], true, true, false
			],
			'When most requirements are present but languages are not at the top, do not show alert' => [
				$this->enableLanguageAlertFeatureConfig(),
				$testTitle,
				[ 'fr', 'en', 'ko' ], false, false, false
			],
			'When most requirements are present but languages should be hidden, do not show alert' => [
				$this->enableLanguageAlertFeatureConfig(),
				$testTitle,
				[ 'fr', 'en', 'ko' ], true, true, false
			],
			'When it is a main page, features are enabled, and there are languages, show alert' => [
				$this->enableLanguageAlertFeatureConfig(),
				$testTitleMainPage,
				[ 'fr', 'en', 'ko' ], true, false, true
			],
			'When all the requirements are present on a non-main page, show alert' => [
				$this->enableLanguageAlertFeatureConfig(),
				$testTitle,
				[ 'fr', 'en', 'ko' ], true, false, true
			],
		];
	}

	/**
	 * @dataProvider providerLanguageAlertRequirements
	 * @covers ::shouldLanguageAlertBeInSidebar
	 * @param array $requirements
	 * @param Title $title
	 * @param array $getLanguagesCached
	 * @param bool $isLanguagesInContentAt
	 * @param bool $shouldHideLanguages
	 * @param bool $expected
	 * @throws Exception
	 */
	public function testShouldLanguageAlertBeInSidebar(
		array $requirements,
		Title $title,
		array $getLanguagesCached,
		bool $isLanguagesInContentAt,
		bool $shouldHideLanguages,
		bool $expected
	) {
		$config = new HashConfig( array_merge( $requirements, [
			'DefaultSkin' => 'vector-2022',
			'VectorDefaultSkinVersion' => '2',
			'VectorSkinMigrationMode' => true,
		] ) );
		$this->installMockMwServices( $config );

		$mockSkinVector = $this->getMockBuilder( SkinVector::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getTitle', 'getLanguagesCached','isLanguagesInContentAt', 'shouldHideLanguages' ] )
			->getMock();
		$mockSkinVector->method( 'getTitle' )
			->willReturn( $title );
		$mockSkinVector->method( 'getLanguagesCached' )
			->willReturn( $getLanguagesCached );
		$mockSkinVector->method( 'isLanguagesInContentAt' )->with( 'top' )
			->willReturn( $isLanguagesInContentAt );
		$mockSkinVector->method( 'shouldHideLanguages' )
			->willReturn( $shouldHideLanguages );

		$shouldLanguageAlertBeInSidebarMethod = new ReflectionMethod(
			SkinVector::class,
			'shouldLanguageAlertBeInSidebar'
		);
		$shouldLanguageAlertBeInSidebarMethod->setAccessible( true );

		$this->assertSame(
			$shouldLanguageAlertBeInSidebarMethod->invoke( $mockSkinVector ),
			$expected
		);
	}

}
