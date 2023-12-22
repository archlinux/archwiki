<?php

namespace MediaWiki\Tests\Rest\Handler\Helper;

use BagOStuff;
use CssContent;
use DeferredUpdates;
use EmptyBagOStuff;
use Exception;
use HashBagOStuff;
use Language;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Edit\SimpleParsoidOutputStash;
use MediaWiki\Hook\ParserLogLinterDataHook;
use MediaWiki\Logger\Spi as LoggerSpi;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Page\PageRecord;
use MediaWiki\Page\ParserOutputAccess;
use MediaWiki\Parser\ParserCacheFactory;
use MediaWiki\Parser\Parsoid\PageBundleParserOutputConverter;
use MediaWiki\Parser\Parsoid\ParsoidOutputAccess;
use MediaWiki\Parser\Parsoid\ParsoidParser;
use MediaWiki\Parser\Parsoid\ParsoidParserFactory;
use MediaWiki\Parser\Parsoid\ParsoidRenderID;
use MediaWiki\Parser\RevisionOutputCache;
use MediaWiki\Rest\Handler\Helper\HtmlOutputRendererHelper;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\Utils\MWTimestamp;
use MediaWikiIntegrationTestCase;
use NullStatsdDataFactory;
use ParserCache;
use ParserOptions;
use ParserOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use Psr\Log\NullLogger;
use Wikimedia\Bcp47Code\Bcp47CodeValue;
use Wikimedia\Message\MessageValue;
use Wikimedia\Parsoid\Core\ClientError;
use Wikimedia\Parsoid\Core\PageBundle;
use Wikimedia\Parsoid\Core\ResourceLimitExceededException;
use Wikimedia\Parsoid\Parsoid;
use Wikimedia\TestingAccessWrapper;
use WikitextContent;

/**
 * @covers \MediaWiki\Rest\Handler\Helper\HtmlOutputRendererHelper
 * @group Database
 */
class HtmlOutputRendererHelperTest extends MediaWikiIntegrationTestCase {
	private const CACHE_EPOCH = '20001111010101';

	private const TIMESTAMP_OLD = '20200101112233';
	private const TIMESTAMP = '20200101223344';
	private const TIMESTAMP_LATER = '20200101234200';

	private const WIKITEXT_OLD = 'Hello \'\'\'Goat\'\'\'';
	private const WIKITEXT = 'Hello \'\'\'World\'\'\'';

	private const HTML_OLD = '>Goat<';
	private const HTML = '>World<';

	private const PARAM_DEFAULTS = [
		'stash' => false,
		'flavor' => 'view',
	];

	private const MOCK_HTML = 'mocked HTML';
	private const MOCK_HTML_VARIANT = 'ockedmay HTML';

	private function exactlyOrAny( ?int $count ): InvocationOrder {
		return $count === null ? $this->any() : $this->exactly( $count );
	}

	public function getParsoidRenderID( ParserOutput $pout ) {
		return new ParsoidRenderID( $pout->getCacheRevisionId(), $pout->getCacheTime() );
	}

	/**
	 * @param LoggerInterface|null $logger
	 *
	 * @return LoggerSpi
	 */
	private function getLoggerSpi( $logger = null ) {
		$logger = $logger ?: new NullLogger();
		$spi = $this->createNoOpMock( LoggerSpi::class, [ 'getLogger' ] );
		$spi->method( 'getLogger' )->willReturn( $logger );
		return $spi;
	}

	/**
	 * @return MockObject|ParsoidOutputAccess
	 */
	public function newMockParsoidOutputAccess(): ParsoidOutputAccess {
		$expectedCalls = [
			'getParserOutput' => null,
			'parseUncacheable' => null,
			'getParsoidRenderID' => null
		];

		$parsoid = $this->createNoOpMock( ParsoidOutputAccess::class, array_keys( $expectedCalls ) );

		$parsoid->expects( $this->exactlyOrAny( $expectedCalls[ 'getParserOutput' ] ) )
			->method( 'getParserOutput' )
			->willReturnCallback( function (
				PageRecord $page,
				ParserOptions $parserOpts,
				$rev = null,
				int $options = 0
			) {
				$pout = $this->makeParserOutput(
					$parserOpts,
					$this->getMockHtml( $rev ),
					$rev,
					$page
				); // will use fake time
				return Status::newGood( $pout );
			} );

		$parsoid->method( 'getParsoidRenderID' )
			->willReturnCallback( [ $this, 'getParsoidRenderID' ] );

		$parsoid->expects( $this->exactlyOrAny( $expectedCalls[ 'parseUncacheable' ] ) )
			->method( 'parseUncacheable' )
			->willReturnCallback( function (
				PageIdentity $page,
				ParserOptions $parserOpts,
				$rev,
				bool $lenientRevHandling,
				array $envOptions = []
			) {
				$html = $this->getMockHtml( $rev, $envOptions );

				$pout = $this->makeParserOutput(
					$parserOpts,
					$html,
					$rev,
					$page
				);

				return Status::newGood( $pout );
			} );

		$parsoid->expects( $this->exactlyOrAny( $expectedCalls[ 'getParsoidRenderID' ] ) )
			->method( 'getParsoidRenderID' )
			->willReturnCallback( [ $this, 'getParsoidRenderID' ] );

		return $parsoid;
	}

