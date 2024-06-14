<?php

namespace MediaWiki\Tests\ResourceLoader;

use Content;
use CssContent;
use EmptyResourceLoader;
use JavaScriptContent;
use JavaScriptContentHandler;
use LinkCacheTestTrait;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Page\PageRecord;
use MediaWiki\Page\PageStore;
use MediaWiki\Request\FauxRequest;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\DerivativeContext;
use MediaWiki\ResourceLoader\WikiModule;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use ReflectionMethod;
use RuntimeException;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use WikitextContent;

/**
 * @group ResourceLoader
 * @group Database
 * @covers \MediaWiki\ResourceLoader\WikiModule
 */
class WikiModuleTest extends ResourceLoaderTestCase {
	use LinkCacheTestTrait;

	/**
	 * @dataProvider provideConstructor
	 */
	public function testConstructor( $params ) {
		$module = new WikiModule( $params );
		$this->assertInstanceOf( WikiModule::class, $module );
	}

	public static function provideConstructor() {
		yield 'null' => [ null ];
		yield 'empty' => [ [] ];
		yield 'unknown settings' => [ [ 'foo' => 'baz' ] ];
		yield 'real settings' => [ [ 'MediaWiki:Common.js' ] ];
	}

	private function makeTitleInfo( array $mockInfo ) {
		$wikiModuleClass = TestingAccessWrapper::newFromClass( WikiModule::class );
		$info = [];
		foreach ( $mockInfo as $val ) {
			$title = $val['title'];
			unset( $val['title'] );
			$info[ $wikiModuleClass->makeTitleKey( $title ) ] = $val;
		}
		return $info;
	}

	/**
	 * @dataProvider provideGetPages
	 */
	public function testGetPages( $params, Config $config, $expected ) {
		$module = new WikiModule( $params );
		$module->setConfig( $config );

		// Because getPages is protected..
		$getPages = new ReflectionMethod( $module, 'getPages' );
		$getPages->setAccessible( true );
		$out = $getPages->invoke( $module, Context::newDummyContext() );
		$this->assertSame( $expected, $out );
	}

	public static function provideGetPages() {
		$settings = self::getSettings() + [
			MainConfigNames::UseSiteJs => true,
			MainConfigNames::UseSiteCss => true,
		];

		$params = [
			'styles' => [ 'MediaWiki:Common.css' ],
			'scripts' => [ 'MediaWiki:Common.js' ],
		];

		return [
			[ [], new HashConfig( $settings ), [] ],
			[ $params, new HashConfig( $settings ), [
				'MediaWiki:Common.js' => [ 'type' => 'script' ],
				'MediaWiki:Common.css' => [ 'type' => 'style' ]
			] ],
			[ $params, new HashConfig( [ MainConfigNames::UseSiteCss => false ] + $settings ), [
				'MediaWiki:Common.js' => [ 'type' => 'script' ],
			] ],
			[ $params, new HashConfig( [ MainConfigNames::UseSiteJs => false ] + $settings ), [
				'MediaWiki:Common.css' => [ 'type' => 'style' ],
			] ],
			[ $params,
				new HashConfig(
					[ MainConfigNames::UseSiteJs => false, MainConfigNames::UseSiteCss => false ]
				),
				[]
			],
		];
	}

	/**
	 * @dataProvider provideGetGroup
	 */
	public function testGetGroup( $params, $expected ) {
		$module = new WikiModule( $params );
		$this->assertSame( $expected, $module->getGroup() );
	}

	public static function provideGetGroup() {
		yield 'no group' => [ [], null ];
		yield 'some group' => [ [ 'group' => 'foobar' ], 'foobar' ];
	}

	/**
	 * @dataProvider provideGetType
	 */
	public function testGetType( $params, $expected ) {
		$module = new WikiModule( $params );
		$this->assertSame( $expected, $module->getType() );
	}

