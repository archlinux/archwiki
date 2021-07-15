<?php

use MediaWiki\Interwiki\ClassicInterwikiLookup;

/**
 * @covers Scribunto_LuaTitleLibrary
 * @group Database
 */
class Scribunto_LuaTitleLibraryTest extends Scribunto_LuaEngineTestBase {
	/** @inheritDoc */
	protected static $moduleName = 'TitleLibraryTests';

	/** @var Title|null */
	private $testTitle = null;

	/** @var int */
	private $testPageId = null;

	protected function setUp() : void {
		$this->setTestTitle( null );
		parent::setUp();

		// Set up interwikis (via wgInterwikiCache) before creating any Titles
		$this->setMwGlobals( [
			'wgServer' => '//wiki.local',
			'wgCanonicalServer' => 'http://wiki.local',
			'wgUsePathInfo' => true,
			'wgActionPaths' => [],
			'wgScript' => '/w/index.php',
			'wgScriptPath' => '/w',
			'wgArticlePath' => '/wiki/$1',
			'wgInterwikiCache' => ClassicInterwikiLookup::buildCdbHash( [
				[
					'iw_prefix' => 'interwikiprefix',
					'iw_url'    => '//test.wikipedia.org/wiki/$1',
					'iw_local'  => 0,
				],
			] ),
		] );

		// Page for getContent test
		$page = WikiPage::factory( Title::newFromText( 'ScribuntoTestPage' ) );
		$page->doEditContent(
			new WikitextContent(
				'{{int:mainpage}}<includeonly>...</includeonly><noinclude>...</noinclude>'
			),
			'Summary'
		);
		$this->testPageId = $page->getId();

		// Pages for redirectTarget tests
		$page = WikiPage::factory( Title::newFromText( 'ScribuntoTestRedirect' ) );
		$page->doEditContent(
			new WikitextContent( '#REDIRECT [[ScribuntoTestTarget]]' ),
			'Summary'
		);
		$page = WikiPage::factory( Title::newFromText( 'ScribuntoTestNonRedirect' ) );
		$page->doEditContent(
			new WikitextContent( 'Not a redirect.' ),
			'Summary'
		);

		// Set restrictions for protectionLevels and cascadingProtection tests
		// Since mRestrictionsLoaded is true, they don't count as expensive
		$title = Title::newFromText( 'Main Page' );
		$title->mRestrictionsLoaded = true;
		$title->mRestrictions = [ 'edit' => [], 'move' => [] ];
		$title->mCascadeSources = [
			Title::makeTitle( NS_MAIN, "Lockbox" ),
			Title::makeTitle( NS_MAIN, "Lockbox2" ),
		];
		$title->mCascadingRestrictions = [ 'edit' => [ 'sysop' ] ];
		$title = Title::newFromText( 'Module:TestFramework' );
		$title->mRestrictionsLoaded = true;
		$title->mRestrictions = [
			'edit' => [ 'sysop', 'bogus' ],
			'move' => [ 'sysop', 'bogus' ],
		];
		$title->mCascadeSources = [];
		$title->mCascadingRestrictions = [];
		$title = Title::newFromText( 'interwikiprefix:Module:TestFramework' );
		$title->mRestrictionsLoaded = true;
		$title->mRestrictions = [];
		$title->mCascadeSources = [];
		$title->mCascadingRestrictions = [];
		$title = Title::newFromText( 'Talk:Has/A/Subpage' );
		$title->mRestrictionsLoaded = true;
		$title->mRestrictions = [ 'create' => [ 'sysop' ] ];
		$title->mCascadeSources = [];
		$title->mCascadingRestrictions = [];
		$title = Title::newFromText( 'Not/A/Subpage' );
		$title->mRestrictionsLoaded = true;
		$title->mRestrictions = [ 'edit' => [ 'autoconfirmed' ], 'move' => [ 'sysop' ] ];
		$title->mCascadeSources = [];
		$title->mCascadingRestrictions = [];
		$title = Title::newFromText( 'Module talk:Test Framework' );
		$title->mRestrictionsLoaded = true;
		$title->mRestrictions = [ 'edit' => [], 'move' => [ 'sysop' ] ];
		$title->mCascadeSources = [];
		$title->mCascadingRestrictions = [];

		// Note this depends on every iteration of the data provider running with a clean parser
		$this->getEngine()->getParser()->getOptions()->setExpensiveParserFunctionLimit( 10 );

		// Indicate to the tests that it's safe to create the title objects
		$interpreter = $this->getEngine()->getInterpreter();
		$interpreter->callFunction(
			$interpreter->loadString( "mw.title.testPageId = $this->testPageId", 'fortest' )
		);
	}