	private function getMockHtml( $rev, array $envOptions = null ) {
		if ( $rev instanceof RevisionRecord ) {
			$html = '<p>' . $rev->getContent( SlotRecord::MAIN )->getText() . '</p>';
		} elseif ( is_int( $rev ) ) {
			$html = '<p>rev:' . $rev . '</p>';
		} else {
			$html = self::MOCK_HTML;
		}

		if ( $envOptions ) {
			$html .= "\n<!--" . json_encode( $envOptions ) . "\n-->";
		}

		return $html;
	}

	/**
	 * @param ParserOptions $parserOpts
	 * @param string $html
	 * @param RevisionRecord|int|null $rev
	 * @param PageIdentity $page
	 * @param string|null $version
	 *
	 * @return ParserOutput
	 */
	private function makeParserOutput(
		ParserOptions $parserOpts,
		string $html,
		$rev,
		PageIdentity $page,
		string $version = null
	): ParserOutput {
		$lang = $parserOpts->getTargetLanguage();
		$lang = $lang ? $lang->getCode() : 'en';
		$version ??= Parsoid::defaultHTMLVersion();

		$html = "<!DOCTYPE html><html lang=\"$lang\"><body><div id='t3s7'>$html</div></body></html>";

		if ( $rev instanceof RevisionRecord ) {
			$rev = $rev->getId();
		}

		$pout = new ParserOutput( $html );
		$pout->setCacheRevisionId( $rev ?: $page->getLatest() );
		$pout->setCacheTime( wfTimestampNow() ); // will use fake time
		$pout->setExtensionData( PageBundleParserOutputConverter::PARSOID_PAGE_BUNDLE_KEY, [
			'parsoid' => [ 'ids' => [
				't3s7' => [ 'dsr' => [ 0, 0, 0, 0 ] ],
			] ],
			'mw' => [ 'ids' => [] ],
			'version' => $version,
			'headers' => [
				'content-language' => $lang
			]
		] );

		return $pout;
	}

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( MainConfigNames::CacheEpoch, self::CACHE_EPOCH );

