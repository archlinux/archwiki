<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Page\ParserOutputAccess;
use MediaWiki\Parser\ParserCacheFactory;
use MediaWiki\Parser\ParserOutputFlags;
use MediaWiki\Parser\Parsoid\PageBundleParserOutputConverter;
use MediaWiki\Parser\Parsoid\ParsoidOutputAccess;
use MediaWiki\Parser\Parsoid\ParsoidParser;
use MediaWiki\Parser\Parsoid\ParsoidParserFactory;
use MediaWiki\Rest\HttpException;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Wikimedia\Parsoid\Config\PageConfig;
use Wikimedia\Parsoid\Core\ContentMetadataCollector;
use Wikimedia\Parsoid\Core\PageBundle;
use Wikimedia\Parsoid\Parsoid;

/**
 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess
 * @group Database
 */
class ParsoidOutputAccessTest extends MediaWikiIntegrationTestCase {
	private const WIKITEXT = 'Hello \'\'\'Parsoid\'\'\'!';
	private const MOCKED_HTML = 'mocked HTML';
	private const ENV_OPTS = [ 'pageBundle' => true ];

	/**
	 * @param int $expectedCalls
	 *
	 * @return MockObject|Parsoid
	 */
	private function newMockParsoid( $expectedCalls = 1 ) {
		$parsoid = $this->createNoOpMock( Parsoid::class, [ 'wikitext2html' ] );
		$parsoid->expects( $this->exactly( $expectedCalls ) )->method( 'wikitext2html' )->willReturnCallback(
			static function ( PageConfig $pageConfig, $options, &$headers, ?ContentMetadataCollector $metadata = null ) {
				$wikitext = $pageConfig->getRevisionContent()->getContent( SlotRecord::MAIN );
				if ( $metadata !== null ) {
					$metadata->setExtensionData( 'my-key', 'my-data' );
					$metadata->setPageProperty( 'forcetoc', '' );
					$metadata->setOutputFlag( ParserOutputFlags::NO_GALLERY );
				}

				return new PageBundle(
					self::MOCKED_HTML . ' of ' . $wikitext,
					[ 'parsoid-data' ],
					[ 'mw-data' ],
					Parsoid::defaultHTMLVersion(),
					[ 'content-language' => 'en' ],
					$pageConfig->getContentModel()
				);
			}
		);

		return $parsoid;
	}

	/**
	 * @param int $expectedParses
	 * @param array $parsoidCacheConfig
	 * @param BagOStuff|null $parserCacheBag
	 *
	 * @return ParsoidOutputAccess
	 * @throws Exception
	 */
	private function resetServicesWithMockedParsoid(
		$expectedParses,
		$parsoidCacheConfig = [],
		?BagOStuff $parserCacheBag = null
	): void {
		$services = $this->getServiceContainer();

		$parsoidCacheConfig += [
			'CacheThresholdTime' => 0,
		];

		$parserCacheFactoryOptions = new ServiceOptions( ParserCacheFactory::CONSTRUCTOR_OPTIONS, [
			'CacheEpoch' => '20200202112233',
			'OldRevisionParserCacheExpireTime' => 60 * 60,
		] );

		$parserCacheFactory = new ParserCacheFactory(
			$parserCacheBag ?: new HashBagOStuff(),
			new WANObjectCache( [ 'cache' => new HashBagOStuff(), ] ),
			$this->createHookContainer(),
			new JsonCodec(),
			new NullStatsdDataFactory(),
			new NullLogger(),
			$parserCacheFactoryOptions,
			$services->getTitleFactory(),
			$services->getWikiPageFactory()
		);

		$mockParsoid = $this->newMockParsoid( $expectedParses );
		$parsoidParser = new ParsoidParser(
			$mockParsoid,
			$services->getParsoidPageConfigFactory(),
			$services->getLanguageConverterFactory(),
			$services->getParserFactory(),
			$services->getGlobalIdGenerator()
		);

		// Create a mock Parsoid factory that returns the ParsoidParser object
		// with the mocked Parsoid object.
		$mockParsoidParserFactory = $this->createNoOpMock( ParsoidParserFactory::class, [ 'create' ] );
		$mockParsoidParserFactory->expects( $this->exactly( $expectedParses ) )
			->method( 'create' )
			->willReturn( $parsoidParser );

		$this->setService( 'ParsoidParserFactory', $mockParsoidParserFactory );
	}

