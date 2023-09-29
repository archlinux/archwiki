<?php

namespace MediaWiki\Extension\VisualEditor\Tests;

use Generator;
use Language;
use LanguageCode;
use MediaWiki\Extension\VisualEditor\DirectParsoidClient;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Parser\Parsoid\ParsoidOutputAccess;
use MediaWiki\Rest\Handler\Helper\PageRestHelperFactory;
use MediaWiki\Rest\HttpException;
use MediaWiki\Revision\RevisionRecord;
use MediaWikiIntegrationTestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\VisualEditor\DirectParsoidClient
 * @group Database
 */
class DirectParsoidClientTest extends MediaWikiIntegrationTestCase {

	/**
	 * @return DirectParsoidClient
	 */
	private function createDirectClient(): DirectParsoidClient {
		$services = $this->getServiceContainer();
		$directClient = new DirectParsoidClient(
			$services->getPageRestHelperFactory(),
			$services->getUserFactory()->newAnonymous()
		);

		return $directClient;
	}

	/**
	 * @return DirectParsoidClient
	 */
	private function createDirectClientWithHttpExceptionFromFactory(): DirectParsoidClient {
		$factory = $this->createNoOpMock( PageRestHelperFactory::class, [
			'newHtmlOutputRendererHelper',
			'newHtmlInputTransformHelper',
		] );

		$e = new HttpException( 'testing', 400 );
		$factory->method( 'newHtmlOutputRendererHelper' )->willThrowException( $e );
		$factory->method( 'newHtmlInputTransformHelper' )->willThrowException( $e );

		$services = $this->getServiceContainer();
		$directClient = new DirectParsoidClient(
			$factory,
			$services->getUserFactory()->newAnonymous()
		);

		return $directClient;
	}

	/** @return Generator */
	public function provideLanguageCodes() {
		yield 'German language code' => [ 'de' ];
		yield 'English language code' => [ 'en' ];
		yield 'French language code' => [ 'fr' ];
		yield 'No language code, fallback to en' => [ null ];
	}

	private function createLanguage( $langCode, $allowNull = false ) {
		if ( $langCode === null ) {
			$language = $this->getServiceContainer()->getContentLanguage();
			$langCode = $language->getCode();
			if ( $allowNull ) {
				$language = null;
			}
		} else {
			$language = $this->createNoOpMock(
				Language::class,
				[ 'getCode', 'toBcp47Code', 'getDir' ]
			);
			$language->method( 'getCode' )->willReturn( $langCode );
			$language->method( 'toBcp47Code' )->willReturn( LanguageCode::bcp47( $langCode ) );
			$language->method( 'getDir' )->willReturn( 'ltr' );
		}

		return [ $language, $langCode ];
	}

	/**
	 * @covers ::getPageHtml
	 * @dataProvider provideLanguageCodes
	 */
	public function testGetPageHtml( $langCode ) {
		$directClient = $this->createDirectClient();

		$revision = $this->getExistingTestPage( 'DirectParsoidClient' )
			->getRevisionRecord();

		[ $language, $langCode ] = $this->createLanguage( $langCode, true );
		$response = $directClient->getPageHtml( $revision, $language );

		$pageHtml = $response['body'];
		$headers = $response['headers'];

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'body', $response );
		$this->assertStringContainsString( 'DirectParsoidClient', $pageHtml );

		$this->assertArrayHasKey( 'headers', $response );
		$this->assertSame( $langCode, $headers['content-language'] );
		$this->assertStringContainsString( 'lang="' . $langCode . '"', $pageHtml );