	public static function provideGetType() {
		yield 'empty' => [
			[],
			WikiModule::LOAD_GENERAL,
		];
		yield 'scripts' => [
			[ 'scripts' => [ 'Example.js' ] ],
			WikiModule::LOAD_GENERAL,
		];
		yield 'styles' => [
			[ 'styles' => [ 'Example.css' ] ],
			WikiModule::LOAD_STYLES,
		];
		yield 'styles and scripts' => [
			[ 'styles' => [ 'Example.css' ], 'scripts' => [ 'Example.js' ] ],
			WikiModule::LOAD_GENERAL,
		];
	}

	/**
	 * @dataProvider provideIsKnownEmpty
	 */
	public function testIsKnownEmpty( $titleInfo, $group, $dependencies, $expected ) {
		$module = $this->getMockBuilder( WikiModule::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getTitleInfo', 'getGroup', 'getDependencies' ] )
			->getMock();
		$module->method( 'getTitleInfo' )
			->willReturn( $this->makeTitleInfo( $titleInfo ) );
		$module->method( 'getGroup' )
			->willReturn( $group );
		$module->method( 'getDependencies' )
			->willReturn( $dependencies );
		$context = $this->createMock( Context::class );
		$this->assertSame( $expected, $module->isKnownEmpty( $context ) );
	}

	public static function provideIsKnownEmpty() {
		yield 'nothing' => [
			[],
			null,
			[],
			// No pages exist, considered empty.
			true,
		];

		yield 'an empty page exists (no group)' => [
			[ [ 'title' => new TitleValue( NS_USER, 'Example/foo.js' ), 'page_len' => 0 ] ],
			null,
			[],
			// There is an existing page, so we should let the module be queued.
			// Its emptiness might be temporary, hence considered non-empty (T70488).
			false,
		];
		yield 'an empty page exists (site group)' => [
			[ [ 'title' => new TitleValue( NS_MEDIAWIKI, 'Foo.js' ), 'page_len' => 0 ] ],
			'site',
			[],
			// There is an existing page, hence considered non-empty.
			false,
		];
		yield 'an empty page exists (user group)' => [
			[ [ 'title' => new TitleValue( NS_USER, 'Example/foo.js' ), 'page_len' => 0 ] ],
			'user',
			[],
			// There is an existing page, but it is empty.
			// For user-specific modules, don't bother loading a known-empty module.
			// Given user-specific HTML output, this will vary and re-appear if/when
			// the page becomes non-empty again.
			true,
		];

		yield 'no pages but having dependencies (no group)' => [
			[],
			null,
			[ 'another-module' ],
			false,
		];
		yield 'no pages but having dependencies (site group)' => [
			[],
			'site',
			[ 'another-module' ],
			false,
		];
		yield 'no pages but having dependencies (user group)' => [
			[],
			'user',
			[ 'another-module' ],
			false,
		];

		yield 'a non-empty page exists (user group)' => [
			[ [ 'title' => new TitleValue( NS_USER, 'Example/foo.js' ), 'page_len' => 25 ] ],
			'user',
			[],
			false,
		];
		yield 'a non-empty page exists (site group)' => [
			[ [ 'title' => new TitleValue( NS_MEDIAWIKI, 'Foo.js' ), 'page_len' => 25 ] ],
			'site',
			[],
			false,
		];
	}

	public function testGetPreloadedTitleInfo() {
		// Set up
		ConvertibleTimestamp::setFakeTime( '20110401090000' );
		$this->editPage( 'MediaWiki:TestA.css', '.mw-first {}', 'First' );
		$this->editPage( 'MediaWiki:TestEmpty.css', '', 'Empty' );
		$this->editPage( 'MediaWiki:TestB.css', '.mw-second {}', 'Second' );
		$rl = new EmptyResourceLoader();
		$rl->getConfig()->set( 'UseSiteJs', true );
		$rl->getConfig()->set( 'UseSiteCss', true );
		$rl->register( 'testmodule1', [
			'class' => TestResourceLoaderWikiModule::class,
			'styles' => [
				'MediaWiki:TestA.css',
				// Regression against T145673. It's impossible to statically declare page names in
				// a canonical way since the canonical prefix is localised. As such, the preload
				// cache computed the right cache key, but failed to find the results when
				// doing an intersect on the canonical result, producing an empty array.
				'mediawiki: testEmpty.css',
			],
		] );
		$rl->register( 'testmodule2', [
			'class' => TestResourceLoaderWikiModule::class,
			'styles' => [
				'MediaWiki:TestB.css',
			],
		] );
		$context = new Context( $rl, new FauxRequest() );

		// Warm up the cache
		WikiModule::preloadTitleInfo(
			$context,
			[ 'testmodule1', 'testmodule2' ]
		);
		// The module uses TestResourceLoaderWikiModule, which disables fetchTitleInfo() by default.
		// If getTitleInfo() returns the data here, it means preloadTitleInfo succeeded.
		$module1 = TestingAccessWrapper::newFromObject( $rl->getModule( 'testmodule1' ) );
		$this->assertArrayContains( [
			'8:TestA.css' => [ 'page_len' => '12', 'page_touched' => '20110401090000' ],
			'8:TestEmpty.css' => [ 'page_len' => '0', 'page_touched' => '20110401090000' ],
		], $module1->getTitleInfo( $context ), 'Title info' );
		$module2 = TestingAccessWrapper::newFromObject( $rl->getModule( 'testmodule2' ) );
		$this->assertArrayContains( [
			'8:TestB.css' => [ 'page_len' => '13', 'page_touched' => '20110401090000' ],
		], $module2->getTitleInfo( $context ), 'Title info' );
	}

	public function testGetPreloadedBadTitle() {
		// Set up
		TestResourceLoaderWikiModule::$returnFetchTitleInfo = [];
		$rl = new EmptyResourceLoader();
		$rl->getConfig()->set( 'UseSiteJs', true );
		$rl->getConfig()->set( 'UseSiteCss', true );
		$rl->register( 'testmodule', [
			'class' => TestResourceLoaderWikiModule::class,
			// Covers preloadTitleInfo branch for invalid page name
			'styles' => [ '[x]' ],
		] );
		$context = new Context( $rl, new FauxRequest() );

		// Act
		TestResourceLoaderWikiModule::preloadTitleInfo(
			$context,
			[ 'testmodule' ]
		);

		// Assert
		$module = TestingAccessWrapper::newFromObject( $rl->getModule( 'testmodule' ) );
		$this->assertSame( [], $module->getTitleInfo( $context ), 'Title info' );
	}

	public function testGetPreloadedTitleInfoEmpty() {
		$context = new Context( new EmptyResourceLoader(), new FauxRequest() );
		// This covers the early return case
		$this->assertSame(
			null,
			WikiModule::preloadTitleInfo(
				$context,
				[]
			)
		);
	}

	public static function provideGetContent() {
		yield 'Bad title' => [ null, '[x]' ];

		yield 'No JS content found' => [ null, [
			'text' => 'MediaWiki:Foo.js',
			'ns' => NS_MEDIAWIKI,
			'title' => 'Foo.js',
		] ];

		yield 'JS content' => [ 'code;', [
			'text' => 'MediaWiki:Foo.js',
			'ns' => NS_MEDIAWIKI,
			'title' => 'Foo.js',
		], new JavaScriptContent( 'code;' ) ];

		yield 'CSS content' => [ 'code {}', [
			'text' => 'MediaWiki:Foo.css',
			'ns' => NS_MEDIAWIKI,
			'title' => 'Foo.css',
		], new CssContent( 'code {}' ) ];

		yield 'Wikitext content' => [ null, [
			'text' => 'MediaWiki:Foo',
			'ns' => NS_MEDIAWIKI,
			'title' => 'Foo',
		], new WikitextContent( 'code;' ) ];
	}

	/**
	 * @dataProvider provideGetContent
	 */
	public function testGetContent( $expected, $title, Content $contentObj = null ) {
		$context = $this->getResourceLoaderContext( [], new EmptyResourceLoader );
		$module = $this->getMockBuilder( WikiModule::class )
			->onlyMethods( [ 'getContentObj' ] )->getMock();
		$module->method( 'getContentObj' )
			->willReturn( $contentObj );

		if ( is_array( $title ) ) {
			$title += [ 'ns' => NS_MAIN, 'id' => 1, 'len' => 1, 'redirect' => 0 ];
			$titleText = $title['text'];
			// Mock page table access via PageStore
			$pageStore = $this->createNoOpMock( PageStore::class, [ 'getPageByText' ] );
			$pageStore->method( 'getPageByText' )->willReturn(
				new PageIdentityValue(
					$title['id'], $title['ns'], $title['text'], PageRecord::LOCAL
				)
			);
		} else {
			$titleText = $title;
		}

		$module = TestingAccessWrapper::newFromObject( $module );
		$this->assertSame(
			$expected,
			$module->getContent( $titleText, $context )
		);
	}

	public function testContentOverrides() {
		$pages = [
			'MediaWiki:Common.css' => [ 'type' => 'style' ],
		];

		$rl = new EmptyResourceLoader();

		$module = $this->getMockBuilder( WikiModule::class )
			->onlyMethods( [ 'getPages' ] )
			->getMock();
		$module->method( 'getPages' )->willReturn( $pages );
		$module->setConfig( $rl->getConfig() );

		$context = new DerivativeContext(
			new Context( $rl, new FauxRequest() )
		);
		$context->setContentOverrideCallback( static function ( PageIdentity $t ) {
			if ( $t->getDBkey() === 'Common.css' ) {
				return new CssContent( '.override{}' );
			}
			return null;
		} );

		$this->assertTrue( $module->shouldEmbedModule( $context ) );
		$this->assertSame( [
			'all' => [
				"/*\nMediaWiki:Common.css\n*/\n.override{}"
			]
		], $module->getStyles( $context ) );

		$context->setContentOverrideCallback( static function ( PageIdentity $t ) {
			if ( $t->getDBkey() === 'Skin.css' ) {
				return new CssContent( '.override{}' );
			}
			return null;
		} );
		$this->assertFalse( $module->shouldEmbedModule( $context ) );
	}

	public function testGetContentForRedirects() {
		// Set up context and module object
		$context = new DerivativeContext(
			$this->getResourceLoaderContext( [], new EmptyResourceLoader )
		);
		$module = $this->getMockBuilder( WikiModule::class )
			->onlyMethods( [ 'getPages' ] )
			->getMock();
		$module->method( 'getPages' )
			->willReturn( [
				'MediaWiki:Redirect.js' => [ 'type' => 'script' ]
			] );
		$module->setConfig( $context->getResourceLoader()->getConfig() );
		$context->setContentOverrideCallback( static function ( PageIdentity $title ) {
			if ( $title->getDBkey() === 'Redirect.js' ) {
				$handler = new JavaScriptContentHandler();
				return $handler->makeRedirectContent(
					Title::makeTitle( NS_MEDIAWIKI, 'Target.js' )
				);
			} elseif ( $title->getDBkey() === 'Target.js' ) {
				return new JavaScriptContent( 'target;' );
			} else {
				return null;
			}
		} );

		// Mock away Title's db queries with LinkCache
		$this->addGoodLinkObject( 1, new TitleValue( NS_MEDIAWIKI, 'Redirect.js' ), 1, 1 );

		$this->assertSame(
			"/*\nMediaWiki:Redirect.js\n*/\ntarget;\n",
			$module->getScript( $context ),
			'Redirect resolved by getContent'
		);
	}

	protected function tearDown(): void {
		Title::clearCaches();
		parent::tearDown();
	}
}

class TestResourceLoaderWikiModule extends WikiModule {
	public static $returnFetchTitleInfo = null;

	protected static function fetchTitleInfo( IReadableDatabase $db, array $pages, $fname = null ) {
		$ret = self::$returnFetchTitleInfo;
		self::$returnFetchTitleInfo = null;
		if ( $ret === null ) {
			// If a call is expected, a mock return value must be planted first
			throw new RuntimeException( 'Unexpected fetchTitleInfo call' );
		}
		return $ret;
	}
}
