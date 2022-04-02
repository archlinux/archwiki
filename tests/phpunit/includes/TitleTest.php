<?php

use MediaWiki\Cache\CacheKeyHelper;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Tests\Unit\DummyServicesTrait;
use Wikimedia\Assert\PreconditionException;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @group Title
 */
class TitleTest extends MediaWikiIntegrationTestCase {
	use DummyServicesTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->mergeMwGlobalArrayValue(
			'wgExtraNamespaces',
			[
				12302 => 'TEST-JS',
				12303 => 'TEST-JS_TALK',
			]
		);
		$this->mergeMwGlobalArrayValue(
			'wgNamespaceContentModels',
			[
				12302 => CONTENT_MODEL_JAVASCRIPT,
			]
		);

		$this->setMwGlobals( [
			'wgAllowUserJs' => false,
			'wgDefaultLanguageVariant' => false,
			'wgMetaNamespace' => 'Project',
			'wgServer' => 'https://example.org',
			'wgCanonicalServer' => 'https://example.org',
			'wgScriptPath' => '/w',
			'wgScript' => '/w/index.php',
			'wgArticlePath' => '/wiki/$1',
		] );
		$this->setUserLang( 'en' );
		$this->setMwGlobals( 'wgLanguageCode', 'en' );

		// For testSecureAndSplitValid, testSecureAndSplitInvalid
		$this->setMwGlobals( 'wgLocalInterwikis', [ 'localtestiw' ] );