	protected function getTestTitle() {
		return $this->testTitle ?? parent::getTestTitle();
	}

	protected function setTestTitle( $title ) {
		$this->testTitle = $title !== null ? Title::newFromText( $title ) : null;
		$this->resetEngine();
	}

	protected function getTestModules() {
		return parent::getTestModules() + [
			'TitleLibraryTests' => __DIR__ . '/TitleLibraryTests.lua',
		];
	}

	public function testAddsLinks() {
		$engine = $this->getEngine();
		$interpreter = $engine->getInterpreter();

		// Loading a title should create a link
		$links = $engine->getParser()->getOutput()->getLinks();
		$this->assertFalse( isset( $links[NS_PROJECT]['Referenced_from_Lua'] ) );

		$interpreter->callFunction( $interpreter->loadString(
			'local _ = mw.title.new( "Project:Referenced from Lua" ).id', 'reference title'
		) );

		$links = $engine->getParser()->getOutput()->getLinks();
		$this->assertArrayHasKey( NS_PROJECT, $links );
		$this->assertArrayHasKey( 'Referenced_from_Lua', $links[NS_PROJECT] );

		// Loading the page content should create a templatelink
		$templates = $engine->getParser()->getOutput()->getTemplates();
		$this->assertFalse( isset( $links[NS_PROJECT]['Loaded_from_Lua'] ) );

		$interpreter->callFunction( $interpreter->loadString(
			'mw.title.new( "Project:Loaded from Lua" ):getContent()', 'load title'
		) );

		$templates = $engine->getParser()->getOutput()->getTemplates();
		$this->assertArrayHasKey( NS_PROJECT, $templates );
		$this->assertArrayHasKey( 'Loaded_from_Lua', $templates[NS_PROJECT] );
	}

	/**
	 * @dataProvider provideVaryPageId
	 */
	public function testVaryPageId( $testTitle, $code, $flag ) {
		$this->setTestTitle( $testTitle );

		$code = strtr( $code, [ '$$ID$$' => $this->testPageId ] );

		$engine = $this->getEngine();
		$interpreter = $engine->getInterpreter();
		$this->assertFalse(
			$engine->getParser()->getOutput()->getFlag( 'vary-page-id' ), 'sanity check'
		);

		$interpreter->callFunction( $interpreter->loadString(
			"local _ = $code", 'reference title but not id'
		) );
		$this->assertFalse( $engine->getParser()->getOutput()->getFlag( 'vary-page-id' ) );

		$interpreter->callFunction( $interpreter->loadString(
			"local _ = $code.id", 'reference id'
		) );
		$this->assertSame( $flag, $engine->getParser()->getOutput()->getFlag( 'vary-page-id' ) );
	}

	public function provideVaryPageId() {
		return [
			'by getCurrentTitle()' => [
				'ScribuntoTestPage',
				'mw.title.getCurrentTitle()',
				true
			],
			'by name' => [
				'ScribuntoTestPage',
				'mw.title.new("ScribuntoTestPage")',
				true
			],
			'by id' => [
				'ScribuntoTestPage',
				'mw.title.new( $$ID$$ )',
				true
			],

			'other page by name' => [
				'ScribuntoTestRedirect',
				'mw.title.new("ScribuntoTestPage")',
				false
			],
			'other page by id' => [
				'ScribuntoTestRedirect',
				'mw.title.new( $$ID$$ )',
				false
			],

			'new page by getCurrentTitle()' => [
				'ScribuntoTestPage/DoesNotExist',
				'mw.title.getCurrentTitle()',
				true
			],
			'new page by name' => [
				'ScribuntoTestPage/DoesNotExist',
				'mw.title.new("ScribuntoTestPage/DoesNotExist")',
				true
			],
		];
	}
}