	/**
	 * @return ParsoidOutputAccess
	 */
	private function getParsoidOutputAccessWithCache(): ParsoidOutputAccess {
		$services = $this->getServiceContainer();
		return new ParsoidOutputAccess(
			new ServiceOptions(
				ParsoidOutputAccess::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig(),
				[ 'ParsoidWikiID' => 'MyWiki' ]
			),
			$services->getParsoidParserFactory(),
			$services->getParserOutputAccess(),
			$services->getPageStore(),
			$services->getRevisionLookup(),
			$services->getParsoidSiteConfig(),
			$services->getContentHandlerFactory()
		);
	}

	/**
	 * @return ParserOptions
	 */
	private function getParserOptions() {
		return ParserOptions::newFromAnon();
	}

	private function getHtml( $value ) {
		if ( $value instanceof StatusValue ) {
			$value = $value->getValue();
		}

		if ( $value instanceof ParserOutput ) {
			$value = $value->getRawText();
		}

		$html = preg_replace( '/<!--.*?-->/s', '', $value );
		$html = trim( preg_replace( '/[\r\n]{2,}/', "\n", $html ) );
		$html = trim( preg_replace( '/\s{2,}/', ' ', $html ) );
		return $html;
	}

	private function assertContainsHtml( $needle, $actual, $msg = '' ) {
		$this->assertNotNull( $actual );

		if ( $actual instanceof StatusValue ) {
			$this->assertStatusOK( $actual, 'isOK' );
		}

		$this->assertStringContainsString( $needle, $this->getHtml( $actual ), $msg );
	}

	/**
	 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess::getParserOutput
	 */
	public function testGetParserOutputThrowsIfRevisionNotFound() {
		$this->resetServicesWithMockedParsoid( 0 );
		$access = $this->getParsoidOutputAccessWithCache();
		$parserOptions = $this->getParserOptions();

		$page = $this->getNonexistingTestPage( __METHOD__ );

		$this->expectException( RevisionAccessException::class );
		$access->getParserOutput( $page, $parserOptions );
	}