		// Clean up these tables after each test
		$this->tablesUsed = [
			'page',
			'revision',
			'comment',
			'text',
			'content'
		];
	}

	/**
	 * @param array $returns
	 *
	 * @return MockObject|User
	 */
	private function newUser( array $returns = [] ): MockObject {
		$user = $this->createNoOpMock( User::class, [ 'pingLimiter' ] );
		$user->method( 'pingLimiter' )->willReturn( $returns['pingLimiter'] ?? false );
		return $user;
	}

	/**
	 * @param BagOStuff|null $cache
	 * @param ?ParsoidOutputAccess $access
	 *
	 * @return HtmlOutputRendererHelper
	 * @throws Exception
	 */
	private function newHelper(
		BagOStuff $cache = null,
		?ParsoidOutputAccess $access = null
	): HtmlOutputRendererHelper {
		$chFactory = $this->getServiceContainer()->getContentHandlerFactory();
		$cache = $cache ?: new EmptyBagOStuff();
		$stash = new SimpleParsoidOutputStash( $chFactory, $cache, 1 );

		$services = $this->getServiceContainer();

		$helper = new HtmlOutputRendererHelper(
			$stash,
			new NullStatsdDataFactory(),
			$access ?? $this->newMockParsoidOutputAccess(),
			$services->getHtmlTransformFactory(),
			$services->getContentHandlerFactory(),
			$services->getLanguageFactory()
		);

		return $helper;
	}

	private function getExistingPageWithRevisions( $name ) {
		$page = $this->getNonexistingTestPage( $name );

		MWTimestamp::setFakeTime( self::TIMESTAMP_OLD );
		$this->editPage( $page, self::WIKITEXT_OLD );
		$revisions['first'] = $page->getRevisionRecord();

		MWTimestamp::setFakeTime( self::TIMESTAMP );
		$this->editPage( $page, self::WIKITEXT );
		$revisions['latest'] = $page->getRevisionRecord();

		MWTimestamp::setFakeTime( self::TIMESTAMP_LATER );
		return [ $page, $revisions ];
	}

	private function getNonExistingPageWithFakeRevision( $name ) {
		$page = $this->getNonexistingTestPage( $name );
		MWTimestamp::setFakeTime( self::TIMESTAMP_OLD );

		$content = new WikitextContent( self::WIKITEXT_OLD );
		$rev = new MutableRevisionRecord( $page->getTitle() );
		$rev->setPageId( $page->getId() );
		$rev->setContent( SlotRecord::MAIN, $content );

		return [ $page, $rev ];
	}

	public static function provideRevisionReferences() {
		return [
			'current' => [ null, [ 'html' => self::HTML, 'timestamp' => self::TIMESTAMP ] ],
			'old' => [ 'first', [ 'html' => self::HTML_OLD, 'timestamp' => self::TIMESTAMP_OLD ] ],
		];
	}

	/**
	 * @dataProvider provideRevisionReferences()
	 */
	public function testGetHtml( $revRef ) {
		[ $page, $revisions ] = $this->getExistingPageWithRevisions( __METHOD__ );

		// Test with just the revision ID, not the object! We do that elsewhere.
		$revId = $revRef ? $revisions[ $revRef ]->getId() : null;

		$helper = $this->newHelper();
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );

		if ( $revId ) {
			$helper->setRevision( $revId );
			$this->assertSame( $revId, $helper->getRevisionId() );
		} else {
			// current revision
			$this->assertSame( 0, $helper->getRevisionId() );
		}

		$htmlresult = $helper->getHtml()->getRawText();

		$this->assertStringContainsString( $this->getMockHtml( $revId ), $htmlresult );
	}

	public function testGetHtmlWithVariant() {
		$this->overrideConfigValue( MainConfigNames::UsePigLatinVariant, true );
		$page = $this->getExistingTestPage( __METHOD__ );

		$helper = $this->newHelper();
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );
		$helper->setVariantConversionLanguage( new Bcp47CodeValue( 'en-x-piglatin' ) );

		$htmlResult = $helper->getHtml()->getRawText();
		$this->assertStringContainsString( self::MOCK_HTML_VARIANT, $htmlResult );
		$this->assertStringContainsString( 'en-x-piglatin', $helper->getETag() );

		$pbResult = $helper->getPageBundle();
		$this->assertStringContainsString( self::MOCK_HTML_VARIANT, $pbResult->html );
		$this->assertStringContainsString( 'en-x-piglatin', $pbResult->headers['content-language'] );
	}

	public function testGetHtmlWillLint() {
		$this->overrideConfigValue( MainConfigNames::ParsoidSettings, [
			'linting' => true
		] );

		$page = $this->getExistingTestPage( __METHOD__ );

		$mockHandler = $this->createMock( ParserLogLinterDataHook::class );
		$mockHandler->expects( $this->once() ) // this is the critical assertion in this test case!
			->method( 'onParserLogLinterData' );

		$this->setTemporaryHook(
			'ParserLogLinterData',
			$mockHandler
		);

		// Use the real ParsoidOutputAccess, so we use the real hook container.
		$access = $this->getServiceContainer()->getParsoidOutputAccess();

		$helper = $this->newHelper( null, $access );
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );

		// Do it.
		$helper->getHtml();
	}

	public function testGetPageBundleWithOptions() {
		$this->markTestSkipped( 'T347426: Support for non-default output content major version has been disabled.' );
		$page = $this->getExistingTestPage( __METHOD__ );

		$helper = $this->newHelper();
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );

		// Calling setParsoidOptions must disable caching and force the ETag to null
		$helper->setOutputProfileVersion( '999.0.0' );

		$pb = $helper->getPageBundle();

		// NOTE: Check that the options are present in the HTML.
		//       We don't do real parsing, so this is how they are represented in the output.
		$this->assertStringContainsString( '"outputContentVersion":"999.0.0"', $pb->html );
		$this->assertStringContainsString( '"offsetType":"byte"', $pb->html );

		$response = new Response();
		$helper->putHeaders( $response, true );
		$this->assertStringContainsString( 'private', $response->getHeaderLine( 'Cache-Control' ) );
	}

	public function testGetPreviewHtml_setContent() {
		$page = $this->getNonexistingTestPage();

		$helper = $this->newHelper();
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );
		$helper->setContent( new WikitextContent( 'text to preview' ) );

		// getRevisionId() should return null for fake revisions.
		$this->assertNull( $helper->getRevisionId() );

		$htmlresult = $helper->getHtml()->getRawText();

		$this->assertStringContainsString( 'text to preview', $htmlresult );
	}

	public function testGetPreviewHtml_setContentSource() {
		$page = $this->getNonexistingTestPage();

		$helper = $this->newHelper();
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );
		$helper->setContentSource( 'text to preview', CONTENT_MODEL_WIKITEXT );

		$htmlresult = $helper->getHtml()->getRawText();

		$this->assertStringContainsString( 'text to preview', $htmlresult );
	}

	public function testHtmlIsStashedForExistingPage() {
		[ $page, ] = $this->getExistingPageWithRevisions( __METHOD__ );

		$cache = new HashBagOStuff();

		$helper = $this->newHelper( $cache );

		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );
		$helper->setStashingEnabled( true );

		$htmlresult = $helper->getHtml()->getRawText();
		$this->assertStringContainsString( self::MOCK_HTML, $htmlresult );

		$eTag = $helper->getETag();
		$parsoidStashKey = ParsoidRenderID::newFromETag( $eTag );

		$chFactory = $this->createNoOpMock( IContentHandlerFactory::class );
		$stash = new SimpleParsoidOutputStash( $chFactory, $cache, 1 );
		$this->assertNotNull( $stash->get( $parsoidStashKey ) );
	}

	public function testHtmlIsStashedForFakeRevision() {
		$page = $this->getNonexistingTestPage();

		$cache = new HashBagOStuff();
		$helper = $this->newHelper( $cache );

		$text = 'just some wikitext';

		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );
		$helper->setContent( new WikitextContent( $text ) );
		$helper->setStashingEnabled( true );

		$htmlresult = $helper->getHtml()->getRawText();
		$this->assertStringContainsString( $text, $htmlresult );

		$eTag = $helper->getETag();
		$parsoidStashKey = ParsoidRenderID::newFromETag( $eTag );

		$chFactory = $this->getServiceContainer()->getContentHandlerFactory();
		$stash = new SimpleParsoidOutputStash( $chFactory, $cache, 1 );

		$selserContext = $stash->get( $parsoidStashKey );
		$this->assertNotNull( $selserContext );

		/** @var WikitextContent $stashedContent */
		$stashedContent = $selserContext->getContent();
		$this->assertNotNull( $stashedContent );
		$this->assertInstanceOf( WikitextContent::class, $stashedContent );
		$this->assertSame( $text, $stashedContent->getText() );
	}

	public function testStashRateLimit() {
		$page = $this->getExistingTestPage( __METHOD__ );

		$helper = $this->newHelper();

		$user = $this->newUser( [ 'pingLimiter' => true ] );
		$helper->init( $page, self::PARAM_DEFAULTS, $user );
		$helper->setStashingEnabled( true );

		$this->expectException( LocalizedHttpException::class );
		$this->expectExceptionCode( 429 );
		$helper->getHtml();
	}

	public function testInteractionOfStashAndFlavor() {
		$page = $this->getExistingTestPage( __METHOD__ );

		$helper = $this->newHelper();

		$user = $this->newUser( [ 'pingLimiter' => true ] );
		$helper->init( $page, self::PARAM_DEFAULTS, $user );

		// Assert that the initial flavor is "view"
		$this->assertSame( 'view', $helper->getFlavor() );

		// Assert that we can change the flavor to "edit"
		$helper->setFlavor( 'edit' );
		$this->assertSame( 'edit', $helper->getFlavor() );

		// Assert that enabling stashing will force the flavor to be "stash"
		$helper->setStashingEnabled( true );
		$this->assertSame( 'stash', $helper->getFlavor() );

		// Assert that disabling stashing will reset the flavor to "view"
		$helper->setStashingEnabled( false );
		$this->assertSame( 'view', $helper->getFlavor() );

		// Assert that we cannot change the flavor to "view" when stashing is enabled
		$helper->setStashingEnabled( true );
		$helper->setFlavor( 'view' );
		$this->assertSame( 'stash', $helper->getFlavor() );
	}

	public function testGetHtmlFragment() {
		$page = $this->getExistingTestPage();

		$helper = $this->newHelper();
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );
		$helper->setFlavor( 'fragment' );

		$htmlresult = $helper->getHtml()->getRawText();

		$this->assertStringContainsString( 'fragment', $helper->getETag() );
		$this->assertStringContainsString( self::MOCK_HTML, $htmlresult );
		$this->assertStringNotContainsString( "<body", $htmlresult );
		$this->assertStringNotContainsString( "<section", $htmlresult );
	}

	public function testGetHtmlForEdit() {
		$page = $this->getExistingTestPage();

		$helper = $this->newHelper();
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );
		$helper->setContentSource( 'hello {{world}}', CONTENT_MODEL_WIKITEXT );
		$helper->setFlavor( 'edit' );

		$htmlresult = $helper->getHtml()->getRawText();

		$this->assertStringContainsString( 'edit', $helper->getETag() );

		$this->assertStringContainsString( 'hello', $htmlresult );
		$this->assertStringContainsString( 'data-parsoid=', $htmlresult );
		$this->assertStringContainsString( '"dsr":', $htmlresult );
	}

	/**
	 * @dataProvider provideRevisionReferences()
	 */
	public function testEtagLastModified( $revRef ) {
		[ $page, $revisions ] = $this->getExistingPageWithRevisions( __METHOD__ );
		$rev = $revRef ? $revisions[ $revRef ] : null;

		$cache = new HashBagOStuff();

		// First, test it works if nothing was cached yet.
		$helper = $this->newHelper( $cache );
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser(), $rev );

		// put HTML into the cache
		$pout = $helper->getHtml();

		$renderId = $this->getParsoidRenderID( $pout );
		$lastModified = $pout->getCacheTime();

		if ( $rev ) {
			$this->assertSame( $rev->getId(), $helper->getRevisionId() );
		} else {
			// current revision
			$this->assertSame( 0, $helper->getRevisionId() );
		}

		// make sure the etag didn't change after getHtml();
		$this->assertStringContainsString( $renderId->getKey(), $helper->getETag() );
		$this->assertSame(
			MWTimestamp::convert( TS_MW, $lastModified ),
			MWTimestamp::convert( TS_MW, $helper->getLastModified() )
		);

		// Now, expire the cache. etag and timestamp should change
		$now = MWTimestamp::convert( TS_UNIX, self::TIMESTAMP_LATER ) + 10000;
		MWTimestamp::setFakeTime( $now );
		$this->assertTrue(
			$page->getTitle()->invalidateCache( MWTimestamp::convert( TS_MW, $now ) ),
			'Cannot invalidate cache'
		);
		DeferredUpdates::doUpdates();
		$page->clear();

		$helper = $this->newHelper( $cache );
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser(), $rev );

		$this->assertStringNotContainsString( $renderId->getKey(), $helper->getETag() );
		$this->assertSame(
			MWTimestamp::convert( TS_MW, $now ),
			MWTimestamp::convert( TS_MW, $helper->getLastModified() )
		);
	}

	/**
	 * @covers \MediaWiki\Rest\Handler\Helper\HtmlOutputRendererHelper::init
	 * @covers \MediaWiki\Parser\Parsoid\ParsoidOutputAccess::parseUncacheable
	 */
	public function testEtagLastModifiedWithPageIdentity() {
		[ $fakePage, $fakeRevision ] = $this->getNonExistingPageWithFakeRevision( __METHOD__ );
		$poa = $this->createMock( ParsoidOutputAccess::class );
		$poa->expects( $this->once() )
			->method( 'parseUncacheable' )
			->willReturnCallback( function (
				PageIdentity $page,
				ParserOptions $parserOpts,
				$rev,
				bool $lenientRevHandling,
				array $envOptions = []
			) use ( $fakePage, $fakeRevision ) {
				self::assertSame( $page, $fakePage, '$page and $fakePage should be the same' );
				self::assertSame( $rev, $fakeRevision, '$rev and $fakeRevision should be the same' );

				$html = $this->getMockHtml( $rev, $envOptions );
				$pout = $this->makeParserOutput( $parserOpts, $html, $rev, $page );
				return Status::newGood( $pout );
			} );
		$poa->method( 'getParsoidRenderID' )
			->willReturnCallback( [ $this, 'getParsoidRenderID' ] );

		$helper = $this->newHelper( null, $poa );
		$helper->init( $fakePage, self::PARAM_DEFAULTS, $this->newUser() );
		$helper->setRevision( $fakeRevision );

		$this->assertNull( $helper->getRevisionId() );

		$pout = $helper->getHtml();
		$renderId = $this->getParsoidRenderID( $pout );
		$lastModified = $pout->getCacheTime();

		$this->assertStringContainsString( $renderId->getKey(), $helper->getETag() );
		$this->assertSame(
			MWTimestamp::convert( TS_MW, $lastModified ),
			MWTimestamp::convert( TS_MW, $helper->getLastModified() )
		);
	}

	public static function provideETagSuffix() {
		yield 'stash + html' =>
		[ [ 'stash' => true ], 'html', '/stash/html' ];

		yield 'view html' =>
		[ [], 'html', '/view/html' ];

		yield 'stash + wrapped' =>
		[ [ 'stash' => true ], 'with_html', '/stash/with_html' ];

		yield 'view wrapped' =>
		[ [], 'with_html', '/view/with_html' ];

		yield 'stash' =>
		[ [ 'stash' => true ], '', '/stash' ];

		yield 'flavor = fragment' =>
		[ [ 'flavor' => 'fragment' ], '', '/fragment' ];

		yield 'flavor = fragment + stash = true: stash should take over' =>
		[ [ 'stash' => true, 'flavor' => 'fragment' ], '', '/stash' ];

		yield 'nothing' =>
		[ [], '', '/view' ];
	}

	/**
	 * @dataProvider provideETagSuffix()
	 */
	public function testETagSuffix( array $params, string $mode, string $suffix ) {
		$page = $this->getExistingTestPage( __METHOD__ );

		$cache = new HashBagOStuff();

		// First, test it works if nothing was cached yet.
		$helper = $this->newHelper( $cache );
		$helper->init( $page, $params + self::PARAM_DEFAULTS, $this->newUser() );

		$etag = $helper->getETag( $mode );
		$etag = trim( $etag, '"' );
		$this->assertStringEndsWith( $suffix, $etag );
	}

	public static function provideHandlesParsoidError() {
		yield 'ClientError' => [
			new ClientError( 'TEST_TEST' ),
			new LocalizedHttpException(
				new MessageValue( 'rest-html-backend-error' ),
				400,
				[
					'reason' => 'TEST_TEST'
				]
			)
		];
		yield 'ResourceLimitExceededException' => [
			new ResourceLimitExceededException( 'TEST_TEST' ),
			new LocalizedHttpException(
				new MessageValue( 'rest-resource-limit-exceeded' ),
				413,
				[
					'reason' => 'TEST_TEST'
				]
			)
		];
		yield 'RevisionAccessException' => [
			new RevisionAccessException( 'TEST_TEST' ),
			new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-title' ),
				404,
				[
					'reason' => 'TEST_TEST'
				]
			)
		];
	}

	private function resetServicesWithMockedParsoid( ?Parsoid $mockParsoid = null ): void {
		$services = $this->getServiceContainer();

		// Init mock Parsoid object
		if ( !$mockParsoid ) {
			$mockParsoid = $this->createNoOpMock( Parsoid::class, [ 'wikitext2html' ] );
			$mockParsoid->method( 'wikitext2html' )
				->willReturn( new PageBundle( 'This is HTML' ) );
		}

		// Install it in the ParsoidParser object
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
		$mockParsoidParserFactory->method( 'create' )->willReturn( $parsoidParser );

		$this->setService( 'ParsoidParserFactory', $mockParsoidParserFactory );
	}

	private function newRealParsoidOutputAccess( $overrides = [] ): ParsoidOutputAccess {
		$services = $this->getServiceContainer();

		if ( isset( $overrides['parserCache'] ) ) {
			$parserCache = $overrides['parserCache'];
		} else {
			$parserCache = $this->createNoOpMock( ParserCache::class, [ 'get', 'save' ] );
			$parserCache->method( 'get' )->willReturn( false );
			$parserCache->method( 'save' )->willReturn( null );
		}

		if ( isset( $overrides['revisionCache'] ) ) {
			$revisionCache = $overrides['revisionCache'];
		} else {
			$revisionCache = $this->createNoOpMock( RevisionOutputCache::class, [ 'get', 'save' ] );
			$revisionCache->method( 'get' )->willReturn( false );
			$revisionCache->method( 'save' )->willReturn( null );
		}

		$parserCacheFactory = $this->createNoOpMock(
			ParserCacheFactory::class,
			[ 'getParserCache', 'getRevisionOutputCache' ]
		);
		$parserCacheFactory->method( 'getParserCache' )->willReturn( $parserCache );
		$parserCacheFactory->method( 'getRevisionOutputCache' )->willReturn( $revisionCache );
		$parserOutputAccess = new ParserOutputAccess(
			$parserCacheFactory,
			$services->getRevisionLookup(),
			$services->getRevisionRenderer(),
			new NullStatsdDataFactory(),
			$services->getDBLoadBalancerFactory(),
			$services->getChronologyProtector(),
			$this->getLoggerSpi(),
			$services->getWikiPageFactory(),
			$services->getTitleFormatter()
		);

		return new ParsoidOutputAccess(
			new ServiceOptions(
				ParsoidOutputAccess::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig(),
				[ 'ParsoidWikiID' => 'MyWiki' ]
			),
			$services->getParsoidParserFactory(),
			$parserOutputAccess,
			$services->getPageStore(),
			$services->getRevisionLookup(),
			$services->getParsoidSiteConfig(),
			$services->getContentHandlerFactory()
		);
	}

	/**
	 * @dataProvider provideHandlesParsoidError
	 */
	public function testHandlesParsoidError(
		Exception $parsoidException,
		Exception $expectedException
	) {
		$page = $this->getExistingTestPage( __METHOD__ );

		$parsoid = $this->createNoOpMock( Parsoid::class, [ 'wikitext2html' ] );
		$parsoid->method( 'wikitext2html' )
			->willThrowException( $parsoidException );

		$parserCache = $this->createNoOpMock( ParserCache::class, [ 'get', 'makeParserOutputKey', 'getMetadata' ] );
		$parserCache->method( 'get' )->willReturn( false );
		$parserCache->expects( $this->once() )->method( 'getMetadata' );
		$parserCache->expects( $this->atLeastOnce() )->method( 'makeParserOutputKey' );

		$this->resetServicesWithMockedParsoid( $parsoid );
		$access = $this->newRealParsoidOutputAccess( [ 'parserCache' => $parserCache ] );

		$helper = $this->newHelper( null, $access );
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );

		$this->expectExceptionObject( $expectedException );
		$helper->getHtml();
	}

	public function testWillUseParserCache() {
		$page = $this->getExistingTestPage( __METHOD__ );

		// NOTE: Use a simple PageIdentity here, to make sure the relevant PageRecord
		//       will be looked up as needed.
		$page = PageIdentityValue::localIdentity( $page->getId(), $page->getNamespace(), $page->getDBkey() );

		// This is the key assertion in this test case: get() and save() are both called.
		$parserCache = $this->createNoOpMock( ParserCache::class, [ 'get', 'save', 'getMetadata', 'makeParserOutputKey' ] );
		$parserCache->expects( $this->once() )->method( 'get' )->willReturn( false );
		$parserCache->expects( $this->once() )->method( 'save' );
		$parserCache->expects( $this->once() )->method( 'getMetadata' );
		$parserCache->expects( $this->atLeastOnce() )->method( 'makeParserOutputKey' );

		$this->resetServicesWithMockedParsoid();
		$access = $this->newRealParsoidOutputAccess( [
			'parserCache' => $parserCache,
			'revisionCache' => $this->createNoOpMock( RevisionOutputCache::class )
		] );

		$helper = $this->newHelper( null, $access );
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );

		$helper->getHtml();
	}

	public function testDisableParserCacheWrite() {
		$page = $this->getExistingTestPage( __METHOD__ );

		// NOTE: The save() method is not supported and will throw!
		//       The point of this test case is asserting that save() isn't called.
		$parserCache = $this->createNoOpMock( ParserCache::class, [ 'get', 'getMetadata', 'makeParserOutputKey' ] );
		$parserCache->method( 'get' )->willReturn( false );
		$parserCache->expects( $this->once() )->method( 'getMetadata' );
		$parserCache->expects( $this->atLeastOnce() )->method( 'makeParserOutputKey' );

		$this->resetServicesWithMockedParsoid();
		$access = $this->newRealParsoidOutputAccess( [
			'parserCache' => $parserCache,
			'revisionCache' => $this->createNoOpMock( RevisionOutputCache::class ),
		] );

		$helper = $this->newHelper( null, $access );
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );

		// Set read = true, write = false
		$helper->setUseParserCache( true, false );
		$helper->getHtml();
	}

	public function testDisableParserCacheRead() {
		$page = $this->getExistingTestPage( __METHOD__ );

		// NOTE: The get() method is not supported and will throw!
		//       The point of this test case is asserting that get() isn't called.
		//       We also check that save() is still called.
		$parserCache = $this->createNoOpMock( ParserCache::class, [ 'save', 'getMetadata', 'makeParserOutputKey' ] );
		$parserCache->expects( $this->once() )->method( 'save' );
		$parserCache->expects( $this->once() )->method( 'getMetadata' );
		$parserCache->expects( $this->atLeastOnce() )->method( 'makeParserOutputKey' );

		$this->resetServicesWithMockedParsoid();
		$access = $this->newRealParsoidOutputAccess( [
			'parserCache' => $parserCache,
			'revisionCache' => $this->createNoOpMock( RevisionOutputCache::class ),
		] );

		$helper = $this->newHelper( null, $access );
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );

		// Set read = false, write = true
		$helper->setUseParserCache( false, true );
		$helper->getHtml();
	}

	public function testGetParserOutputWithLanguageOverride() {
		$helper = $this->newHelper();

		[ $page, $revision ] = $this->getNonExistingPageWithFakeRevision( __METHOD__ );

		$helper->init( $page, [], $this->newUser(), $revision );
		$helper->setPageLanguage( 'ar' );

		// check nominal content language
		$this->assertSame( 'ar', $helper->getHtmlOutputContentLanguage()->toBcp47Code() );

		// check content language in HTML
		$output = $helper->getHtml();
		$html = $output->getRawText();
		$this->assertStringContainsString( 'lang="ar"', $html );
	}

	public function testGetParserOutputWithRedundantPageLanguage() {
		$poa = $this->createMock( ParsoidOutputAccess::class );
		$poa->expects( $this->once() )
			->method( 'getParserOutput' )
			->willReturnCallback( function (
				PageIdentity $page,
				ParserOptions $parserOpts,
				$revision = null,
				int $options = 0
			) {
				$usedOptions = [ 'targetLanguage' ];
				self::assertNull( $parserOpts->getTargetLanguage(), 'No target language should be set in ParserOptions' );
				self::assertTrue( $parserOpts->isSafeToCache( $usedOptions ) );

				$html = $this->getMockHtml( $revision );
				$pout = $this->makeParserOutput( $parserOpts, $html, $revision, $page );
				return Status::newGood( $pout );
			} );
		$poa->method( 'getParsoidRenderID' )
			->willReturnCallback( [ $this, 'getParsoidRenderID' ] );

		$helper = $this->newHelper( null, $poa );

		$page = $this->getExistingTestPage();

		$helper->init( $page, [], $this->newUser() );

		// Explicitly set the page language to the default.
		$pageLanguage = $page->getTitle()->getPageLanguage();
		$helper->setPageLanguage( $pageLanguage );

		// Trigger parsing, so the assertions in the mock are executed.
		$helper->getHtml();
	}

	public function provideInit() {
		$page = PageIdentityValue::localIdentity( 7, NS_MAIN, 'Köfte' );
		$user = $this->createNoOpMock( User::class );

		yield 'Minimal' => [
			$page,
			[],
			$user,
			null,
			null,
			[
				'page' => $page,
				'user' => $user,
				'revisionOrId' => null,
				'pageLanguage' => null,
				'stash' => false,
				'flavor' => 'view',
			]
		];

		$rev = $this->createNoOpMock( RevisionRecord::class, [ 'getId' ] );
		$rev->method( 'getId' )->willReturn( 7 );

		$lang = $this->createNoOpMock( Language::class );
		yield 'Revision and Language' => [
			$page,
			[],
			$user,
			$rev,
			$lang,
			[
				'revisionOrId' => $rev,
				'pageLanguage' => $lang,
			]
		];

		yield 'revid and stash' => [
			$page,
			[ 'stash' => true ],
			$user,
			8,
			null,
			[
				'stash' => true,
				'flavor' => 'stash',
				'revisionOrId' => 8,
			]
		];

		yield 'flavor' => [
			$page,
			[ 'flavor' => 'fragment' ],
			$user,
			8,
			null,
			[
				'flavor' => 'fragment',
			]
		];

		yield 'stash winds over flavor' => [
			$page,
			[ 'flavor' => 'fragment', 'stash' => true ],
			$user,
			8,
			null,
			[
				'flavor' => 'stash',
			]
		];
	}

	/**
	 * Whitebox test for ensuring that init() sets the correct members.
	 * Testing init() against behavior would mean duplicating all tests that use setters.
	 *
	 * @param PageIdentity $page
	 * @param array $parameters
	 * @param User $user
	 * @param RevisionRecord|int|null $revision
	 * @param Language|null $pageLanguage
	 * @param array $expected
	 *
	 * @dataProvider provideInit
	 */
	public function testInit(
		PageIdentity $page,
		array $parameters,
		User $user,
		$revision,
		?Language $pageLanguage,
		array $expected
	) {
		$helper = $this->newHelper();

		$helper->init( $page, $parameters, $user, $revision, $pageLanguage );

		$wrapper = TestingAccessWrapper::newFromObject( $helper );
		foreach ( $expected as $name => $value ) {
			$this->assertSame( $value, $wrapper->$name );
		}
	}

	/**
	 * @dataProvider providePutHeaders
	 */
	public function testPutHeaders( ?string $targetLanguage, bool $setContentLanguageHeader ) {
		$this->overrideConfigValue( MainConfigNames::UsePigLatinVariant, true );
		$page = $this->getExistingTestPage( __METHOD__ );
		$expectedCalls = [];

		$helper = $this->newHelper();
		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );

		if ( $targetLanguage ) {
			$helper->setVariantConversionLanguage( new Bcp47CodeValue( $targetLanguage ) );
			$expectedCalls['addHeader'] = [ [ 'Vary', 'Accept-Language' ] ];
		}

		if ( $setContentLanguageHeader ) {
			$expectedCalls['setHeader'][] = [ 'Content-Language', $targetLanguage ?: 'en' ];

			$version = Parsoid::defaultHTMLVersion();
			$expectedCalls['setHeader'][] = [
				'Content-Type',
				'text/html; charset=utf-8; profile="https://www.mediawiki.org/wiki/Specs/HTML/' . $version . '"',
			];
		}

		$responseInterface = $this->getResponseInterfaceMock( $expectedCalls );
		$helper->putHeaders( $responseInterface, $setContentLanguageHeader );
	}

	public static function providePutHeaders() {
		yield 'no target variant language' => [ null, true ];
		yield 'target language is set but setContentLanguageHeader is false' => [ 'en-x-piglatin', false ];
		yield 'target language and setContentLanguageHeader flag is true' =>
			[ 'en-x-piglatin', true ];
	}

	private function getResponseInterfaceMock( array $expectedCalls ) {
		$responseInterface = $this->createNoOpMock( ResponseInterface::class, array_keys( $expectedCalls ) );
		foreach ( $expectedCalls as $method => $argument ) {
			$responseInterface
				->expects( $this->exactly( count( $argument ) ) )
				->method( $method )
				->withConsecutive( ...$argument );
		}

		return $responseInterface;
	}

	public static function provideFlavorsForBadModelOutput() {
		yield 'view' => [ 'view' ];
		yield 'edit' => [ 'edit' ];
		// fragment mode is only for posted wikitext fragments not part of a revision
		// and should not be used with real revisions
		//
		// yield 'fragment' => [ 'fragment' ];
	}

	/**
	 * @dataProvider provideFlavorsForBadModelOutput
	 */
	public function testDummyContentForBadModel( string $flavor ) {
		$this->resetServicesWithMockedParsoid();
		$helper = $this->newHelper( new HashBagOStuff(), $this->newRealParsoidOutputAccess() );

		$page = $this->getNonexistingTestPage( __METHOD__ );
		$this->editPage( $page, new CssContent( '"not wikitext"' ) );

		$helper->init( $page, self::PARAM_DEFAULTS, $this->newUser() );
		$helper->setFlavor( $flavor );

		$output = $helper->getHtml();
		$this->assertStringContainsString( 'Dummy output', $output->getText() );
		$this->assertSame( '0/dummy-output', $output->getExtensionData( 'parsoid-render-id' ) );
	}

	/**
	 * HtmlOutputRendererHelper should force a reparse if getParserOuput doesn't
	 * return Parsoid's default version.
	 */
	public function testForceDefault() {
		$page = $this->getExistingTestPage();

		$poa = $this->createMock( ParsoidOutputAccess::class );
		$poa->method( 'getParserOutput' )
			->willReturnCallback( function (
				PageIdentity $page,
				ParserOptions $parserOpts,
				$revision = null,
				int $options = 0
			) {
				static $first = true;
				if ( $first ) {
					$version = '1.1.1'; // Not the default
					$first = false;
				} else {
					$version = Parsoid::defaultHTMLVersion();
					$this->assertGreaterThan( 0, $options & ParserOutputAccess::OPT_FORCE_PARSE );
				}
				$html = $this->getMockHtml( $revision );
				$pout = $this->makeParserOutput( $parserOpts, $html, $revision, $page, $version );
				return Status::newGood( $pout );
			} );

		$helper = $this->newHelper( null, $poa );
		$helper->init( $page, [], $this->newUser() );
		$pb = $helper->getPageBundle();
		$this->assertSame( $pb->version, Parsoid::defaultHTMLVersion() );
	}

}