		// Define valid interwiki prefixes and their configuration
		// DummyServicesTrait::getDummyInterwikiLookup
		$interwikiLookup = $this->getDummyInterwikiLookup( [
			// testSecureAndSplitValid, testSecureAndSplitInvalid
			[ 'iw_prefix' => 'localtestiw', 'iw_url' => 'localtestiw' ],
			[ 'iw_prefix' => 'remotetestiw', 'iw_url' => 'remotetestiw' ],

			// testSubpages
			'wiki',

			// testIsValid
			'wikipedia',

			// testIsValidRedirectTarget
			'acme',

			// testGetFragmentForURL
			[ 'iw_prefix' => 'de', 'iw_local' => 1 ],
			[ 'iw_prefix' => 'zz', 'iw_local' => 0 ],

			// Some tests use interwikis - define valid prefixes and their configuration
			// DummyServicesTrait::getDummyInterwikiLookup
			[ 'iw_prefix' => 'acme', 'iw_url' => 'https://acme.test/$1' ],
			[ 'iw_prefix' => 'yy', 'iw_url' => 'https://yy.wiki.test/wiki/$1', 'iw_local' => true ]
		] );
		$this->setService( 'InterwikiLookup', $interwikiLookup );
	}

	protected function tearDown(): void {
		Title::clearCaches();
		parent::tearDown();
		// delete dummy pages
		$this->getNonexistingTestPage( 'UTest1' );
		$this->getNonexistingTestPage( 'UTest2' );
	}

	public static function provideInNamespace() {
		return [
			[ 'Main Page', NS_MAIN, true ],
			[ 'Main Page', NS_TALK, false ],
			[ 'Main Page', NS_USER, false ],
			[ 'User:Foo', NS_USER, true ],
			[ 'User:Foo', NS_USER_TALK, false ],
			[ 'User:Foo', NS_TEMPLATE, false ],
			[ 'User_talk:Foo', NS_USER_TALK, true ],
			[ 'User_talk:Foo', NS_USER, false ],
		];
	}

	/**
	 * @dataProvider provideInNamespace
	 * @covers Title::inNamespace
	 */
	public function testInNamespace( $title, $ns, $expectedBool ) {
		$title = Title::newFromText( $title );
		$this->assertEquals( $expectedBool, $title->inNamespace( $ns ) );
	}

	/**
	 * @covers Title::inNamespaces
	 */
	public function testInNamespaces() {
		$mainpage = Title::newFromText( 'Main Page' );
		$this->assertTrue( $mainpage->inNamespaces( NS_MAIN, NS_USER ) );
		$this->assertTrue( $mainpage->inNamespaces( [ NS_MAIN, NS_USER ] ) );
		$this->assertTrue( $mainpage->inNamespaces( [ NS_USER, NS_MAIN ] ) );
		$this->assertFalse( $mainpage->inNamespaces( [ NS_PROJECT, NS_TEMPLATE ] ) );
	}

	public static function provideHasSubjectNamespace() {
		return [
			[ 'Main Page', NS_MAIN, true ],
			[ 'Main Page', NS_TALK, true ],
			[ 'Main Page', NS_USER, false ],
			[ 'User:Foo', NS_USER, true ],
			[ 'User:Foo', NS_USER_TALK, true ],
			[ 'User:Foo', NS_TEMPLATE, false ],
			[ 'User_talk:Foo', NS_USER_TALK, true ],
			[ 'User_talk:Foo', NS_USER, true ],
		];
	}

	/**
	 * @dataProvider provideHasSubjectNamespace
	 * @covers Title::hasSubjectNamespace
	 */
	public function testHasSubjectNamespace( $title, $ns, $expectedBool ) {
		$title = Title::newFromText( $title );
		$this->assertEquals( $expectedBool, $title->hasSubjectNamespace( $ns ) );
	}

	public function dataGetContentModel() {
		return [
			[ 'Help:Foo', CONTENT_MODEL_WIKITEXT ],
			[ 'Help:Foo.js', CONTENT_MODEL_WIKITEXT ],
			[ 'Help:Foo/bar.js', CONTENT_MODEL_WIKITEXT ],
			[ 'User:Foo', CONTENT_MODEL_WIKITEXT ],
			[ 'User:Foo.js', CONTENT_MODEL_WIKITEXT ],
			[ 'User:Foo/bar.js', CONTENT_MODEL_JAVASCRIPT ],
			[ 'User:Foo/bar.css', CONTENT_MODEL_CSS ],
			[ 'User talk:Foo/bar.css', CONTENT_MODEL_WIKITEXT ],
			[ 'User:Foo/bar.js.xxx', CONTENT_MODEL_WIKITEXT ],
			[ 'User:Foo/bar.xxx', CONTENT_MODEL_WIKITEXT ],
			[ 'MediaWiki:Foo.js', CONTENT_MODEL_JAVASCRIPT ],
			[ 'MediaWiki:Foo.css', CONTENT_MODEL_CSS ],
			[ 'MediaWiki:Foo/bar.css', CONTENT_MODEL_CSS ],
			[ 'MediaWiki:Foo.JS', CONTENT_MODEL_WIKITEXT ],
			[ 'MediaWiki:Foo.CSS', CONTENT_MODEL_WIKITEXT ],
			[ 'MediaWiki:Foo.css.xxx', CONTENT_MODEL_WIKITEXT ],
			[ 'TEST-JS:Foo', CONTENT_MODEL_JAVASCRIPT ],
			[ 'TEST-JS:Foo.js', CONTENT_MODEL_JAVASCRIPT ],
			[ 'TEST-JS:Foo/bar.js', CONTENT_MODEL_JAVASCRIPT ],
			[ 'TEST-JS_TALK:Foo.js', CONTENT_MODEL_WIKITEXT ],
		];
	}

	/**
	 * @dataProvider dataGetContentModel
	 * @covers Title::getContentModel
	 */
	public function testGetContentModel( $title, $expectedModelId ) {
		$title = Title::newFromText( $title );
		$this->assertEquals( $expectedModelId, $title->getContentModel() );
	}

	/**
	 * @dataProvider dataGetContentModel
	 * @covers Title::hasContentModel
	 */
	public function testHasContentModel( $title, $expectedModelId ) {
		$title = Title::newFromText( $title );
		$this->assertTrue( $title->hasContentModel( $expectedModelId ) );
	}

	public static function provideIsSiteConfigPage() {
		return [
			[ 'Help:Foo', false ],
			[ 'Help:Foo.js', false ],
			[ 'Help:Foo/bar.js', false ],
			[ 'User:Foo', false ],
			[ 'User:Foo.js', false ],
			[ 'User:Foo/bar.js', false ],
			[ 'User:Foo/bar.json', false ],
			[ 'User:Foo/bar.css', false ],
			[ 'User:Foo/bar.JS', false ],
			[ 'User:Foo/bar.JSON', false ],
			[ 'User:Foo/bar.CSS', false ],
			[ 'User talk:Foo/bar.css', false ],
			[ 'User:Foo/bar.js.xxx', false ],
			[ 'User:Foo/bar.xxx', false ],
			[ 'MediaWiki:Foo.js', 'javascript' ],
			[ 'MediaWiki:Foo.json', 'json' ],
			[ 'MediaWiki:Foo.css', 'css' ],
			[ 'MediaWiki:Foo.JS', false ],
			[ 'MediaWiki:Foo.JSON', false ],
			[ 'MediaWiki:Foo.CSS', false ],
			[ 'MediaWiki:Foo/bar.css', 'css' ],
			[ 'MediaWiki:Foo.css.xxx', false ],
			[ 'TEST-JS:Foo', false ],
			[ 'TEST-JS:Foo.js', false ],
		];
	}

	/**
	 * @dataProvider provideIsSiteConfigPage
	 * @covers Title::isSiteConfigPage
	 * @covers Title::isSiteJsConfigPage
	 * @covers Title::isSiteJsonConfigPage
	 * @covers Title::isSiteCssConfigPage
	 */
	public function testSiteConfigPage( $title, $expected ) {
		$title = Title::newFromText( $title );

		// $expected is either false or the relevant type ('javascript', 'json', 'css')
		$this->assertSame(
			$expected !== false,
			$title->isSiteConfigPage()
		);
		$this->assertSame(
			$expected === 'javascript',
			$title->isSiteJsConfigPage()
		);
		$this->assertSame(
			$expected === 'json',
			$title->isSiteJsonConfigPage()
		);
		$this->assertSame(
			$expected === 'css',
			$title->isSiteCssConfigPage()
		);
	}

	public static function provideIsUserConfigPage() {
		return [
			[ 'Help:Foo', false ],
			[ 'Help:Foo.js', false ],
			[ 'Help:Foo/bar.js', false ],
			[ 'User:Foo', false ],
			[ 'User:Foo.js', false ],
			[ 'User:Foo/bar.js', 'javascript' ],
			[ 'User:Foo/bar.JS', false ],
			[ 'User:Foo/bar.json', 'json' ],
			[ 'User:Foo/bar.JSON', false ],
			[ 'User:Foo/bar.css', 'css' ],
			[ 'User:Foo/bar.CSS', false ],
			[ 'User talk:Foo/bar.css', false ],
			[ 'User:Foo/bar.js.xxx', false ],
			[ 'User:Foo/bar.xxx', false ],
			[ 'MediaWiki:Foo.js', false ],
			[ 'MediaWiki:Foo.json', false ],
			[ 'MediaWiki:Foo.css', false ],
			[ 'MediaWiki:Foo.JS', false ],
			[ 'MediaWiki:Foo.JSON', false ],
			[ 'MediaWiki:Foo.CSS', false ],
			[ 'MediaWiki:Foo.css.xxx', false ],
			[ 'TEST-JS:Foo', false ],
			[ 'TEST-JS:Foo.js', false ],
		];
	}

	/**
	 * @dataProvider provideIsUserConfigPage
	 * @covers Title::isUserConfigPage
	 * @covers Title::isUserJsConfigPage
	 * @covers Title::isUserJsonConfigPage
	 * @covers Title::isUserCssConfigPage
	 */
	public function testIsUserConfigPage( $title, $expected ) {
		$title = Title::newFromText( $title );

		// $expected is either false or the relevant type ('javascript', 'json', 'css')
		$this->assertSame(
			$expected !== false,
			$title->isUserConfigPage()
		);
		$this->assertSame(
			$expected === 'javascript',
			$title->isUserJsConfigPage()
		);
		$this->assertSame(
			$expected === 'json',
			$title->isUserJsonConfigPage()
		);
		$this->assertSame(
			$expected === 'css',
			$title->isUserCssConfigPage()
		);
	}

	public static function provideIsWikitextPage() {
		return [
			[ 'Help:Foo', true ],
			[ 'Help:Foo.js', true ],
			[ 'Help:Foo/bar.js', true ],
			[ 'User:Foo', true ],
			[ 'User:Foo.js', true ],
			[ 'User:Foo/bar.js', false ],
			[ 'User:Foo/bar.json', false ],
			[ 'User:Foo/bar.css', false ],
			[ 'User talk:Foo/bar.css', true ],
			[ 'User:Foo/bar.js.xxx', true ],
			[ 'User:Foo/bar.xxx', true ],
			[ 'MediaWiki:Foo.js', false ],
			[ 'User:Foo/bar.JS', true ],
			[ 'User:Foo/bar.JSON', true ],
			[ 'User:Foo/bar.CSS', true ],
			[ 'MediaWiki:Foo.json', false ],
			[ 'MediaWiki:Foo.css', false ],
			[ 'MediaWiki:Foo.JS', true ],
			[ 'MediaWiki:Foo.JSON', true ],
			[ 'MediaWiki:Foo.CSS', true ],
			[ 'MediaWiki:Foo.css.xxx', true ],
			[ 'TEST-JS:Foo', false ],
			[ 'TEST-JS:Foo.js', false ],
		];
	}

	/**
	 * @dataProvider provideIsWikitextPage
	 * @covers Title::isWikitextPage
	 */
	public function testIsWikitextPage( $title, $expectedBool ) {
		$title = Title::newFromText( $title );
		$this->assertEquals( $expectedBool, $title->isWikitextPage() );
	}

	public static function provideGetOtherPage() {
		return [
			[ 'Main Page', 'Talk:Main Page' ],
			[ 'Talk:Main Page', 'Main Page' ],
			[ 'Help:Main Page', 'Help talk:Main Page' ],
			[ 'Help talk:Main Page', 'Help:Main Page' ],
			[ 'Special:FooBar', null ],
			[ 'Media:File.jpg', null ],
		];
	}

	/**
	 * @dataProvider provideGetOtherpage
	 * @covers Title::getOtherPage
	 *
	 * @param string $text
	 * @param string|null $expected
	 */
	public function testGetOtherPage( $text, $expected ) {
		if ( $expected === null ) {
			$this->expectException( MWException::class );
		}

		$title = Title::newFromText( $text );
		$this->assertEquals( $expected, $title->getOtherPage()->getPrefixedText() );
	}

	/**
	 * @covers Title::clearCaches
	 */
	public function testClearCaches() {
		$linkCache = MediaWikiServices::getInstance()->getLinkCache();

		$title1 = Title::newFromText( 'Foo' );
		$linkCache->addGoodLinkObj( 23, $title1 );

		Title::clearCaches();

		$title2 = Title::newFromText( 'Foo' );
		$this->assertNotSame( $title1, $title2, 'title cache should be empty' );
		$this->assertSame( 0, $linkCache->getGoodLinkID( 'Foo' ), 'link cache should be empty' );
	}

	public function provideGetLinkURL() {
		yield 'Simple' => [
			'/wiki/Goats',
			NS_MAIN,
			'Goats'
		];

		yield 'Fragment' => [
			'/wiki/Goats#Goatificatiön',
			NS_MAIN,
			'Goats',
			'Goatificatiön'
		];

		yield 'Fragment only (query is ignored)' => [
			'#Goatificatiön',
			NS_MAIN,
			'',
			'Goatificatiön',
			'',
			[
				'a' => 1,
			]
		];

		yield 'Unknown interwiki with fragment' => [
			'https://xx.wiki.test/wiki/xyzzy:Goats#Goatificatiön',
			NS_MAIN,
			'Goats',
			'Goatificatiön',
			'xyzzy'
		];

		yield 'Interwiki with fragment' => [
			'https://acme.test/Goats#Goatificati.C3.B6n',
			NS_MAIN,
			'Goats',
			'Goatificatiön',
			'acme'
		];

		yield 'Interwiki with query' => [
			'https://acme.test/Goats?a=1&b=blank+blank#Goatificati.C3.B6n',
			NS_MAIN,
			'Goats',
			'Goatificatiön',
			'acme',
			[
				'a' => 1,
				'b' => 'blank blank'
			]
		];

		yield 'Local interwiki with fragment' => [
			'https://yy.wiki.test/wiki/Goats#Goatificatiön',
			NS_MAIN,
			'Goats',
			'Goatificatiön',
			'yy'
		];
	}

	/**
	 * @dataProvider provideGetLinkURL
	 *
	 * @covers Title::getLinkURL
	 * @covers Title::getFullURL
	 * @covers Title::getLocalURL
	 * @covers Title::getFragmentForURL
	 */
	public function testGetLinkURL(
		$expected,
		$ns,
		$title,
		$fragment = '',
		$interwiki = '',
		$query = '',
		$query2 = false,
		$proto = false
	) {
		$this->setMwGlobals( [
			'wgServer' => 'https://xx.wiki.test',
			'wgArticlePath' => '/wiki/$1',
			'wgExternalInterwikiFragmentMode' => 'legacy',
			'wgFragmentMode' => [ 'html5', 'legacy' ]
		] );

		$title = Title::makeTitle( $ns, $title, $fragment, $interwiki );
		$this->assertSame( $expected, $title->getLinkURL( $query, $query2, $proto ) );
	}

	public function provideProperPage() {
		return [
			[ NS_MAIN, 'Test' ],
			[ NS_MAIN, 'User' ],
		];
	}

	/**
	 * @dataProvider provideProperPage
	 * @covers Title::toPageIdentity
	 */
	public function testToPageIdentity( $ns, $text ) {
		$title = Title::makeTitle( $ns, $text );

		$page = $title->toPageIdentity();

		$this->assertNotSame( $title, $page );
		$this->assertSame( $title->getId(), $page->getId() );
		$this->assertSame( $title->getNamespace(), $page->getNamespace() );
		$this->assertSame( $title->getDBkey(), $page->getDBkey() );
		$this->assertSame( $title->getWikiId(), $page->getWikiId() );
	}

	/**
	 * @dataProvider provideProperPage
	 * @covers Title::toPageRecord
	 */
	public function testToPageRecord( $ns, $text ) {
		$title = Title::makeTitle( $ns, $text );
		$wikiPage = $this->getExistingTestPage( $title );

		$record = $title->toPageRecord();

		$this->assertNotSame( $title, $record );
		$this->assertNotSame( $title, $wikiPage );

		$this->assertSame( $title->getId(), $record->getId() );
		$this->assertSame( $title->getNamespace(), $record->getNamespace() );
		$this->assertSame( $title->getDBkey(), $record->getDBkey() );
		$this->assertSame( $title->getWikiId(), $record->getWikiId() );

		$this->assertSame( $title->getLatestRevID(), $record->getLatest() );
		$this->assertSame( MWTimestamp::convert( TS_MW, $title->getTouched() ), $record->getTouched() );
		$this->assertSame( $title->isNewPage(), $record->isNew() );
		$this->assertSame( $title->isRedirect(), $record->isRedirect() );
	}

	/**
	 * @dataProvider provideImproperPage
	 * @covers Title::toPageRecord
	 */
	public function testToPageRecord_fail( $ns, $text, $fragment = '', $interwiki = '' ) {
		$title = Title::makeTitle( $ns, $text, $fragment, $interwiki );

		$this->expectException( PreconditionException::class );
		$title->toPageRecord();
	}

	public function provideImproperPage() {
		return [
			[ NS_MAIN, '' ],
			[ NS_MAIN, '<>' ],
			[ NS_MAIN, '|' ],
			[ NS_MAIN, '#' ],
			[ NS_PROJECT, '#test' ],
			[ NS_MAIN, '', 'test', 'acme' ],
			[ NS_MAIN, ' Test' ],
			[ NS_MAIN, '_Test' ],
			[ NS_MAIN, 'Test ' ],
			[ NS_MAIN, 'Test_' ],
			[ NS_MAIN, "Test\nthis" ],
			[ NS_MAIN, "Test\tthis" ],
			[ -33, 'Test' ],
			[ 77663399, 'Test' ],

			// Valid but can't exist
			[ NS_MAIN, '', 'test' ],
			[ NS_SPECIAL, 'Test' ],
			[ NS_MAIN, 'Test', '', 'acme' ],
		];
	}

	/**
	 * @dataProvider provideImproperPage
	 * @covers Title::getId
	 */
	public function testGetId_fail( $ns, $text, $fragment = '', $interwiki = '' ) {
		$title = Title::makeTitle( $ns, $text, $fragment, $interwiki );

		$this->expectException( PreconditionException::class );
		$title->getId();
	}

	/**
	 * @dataProvider provideImproperPage
	 * @covers Title::getId
	 */
	public function testGetId_fragment() {
		$title = Title::makeTitle( NS_MAIN, 'Test', 'References' );

		// should not throw
		$this->assertIsInt( $title->getId() );
	}

	/**
	 * @dataProvider provideImproperPage
	 * @covers Title::toPageIdentity
	 */
	public function testToPageIdentity_fail( $ns, $text, $fragment = '', $interwiki = '' ) {
		$title = Title::makeTitle( $ns, $text, $fragment, $interwiki );

		$this->expectException( PreconditionException::class );
		$title->toPageIdentity();
	}

	public function provideMakeTitle() {
		yield 'main namespace' => [ 'Foo', NS_MAIN, 'Foo' ];
		yield 'user namespace' => [ 'User:Foo', NS_USER, 'Foo' ];
		yield 'fragment' => [ 'Foo#Section', NS_MAIN, 'Foo', 'Section' ];
		yield 'only fragment' => [ '#Section', NS_MAIN, '', 'Section' ];
		yield 'interwiki' => [ 'acme:Foo', NS_MAIN, 'Foo', '', 'acme' ];
		yield 'normalized underscores' => [ 'Foo Bar', NS_MAIN, 'Foo_Bar' ];
	}

	/**
	 * @dataProvider provideMakeTitle
	 * @covers Title::makeTitle
	 */
	public function testMakeTitle( $expected, $ns, $text, $fragment = '', $interwiki = '' ) {
		$title = Title::makeTitle( $ns, $text, $fragment, $interwiki );

		$this->assertTrue( $title->isValid() );
		$this->assertSame( $expected, $title->getFullText() );
	}

	public function provideMakeTitle_invalid() {
		yield 'bad namespace' => [ 'Special:Badtitle/NS-1234:Foo', -1234, 'Foo' ];
		yield 'lower case' => [ 'User:foo', NS_USER, 'foo' ];
		yield 'empty' => [ '', NS_MAIN, '' ];
		yield 'bad character' => [ 'Foo|Bar', NS_MAIN, 'Foo|Bar' ];
		yield 'bad interwiki' => [ 'qwerty:Foo', NS_MAIN, 'Foo', null, 'qwerty' ];
	}

	/**
	 * @dataProvider provideMakeTitle_invalid
	 * @covers Title::makeTitle
	 */
	public function testMakeTitle_invalid( $expected, $ns, $text, $fragment = '', $interwiki = '' ) {
		$title = Title::makeTitle( $ns, $text, $fragment, $interwiki );

		$this->assertFalse( $title->isValid() );
		$this->assertSame( $expected, $title->getFullText() );
	}

	public function provideMakeTitleSafe() {
		yield 'main namespace' => [ 'Foo', NS_MAIN, 'Foo' ];
		yield 'user namespace' => [ 'User:Foo', NS_USER, 'Foo' ];
		yield 'fragment' => [ 'Foo#Section', NS_MAIN, 'Foo', 'Section' ];
		yield 'only fragment' => [ '#Section', NS_MAIN, '', 'Section' ];
		yield 'interwiki' => [ 'acme:Foo', NS_MAIN, 'Foo', '', 'acme' ];

		// Normalize
		yield 'normalized underscores' => [ 'Foo Bar', NS_MAIN, 'Foo_Bar' ];
		yield 'lower case' => [ 'User:Foo', NS_USER, 'foo' ];

		// Bad interwiki becomes part of the title text. Is this intentional?
		yield 'bad interwiki' => [ 'Qwerty:Foo', NS_MAIN, 'Foo', '', 'qwerty' ];
	}

	/**
	 * @dataProvider provideMakeTitleSafe
	 * @covers Title::makeTitleSafe
	 */
	public function testMakeTitleSafe( $expected, $ns, $text, $fragment = '', $interwiki = '' ) {
		$title = Title::makeTitleSafe( $ns, $text, $fragment, $interwiki );

		$this->assertTrue( $title->isValid() );
		$this->assertSame( $expected, $title->getFullText() );
	}

	public function provideMakeTitleSafe_invalid() {
		yield 'bad namespace' => [ -1234, 'Foo' ];
		yield 'empty' => [ '', NS_MAIN, '' ];
		yield 'bad character' => [ NS_MAIN, 'Foo|Bar' ];
	}

	/**
	 * @dataProvider provideMakeTitleSafe_invalid
	 * @covers Title::makeTitleSafe
	 */
	public function testMakeTitleSafe_invalid( $ns, $text, $fragment = '', $interwiki = '' ) {
		$title = Title::makeTitleSafe( $ns, $text, $fragment, $interwiki );

		$this->assertNull( $title );
	}

	/**
	 * @covers Title::getContentModel
	 * @covers Title::setContentModel
	 * @covers Title::uncache
	 */
	public function testSetContentModel() {
		// NOTE: must use newFromText to test behavior of internal instance cache (T281337)
		$title = Title::newFromText( 'Foo' );

		$title->setContentModel( CONTENT_MODEL_UNKNOWN );
		$this->assertSame( CONTENT_MODEL_UNKNOWN, $title->getContentModel() );

		// Ensure that the instance we get back from newFromText isn't the modified one.
		$title = Title::newFromText( 'Foo' );
		$this->assertNotSame( CONTENT_MODEL_UNKNOWN, $title->getContentModel() );
	}

	/**
	 * @covers Title::newFromID
	 * @covers Title::newFromIDs
	 * @covers Title::newFromRow
	 */
	public function testNewFromIds() {
		// First id
		$existingPage1 = $this->getExistingTestPage( 'UTest1' );
		$existingTitle1 = $existingPage1->getTitle();
		$existingId1 = $existingTitle1->getId();

		$this->assertGreaterThan( 0, $existingId1, 'Sanity: Existing test page should have a positive id' );

		$newFromId1 = Title::newFromID( $existingId1 );
		$this->assertInstanceOf( Title::class, $newFromId1, 'newFromID returns a title for an existing id' );
		$this->assertTrue(
			$newFromId1->equals( $existingTitle1 ),
			'newFromID returns the correct title'
		);

		// Second id
		$existingPage2 = $this->getExistingTestPage( 'UTest2' );
		$existingTitle2 = $existingPage2->getTitle();
		$existingId2 = $existingTitle2->getId();

		$this->assertGreaterThan( 0, $existingId2, 'Sanity: Existing test page should have a positive id' );

		$newFromId2 = Title::newFromID( $existingId2 );
		$this->assertInstanceOf( Title::class, $newFromId2, 'newFromID returns a title for an existing id' );
		$this->assertTrue(
			$newFromId2->equals( $existingTitle2 ),
			'newFromID returns the correct title'
		);

		// newFromIDs using both
		$titles = Title::newFromIDs( [ $existingId1, $existingId2 ] );
		$this->assertCount( 2, $titles );
		$this->assertTrue(
			$titles[0]->equals( $existingTitle1 ) &&
				$titles[1]->equals( $existingTitle2 ),
			'newFromIDs returns an array that matches the correct titles'
		);

		// newFromIds early return for an empty array of ids
		$this->assertSame( [], Title::newFromIDs( [] ) );
	}

	/**
	 * @covers Title::newFromID
	 */
	public function testNewFromMissingId() {
		// Testing return of null for an id that does not exist
		$maxPageId = (int)$this->db->selectField(
			'page',
			'max(page_id)',
			'',
			__METHOD__
		);
		$res = Title::newFromId( $maxPageId + 1 );
		$this->assertNull( $res, 'newFromID returns null for missing ids' );
	}

	public static function provideValidSecureAndSplit() {
		return [
			[ 'Sandbox' ],
			[ 'A "B"' ],
			[ 'A \'B\'' ],
			[ '.com' ],
			[ '~' ],
			[ '#' ],
			[ '"' ],
			[ '\'' ],
			[ 'Talk:Sandbox' ],
			[ 'Talk:Foo:Sandbox' ],
			[ 'File:Example.svg' ],
			[ 'File_talk:Example.svg' ],
			[ 'Foo/.../Sandbox' ],
			[ 'Sandbox/...' ],
			[ 'A~~' ],
			[ ':A' ],
			// Length is 256 total, but only title part matters
			[ 'Category:' . str_repeat( 'x', 248 ) ],
			[ str_repeat( 'x', 252 ) ],
			// interwiki prefix
			[ 'localtestiw: #anchor' ],
			[ 'localtestiw:' ],
			[ 'localtestiw:foo' ],
			[ 'localtestiw: foo # anchor' ],
			[ 'localtestiw: Talk: Sandbox # anchor' ],
			[ 'remotetestiw:' ],
			[ 'remotetestiw: Talk: # anchor' ],
			[ 'remotetestiw: #bar' ],
			[ 'remotetestiw: Talk:' ],
			[ 'remotetestiw: Talk: Foo' ],
			[ 'localtestiw:remotetestiw:' ],
			[ 'localtestiw:remotetestiw:foo' ]
		];
	}

	public static function provideInvalidSecureAndSplit() {
		return [
			[ '', 'title-invalid-empty' ],
			[ ':', 'title-invalid-empty' ],
			[ '__  __', 'title-invalid-empty' ],
			[ '  __  ', 'title-invalid-empty' ],
			// Bad characters forbidden regardless of wgLegalTitleChars
			[ 'A [ B', 'title-invalid-characters' ],
			[ 'A ] B', 'title-invalid-characters' ],
			[ 'A { B', 'title-invalid-characters' ],
			[ 'A } B', 'title-invalid-characters' ],
			[ 'A < B', 'title-invalid-characters' ],
			[ 'A > B', 'title-invalid-characters' ],
			[ 'A | B', 'title-invalid-characters' ],
			[ "A \t B", 'title-invalid-characters' ],
			[ "A \n B", 'title-invalid-characters' ],
			// URL encoding
			[ 'A%20B', 'title-invalid-characters' ],
			[ 'A%23B', 'title-invalid-characters' ],
			[ 'A%2523B', 'title-invalid-characters' ],
			// XML/HTML character entity references
			// Note: Commented out because they are not marked invalid by the PHP test as
			// Title::newFromText runs Sanitizer::decodeCharReferencesAndNormalize first.
			// 'A &eacute; B',
			// 'A &#233; B',
			// 'A &#x00E9; B',
			// Subject of NS_TALK does not roundtrip to NS_MAIN
			[ 'Talk:File:Example.svg', 'title-invalid-talk-namespace' ],
			// Directory navigation
			[ '.', 'title-invalid-relative' ],
			[ '..', 'title-invalid-relative' ],
			[ './Sandbox', 'title-invalid-relative' ],
			[ '../Sandbox', 'title-invalid-relative' ],
			[ 'Foo/./Sandbox', 'title-invalid-relative' ],
			[ 'Foo/../Sandbox', 'title-invalid-relative' ],
			[ 'Sandbox/.', 'title-invalid-relative' ],
			[ 'Sandbox/..', 'title-invalid-relative' ],
			// Tilde
			[ 'A ~~~ Name', 'title-invalid-magic-tilde' ],
			[ 'A ~~~~ Signature', 'title-invalid-magic-tilde' ],
			[ 'A ~~~~~ Timestamp', 'title-invalid-magic-tilde' ],
			// Length
			[ str_repeat( 'x', 256 ), 'title-invalid-too-long' ],
			// Namespace prefix without actual title
			[ 'Talk:', 'title-invalid-empty' ],
			[ 'Talk:#', 'title-invalid-empty' ],
			[ 'Category: ', 'title-invalid-empty' ],
			[ 'Category: #bar', 'title-invalid-empty' ],
			// interwiki prefix
			[ 'localtestiw: Talk: # anchor', 'title-invalid-empty' ],
			[ 'localtestiw: Talk:', 'title-invalid-empty' ]
		];
	}

	/**
	 * See also mediawiki.Title.test.js
	 * @covers Title::secureAndSplit
	 * @dataProvider provideValidSecureAndSplit
	 * @note This mainly tests MediaWikiTitleCodec::parseTitle().
	 */
	public function testSecureAndSplitValid( $text ) {
		$this->assertInstanceOf( Title::class, Title::newFromText( $text ), "Valid: $text" );
	}

	/**
	 * See also mediawiki.Title.test.js
	 * @covers Title::secureAndSplit
	 * @dataProvider provideInvalidSecureAndSplit
	 * @note This mainly tests MediaWikiTitleCodec::parseTitle().
	 */
	public function testSecureAndSplitInvalid( $text, $expectedErrorMessage ) {
		try {
			Title::newFromTextThrow( $text ); // should throw
			$this->fail( "Title::newFromTextThrow should have thrown with $text" );
		} catch ( MalformedTitleException $ex ) {
			$this->assertEquals( $expectedErrorMessage, $ex->getErrorMessage(), "Invalid: $text" );
		}
	}

	public static function provideSpecialNamesWithAndWithoutParameter() {
		return [
			[ 'Special:Version', null ],
			[ 'Special:Version/', '' ],
			[ 'Special:Version/param', 'param' ],
		];
	}

	/**
	 * @dataProvider provideSpecialNamesWithAndWithoutParameter
	 * @covers Title::fixSpecialName
	 */
	public function testFixSpecialNameRetainsParameter( $text, $expectedParam ) {
		$title = Title::newFromText( $text );
		$fixed = $title->fixSpecialName();
		$stuff = explode( '/', $fixed->getDBkey(), 2 );
		if ( count( $stuff ) == 2 ) {
			$par = $stuff[1];
		} else {
			$par = null;
		}
		$this->assertEquals(
			$expectedParam,
			$par,
			"T33100 regression check: Title->fixSpecialName() should preserve parameter"
		);
	}

	public function flattenErrorsArray( $errors ) {
		$result = [];
		foreach ( $errors as $error ) {
			$result[] = $error[0];
		}

		return $result;
	}

	public static function provideGetPageViewLanguage() {
		# Format:
		# - expected
		# - Title name
		# - content language (expected in most cases)
		# - wgLang (on some specific pages)
		# - wgDefaultLanguageVariant
		return [
			[ 'fr', 'Help:I_need_somebody', 'fr', 'fr', false ],
			[ 'es', 'Help:I_need_somebody', 'es', 'zh-tw', false ],
			[ 'zh', 'Help:I_need_somebody', 'zh', 'zh-tw', false ],

			[ 'es', 'Help:I_need_somebody', 'es', 'zh-tw', 'zh-cn' ],
			[ 'es', 'MediaWiki:About', 'es', 'zh-tw', 'zh-cn' ],
			[ 'es', 'MediaWiki:About/', 'es', 'zh-tw', 'zh-cn' ],
			[ 'de', 'MediaWiki:About/de', 'es', 'zh-tw', 'zh-cn' ],
			[ 'en', 'MediaWiki:Common.js', 'es', 'zh-tw', 'zh-cn' ],
			[ 'en', 'MediaWiki:Common.css', 'es', 'zh-tw', 'zh-cn' ],
			[ 'en', 'User:JohnDoe/Common.js', 'es', 'zh-tw', 'zh-cn' ],
			[ 'en', 'User:JohnDoe/Monobook.css', 'es', 'zh-tw', 'zh-cn' ],

			[ 'zh-cn', 'Help:I_need_somebody', 'zh', 'zh-tw', 'zh-cn' ],
			[ 'zh', 'MediaWiki:About', 'zh', 'zh-tw', 'zh-cn' ],
			[ 'zh', 'MediaWiki:About/', 'zh', 'zh-tw', 'zh-cn' ],
			[ 'de', 'MediaWiki:About/de', 'zh', 'zh-tw', 'zh-cn' ],
			[ 'zh-cn', 'MediaWiki:About/zh-cn', 'zh', 'zh-tw', 'zh-cn' ],
			[ 'zh-tw', 'MediaWiki:About/zh-tw', 'zh', 'zh-tw', 'zh-cn' ],
			[ 'en', 'MediaWiki:Common.js', 'zh', 'zh-tw', 'zh-cn' ],
			[ 'en', 'MediaWiki:Common.css', 'zh', 'zh-tw', 'zh-cn' ],
			[ 'en', 'User:JohnDoe/Common.js', 'zh', 'zh-tw', 'zh-cn' ],
			[ 'en', 'User:JohnDoe/Monobook.css', 'zh', 'zh-tw', 'zh-cn' ],

			[ 'zh-tw', 'Special:NewPages', 'es', 'zh-tw', 'zh-cn' ],
			[ 'zh-tw', 'Special:NewPages', 'zh', 'zh-tw', 'zh-cn' ],

		];
	}

	/**
	 * @dataProvider provideGetPageViewLanguage
	 * @covers Title::getPageViewLanguage
	 */
	public function testGetPageViewLanguage( $expected, $titleText, $contLang,
		$lang, $variant, $msg = ''
	) {
		// Setup environnement for this test
		$this->setMwGlobals( [
			'wgDefaultLanguageVariant' => $variant,
			'wgAllowUserJs' => true,
		] );
		$this->setUserLang( $lang );
		$this->setMwGlobals( 'wgLanguageCode', $contLang );

		$title = Title::newFromText( $titleText );
		$this->assertInstanceOf( Title::class, $title,
			"Test must be passed a valid title text, you gave '$titleText'"
		);
		$this->assertEquals( $expected,
			$title->getPageViewLanguage()->getCode(),
			$msg
		);
	}

	public function provideSubpage() {
		// NOTE: avoid constructing Title objects in the provider, since it may access the database.
		return [
			[ 'Foo', 'x', new TitleValue( NS_MAIN, 'Foo/x' ) ],
			[ 'Foo#bar', 'x', new TitleValue( NS_MAIN, 'Foo/x' ) ],
			[ 'User:Foo', 'x', new TitleValue( NS_USER, 'Foo/x' ) ],
			[ 'wiki:User:Foo', 'x', new TitleValue( NS_MAIN, 'User:Foo/x', '', 'wiki' ) ],
		];
	}

	/**
	 * @dataProvider provideSubpage
	 * @covers Title::getSubpage
	 */
	public function testSubpage( $title, $sub, LinkTarget $expected ) {
		$title = Title::newFromText( $title );
		$expected = Title::newFromLinkTarget( $expected );
		$actual = $title->getSubpage( $sub );

		// NOTE: convert to string for comparison
		$this->assertSame( $expected->getPrefixedText(), $actual->getPrefixedText(), 'text form' );
		$this->assertTrue( $expected->equals( $actual ), 'Title equality' );
	}

	public static function provideIsAlwaysKnown() {
		return [
			[ 'Some nonexistent page', false ],
			[ 'UTPage', false ],
			[ '#test', true ],
			[ 'Special:BlankPage', true ],
			[ 'Special:SomeNonexistentSpecialPage', false ],
			[ 'MediaWiki:Parentheses', true ],
			[ 'MediaWiki:Some nonexistent message', false ],
		];
	}

	/**
	 * @covers Title::isAlwaysKnown
	 * @dataProvider provideIsAlwaysKnown
	 * @param string $page
	 * @param bool $isKnown
	 */
	public function testIsAlwaysKnown( $page, $isKnown ) {
		$title = Title::newFromText( $page );
		$this->assertEquals( $isKnown, $title->isAlwaysKnown() );
	}

	public static function provideIsValid() {
		return [
			[ Title::makeTitle( NS_MAIN, '' ), false ],
			[ Title::makeTitle( NS_MAIN, '<>' ), false ],
			[ Title::makeTitle( NS_MAIN, '|' ), false ],
			[ Title::makeTitle( NS_MAIN, '#' ), false ],
			[ Title::makeTitle( NS_PROJECT, '#' ), false ],
			[ Title::makeTitle( NS_MAIN, '', 'test' ), true ],
			[ Title::makeTitle( NS_PROJECT, '#test' ), false ],
			[ Title::makeTitle( NS_MAIN, '', 'test', 'wikipedia' ), true ],
			[ Title::makeTitle( NS_MAIN, 'Test', '', 'wikipedia' ), true ],
			[ Title::makeTitle( NS_MAIN, 'Test' ), true ],
			[ Title::makeTitle( NS_SPECIAL, 'Test' ), true ],
			[ Title::makeTitle( NS_MAIN, ' Test' ), false ],
			[ Title::makeTitle( NS_MAIN, '_Test' ), false ],
			[ Title::makeTitle( NS_MAIN, 'Test ' ), false ],
			[ Title::makeTitle( NS_MAIN, 'Test_' ), false ],
			[ Title::makeTitle( NS_MAIN, "Test\nthis" ), false ],
			[ Title::makeTitle( NS_MAIN, "Test\tthis" ), false ],
			[ Title::makeTitle( -33, 'Test' ), false ],
			[ Title::makeTitle( 77663399, 'Test' ), false ],
		];
	}

	/**
	 * @covers Title::isValid
	 * @dataProvider provideIsValid
	 * @param Title $title
	 * @param bool $isValid
	 */
	public function testIsValid( Title $title, $isValid ) {
		$this->assertEquals( $isValid, $title->isValid(), $title->getFullText() );
	}

	public static function provideIsValidRedirectTarget() {
		return [
			[ Title::makeTitle( NS_MAIN, '' ), false ],
			[ Title::makeTitle( NS_MAIN, '', 'test' ), false ],
			[ Title::makeTitle( NS_MAIN, 'Foo', 'test' ), true ],
			[ Title::makeTitle( NS_MAIN, '<>' ), false ],
			[ Title::makeTitle( NS_MAIN, '_' ), false ],
			[ Title::makeTitle( NS_MAIN, 'Test', '', 'acme' ), true ],
			[ Title::makeTitle( NS_SPECIAL, 'UserLogout' ), false ],
			[ Title::makeTitle( NS_SPECIAL, 'RecentChanges' ), true ],
		];
	}

	/**
	 * @covers Title::isValidRedirectTarget
	 * @dataProvider provideIsValidRedirectTarget
	 * @param Title $title
	 * @param bool $isValid
	 */
	public function testIsValidRedirectTarget( Title $title, $isValid ) {
		// InterwikiLookup is configured in setUp()
		$this->assertEquals( $isValid, $title->isValidRedirectTarget(), $title->getFullText() );
	}

	public static function provideCanExist() {
		return [
			[ Title::makeTitle( NS_MAIN, '' ), false ],
			[ Title::makeTitle( NS_MAIN, '<>' ), false ],
			[ Title::makeTitle( NS_MAIN, '|' ), false ],
			[ Title::makeTitle( NS_MAIN, '#' ), false ],
			[ Title::makeTitle( NS_PROJECT, '#test' ), false ],
			[ Title::makeTitle( NS_MAIN, '', 'test', 'acme' ), false ],
			[ Title::makeTitle( NS_MAIN, 'Test' ), true ],
			[ Title::makeTitle( NS_MAIN, ' Test' ), false ],
			[ Title::makeTitle( NS_MAIN, '_Test' ), false ],
			[ Title::makeTitle( NS_MAIN, 'Test ' ), false ],
			[ Title::makeTitle( NS_MAIN, 'Test_' ), false ],
			[ Title::makeTitle( NS_MAIN, "Test\nthis" ), false ],
			[ Title::makeTitle( NS_MAIN, "Test\tthis" ), false ],
			[ Title::makeTitle( -33, 'Test' ), false ],
			[ Title::makeTitle( 77663399, 'Test' ), false ],

			// Valid but can't exist
			[ Title::makeTitle( NS_MAIN, '', 'test' ), false ],
			[ Title::makeTitle( NS_SPECIAL, 'Test' ), false ],
			[ Title::makeTitle( NS_MAIN, 'Test', '', 'acme' ), false ],
		];
	}

	/**
	 * @covers Title::canExist
	 * @dataProvider provideCanExist
	 * @param Title $title
	 * @param bool $canExist
	 */
	public function testCanExist( Title $title, $canExist ) {
		$this->assertEquals( $canExist, $title->canExist(), $title->getFullText() );
	}

	/**
	 * @covers Title::isAlwaysKnown
	 */
	public function testIsAlwaysKnownOnInterwiki() {
		$title = Title::makeTitle( NS_MAIN, 'Interwiki link', '', 'externalwiki' );
		$this->assertTrue( $title->isAlwaysKnown() );
	}

	public function provideGetSkinFromConfigSubpage() {
		return [
			[ 'User:Foo', '' ],
			[ 'User:Foo.css', '' ],
			[ 'User:Foo/', '' ],
			[ 'User:Foo/bar', '' ],
			[ 'User:Foo./bar', '' ],
			[ 'User:Foo/bar.', 'bar' ],
			[ 'User:Foo/bar.css', 'bar' ],
			[ '/bar.css', '' ],
			[ '//bar.css', 'bar' ],
			[ '.css', '' ],
		];
	}

	/**
	 * @dataProvider provideGetSkinFromConfigSubpage
	 * @covers Title::getSkinFromConfigSubpage
	 */
	public function testGetSkinFromConfigSubpage( $title, $expected ) {
		$title = Title::newFromText( $title );
		$this->assertSame( $expected, $title->getSkinFromConfigSubpage() );
	}

	/**
	 * @covers Title::getWikiId
	 */
	public function testGetWikiId() {
		$title = Title::newFromText( 'Foo' );
		$this->assertFalse( $title->getWikiId() );
	}

	/**
	 * @covers Title::getFragment
	 * @covers Title::getFragment
	 * @covers Title::uncache
	 */
	public function testSetFragment() {
		// NOTE: must use newFromText to test behavior of internal instance cache (T281337)
		$title = Title::newFromText( 'Foo' );

		$title->setFragment( '#Xyzzy' );
		$this->assertSame( 'Xyzzy', $title->getFragment() );

		// Ensure that the instance we get back from newFromText isn't the modified one.
		$title = Title::newFromText( 'Foo' );
		$this->assertNotSame( 'Xyzzy', $title->getFragment() );
	}

	/**
	 * @covers Title::__clone
	 */
	public function testClone() {
		// NOTE: must use newFromText to test behavior of internal instance cache (T281337)
		$title = Title::newFromText( 'Foo' );

		$clone = clone $title;
		$clone->setFragment( '#Xyzzy' );

		// Ensure that the instance we get back from newFromText is the original one
		$title2 = Title::newFromText( 'Foo' );
		$this->assertSame( $title, $title2 );
	}

	public static function provideBaseTitleCases() {
		return [
			# Namespace, Title text, expected base
			[ NS_USER, 'John_Doe', 'John Doe' ],
			[ NS_USER, 'John_Doe/subOne/subTwo', 'John Doe/subOne' ],
			[ NS_USER, 'Foo / Bar / Baz', 'Foo / Bar ' ],
			[ NS_USER, 'Foo/', 'Foo' ],
			[ NS_USER, 'Foo/Bar/', 'Foo/Bar' ],
			[ NS_USER, '/', '/' ],
			[ NS_USER, '//', '/' ],
			[ NS_USER, '/oops/', '/oops' ],
			[ NS_USER, '/indeed', '/indeed' ],
			[ NS_USER, '//indeed', '/' ],
			[ NS_USER, '/Ramba/Zamba/Mamba/', '/Ramba/Zamba/Mamba' ],
			[ NS_USER, '//x//y//z//', '//x//y//z/' ],
		];
	}

	/**
	 * @dataProvider provideBaseTitleCases
	 * @covers Title::getBaseText
	 */
	public function testGetBaseText( $namespace, $title, $expected ) {
		$title = Title::makeTitle( $namespace, $title );
		$this->assertSame( $expected, $title->getBaseText() );
	}

	/**
	 * @dataProvider provideBaseTitleCases
	 * @covers Title::getBaseTitle
	 */
	public function testGetBaseTitle( $namespace, $title, $expected ) {
		$title = Title::makeTitle( $namespace, $title );
		$base = $title->getBaseTitle();
		$this->assertTrue( $base->isValid() );
		$this->assertTrue(
			$base->equals( Title::makeTitleSafe( $title->getNamespace(), $expected ) )
		);
	}

	public static function provideRootTitleCases() {
		return [
			# Namespace, Title, expected base
			[ NS_USER, 'John_Doe', 'John Doe' ],
			[ NS_USER, 'John_Doe/subOne/subTwo', 'John Doe' ],
			[ NS_USER, 'Foo / Bar / Baz', 'Foo ' ],
			[ NS_USER, 'Foo/', 'Foo' ],
			[ NS_USER, 'Foo/Bar/', 'Foo' ],
			[ NS_USER, '/', '/' ],
			[ NS_USER, '//', '/' ],
			[ NS_USER, '/oops/', '/oops' ],
			[ NS_USER, '/Ramba/Zamba/Mamba/', '/Ramba' ],
			[ NS_USER, '//x//y//z//', '//x' ],
			[ NS_TALK, '////', '///' ],
			[ NS_TEMPLATE, '////', '///' ],
			[ NS_TEMPLATE, 'Foo////', 'Foo' ],
			[ NS_TEMPLATE, 'Foo////Bar', 'Foo' ],
		];
	}

	/**
	 * @dataProvider provideRootTitleCases
	 * @covers Title::getRootText
	 */
	public function testGetRootText( $namespace, $title, $expected ) {
		$title = Title::makeTitle( $namespace, $title );
		$this->assertEquals( $expected, $title->getRootText() );
	}

	/**
	 * @dataProvider provideRootTitleCases
	 * @covers Title::getRootTitle
	 */
	public function testGetRootTitle( $namespace, $title, $expected ) {
		$title = Title::makeTitle( $namespace, $title );
		$root = $title->getRootTitle();
		$this->assertTrue( $root->isValid() );
		$this->assertTrue(
			$root->equals( Title::makeTitleSafe( $title->getNamespace(), $expected ) )
		);
	}

	public static function provideSubpageTitleCases() {
		return [
			# Namespace, Title, expected base
			[ NS_USER, 'John_Doe', 'John Doe' ],
			[ NS_USER, 'John_Doe/subOne/subTwo', 'subTwo' ],
			[ NS_USER, 'John_Doe/subOne', 'subOne' ],
			[ NS_USER, '/', '/' ],
			[ NS_USER, '//', '' ],
			[ NS_USER, '/oops/', '' ],
			[ NS_USER, '/indeed', '/indeed' ],
			[ NS_USER, '//indeed', 'indeed' ],
			[ NS_USER, '/Ramba/Zamba/Mamba/', '' ],
			[ NS_USER, '//x//y//z//', '' ],
			[ NS_TEMPLATE, 'Foo', 'Foo' ],
			[ NS_CATEGORY, 'Foo', 'Foo' ],
			[ NS_MAIN, 'Bar', 'Bar' ],
		];
	}

	/**
	 * @dataProvider provideSubpageTitleCases
	 * @covers Title::getSubpageText
	 */
	public function testGetSubpageText( $namespace, $title, $expected ) {
		$title = Title::makeTitle( $namespace, $title );
		$this->assertEquals( $expected, $title->getSubpageText() );
	}

	public static function provideGetTitleValue() {
		return [
			[ 'Foo' ],
			[ 'Foo#bar' ],
			[ 'User:Hansi_Maier' ],
		];
	}

	/**
	 * @covers Title::getTitleValue
	 * @dataProvider provideGetTitleValue
	 */
	public function testGetTitleValue( $text ) {
		$title = Title::newFromText( $text );
		$value = $title->getTitleValue();

		$dbkey = str_replace( ' ', '_', $value->getText() );
		$this->assertEquals( $title->getDBkey(), $dbkey );
		$this->assertEquals( $title->getNamespace(), $value->getNamespace() );
		$this->assertEquals( $title->getFragment(), $value->getFragment() );
	}

	public static function provideGetFragment() {
		return [
			[ 'Foo', '' ],
			[ 'Foo#bar', 'bar' ],
			[ 'Foo#bär', 'bär' ],

			// Inner whitespace is normalized
			[ 'Foo#bar_bar', 'bar bar' ],
			[ 'Foo#bar bar', 'bar bar' ],
			[ 'Foo#bar   bar', 'bar bar' ],

			// Leading whitespace is kept, trailing whitespace is trimmed.
			// XXX: Is this really want we want?
			[ 'Foo#_bar_bar_', ' bar bar' ],
			[ 'Foo# bar bar ', ' bar bar' ],
		];
	}

	/**
	 * @covers Title::getFragment
	 * @dataProvider provideGetFragment
	 *
	 * @param string $full
	 * @param string $fragment
	 */
	public function testGetFragment( $full, $fragment ) {
		$title = Title::newFromText( $full );
		$this->assertEquals( $fragment, $title->getFragment() );
	}

	/**
	 * @covers Title::exists
	 */
	public function testExists() {
		$title = Title::makeTitle( NS_PROJECT, 'New page' );
		$linkCache = MediaWikiServices::getInstance()->getLinkCache();

		$article = new Article( $title );
		$page = $article->getPage();
		$page->doUserEditContent(
			new WikitextContent( 'Some [[link]]' ),
			$this->getTestSysop()->getUser(),
			'summary'
		);

		// Tell Title it doesn't know whether it exists
		$title->mArticleID = -1;

		// Tell the link cache it doesn't exist when it really does
		$linkCache->clearLink( $title );
		$linkCache->addBadLinkObj( $title );

		$this->assertFalse(
			$title->exists(),
			'exists() should rely on link cache unless READ_LATEST is used'
		);
		$this->assertTrue(
			$title->exists( Title::READ_LATEST ),
			'exists() should re-query database when READ_LATEST is used'
		);
	}

	/**
	 * @covers Title::getArticleID
	 * @covers Title::getId
	 */
	public function testGetArticleID() {
		$title = Title::makeTitle( NS_PROJECT, __METHOD__ );
		$this->assertSame( 0, $title->getArticleID() );
		$this->assertSame( $title->getArticleID(), $title->getId() );

		$article = new Article( $title );
		$page = $article->getPage();
		$page->doUserEditContent(
			new WikitextContent( 'Some [[link]]' ),
			$this->getTestSysop()->getUser(),
			'summary'
		);

		$this->assertGreaterThan( 0, $title->getArticleID() );
		$this->assertSame( $title->getArticleID(), $title->getId() );
	}

	public function provideNonProperTitles() {
		return [
			'section link' => [ Title::makeTitle( NS_MAIN, '', 'Section' ) ],
			'empty' => [ Title::makeTitle( NS_MAIN, '' ) ],
			'bad chars' => [ Title::makeTitle( NS_MAIN, '_|_' ) ],
			'empty in namspace' => [ Title::makeTitle( NS_USER, '' ) ],
			'special' => [ Title::makeTitle( NS_SPECIAL, 'RecentChanges' ) ],
			'interwiki' => [ Title::makeTitle( NS_MAIN, 'Test', '', 'acme' ) ],
		];
	}

	/**
	 * @dataProvider provideNonProperTitles
	 * @covers Title::getArticleID
	 */
	public function testGetArticleIDFromNonProperTitle( $title ) {
		// make sure nothing explodes
		$this->assertSame( 0, $title->getArticleID() );
	}

	public function provideCanHaveTalkPage() {
		return [
			'User page has talk page' => [
				Title::makeTitle( NS_USER, 'Jane' ), true
			],
			'Talke page has talk page' => [
				Title::makeTitle( NS_TALK, 'Foo' ), true
			],
			'Special page cannot have talk page' => [
				Title::makeTitle( NS_SPECIAL, 'Thing' ), false
			],
			'Virtual namespace cannot have talk page' => [
				Title::makeTitle( NS_MEDIA, 'Kitten.jpg' ), false
			],
			'Relative link has no talk page' => [
				Title::makeTitle( NS_MAIN, '', 'Kittens' ), false
			],
			'Interwiki link has no talk page' => [
				Title::makeTitle( NS_MAIN, 'Kittens', '', 'acme' ), false
			],
		];
	}

	public function provideIsWatchable() {
		return [
			'User page is watchable' => [
				Title::makeTitle( NS_USER, 'Jane' ), true
			],
			'Talk page is watchable' => [
				Title::makeTitle( NS_TALK, 'Foo' ), true
			],
			'Special page is not watchable' => [
				Title::makeTitle( NS_SPECIAL, 'Thing' ), false
			],
			'Virtual namespace is not watchable' => [
				Title::makeTitle( NS_MEDIA, 'Kitten.jpg' ), false
			],
			'Relative link is not watchable' => [
				Title::makeTitle( NS_MAIN, '', 'Kittens' ), false
			],
			'Interwiki link is not watchable' => [
				Title::makeTitle( NS_MAIN, 'Kittens', '', 'acme' ), false
			],
			'Invalid title is not watchable' => [
				Title::makeTitle( NS_MAIN, '<' ), false
			]
		];
	}

	public static function provideGetTalkPage_good() {
		return [
			[ Title::makeTitle( NS_MAIN, 'Test' ), Title::makeTitle( NS_TALK, 'Test' ) ],
			[ Title::makeTitle( NS_TALK, 'Test' ), Title::makeTitle( NS_TALK, 'Test' ) ],
		];
	}

	public static function provideGetTalkPage_bad() {
		return [
			[ Title::makeTitle( NS_SPECIAL, 'Test' ) ],
			[ Title::makeTitle( NS_MEDIA, 'Test' ) ],
		];
	}

	public static function provideGetTalkPage_broken() {
		// These cases *should* be bad, but are not treated as bad, for backwards compatibility.
		// See discussion on T227817.
		return [
			[
				Title::makeTitle( NS_MAIN, '', 'Kittens' ),
				Title::makeTitle( NS_TALK, '' ), // Section is lost!
				false,
			],
			[
				Title::makeTitle( NS_MAIN, 'Kittens', '', 'acme' ),
				Title::makeTitle( NS_TALK, 'Kittens', '' ), // Interwiki prefix is lost!
				true,
			],
		];
	}

	public static function provideGetSubjectPage_good() {
		return [
			[ Title::makeTitle( NS_TALK, 'Test' ), Title::makeTitle( NS_MAIN, 'Test' ) ],
			[ Title::makeTitle( NS_MAIN, 'Test' ), Title::makeTitle( NS_MAIN, 'Test' ) ],
		];
	}

	public static function provideGetOtherPage_good() {
		return [
			[ Title::makeTitle( NS_MAIN, 'Test' ), Title::makeTitle( NS_TALK, 'Test' ) ],
			[ Title::makeTitle( NS_TALK, 'Test' ), Title::makeTitle( NS_MAIN, 'Test' ) ],
		];
	}

	/**
	 * @dataProvider provideCanHaveTalkPage
	 * @covers Title::canHaveTalkPage
	 *
	 * @param Title $title
	 * @param bool $expected
	 */
	public function testCanHaveTalkPage( Title $title, $expected ) {
		$actual = $title->canHaveTalkPage();
		$this->assertSame( $expected, $actual, $title->getPrefixedDBkey() );
	}

	/**
	 * @dataProvider provideIsWatchable
	 * @covers Title::isWatchable
	 *
	 * @param Title $title
	 * @param bool $expected
	 */
	public function testIsWatchable( Title $title, $expected ) {
		$this->hideDeprecated( 'Title::isWatchable' );
		$actual = $title->isWatchable();
		$this->assertSame( $expected, $actual, $title->getPrefixedDBkey() );
	}

	/**
	 * @dataProvider provideGetTalkPage_good
	 * @covers Title::getTalkPageIfDefined
	 */
	public function testGetTalkPage_good( Title $title, Title $expected ) {
		$actual = $title->getTalkPage();
		$this->assertTrue( $expected->equals( $actual ), $title->getPrefixedDBkey() );
	}

	/**
	 * @dataProvider provideGetTalkPage_bad
	 * @covers Title::getTalkPageIfDefined
	 */
	public function testGetTalkPage_bad( Title $title ) {
		$this->expectException( MWException::class );
		$title->getTalkPage();
	}

	/**
	 * @dataProvider provideGetTalkPage_broken
	 * @covers Title::getTalkPageIfDefined
	 */
	public function testGetTalkPage_broken( Title $title, Title $expected, $valid ) {
		$errorLevel = error_reporting( E_ERROR );

		// NOTE: Eventually we want to throw in this case. But while there is still code that
		// calls this method without checking, we want to avoid fatal errors.
		// See discussion on T227817.
		$result = $title->getTalkPage();
		$this->assertTrue( $expected->equals( $result ) );
		$this->assertSame( $valid, $result->isValid() );

		error_reporting( $errorLevel );
	}

	/**
	 * @dataProvider provideGetTalkPage_good
	 * @covers Title::getTalkPageIfDefined
	 */
	public function testGetTalkPageIfDefined_good( Title $title, Title $expected ) {
		$actual = $title->getTalkPageIfDefined();
		$this->assertNotNull( $actual, $title->getPrefixedDBkey() );
		$this->assertTrue( $expected->equals( $actual ), $title->getPrefixedDBkey() );
	}

	/**
	 * @dataProvider provideGetTalkPage_bad
	 * @covers Title::getTalkPageIfDefined
	 */
	public function testGetTalkPageIfDefined_bad( Title $title ) {
		$talk = $title->getTalkPageIfDefined();
		$this->assertNull(
			$talk,
			$title->getPrefixedDBkey()
		);
	}

	/**
	 * @dataProvider provideGetSubjectPage_good
	 * @covers Title::getSubjectPage
	 */
	public function testGetSubjectPage_good( Title $title, Title $expected ) {
		$actual = $title->getSubjectPage();
		$this->assertTrue( $expected->equals( $actual ), $title->getPrefixedDBkey() );
	}

	/**
	 * @dataProvider provideGetOtherPage_good
	 * @covers Title::getOtherPage
	 */
	public function testGetOtherPage_good( Title $title, Title $expected ) {
		$actual = $title->getOtherPage();
		$this->assertTrue( $expected->equals( $actual ), $title->getPrefixedDBkey() );
	}

	/**
	 * @dataProvider provideGetTalkPage_bad
	 * @covers Title::getOtherPage
	 */
	public function testGetOtherPage_bad( Title $title ) {
		$this->expectException( MWException::class );
		$title->getOtherPage();
	}

	public static function provideIsMovable() {
		return [
			'Simple title' => [ 'Foo', true ],
			// @todo Should these next two really be true?
			'Empty name' => [ Title::makeTitle( NS_MAIN, '' ), true ],
			'Invalid name' => [ Title::makeTitle( NS_MAIN, '<' ), true ],
			'Interwiki' => [ Title::makeTitle( NS_MAIN, 'Test', '', 'otherwiki' ), false ],
			'Special page' => [ 'Special:FooBar', false ],
			'Aborted by hook' => [ 'Hooked in place', false,
				static function ( Title $title, &$result ) {
					$result = false;
				}
			],
		];
	}

	/**
	 * @dataProvider provideIsMovable
	 * @covers Title::isMovable
	 *
	 * @param string|Title $title
	 * @param bool $expected
	 * @param callable|null $hookCallback For TitleIsMovable
	 */
	public function testIsMovable( $title, $expected, $hookCallback = null ) {
		if ( $hookCallback ) {
			$this->setTemporaryHook( 'TitleIsMovable', $hookCallback );
		}
		if ( is_string( $title ) ) {
			$title = Title::newFromText( $title );
		}

		$this->assertSame( $expected, $title->isMovable() );
	}

	public function provideGetPrefixedText() {
		return [
			// ns = 0
			[
				Title::makeTitle( NS_MAIN, 'Foo bar' ),
				'Foo bar'
			],
			// ns = 2
			[
				Title::makeTitle( NS_USER, 'Foo bar' ),
				'User:Foo bar'
			],
			// ns = 3
			[
				Title::makeTitle( NS_USER_TALK, 'Foo bar' ),
				'User talk:Foo bar'
			],
			// fragment not included
			[
				Title::makeTitle( NS_MAIN, 'Foo bar', 'fragment' ),
				'Foo bar'
			],
			// ns = -2
			[
				Title::makeTitle( NS_MEDIA, 'Foo bar' ),
				'Media:Foo bar'
			],
			// non-existent namespace
			[
				Title::makeTitle( 100777, 'Foo bar' ),
				'Special:Badtitle/NS100777:Foo bar'
			],
		];
	}

	/**
	 * @covers Title::getPrefixedText
	 * @dataProvider provideGetPrefixedText
	 */
	public function testGetPrefixedText( Title $title, $expected ) {
		$this->assertEquals( $expected, $title->getPrefixedText() );
	}

	public function provideGetPrefixedDBKey() {
		return [
			// ns = 0
			[
				Title::makeTitle( NS_MAIN, 'Foo_bar' ),
				'Foo_bar'
			],
			// ns = 2
			[
				Title::makeTitle( NS_USER, 'Foo_bar' ),
				'User:Foo_bar'
			],
			// ns = 3
			[
				Title::makeTitle( NS_USER_TALK, 'Foo_bar' ),
				'User_talk:Foo_bar'
			],
			// fragment not included
			[
				Title::makeTitle( NS_MAIN, 'Foo_bar', 'fragment' ),
				'Foo_bar'
			],
			// ns = -2
			[
				Title::makeTitle( NS_MEDIA, 'Foo_bar' ),
				'Media:Foo_bar'
			],
			// non-existent namespace
			[
				Title::makeTitle( 100777, 'Foo_bar' ),
				'Special:Badtitle/NS100777:Foo_bar'
			],
		];
	}

	/**
	 * @covers Title::getPrefixedDBKey
	 * @dataProvider provideGetPrefixedDBKey
	 */
	public function testGetPrefixedDBKey( Title $title, $expected ) {
		$this->assertEquals( $expected, $title->getPrefixedDBkey() );
	}

	public function provideGetFragmentForURL() {
		return [
			[ 'Foo', '' ],
			[ 'Foo#ümlåût', '#ümlåût' ],
			[ 'de:Foo#Bå®', '#Bå®' ],
			[ 'zz:Foo#тест', '#.D1.82.D0.B5.D1.81.D1.82' ],
		];
	}

	/**
	 * @covers Title::getFragmentForURL
	 * @dataProvider provideGetFragmentForURL
	 *
	 * @param string $titleStr
	 * @param string $expected
	 */
	public function testGetFragmentForURL( $titleStr, $expected ) {
		$this->setMwGlobals( [
			'wgFragmentMode' => [ 'html5' ],
			'wgExternalInterwikiFragmentMode' => 'legacy',
		] );
		// InterwikiLookup is configured in setUp()

		$title = Title::newFromText( $titleStr );
		self::assertEquals( $expected, $title->getFragmentForURL() );
	}

	public function provideIsRawHtmlMessage() {
		return [
			[ 'MediaWiki:Foobar', true ],
			[ 'MediaWiki:Foo bar', true ],
			[ 'MediaWiki:Foo-bar', true ],
			[ 'MediaWiki:foo bar', true ],
			[ 'MediaWiki:foo-bar', true ],
			[ 'MediaWiki:foobar', true ],
			[ 'MediaWiki:some-other-message', false ],
			[ 'Main Page', false ],
		];
	}

	/**
	 * @covers Title::isRawHtmlMessage
	 * @dataProvider provideIsRawHtmlMessage
	 */
	public function testIsRawHtmlMessage( $textForm, $expected ) {
		$this->setMwGlobals( 'wgRawHtmlMessages', [
			'foobar',
			'foo_bar',
			'foo-bar',
		] );

		$title = Title::newFromText( $textForm );
		$this->assertSame( $expected, $title->isRawHtmlMessage() );
	}

	/**
	 * @covers Title::newMainPage
	 */
	public function testNewMainPage() {
		$mock = $this->createMock( MessageCache::class );
		$mock->method( 'get' )->willReturn( 'Foresheet' );
		$mock->method( 'transform' )->willReturn( 'Foresheet' );

		$this->setService( 'MessageCache', $mock );

		$this->assertSame(
			'Foresheet',
			Title::newMainPage()->getText()
		);
	}

	/**
	 * Regression test for T297571
	 *
	 * @covers Title::newMainPage
	 */
	public function testNewMainPageNoRecursion() {
		$mock = $this->createMock( MessageCache::class );
		$mock->method( 'get' )->willReturn( 'localtestiw:' );
		$mock->method( 'transform' )->willReturn( 'localtestiw:' );
		$this->setService( 'MessageCache', $mock );

		$this->assertSame(
			'Main Page',
			Title::newMainPage()->getPrefixedText()
		);
	}

	/**
	 * @covers Title::newMainPage
	 */
	public function testNewMainPageWithLocal() {
		$local = $this->createMock( MessageLocalizer::class );
		$local->method( 'msg' )->willReturn( new RawMessage( 'Prime Article' ) );

		$this->assertSame(
			'Prime Article',
			Title::newMainPage( $local )->getText()
		);
	}

	/**
	 * @covers Title::loadRestrictions
	 */
	public function testLoadRestrictions() {
		$title = Title::newFromText( 'UTPage1' );
		$title->loadRestrictions();
		$this->assertTrue( $title->areRestrictionsLoaded() );
		$title = $this->getExistingTestPage( 'UTest1' )->getTitle();
		$title->loadRestrictions();
		$this->assertTrue( $title->areRestrictionsLoaded() );
		$this->assertFalse( $title->getRestrictionExpiry( 'create' ),
			"Existing page can't have create protection" );
		$this->assertSame( 'infinity', $title->getRestrictionExpiry( 'edit' ) );
		$page = $this->getNonexistingTestPage( 'UTest1' );
		$title = $page->getTitle();
		$protectExpiry = wfTimestamp( TS_MW, time() + 10000 );
		$cascade = 0;
		$page->doUpdateRestrictions(
			[ 'create' => 'sysop' ],
			[ 'create' => $protectExpiry ],
			$cascade,
			'test',
			$this->getTestSysop()->getUser()
		);
		$title->flushRestrictions();
		$title->loadRestrictions();
		$this->assertSame(
			$title->getRestrictionExpiry( 'create' ),
			$protectExpiry
		);
	}

	public function provideRestrictionsRows() {
		yield [ [ (object)[
			'pr_id' => 1,
			'pr_page' => 1,
			'pr_type' => 'edit',
			'pr_level' => 'sysop',
			'pr_cascade' => 0,
			'pr_user' => null,
			'pr_expiry' => 'infinity'
		] ] ];
		yield [ [ (object)[
			'pr_id' => 1,
			'pr_page' => 1,
			'pr_type' => 'edit',
			'pr_level' => 'sysop',
			'pr_cascade' => 0,
			'pr_user' => null,
			'pr_expiry' => 'infinity'
		] ] ];
		yield [ [ (object)[
			'pr_id' => 1,
			'pr_page' => 1,
			'pr_type' => 'move',
			'pr_level' => 'sysop',
			'pr_cascade' => 0,
			'pr_user' => null,
			'pr_expiry' => wfTimestamp( TS_MW, time() + 10000 )
		] ] ];
	}

	/**
	 * @covers Title::loadRestrictionsFromRows
	 * @dataProvider provideRestrictionsRows
	 */
	public function testloadRestrictionsFromRows( $rows ) {
		$title = $this->getExistingTestPage( 'UTest1' )->getTitle();
		$title->loadRestrictionsFromRows( $rows );
		$this->assertSame(
			$rows[0]->pr_level,
			$title->getRestrictions( $rows[0]->pr_type )[0]
		);
		$this->assertSame(
			$rows[0]->pr_expiry,
			$title->getRestrictionExpiry( $rows[0]->pr_type )
		);
	}

	/**
	 * @dataProvider provideRestrictionStoreForwarding
	 * @covers Title::getFilteredRestrictionTypes
	 * @covers Title::getRestrictionTypes
	 * @covers Title::getTitleProtection
	 * @covers Title::deleteTitleProtection
	 * @covers Title::isSemiProtected
	 * @covers Title::isProtected
	 * @covers Title::isCascadeProtected
	 * @covers Title::areCascadeProtectionSourcesLoaded
	 * @covers Title::getCascadeProtectionSources
	 * @covers Title::areRestrictionsLoaded
	 * @covers Title::getRestrictions
	 * @covers Title::getAllRestrictions
	 * @covers Title::getRestrictionExpiry
	 * @covers Title::areRestrictionsCascading
	 * @covers Title::loadRestrictionsFromRows
	 * @covers Title::loadRestrictions
	 * @covers Title::flushRestrictions
	 */
	public function testRestrictionStoreForwarding(
		string $method, array $params, $return, array $options = []
	) {
		$expectedParams = $options['expectedParams'] ?? $params;

		if ( isset( $options['static'] ) ) {
			$callee = 'Title';
		} else {
			$callee = $this->getExistingTestPage()->getTitle();
			$expectedParams = array_merge( [ $callee ], $expectedParams );
		}

		$mockRestrictionStore = $this->createMock( RestrictionStore::class );

		$expectedMethod = $options['expectedMethod'] ?? $method;

		// Don't try to forward to a method that doesn't exist!
		$this->assertIsCallable( [ $mockRestrictionStore, $expectedMethod ] );

		$expectedCall = $mockRestrictionStore->expects( $this->once() )
			->method( $expectedMethod )
			->with( ...$expectedParams );
		if ( !isset( $options['void'] ) ) {
			$expectedCall->willReturn( $return );
		}

		$mockRestrictionStore->expects( $this->never() )
			->method( $this->anythingBut( $expectedMethod ) );

		$this->setService( 'RestrictionStore', $mockRestrictionStore );

		$options['expectedReturn'] = $options['expectedReturn'] ?? $return;

		$comparisonMethod = isset( $options['weakCompareReturn'] ) ? 'assertEquals' : 'assertSame';

		$this->$comparisonMethod( $options['expectedReturn'], [ $callee, $method ]( ...$params ) );
	}

	public static function provideRestrictionStoreForwarding() {
		$pageIdentity = PageIdentityValue::localIdentity( 144, NS_MAIN, 'Sample' );
		$title = Title::castFromPageIdentity( $pageIdentity );
		return [
			[ 'getFilteredRestrictionTypes', [ true ], [ 'abc' ],
				[ 'static' => true, 'expectedMethod' => 'listAllRestrictionTypes' ] ],
			[ 'getFilteredRestrictionTypes', [ false ], [ 'def' ],
				[ 'static' => true, 'expectedMethod' => 'listAllRestrictionTypes' ] ],
			[ 'getRestrictionTypes', [], [ 'ghi' ],
				[ 'expectedMethod' => 'listApplicableRestrictionTypes' ] ],
			[ 'getTitleProtection', [], [ 'jkl' ], [ 'expectedMethod' => 'getCreateProtection' ] ],
			[ 'getTitleProtection', [], null,
				[ 'expectedMethod' => 'getCreateProtection', 'expectedReturn' => false ] ],
			[ 'deleteTitleProtection', [], null,
				[ 'expectedMethod' => 'deleteCreateProtection', 'void' => true ] ],
			[ 'isSemiProtected', [ 'phlebotomize' ], true ],
			[ 'isSemiProtected', [ 'splecotomize' ], false ],
			[ 'isProtected', [ 'strezotomize' ], true ],
			[ 'isProtected', [ 'chrelotomize' ], false ],
			[ 'isCascadeProtected', [], true ],
			[ 'isCascadeProtected', [], false ],
			[ 'areCascadeProtectionSourcesLoaded', [ true ], true, [ 'expectedParams' => [] ] ],
			[ 'areCascadeProtectionSourcesLoaded', [ true ], false, [ 'expectedParams' => [] ] ],
			[ 'areCascadeProtectionSourcesLoaded', [ false ], true, [ 'expectedParams' => [] ] ],
			[ 'areCascadeProtectionSourcesLoaded', [ false ], false, [ 'expectedParams' => [] ] ],
			[ 'getCascadeProtectionSources', [], [ [ $pageIdentity ], [ 'mno' ] ],
				[ 'expectedReturn' => [ [ $title ], [ 'mno' ] ], 'weakCompareReturn' => true ] ],
			[ 'getCascadeProtectionSources', [], [ [], [] ],
				[ 'expectedReturn' => [ [], [] ] ] ],
			[ 'getCascadeProtectionSources', [ true ], [ [ $pageIdentity ], [ 'mno' ] ],
				[ 'expectedParams' => [], 'expectedReturn' => [ [ $title ], [ 'mno' ] ],
				'weakCompareReturn' => true ] ],
			[ 'getCascadeProtectionSources', [ true ], [ [], [] ],
				[ 'expectedParams' => [], 'expectedReturn' => [ [], [] ] ] ],
			[ 'getCascadeProtectionSources', [ false ], false,
				[ 'expectedMethod' => 'isCascadeProtected', 'expectedParams' => [],
				'expectedReturn' => [ false, [] ] ] ],
			[ 'getCascadeProtectionSources', [ false ], true,
				[ 'expectedMethod' => 'isCascadeProtected', 'expectedParams' => [],
				'expectedReturn' => [ true, [] ] ] ],
			[ 'areRestrictionsLoaded', [], true ],
			[ 'areRestrictionsLoaded', [], false ],
			[ 'getRestrictions', [ 'stu' ], [ 'vwx' ] ],
			[ 'getAllRestrictions', [], [ 'yza' ] ],
			[ 'getRestrictionExpiry', [ 'bcd' ], 'efg' ],
			[ 'getRestrictionExpiry', [ 'hij' ], null, [ 'expectedReturn' => false ] ],
			[ 'areRestrictionsCascading', [], true ],
			[ 'areRestrictionsCascading', [], false ],
			[ 'loadRestrictionsFromRows', [ [ 'hij' ], 'klm' ], null, [ 'void' => true ] ],
			[ 'loadRestrictions', [ 'nop', 123 ], null,
				[ 'void' => true, 'expectedParams' => [ 123, 'nop' ] ] ],
			[ 'flushRestrictions', [], null, [ 'void' => true ] ],
		];
	}

	/**
	 * @covers Title::getRestrictions
	 */
	public function testGetRestrictions() {
		$title = $this->getExistingTestPage( 'UTest1' )->getTitle();
		$rs = MediaWikiServices::getInstance()->getRestrictionStore();
		$wrapper = TestingAccessWrapper::newFromObject( $rs );
		$wrapper->cache = [ CacheKeyHelper::getKeyForPage( $title ) => [
			'restrictions' => [
				'a' => [ 'sysop' ],
				'b' => [ 'sysop' ],
				'c' => [ 'sysop' ]
			],
		] ];
		$this->assertArrayEquals( [ 'sysop' ], $title->getRestrictions( 'a' ) );
		$this->assertArrayEquals( [], $title->getRestrictions( 'error' ) );
		// TODO: maybe test if loadRestrictionsFromRows() is called?
	}

	/**
	 * @covers Title::getAllRestrictions
	 */
	public function testGetAllRestrictions() {
		$restrictions = [
			'a' => [ 'sysop' ],
			'b' => [ 'sysop' ],
			'c' => [ 'sysop' ],
		];
		$title = $this->getExistingTestPage( 'UTest1' )->getTitle();
		$rs = MediaWikiServices::getInstance()->getRestrictionStore();
		$wrapper = TestingAccessWrapper::newFromObject( $rs );
		$wrapper->cache = [ CacheKeyHelper::getKeyForPage( $title ) => [
			'restrictions' => $restrictions
		] ];
		$this->assertArrayEquals(
			$restrictions,
			$title->getAllRestrictions()
		);
	}

	/**
	 * @covers Title::getRestrictionExpiry
	 */
	public function testGetRestrictionExpiry() {
		$title = $this->getExistingTestPage( 'UTest1' )->getTitle();
		$rs = MediaWikiServices::getInstance()->getRestrictionStore();
		$wrapper = TestingAccessWrapper::newFromObject( $rs );
		$wrapper->cache = [ CacheKeyHelper::getKeyForPage( $title ) => [
			'expiry' => [
				'a' => 'infinity', 'b' => 'infinity', 'c' => 'infinity'
			],
			// XXX This is bogus, restrictions will never be empty when expiry is not
			'restrictions' => [],
		] ];
		$this->assertSame( 'infinity', $title->getRestrictionExpiry( 'a' ) );
		$this->assertArrayEquals( [], $title->getRestrictions( 'error' ) );
	}

	/**
	 * @covers Title::getTitleProtection
	 */
	public function testGetTitleProtection() {
		$title = $this->getNonexistingTestPage( 'UTest1' )->getTitle();
		$this->assertFalse( $title->getTitleProtection() );
	}

	/**
	 * @covers Title::isSemiProtected
	 */
	public function testIsSemiProtected() {
		$title = $this->getExistingTestPage( 'UTest1' )->getTitle();
		$this->setMwGlobals( [
			'wgSemiprotectedRestrictionLevels' => [ 'autoconfirmed' ],
			'wgRestrictionLevels' => [ '', 'autoconfirmed', 'sysop' ]
		] );
		$rs = MediaWikiServices::getInstance()->getRestrictionStore();
		$wrapper = TestingAccessWrapper::newFromObject( $rs );
		$wrapper->cache = [ CacheKeyHelper::getKeyForPage( $title ) => [
			'restrictions' => [ 'edit' => [ 'sysop' ] ],
		] ];
		$this->assertFalse( $title->isSemiProtected( 'edit' ) );
		$wrapper->cache = [ CacheKeyHelper::getKeyForPage( $title ) => [
			'restrictions' => [ 'edit' => [ 'autoconfirmed' ] ],
		] ];
		$this->assertTrue( $title->isSemiProtected( 'edit' ) );
	}

	/**
	 * @covers Title::deleteTitleProtection
	 */
	public function testDeleteTitleProtection() {
		$title = $this->getExistingTestPage( 'UTest1' )->getTitle();
		$this->assertFalse( $title->getTitleProtection() );
	}

	/**
	 * @covers Title::isProtected
	 */
	public function testIsProtected() {
		$title = $this->getExistingTestPage( 'UTest1' )->getTitle();
		$this->setMwGlobals( [
			'wgRestrictionLevels' => [ '', 'autoconfirmed', 'sysop' ],
			'wgRestrictionTypes' => [ 'create', 'edit', 'move', 'upload' ]
		] );
		$rs = MediaWikiServices::getInstance()->getRestrictionStore();
		$wrapper = TestingAccessWrapper::newFromObject( $rs );
		$wrapper->cache = [ CacheKeyHelper::getKeyForPage( $title ) => [
			'restrictions' => [ 'edit' => [ 'sysop' ] ],
		] ];
		$this->assertTrue( $title->isProtected( 'edit' ) );
		$wrapper->cache = [ CacheKeyHelper::getKeyForPage( $title ) => [
			'restrictions' => [ 'edit' => [ 'test' ] ],
		] ];
		$this->assertFalse( $title->isProtected( 'edit' ) );
	}

	/**
	 * @covers Title::isNamespaceProtected
	 */
	public function testIsNamespaceProtected() {
		$this->hideDeprecated( 'Title::isNamespaceProtected' );
		$title = $this->getExistingTestPage( 'UTest1' )->getTitle();
		$this->setMwGlobals( [
			'wgNamespaceProtection' => []
		] );
		$this->assertFalse(
			$title->isNamespaceProtected( $this->getTestUser()->getUser() )
		);
		$this->setMwGlobals( [
			'wgNamespaceProtection' => [
				NS_MAIN => [ 'edit-main' ]
			]
		] );
		$this->assertTrue(
			$title->isNamespaceProtected( $this->getTestUser()->getUser() )
		);
	}

	/**
	 * @covers Title::isCascadeProtected
	 */
	public function testIsCascadeProtected() {
		$page = $this->getExistingTestPage( 'UTest1' );
		$title = $page->getTitle();
		$rs = MediaWikiServices::getInstance()->getRestrictionStore();
		$wrapper = TestingAccessWrapper::newFromObject( $rs );
		$wrapper->cache = [ CacheKeyHelper::getKeyForPage( $title ) => [
			'has_cascading' => true,
		] ];
		$this->assertTrue( $title->isCascadeProtected() );
		$wrapper->cache = [];
		$this->assertFalse( $title->isCascadeProtected() );
		$wrapper->cache = [];
		$cascade = 1;
		$anotherPage = $this->getExistingTestPage( 'UTest2' );
		$anotherPage->doUserEditContent(
			new WikitextContent( '{{:UTest1}}' ),
			$this->getTestSysop()->getUser(),
			'test'
		);
		$anotherPage->doUpdateRestrictions(
			[ 'edit' => 'sysop' ],
			[],
			$cascade,
			'test',
			$this->getTestSysop()->getUser()
		);
		$this->assertTrue( $title->isCascadeProtected() );
	}

	/**
	 * @covers Title::getCascadeProtectionSources
	 * @group Broken
	 */
	public function testGetCascadeProtectionSources() {
		$page = $this->getExistingTestPage( 'UTest1' );
		$title = $page->getTitle();

		$title->mCascadeSources = [];
		$this->assertArrayEquals(
			[ [], [] ],
			$title->getCascadeProtectionSources( true )
		);

		$reflection = new ReflectionClass( $title );
		$reflection_property = $reflection->getProperty( 'mHasCascadingRestrictions' );
		$reflection_property->setAccessible( true );
		$reflection_property->setValue( $title, true );
		$this->assertArrayEquals(
			[ true, [] ],
			$title->getCascadeProtectionSources( false )
		);

		$title->mCascadeSources = null;
		$reflection_property->setValue( $title, null );
		$this->assertArrayEquals(
			[ false, [] ],
			$title->getCascadeProtectionSources( false )
		);

		$title->mCascadeSources = null;
		$reflection_property->setValue( $title, null );
		$this->assertArrayEquals(
			[ [], [] ],
			$title->getCascadeProtectionSources( true )
		);

		// TODO: this might partially duplicate testIsCascadeProtected method above

		$cascade = 1;
		$anotherPage = $this->getExistingTestPage( 'UTest2' );
		$anotherPage->doUserEditContent(
			new WikitextContent( '{{:UTest1}}' ),
			$this->getTestSysop()->getUser(),
			'test'
		);
		$anotherPage->doUpdateRestrictions(
			[ 'edit' => 'sysop' ],
			[],
			$cascade,
			'test',
			$this->getTestSysop()->getUser()
		);

		$this->assertArrayEquals(
			[ true, [] ],
			$title->getCascadeProtectionSources( false )
		);

		$title->mCascadeSources = null;
		$result = $title->getCascadeProtectionSources( true );
		$this->assertArrayEquals(
			[ 'edit' => [ 'sysop' ] ],
			$result[1]
		);
		$this->assertArrayHasKey(
			$anotherPage->getTitle()->getArticleID(), $result[0]
		);
	}

	/**
	 * @covers Title::getCdnUrls
	 */
	public function testGetCdnUrls() {
		$this->assertEquals(
			[
				'https://example.org/wiki/Example',
				'https://example.org/w/index.php?title=Example&action=history',
			],
			Title::makeTitle( NS_MAIN, 'Example' )->getCdnUrls(),
			'article'
		);
	}

	/**
	 * @covers \MediaWiki\Page\PageStore::getSubpages
	 */
	public function testGetSubpages() {
		$existingPage = $this->getExistingTestPage();
		$title = $existingPage->getTitle();

		$this->setMwGlobals( 'wgNamespacesWithSubpages', [ $title->getNamespace() => true ] );

		$this->getExistingTestPage( $title->getSubpage( 'A' ) );
		$this->getExistingTestPage( $title->getSubpage( 'B' ) );

		$notQuiteSubpageTitle = $title->getPrefixedDBkey() . 'X'; // no slash!
		$this->getExistingTestPage( $notQuiteSubpageTitle );

		$subpages = iterator_to_array( $title->getSubpages() );

		$this->assertCount( 2, $subpages );
		$this->assertCount( 1, $title->getSubpages( 1 ) );
	}

	/**
	 * @covers \MediaWiki\Page\PageStore::getSubpages
	 */
	public function testGetSubpages_disabled() {
		$this->setMwGlobals( 'wgNamespacesWithSubpages', [] );

		$existingPage = $this->getExistingTestPage();
		$title = $existingPage->getTitle();

		$this->getExistingTestPage( $title->getSubpage( 'A' ) );
		$this->getExistingTestPage( $title->getSubpage( 'B' ) );

		$this->assertEmpty( $title->getSubpages() );
	}

	public function provideNamespaces() {
		// For ->isExternal() code path, construct a title with interwiki
		$title = Title::makeTitle( NS_FILE, 'test', 'frag', 'meta' );
		return [
			[ NS_MAIN, '' ],
			[ NS_FILE, 'File' ],
			[ NS_MEDIA, 'Media' ],
			[ NS_TALK, 'Talk' ],
			[ NS_CATEGORY, 'Category' ],
			[ $title, 'File' ],
		];
	}

	/**
	 * @covers Title::getNsText
	 * @dataProvider provideNamespaces
	 */
	public function testGetNsText( $namespace, $expected ) {
		if ( $namespace instanceof Title ) {
			$this->assertSame( $expected, $namespace->getNsText() );
		} else {
			$actual = Title::makeTitle( $namespace, 'Title_test' )->getNsText();
			$this->assertSame( $expected, $actual );
		}
	}

	public function providePagesWithSubjects() {
		return [
			[ Title::makeTitle( NS_USER_TALK, 'User_test' ), 'User' ],
			[ Title::makeTitle( NS_PROJECT, 'Test' ), 'Project' ],
			[ Title::makeTitle( NS_MAIN, 'Test' ), '' ],
			[ Title::makeTitle( NS_CATEGORY, 'Cat_test' ), 'Category' ],
		];
	}

	/**
	 * @covers Title::getSubjectNsText
	 * @dataProvider providePagesWithSubjects
	 */
	public function testGetSubjectNsText( Title $title, $expected ) {
		$actual = $title->getSubjectNsText();
		$this->assertSame( $expected, $actual );
	}

	public function provideTitlesWithTalkPages() {
		return [
			[ Title::makeTitle( NS_HELP, 'Help page' ), 'Help_talk' ],
			[ Title::newMainPage(), 'Talk' ],
			[ Title::makeTitle( NS_PROJECT, 'Test' ), 'Project_talk' ],
		];
	}

	/**
	 * @covers Title::getTalkNsText
	 * @dataProvider provideTitlesWithTalkPages
	 */
	public function testGetTalkNsText( Title $title, $expected ) {
		$actual = $title->getTalkNsText();
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @covers Title::isSpecial
	 */
	public function testIsSpecial() {
		$title = Title::makeTitle( NS_SPECIAL, 'Recentchanges/Subpage' );
		$this->assertTrue( $title->isSpecial( 'Recentchanges' ) );
	}

	/**
	 * @covers Title::isSpecial
	 */
	public function testIsNotSpecial() {
		$title = Title::newFromText( 'NotSpecialPage/Subpage', NS_SPECIAL );
		$this->assertFalse( $title->isSpecial( 'NotSpecialPage' ) );
	}

	/**
	 * @covers Title::isTalkPage
	 */
	public function testIsTalkPage() {
		$title = Title::newFromText( 'Talk page', NS_TALK );
		$this->assertTrue( $title->isTalkPage() );

		$titleNotInTalkNs = Title::makeTitle( NS_HELP, 'Test' );
		$this->assertFalse( $titleNotInTalkNs->isTalkPage() );
	}

	/**
	 * @covers Title::getBacklinkCache
	 */
	public function testGetBacklinkCache() {
		$blcFactory = $this->getServiceContainer()->getBacklinkCacheFactory();
		$backlinkCache = $blcFactory->getBacklinkCache( Title::makeTitle( NS_FILE, 'Test' ) );
		$this->assertInstanceOf( BacklinkCache::class, $backlinkCache );
	}

	public function provideNsWithSubpagesSupport() {
		return [
			[ NS_HELP, 'Mainhelp', 'Mainhelp/Subhelp' ],
			[ NS_USER, 'Mainuser', 'Mainuser/Subuser' ],
			[ NS_TALK, 'Maintalk', 'Maintalk/Subtalk' ],
			[ NS_PROJECT, 'Mainproject', 'Mainproject/Subproject' ],
		];
	}

	/**
	 * @covers Title::isSubpage
	 * @covers Title::isSubpageOf
	 * @dataProvider provideNsWithSubpagesSupport
	 */
	public function testIsSubpageOfWithNamespacesSubpages( $namespace, $pageName, $subpageName ) {
		$page = Title::makeTitle( $namespace, $pageName, '', 'meta' );
		$subPage = Title::makeTitle( $namespace, $subpageName, '', 'meta' );

		$this->assertTrue( $subPage->isSubpageOf( $page ) );
		$this->assertTrue( $subPage->isSubpage() );
	}

	public function provideNsWithNoSubpages() {
		return [
			[ NS_CATEGORY, 'Maincat', 'Maincat/Subcat' ],
			[ NS_MAIN, 'Mainpage', 'Mainpage/Subpage' ]
		];
	}

	/**
	 * @covers Title::isSubpage
	 * @covers Title::isSubpageOf
	 * @dataProvider provideNsWithNoSubpages
	 */
	public function testIsSubpageOfWithoutNamespacesSubpages( $namespace, $pageName, $subpageName ) {
		$page = Title::makeTitle( $namespace, $pageName, '', 'meta' );
		$subPage = Title::makeTitle( $namespace, $subpageName, '', 'meta' );

		$this->assertFalse( $page->isSubpageOf( $page ) );
		$this->assertFalse( $subPage->isSubpage() );
	}

	public function provideTitleEditURLs() {
		return [
			[ Title::makeTitle( NS_MAIN, 'Title' ), '/w/index.php?title=Title&action=edit' ],
			[ Title::makeTitle( NS_HELP, 'Test', '', 'mw' ), '' ],
			[ Title::makeTitle( NS_HELP, 'Test' ), '/w/index.php?title=Help:Test&action=edit' ],
		];
	}

	/**
	 * @covers Title::getEditURL
	 * @dataProvider provideTitleEditURLs
	 */
	public function testGetEditURL( Title $title, $expected ) {
		$actual = $title->getEditURL();
		$this->assertSame( $expected, $actual );
	}

	public function provideTitleEditURLsWithActionPaths() {
		return [
			[ Title::newFromText( 'Title', NS_MAIN ), '/wiki/edit/Title' ],
			[ Title::makeTitle( NS_HELP, 'Test', '', 'mw' ), '' ],
			[ Title::newFromText( 'Test', NS_HELP ), '/wiki/edit/Help:Test' ],
		];
	}

	/**
	 * @covers Title::getEditURL
	 * @dataProvider provideTitleEditURLsWithActionPaths
	 */
	public function testGetEditUrlWithActionPaths( Title $title, $expected ) {
		$this->setMwGlobals( 'wgActionPaths', [ 'edit' => '/wiki/edit/$1' ] );
		$actual = $title->getEditURL();
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @covers Title::isMainPage
	 * @covers Title::equals
	 */
	public function testIsMainPage() {
		$this->assertTrue( Title::newMainPage()->isMainPage() );
	}

	/**
	 * @covers Title::isMainPage
	 * @covers Title::equals
	 * @dataProvider provideMainPageTitles
	 */
	public function testIsNotMainPage( Title $title, $expected ) {
		$this->assertSame( $title->isMainPage(), $expected );
	}

	public function provideMainPageTitles() {
		return [
			[ Title::makeTitle( NS_MAIN, 'Test' ), false ],
			[ Title::makeTitle( NS_CATEGORY, 'mw:Category' ), false ],
		];
	}

	/**
	 * @covers Title::getPrefixedURL
	 * @covers Title::prefix
	 * @dataProvider provideDataForTestGetPrefixedURL
	 */
	public function testGetPrefixedURL( Title $title, $expected ) {
		$actual = $title->getPrefixedURL();

		$this->assertSame( $expected, $actual );
	}

	public function provideDataForTestGetPrefixedURL() {
		return [
			[ Title::makeTitle( NS_FILE, 'Title' ), 'File:Title' ],
			[ Title::makeTitle( NS_MEDIA, 'Title' ), 'Media:Title' ],
			[ Title::makeTitle( NS_CATEGORY, 'Title' ), 'Category:Title' ],
			[ Title::makeTitle( NS_FILE, 'Title with spaces' ), 'File:Title_with_spaces' ],
			[
				Title::makeTitle( NS_FILE, 'Title with spaces', '', 'mw' ),
				'mw:File:Title_with_spaces'
			],
		];
	}

	/**
	 * @covers Title::__toString
	 */
	public function testToString() {
		$title = Title::makeTitle( NS_USER, 'User test' );

		$this->assertSame( 'User:User test', (string)$title );
	}

	/**
	 * @covers Title::getFullText
	 * @dataProvider provideDataForTestGetFullText
	 */
	public function testGetFullText( Title $title, $expected ) {
		$actual = $title->getFullText();

		$this->assertSame( $expected, $actual );
	}

	public function provideDataForTestGetFullText() {
		return [
			[ Title::makeTitle( NS_TALK, 'Test' ), 'Talk:Test' ],
			[ Title::makeTitle( NS_HELP, 'Test', 'frag' ), 'Help:Test#frag' ],
			[ Title::makeTitle( NS_TALK, 'Test', 'frag', 'phab' ), 'phab:Talk:Test#frag' ],
		];
	}

	public function provideIsSamePageAs() {
		$title = Title::makeTitle( 0, 'Foo' );
		$title->resetArticleID( 1 );
		yield '(PageIdentityValue) same text, title has ID 0' => [
			$title,
			new PageIdentityValue( 1, 0, 'Foo', PageIdentity::LOCAL ),
			true
		];

		$title = Title::makeTitle( 1, 'Bar_Baz' );
		$title->resetArticleID( 0 );
		yield '(PageIdentityValue) same text, PageIdentityValue has ID 0' => [
			$title,
			new PageIdentityValue( 0, 1, 'Bar_Baz', PageIdentity::LOCAL ),
			true
		];

		$title = Title::makeTitle( 0, 'Foo' );
		$title->resetArticleID( 0 );
		yield '(PageIdentityValue) different text, both IDs are 0' => [
			$title,
			new PageIdentityValue( 0, 0, 'Foozz', PageIdentity::LOCAL ),
			false
		];

		$title = Title::makeTitle( 0, 'Foo' );
		$title->resetArticleID( 0 );
		yield '(PageIdentityValue) different namespace' => [
			$title,
			new PageIdentityValue( 0, 1, 'Foo', PageIdentity::LOCAL ),
			false
		];

		$title = Title::makeTitle( 0, 'Foo', '' );
		$title->resetArticleID( 1 );
		yield '(PageIdentityValue) different wiki, different ID' => [
			$title,
			new PageIdentityValue( 1, 0, 'Foo', 'bar' ),
			false
		];

		$title = Title::makeTitle( 0, 'Foo', '' );
		$title->resetArticleID( 0 );
		yield '(PageIdentityValue) different wiki, both IDs are 0' => [
			$title,
			new PageIdentityValue( 0, 0, 'Foo', 'bar' ),
			false
		];
	}

	/**
	 * @covers Title::isSamePageAs
	 * @dataProvider provideIsSamePageAs
	 */
	public function testIsSamePageAs( Title $firstValue, $secondValue, $expectedSame ) {
		$this->assertSame(
			$expectedSame,
			$firstValue->isSamePageAs( $secondValue )
		);
	}

}