	/**
	 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess::getParserOutput
	 */
	public function testGetParserOutputThrowsIfNotWikitext() {
		$this->markTestSkipped( 'Broken by fix for T324711. Restore once we have T311728.' );

		$this->resetServicesWithMockedParsoid( 0 );
		$access = $this->getParsoidOutputAccessWithCache();
		$parserOptions = $this->getParserOptions();

		$page = $this->getNonexistingTestPage( __METHOD__ );
		$updater = $page->newPageUpdater( $this->getTestUser()->getUserIdentity() );
		$updater->setContent( SlotRecord::MAIN, new JavaScriptContent( '{}' ) );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'testing' ) );

		// NOTE: The fact that we throw an HttpException here is a code smell.
		//       It should be a different exception which gets converted to an HttpException later.
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );
		$access->getParserOutput( $page, $parserOptions );
	}

	/**
	 * Tests that getParserOutput() will return output.
	 *
	 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess::getParserOutput
	 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess::getParsoidRenderID
	 */
	public function testGetParserOutput() {
		$this->resetServicesWithMockedParsoid( 1 );
		$access = $this->getParsoidOutputAccessWithCache();
		$parserOptions = $this->getParserOptions();

		$page = $this->getNonexistingTestPage( __METHOD__ );
		$this->editPage( $page, self::WIKITEXT );

		$status = $access->getParserOutput( $page, $parserOptions );
		$this->assertContainsHtml( self::MOCKED_HTML . ' of ' . self::WIKITEXT, $status );

		$output = $status->getValue();

		// check that getParsoidRenderID() doesn't throw
		$this->assertNotNull( $access->getParsoidRenderID( $output ) );

		// Ensure that we can still create a valid instance of PageBundle from the ParserOutput
		$pageBundle = PageBundleParserOutputConverter::pageBundleFromParserOutput( $output );
		$this->assertSame( $output->getRawText(), $pageBundle->html );

		// Ensure that the expected mw and parsoid fields are set in the PageBundle
		$this->assertNotEmpty( $pageBundle->mw );
		$this->assertNotEmpty( $pageBundle->parsoid );
		$this->assertNotEmpty( $pageBundle->headers );
		$this->assertNotEmpty( $pageBundle->version );

		// Check that the metadata set by our mock parsoid is preserved
		$this->checkMetadata( $output );
	}

	/**
	 * Tests that getParserOutput() will place the generated output for the latest revision
	 * in the parsoid parser cache.
	 *
	 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess::getParserOutput
	 */
	public function testLatestRevisionIsCached() {
		$this->resetServicesWithMockedParsoid( 1 );
		$access = $this->getParsoidOutputAccessWithCache();
		$parserOptions = $this->getParserOptions();

		$page = $this->getNonexistingTestPage( __METHOD__ );
		$this->editPage( $page, self::WIKITEXT );

		$status = $access->getParserOutput( $page, $parserOptions );
		$this->assertContainsHtml( self::MOCKED_HTML . ' of ' . self::WIKITEXT, $status );
		$this->checkMetadata( $status );

		// Get the ParserOutput from cache
		$status = $access->getCachedParserOutput( $page, $parserOptions );
		$this->assertContainsHtml( self::MOCKED_HTML . ' of ' . self::WIKITEXT, $status );
		$this->checkMetadata( $status );

		// Get the ParserOutput from cache, without supplying a PageRecord
		$status = $access->getCachedParserOutput( $page->getTitle(), $parserOptions );
		$this->assertContainsHtml( self::MOCKED_HTML . ' of ' . self::WIKITEXT, $status );
		$this->checkMetadata( $status );

		// Get the ParserOutput again, this should not trigger a new parse.
		$status = $access->getParserOutput( $page, $parserOptions );
		$this->assertContainsHtml( self::MOCKED_HTML . ' of ' . self::WIKITEXT, $status );
		$this->checkMetadata( $status );
	}

	/**
	 * Tests that getParserOutput() will force a parse since we know that
	 * the revision is not in the cache.
	 *
	 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess::getParserOutput
	 */
	public function testLatestRevisionWithForceParse() {
		$this->resetServicesWithMockedParsoid( 2 );
		$access = $this->getParsoidOutputAccessWithCache();
		$parserOptions = $this->getParserOptions();

		$page = $this->getNonexistingTestPage( __METHOD__ );
		$this->editPage( $page, self::WIKITEXT );

		$status = $access->getParserOutput( $page, $parserOptions );
		$this->assertContainsHtml( self::MOCKED_HTML . ' of ' . self::WIKITEXT, $status );
		$this->checkMetadata( $status );

		// Get the ParserOutput again, this should trigger a new parse
		// since we're forcing it to.
		$status = $access->getParserOutput(
			$page,
			$parserOptions,
			null,
			ParserOutputAccess::OPT_FORCE_PARSE
		);
		$this->assertContainsHtml( self::MOCKED_HTML . ' of ' . self::WIKITEXT, $status );
		$this->checkMetadata( $status );
	}

	/**
	 * Tests that getParserOutput() will force a parse since we know that
	 * the revision is not in the cache.
	 *
	 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess::getParserOutput
	 */
	public function testLatestRevisionWithNoUpdateCache() {
		$cacheBag = $this->getMockBuilder( HashBagOStuff::class )
			->onlyMethods( [ 'set', 'setMulti' ] )
			->getMock();
		$cacheBag->expects( $this->never() )->method( 'set' );
		$cacheBag->expects( $this->never() )->method( 'setMulti' );

		// ParserCache should not get anything stored in it.
		$this->resetServicesWithMockedParsoid( 1, [], $cacheBag );
		$access = $this->getParsoidOutputAccessWithCache();
		$parserOptions = $this->getParserOptions();

		$page = $this->getNonexistingTestPage( __METHOD__ );
		$this->editPage( $page, self::WIKITEXT );

		$status = $access->getParserOutput(
			$page,
			$parserOptions,
			null,
			ParserOutputAccess::OPT_NO_UPDATE_CACHE
		);
		$this->assertContainsHtml( self::MOCKED_HTML . ' of ' . self::WIKITEXT, $status );
		$this->checkMetadata( $status );
	}

	/**
	 * Tests that getParserOutput() will not call Parsoid and will not write to ParserCache
	 * for unsupported content models.
	 *
	 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess::getParserOutput
	 */
	public function testDummyContentForBadModel() {
		// Expect no cache writes!
		$cacheBag = $this->getMockBuilder( HashBagOStuff::class )
			->onlyMethods( [ 'set', 'setMulti' ] )
			->getMock();
		$cacheBag->expects( $this->never() )->method( 'set' );
		$cacheBag->expects( $this->never() )->method( 'setMulti' );

		// Expect no calls to parsoid!
		$this->resetServicesWithMockedParsoid( 0, [], $cacheBag );
		$access = $this->getParsoidOutputAccessWithCache();
		$parserOptions = $this->getParserOptions();

		$page = $this->getNonexistingTestPage( __METHOD__ );
		$this->editPage( $page, new JavaScriptContent( '"not wikitext"' ) );

		$status = $access->getParserOutput( $page, $parserOptions );
		$this->assertContainsHtml( 'Dummy output', $status );

		/** @var ParserOutput $parserOutput */
		$parserOutput = $status->getValue();
		$this->assertSame( '0/dummy-output', $parserOutput->getExtensionData( 'parsoid-render-id' ) );

		$expTime = $parserOutput->getCacheExpiry();
		$this->assertSame( 0, $expTime );

		// Get the ParserOutput again, this should trigger a new parse
		// since we suppressed caching for non-wikitext content.
		$status = $access->getParserOutput( $page, $parserOptions );
		$this->assertContainsHtml( 'Dummy output', $status );
	}

	/**
	 * Unsupported functionality at this point
	 */
	public static function provideCacheThresholdData() {
		return [
			yield "fast parse" => [ 1, 2 ], // high threshold, no caching
			yield "slow parse" => [ 0, 1 ], // low threshold, caching
		];
	}

	/**
	 * @dataProvider provideCacheThresholdData()
	 */
	public function testHtmlWithCacheThreshold(
		$cacheThresholdTime,
		$expectedCalls
	) {
		$this->markTestSkipped( 'Supported removed. Will be fixed by T346398.' );

		$page = $this->getExistingTestPage( __METHOD__ );
		$parsoidCacheConfig = [
			'CacheThresholdTime' => $cacheThresholdTime
		];
		$parserOptions = $this->getParserOptions();

		$this->resetServicesWithMockedParsoid( $expectedCalls, $parsoidCacheConfig );
		$access = $this->getParsoidOutputAccessWithCache();
		$status = $access->getParserOutput( $page, $parserOptions );
		$this->assertContainsHtml( self::MOCKED_HTML, $status );
		$this->checkMetadata( $status );

		$status = $access->getParserOutput( $page, $parserOptions );
		$this->assertContainsHtml( self::MOCKED_HTML, $status );
		$this->checkMetadata( $status );
	}

	public function testOldRevisionIsCached() {
		$this->resetServicesWithMockedParsoid( 1 );
		$access = $this->getParsoidOutputAccessWithCache();
		$parserOptions = $this->getParserOptions();

		$page = $this->getNonexistingTestPage( __METHOD__ );
		$status1 = $this->editPage( $page, self::WIKITEXT );
		$rev = $status1->getValue()['revision-record'];

		// Make an edit so that the revision we're getting output
		// for below is not the current revision.
		$this->editPage( $page, 'Second revision' );

		$access->getParserOutput( $page, $parserOptions, $rev );

		// Get the ParserOutput from cache, using revision object
		$status = $access->getCachedParserOutput( $page, $parserOptions, $rev );
		$this->assertContainsHtml( self::MOCKED_HTML . ' of ' . self::WIKITEXT, $status );
		$this->checkMetadata( $status );

		// Get the ParserOutput from cache, using revision ID
		$status = $access->getCachedParserOutput( $page->getTitle(), $parserOptions, $rev->getId() );
		$this->assertContainsHtml( self::MOCKED_HTML . ' of ' . self::WIKITEXT, $status );
		$this->checkMetadata( $status );

		// Get the ParserOutput again, this should not trigger a new parse.
		$status = $access->getParserOutput( $page, $parserOptions, $rev );
		$this->assertContainsHtml( self::MOCKED_HTML . ' of ' . self::WIKITEXT, $status );
		$this->checkMetadata( $status );
	}

	public function testGetParserOutputWithOldRevision() {
		$this->resetServicesWithMockedParsoid( 2 );
		$access = $this->getParsoidOutputAccessWithCache();
		$parserOptions = $this->getParserOptions();

		$page = $this->getNonexistingTestPage( __METHOD__ );
		$status1 = $this->editPage( $page, self::WIKITEXT );
		$rev1 = $status1->getValue()['revision-record'];

		$this->editPage( $page, 'Second revision' );

		$status2 = $access->getParserOutput( $page, $parserOptions );
		$this->assertContainsHtml( self::MOCKED_HTML . ' of Second revision', $status2 );
		$this->checkMetadata( $status2 );

		$status1 = $access->getParserOutput( $page, $parserOptions, $rev1 );
		$this->assertContainsHtml( self::MOCKED_HTML . ' of ' . self::WIKITEXT, $status1 );
		$this->checkMetadata( $status1 );

		// Again, using just the revision ID
		$status1 = $access->getParserOutput( $page, $parserOptions, $rev1->getId() );
		$this->assertContainsHtml( self::MOCKED_HTML . ' of ' . self::WIKITEXT, $status1 );
		$this->checkMetadata( $status1 );

		// check that getParsoidRenderID() doesn't throw
		$output1 = $status1->getValue();
		$this->assertNotNull( $access->getParsoidRenderID( $output1 ) );
	}

	public static function provideSupportsContentModels() {
		yield [ CONTENT_MODEL_WIKITEXT, true ];
		yield [ CONTENT_MODEL_JSON, true ];
		yield [ CONTENT_MODEL_JAVASCRIPT, false ];
		yield [ 'with-text', true ];
		yield [ 'xyzzy', false ];
	}

	/**
	 * @dataProvider provideSupportsContentModels
	 */
	public function testSupportsContentModel( $model, $expected ) {
		$contentHandlers = $this->getConfVar( 'ContentHandlers' );
		$this->overrideConfigValue( 'ContentHandlers', [
			'with-text' => [ 'factory' => static function () {
				return new TextContentHandler( 'with-text', [ CONTENT_FORMAT_WIKITEXT, 'plain/test' ] );
			} ],
		] + $contentHandlers );

		$this->resetServicesWithMockedParsoid( 0 );
		$access = $this->getParsoidOutputAccessWithCache();
		$this->assertSame( $expected, $access->supportsContentModel( $model ) );
	}

	/**
	 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess::getParserOutput
	 */
	public function testParseWithPageRecordAndNoRevision() {
		$pageRecord = $this->getExistingTestPage( __METHOD__ )->toPageRecord();
		$pOpts = ParserOptions::newFromAnon();

		$parsoidOutputAccess = $this->getServiceContainer()->getParsoidOutputAccess();
		$status = $parsoidOutputAccess->getParserOutput( $pageRecord, $pOpts, null );

		$this->assertInstanceOf( Status::class, $status );
		$this->assertTrue( $status->isOK() );
		$this->assertInstanceOf( ParserOutput::class, $status->getValue() );

		/** @var ParserOutput $parserOutput */
		$parserOutput = $status->getValue();
		$this->assertStringContainsString( __METHOD__, $parserOutput->getRawText() );
		$this->assertNotEmpty( $parserOutput->getExtensionData( 'parsoid-render-id' ) );
		$this->assertNotEmpty( $parserOutput->getCacheRevisionId() );
		$this->assertNotEmpty( $parserOutput->getCacheTime() );
	}

	private function checkMetadata( $output ) {
		$parserOutput = $output instanceof StatusValue ? $output->getValue() : $output;

		// Check the metadata added by ::newMockParsoid() is preserved
		$this->assertSame( 'my-data', $parserOutput->getExtensionData( 'my-key' ) );
		$this->assertSame( '', $parserOutput->getPageProperty( 'forcetoc' ) );
		$this->assertSame( true, $parserOutput->getOutputFlag( ParserOutputFlags::NO_GALLERY ) );
	}

	/**
	 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess::getParserOutput
	 */
	public function testParseWithPageRecordAndRevision() {
		$page = $this->getExistingTestPage( __METHOD__ );
		$pageRecord = $page->toPageRecord();
		$pOpts = ParserOptions::newFromAnon();
		$revRecord = $page->getRevisionRecord();

		$parsoidOutputAccess = $this->getServiceContainer()->getParsoidOutputAccess();
		$status = $parsoidOutputAccess->getParserOutput( $pageRecord, $pOpts, $revRecord );

		$this->assertInstanceOf( Status::class, $status );
		$this->assertTrue( $status->isOK() );
		$this->assertInstanceOf( ParserOutput::class, $status->getValue() );

		/** @var ParserOutput $parserOutput */
		$parserOutput = $status->getValue();
		$this->assertStringContainsString( __METHOD__, $parserOutput->getRawText() );
		$this->assertNotEmpty( $parserOutput->getExtensionData( 'parsoid-render-id' ) );
		$this->assertNotEmpty( $parserOutput->getCacheRevisionId() );
		$this->assertNotEmpty( $parserOutput->getCacheTime() );
	}

	/**
	 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess::getParserOutput
	 */
	public function testParseWithPageIdentityAndRevisionId() {
		$page = $this->getExistingTestPage( __METHOD__ );
		$pOpts = ParserOptions::newFromAnon();
		$revId = $page->getLatest();

		$parsoidOutputAccess = $this->getServiceContainer()->getParsoidOutputAccess();
		$status = $parsoidOutputAccess->getParserOutput( $page->getTitle(), $pOpts, $revId );

		$this->assertInstanceOf( Status::class, $status );
		$this->assertTrue( $status->isOK() );
		$this->assertInstanceOf( ParserOutput::class, $status->getValue() );

		/** @var ParserOutput $parserOutput */
		$parserOutput = $status->getValue();
		$this->assertStringContainsString( __METHOD__, $parserOutput->getRawText() );
		$this->assertNotEmpty( $parserOutput->getExtensionData( 'parsoid-render-id' ) );
		$this->assertNotEmpty( $parserOutput->getCacheRevisionId() );
		$this->assertNotEmpty( $parserOutput->getCacheTime() );
	}

	/**
	 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess::parseUncacheable
	 */
	public function testParseWithNonExistingPageAndFakeRevision() {
		$page = $this->getNonexistingTestPage( __METHOD__ );
		$pOpts = ParserOptions::newFromAnon();

		// Create a fake revision record
		$revRecord = new MutableRevisionRecord( $page->getTitle() );
		$revRecord->setId( 0 );
		$revRecord->setPageId( $page->getId() );
		$revRecord->setContent(
			SlotRecord::MAIN,
			new WikitextContent( 'test' )
		);

		$parsoidOutputAccess = $this->getServiceContainer()->getParsoidOutputAccess();
		$status = $parsoidOutputAccess->parseUncacheable( $page->getTitle(), $pOpts, $revRecord );

		$this->assertInstanceOf( Status::class, $status );
		$this->assertTrue( $status->isOK() );
		$this->assertInstanceOf( ParserOutput::class, $status->getValue() );

		/** @var ParserOutput $parserOutput */
		$parserOutput = $status->getValue();
		$this->assertStringContainsString( __METHOD__, $parserOutput->getRawText() );
		$this->assertNotEmpty( $parserOutput->getExtensionData( 'parsoid-render-id' ) );
		// The revision ID is set to 0, so that's what is in the cache.
		$this->assertSame( 0, $parserOutput->getCacheRevisionId() );
		$this->assertNotEmpty( $parserOutput->getCacheTime() );
	}

	/**
	 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess::parseUncacheable
	 */
	public function testParseDeletedRevision() {
		$page = $this->getNonexistingTestPage( __METHOD__ );
		$pOpts = ParserOptions::newFromAnon();

		// Create a fake revision record
		$revRecord = new MutableRevisionRecord( $page->getTitle() );
		$revRecord->setId( 0 );
		$revRecord->setPageId( $page->getId() );
		$revRecord->setContent(
			SlotRecord::MAIN,
			new WikitextContent( 'test' )
		);
		// Induce a RevisionAccessException
		$revRecord->setVisibility( RevisionRecord::DELETED_TEXT );

		$parsoidOutputAccess = $this->getServiceContainer()->getParsoidOutputAccess();
		$status = $parsoidOutputAccess->parseUncacheable( $page->getTitle(), $pOpts, $revRecord );

		$this->assertStatusError( 'parsoid-revision-access', $status );
		$this->assertSame(
			[ 'parsoid-revision-access', 'Not an available content version.' ],
			$status->getErrorsArray()[0] ?? []
		);
	}

	/**
	 * Mock the language class based on a language code.
	 *
	 * @param string $langCode
	 *
	 * @return Language|Language&MockObject|MockObject
	 */
	private function getLanguageMock( string $langCode ) {
		$language = $this->createMock( Language::class );
		$language->method( 'getCode' )->willReturn( $langCode );
		$language->method( 'getDir' )->willReturn( 'ltr' );
		$bcp47 = LanguageCode::bcp47( $langCode );
		$language
			->method( 'getHtmlCode' )
			->willReturn( $bcp47 );
		$language
			->method( 'toBcp47Code' )
			->willReturn( $bcp47 );
		return $language;
	}

	/** @return Generator */
	public function provideParserOptionsWithLanguageOverride() {
		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->method( 'optionsHash' )->willReturn( '' );
		$parserOptions->method( 'getUseParsoid' )->willReturn( true );
		$parserOptions->method( 'getTargetLanguage' )
			->willReturn( null );
		yield 'ParserOptions with no language' => [ $parserOptions, 'en' ];

		$langCode = 'de';
		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->method( 'optionsHash' )->willReturn( '' );
		$parserOptions->method( 'getUseParsoid' )->willReturn( true );
		$parserOptions->method( 'getTargetLanguage' )
			->willReturn( $this->getLanguageMock( $langCode ) );
		yield 'ParserOptions for "de" language' => [ $parserOptions, $langCode ];

		$langCode = 'ar';
		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->method( 'optionsHash' )->willReturn( '' );
		$parserOptions->method( 'getUseParsoid' )->willReturn( true );
		$parserOptions->method( 'getTargetLanguage' )
			->willReturn( $this->getLanguageMock( $langCode ) );
		yield 'ParserOptions for "ar" language' => [ $parserOptions, $langCode ];
	}

	/**
	 * @covers \MediaWiki\Rest\Handler\Helper\HtmlOutputRendererHelper::getParserOutput
	 * @dataProvider provideParserOptionsWithLanguageOverride
	 */
	public function testGetParserOutputWithLanguageOverride( $parserOptions, $expectedLangCode ) {
		$services = $this->getServiceContainer();
		$parserOutputAccess = $services->getParsoidOutputAccess();

		$content = 'Test content for ' . __METHOD__;
		$page = Title::makeTitle( NS_MAIN, 'TestGetParserOutputWithLanguageOverride' );
		$this->editPage( $page, $content );

		$status = $parserOutputAccess->getParserOutput( $page, $parserOptions );

		$this->assertTrue( $status->isOK() );

		// assert dummy content in parsoid output HTML
		$html = $status->getValue()->getRawText();
		$this->assertStringContainsString( $content, $html );

		if ( $parserOptions->getTargetLanguage() !== null ) {
			$targetLanguage = $parserOptions->getTargetLanguage()->getCode();
			$this->assertSame( $expectedLangCode, $targetLanguage );
			$this->assertInstanceOf( Language::class, $parserOptions->getTargetLanguage() );
		} else {
			$this->assertNull( $parserOptions->getTargetLanguage() );
		}

		// assert the page language in parsoid output HTML
		$this->assertStringContainsString( 'lang="' . $expectedLangCode . '"', $html );
		$this->assertStringContainsString( 'content="' . $expectedLangCode . '"', $html );
	}

}