		$this->assertArrayHasKey( 'etag', $headers );
		$this->assertStringContainsString( (string)$revision->getId(), $headers['etag'] );
	}

	/**
	 * @covers ::transformHTML
	 * @dataProvider provideLanguageCodes
	 */
	public function testTransformHtml( $langCode ) {
		$directClient = $this->createDirectClient();

		$pageIdentity = PageIdentityValue::localIdentity(
			1,
			NS_MAIN,
			'DirectParsoidClient'
		);
		[ $language, ] = $this->createLanguage( $langCode );

		$html = '<h2>Hello World</h2>';
		$oldid = $pageIdentity->getId();

		$response = $directClient->transformHTML(
			$pageIdentity,
			$language,
			$html,
			$oldid,
			// Supplying "null" will use the $oldid and look at recent rendering in ParserCache.
			null
		);

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'headers', $response );
		$this->assertArrayHasKey( 'Content-Type', $response['headers'] );

		$this->assertArrayHasKey( 'body', $response );
		// Trim to remove trailing newline
		$wikitext = trim( $response['body'] );
		$this->assertStringContainsString( '== Hello World ==', $wikitext );
	}

	/**
	 * @covers ::transformWikitext
	 * @dataProvider provideLanguageCodes
	 */
	public function testTransformWikitext( $langCode ) {
		$directClient = $this->createDirectClient();

		$page = $this->getExistingTestPage( 'DirectParsoidClient' );
		$pageRecord = $page->toPageRecord();
		$wikitext = '== Hello World ==';
		[ $language, $langCode ] = $this->createLanguage( $langCode );

		$response = $directClient->transformWikitext(
			$pageRecord,
			$language,
			$wikitext,
			false,
			$pageRecord->getLatest(),
			false
		);

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'body', $response );
		$this->assertArrayHasKey( 'headers', $response );

		$headers = $response['headers'];
		$this->assertSame( $langCode, $headers['content-language'] );

		$html = $response['body'];
		$this->assertStringContainsString( $page->getTitle()->getText(), $html );
		$this->assertStringContainsString( '>Hello World</h2>', $html );
	}

	/** @covers ::transformHTML */
	public function testRoundTripSelserWithETag() {
		$directClient = $this->createDirectClient();

		// Nasty wikitext that would be reformated without selser.
		$originalWikitext = '*a\n* b\n*  <i>c</I>';

		/** @var RevisionRecord $revision */
		$revision = $this->editPage( 'RoundTripSelserWithETag', $originalWikitext )
			->getValue()['revision-record'];

		$pageHtmlResponse = $directClient->getPageHtml( $revision );
		$eTag = $pageHtmlResponse['headers']['etag'];
		$oldHtml = $pageHtmlResponse['body'];
		$updatedHtml = str_replace( '</body>', '<p>More Text</p></body>', $oldHtml );

		// Now make a new client object, so we can mock the ParsoidOutputAccess.
		$parsoidOutputAccess = $this->createNoOpMock( ParsoidOutputAccess::class );
		$services = $this->getServiceContainer();
		$directClient = new DirectParsoidClient(
			$services->getPageRestHelperFactory(),
			$services->getUserFactory()->newAnonymous()
		);

		[ $targetLanguage, ] = $this->createLanguage( 'en' );
		$transformHtmlResponse = $directClient->transformHTML(
			$revision->getPage(),
			$targetLanguage,
			$updatedHtml,
			$revision->getId(),
			$eTag
		);

		$updatedWikitext = $transformHtmlResponse['body'];
		$this->assertStringContainsString( $originalWikitext, $updatedWikitext );
		$this->assertStringContainsString( 'More Text', $updatedWikitext );
	}

	/** @covers ::transformHTML */
	public function testRoundTripSelserWithoutETag() {
		$directClient = $this->createDirectClient();

		// Nasty wikitext that would be reformated without selser.
		$originalWikitext = '*a\n* b\n*  <i>c</I>';

		/** @var RevisionRecord $revision */
		$revision = $this->editPage( 'RoundTripSelserWithoutETag', $originalWikitext )
			->getValue()['revision-record'];

		$pageHtmlResponse = $directClient->getPageHtml( $revision );
		$oldHtml = $pageHtmlResponse['body'];
		$updatedHtml = str_replace( '</body>', '<p>More Text</p></body>', $oldHtml );

		[ $targetLanguage, ] = $this->createLanguage( 'en' );
		$transformHtmlResponse = $directClient->transformHTML(
			$revision->getPage(),
			$targetLanguage,
			$updatedHtml,
			$revision->getId(),
			null
		);

		// Selser should still work, because the current rendering of the page still matches.
		$updatedWikitext = $transformHtmlResponse['body'];
		$this->assertStringContainsString( $originalWikitext, $updatedWikitext );
		$this->assertStringContainsString( 'More Text', $updatedWikitext );
	}

	/**
	 * @covers ::getPageHtml
	 */
	public function testGetPageHtml_HttpException() {
		$directClient = $this->createDirectClientWithHttpExceptionFromFactory();

		$revision = $this->getExistingTestPage( 'DirectParsoidClient' )
			->getRevisionRecord();

		$response = $directClient->getPageHtml( $revision );
		$this->assertArrayHasKey( 'error', $response );
		$this->assertSame( 'testing', $response['error']['message'] );
	}

	/**
	 * @covers ::getPageHtml
	 */
	public function testTransformHtml_HttpException() {
		$directClient = $this->createDirectClientWithHttpExceptionFromFactory();

		$page = $this->getExistingTestPage( 'DirectParsoidClient' );

		$response = $directClient->transformHTML(
			$page,
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'en' ),
			'some html',
			null,
			null
		);
		$this->assertArrayHasKey( 'error', $response );
		$this->assertSame( 'testing', $response['error']['message'] );
	}

	/**
	 * @covers ::getPageHtml
	 */
	public function testTransformWikitext_HttpException() {
		$directClient = $this->createDirectClientWithHttpExceptionFromFactory();

		$page = $this->getExistingTestPage( 'DirectParsoidClient' );

		$response = $directClient->transformWikitext(
			$page,
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'en' ),
			'some text',
			false,
			null,
			false
		);
		$this->assertArrayHasKey( 'error', $response );
		$this->assertSame( 'testing', $response['error']['message'] );
	}

}
