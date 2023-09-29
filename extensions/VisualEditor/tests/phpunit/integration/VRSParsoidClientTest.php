<?php

namespace MediaWiki\Extension\VisualEditor\Tests;

use Generator;
use Language;
use MediaWiki\Extension\VisualEditor\DirectParsoidClient;
use MediaWiki\Extension\VisualEditor\VRSParsoidClient;
use MediaWiki\Page\PageIdentityValue;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;
use VirtualRESTServiceClient;

/**
 * @coversDefaultClass \MediaWiki\Extension\VisualEditor\VRSParsoidClient
 * @group Database
 */
class VRSParsoidClientTest extends MediaWikiIntegrationTestCase {

	/** @var string */
	private const HTML = '<h2>VRSParsoidClient test</h2>';

	/** @var string */
	private const WIKITEXT = '== VRSParsoidClient test ==';

	/**
	 * @param array $expectedReq
	 * @param array $response
	 *
	 * @return VRSParsoidClient
	 */
	private function createVRSParsoidClient( array $expectedReq, array $response ): VRSParsoidClient {
		$vrsClient = $this->createNoOpMock(
			VirtualRESTServiceClient::class,
			[ 'run' ]
		);
		$vrsClient->method( 'run' )->willReturnCallback(
			static function ( array $request ) use ( $expectedReq, $response ) {
				foreach ( $expectedReq as $key => $value ) {
					self::assertSame( $value, $request[$key], $key );
				}

				return $response;
			}
		);

		return new VRSParsoidClient(
			$vrsClient,
			new NullLogger()
		);
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
				[ 'getCode' ]
			);
			$language->method( 'getCode' )->willReturn( $langCode );
		}

		return [ $language, $langCode ];
	}

	/**
	 * @covers ::getPageHtml
	 * @dataProvider provideLanguageCodes
	 */
	public function testGetPageHtml( $langCode ) {
		[ $language, $langCode ] = $this->createLanguage( $langCode, true );
		$revision = $this->getExistingTestPage( 'VRSParsoidClient' )
			->getRevisionRecord();
		$revId = $revision->getId();

		$response = [
			'code' => 200,
			'headers' => [],
			'body' => '<html><body>Response body</body></html>',
			'error'	=> '',
		];
		$vrsClient = $this->createVRSParsoidClient(
			[
				'method' => 'GET',
				'url' => '/restbase/local/v1/page/html/VRSParsoidClient/' . $revId . '?redirect=false&stash=true',
				'query' => [],
				'headers' => [
					'Accept-Language' => $langCode,
					'Accept' =>
						'text/html; charset=utf-8; profile="https://www.mediawiki.org/wiki/Specs/HTML/' .
						DirectParsoidClient::PARSOID_VERSION . '"',
					'User-Agent' => 'VisualEditor-MediaWiki/' . MW_VERSION,
					'Api-User-Agent' => 'VisualEditor-MediaWiki/' . MW_VERSION,
					'Promise-Non-Write-API-Action' => 'true'

				]
			],
			$response
		);

		$resp = $vrsClient->getPageHtml( $revision, $language );
		$this->assertIsArray( $resp );
		$this->assertFalse( isset( $resp['error'] ) );
		$this->assertArrayEquals( $resp, $response, false, true );
	}

	/**
	 * @return Generator
	 */
	public function restbaseErrorObjectProvider() {
		yield [
			[
				'code' => 200,
				'headers' => [],
				'body' => '<html><body>Response body</body></html>',
				'error'	=> 'Whats the message?',
			],
			[
				'apierror-visualeditor-docserver-http-error',
				'Whats the message?'
			]
		];

		yield [
			[
				'code' => '500',
				'headers' => [],
				'body' => '{}',
				'error'	=> '',
			],

			[
				'apierror-visualeditor-docserver-http',
				'500',
				'(no message)'
			]
		];

		yield [
			[
				'code' => '404',
				'headers' => [],
				'body' => json_encode( [
					'detail' => 'Another error message',
				] ),
				'error'	=> '',
			],

			[
				'apierror-visualeditor-docserver-http',
				'404',
				'Another error message'
			]
		];

		yield [
			[
				'code' => '205',
				'headers' => [
					'Location' => 'http://example.com/'
				],
				'body' => json_encode( [
					'detail' => 'bla bla bla',
				] ),
				'error'	=> '',
			],

			// not an error
			null
		];
	}

	/**
	 * @covers ::transformHTML
	 * @dataProvider restbaseErrorObjectProvider
	 */
	public function testGetPageHtmlError( $restbaseResponse, $expectedError ) {
		[ $language ] = $this->createLanguage( 'en' );

		$vrsClient = $this->createVRSParsoidClient(
			[],
			$restbaseResponse
		);

		$revision = $this->getExistingTestPage( 'VRSParsoidClient' )
			->getRevisionRecord();

		$pageHtmlResponse = $vrsClient->getPageHtml( $revision, $language );
		$this->assertSame( $expectedError, $pageHtmlResponse["error"] );
	}

	/** @return Generator */
	public function provideTransformHtmlData(): Generator {
		yield 'No oldid and no eTag to set in request headers' => [ null, null ];

		yield 'Oldid to set in request headers with no eTag' => [ 123, null ];

		yield 'eTag to set in request headers with no oldid' => [ null, '1/abc-def' ];

		yield 'Oldid and eTag to set in request headers' => [ 123, '2/e03-f12' ];
	}

	/**
	 * @covers ::transformHTML
	 * @dataProvider provideTransformHtmlData
	 */
	public function testTransformHtml( $oldid, $eTag ) {
		[ $language, $langCode ] = $this->createLanguage( 'en' );

		$response = [
			'code' => 200,
			'headers' => [
				'If-Match' => $eTag,
				'Accept-Language' => $langCode,
			],
			'body' => '<html><body>Response body</body></html>',
			'error'	=> null,
		];

		$vrsClient = $this->createVRSParsoidClient(
			[
				'method' => 'POST',
				'url' =>
					'/restbase/local/v1/transform/html/to/wikitext/VRSParsoidClient' . ( $oldid ? "/$oldid" : '' ),
			],
			$response
		);

		$resp = $vrsClient->transformHTML(
			PageIdentityValue::localIdentity( 1, NS_MAIN, 'VRSParsoidClient' ),
			$language,
			self::HTML,
			$oldid,
			$eTag
		);

		$this->assertIsArray( $resp );
		$this->assertArrayEquals( $resp, $response, false, true );
	}

	/** @return Generator */
	public function provideTransformWikitextData(): Generator {
		// [ $bodyOnly, $oldid, $stash ]
		yield 'Body only: false, oldid: null, stash: false' => [ false, null, false ];

		yield 'Body only: true, oldid: null, stash: false' => [ true, null, false ];

		yield 'Body only: false, oldid: 2, stash: false' => [ false, 2, false ];

		yield 'Body only: false, oldid: null, stash: true' => [ false, null, true ];

		yield 'Body only: true, oldid: 4, stash: true' => [ true, 4, true ];

		yield 'Body only: true, oldid: 10, stash: false' => [ true, 10, false ];

		yield 'Body only: false, oldid: 12, stash: true' => [ false, 12, true ];

		yield 'Body only: true, oldid: null, stash: true' => [ true, null, true ];

		yield 'Body only: false, oldid: 123, stash: false' => [ false, 123, false ];
	}

	/**
	 * @covers ::transformWikitext
	 * @dataProvider provideTransformWikitextData
	 */
	public function testTransformWikitext( $bodyOnly, $oldid, $stash ) {
		[ $language, $langCode ] = $this->createLanguage( 'en' );

		$response = [
			'code' => 200,
			'headers' => [
				'Accept-Language' => $langCode,
			],
			'body' => '<html><body>Response body</body></html>',
			'error' => '',
		];

		$vrsClient = $this->createVRSParsoidClient(
			[
				'method' => 'POST',
				'url' =>
					'/restbase/local/v1/transform/wikitext/to/html/VRSParsoidClient' . ( $oldid ? "/$oldid" : '' ),
			],
			$response
		);

		$resp = $vrsClient->transformWikitext(
			PageIdentityValue::localIdentity( 1, NS_MAIN, 'VRSParsoidClient' ),
			$language,
			self::WIKITEXT,
			$bodyOnly,
			$oldid,
			$stash
		);

		$this->assertIsArray( $resp );
		$this->assertArrayEquals( $resp, $response, false, true );
	}
}
