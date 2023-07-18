<?php

namespace MediaWiki\Extension\VisualEditor\Tests;

use Language;
use MediaWiki\Extension\VisualEditor\DirectParsoidClient;
use MediaWiki\Extension\VisualEditor\DualParsoidClient;
use MediaWiki\Extension\VisualEditor\ParsoidClient;
use MediaWiki\Extension\VisualEditor\VisualEditorParsoidClientFactory;
use MediaWiki\Extension\VisualEditor\VRSParsoidClient;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\VisualEditor\DualParsoidClient
 * @group Database
 */
class DualParsoidClientTest extends MediaWikiIntegrationTestCase {

	/**
	 * @param array $hints
	 * @param string $default
	 *
	 * @return ParsoidClient
	 */
	public function createMockClient( array $hints, string $default ) {
		$vrs = $hints[ 'ShouldUseVRS' ] ?? ( $default === 'vrs' );
		$class = $vrs ? VRSParsoidClient::class : DirectParsoidClient::class;

		$client = $this->createMock( $class );

		$client->method( 'getPageHtml' )->willReturnCallback(
			static function () use ( $vrs ) {
				return [
					'body' => $vrs ? 'mode:vrs' : 'mode:direct',
					'headers' => [
						'etag' => '"abcdef1234"',
					]
				];
			}
		);

		$client->method( 'transformWikitext' )->willReturnCallback(
			static function () use ( $vrs ) {
				return [
					'body' => $vrs ? 'mode:vrs' : 'mode:direct',
					'headers' => [
						'etag' => '"abcdef1234"',
					]
				];
			}
		);

		$client->method( 'transformHTML' )->willReturnCallback(
			static function (
				PageIdentity $page,
				Language $targetLanguage,
				string $html,
				?int $oldid,
				?string $etag
			) use ( $vrs ) {
				return [
					'body' => ( $vrs ? 'mode:vrs' : 'mode:direct' ) . '; etag:' . $etag,
					'headers' => []
				];
			}
		);

		return $client;
	}

	/**
	 * @param string $defaultMode
	 *
	 * @return VisualEditorParsoidClientFactory
	 */
	private function createClientFactory( string $defaultMode ) {
		$factory = $this->createMock( VisualEditorParsoidClientFactory::class );

		$factory->method( 'createParsoidClientInternal' )->willReturnCallback(
			function ( $cookiesToForward, ?Authority $performer = null, array $hints = [] ) use ( $defaultMode ) {
				return $this->createMockClient( $hints, $defaultMode );
			}
		);

		return $factory;
	}

	/**
	 * @param string $defaultMode
	 *
	 * @return DualParsoidClient
	 */
	private function createDualClient( string $defaultMode ): DualParsoidClient {
		$directClient = new DualParsoidClient(
			$this->createClientFactory( $defaultMode ),
			false,
			$this->createNoOpMock( Authority::class )
		);

		return $directClient;
	}

	public function provideDefaultModes() {
		yield 'direct' => [ 'direct' ];
		yield 'vrs' => [ 'vrs' ];
	}

	/**
	 * @dataProvider provideDefaultModes
	 */
	public function testGetPageHTML( $default ) {
		$client = $this->createDualClient( $default );
		$result = $client->getPageHtml( $this->createNoOpMock( RevisionRecord::class ), null );

		$this->assertSame( 'mode:' . $default, $result['body'] );

		$etag = $result['headers']['etag'];
		$this->assertStringContainsString( '"' . $default . ':', $etag );

		// Check round trip using the etag returned by the call above
		$client = $this->createDualClient( 'xyzzy' );
		$result = $client->transformHTML(
			PageIdentityValue::localIdentity( 0, NS_MAIN, 'Dummy' ),
			$this->createNoOpMock( Language::class ),
			'input html',
			null,
			$etag
		);
		$this->assertStringContainsString( 'mode:' . $default, $result['body'] );
	}

	/**
	 * @dataProvider provideDefaultModes
	 */
	public function testTransformWikitext( $default ) {
		$client = $this->createDualClient( $default );
		$result = $client->transformWikitext(
			PageIdentityValue::localIdentity( 0, NS_MAIN, 'Dummy' ),
			$this->createNoOpMock( Language::class ),
			'input wikitext',
			false,
			null,
			false
		);

		$this->assertSame( 'mode:' . $default, $result['body'] );

		$etag = $result['headers']['etag'];
		$this->assertStringContainsString( '"' . $default . ':', $etag );

		// Check round trip using the etag returned by the call above
		$client = $this->createDualClient( 'xyzzy' );
		$result = $client->transformHTML(
			PageIdentityValue::localIdentity( 0, NS_MAIN, 'Dummy' ),
			$this->createNoOpMock( Language::class ),
			'input html',
			null,
			$etag
		);
		$this->assertStringContainsString( 'mode:' . $default, $result['body'] );
	}

	public function provideTransformHTML() {
		$fallbackMode = 'direct';

		yield 'no etag' => [ null, $fallbackMode ];
		yield 'etag without prefix' => [ '"abcdef1234"', $fallbackMode ];
		yield 'etag with bogus prefix' => [ '"bogus:abcdef1234"', $fallbackMode ];
		yield 'etag with direct prefix' => [ '"direct:abcdef1234"', 'direct' ];
		yield 'etag with vrs prefix' => [ '"vrs:abcdef1234"', 'vrs' ];
		yield 'weak etag with vrs prefix' => [ 'W/"vrs:abcdef1234"', 'vrs' ];
	}

	/**
	 * @dataProvider provideTransformHTML
	 */
	public function testTransformHTML( $etag, $mode ) {
		$client = $this->createDualClient( 'direct' );

		$result = $client->transformHTML(
			PageIdentityValue::localIdentity( 0, NS_MAIN, 'Dummy' ),
			$this->createNoOpMock( Language::class ),
			'input html',
			null,
			$etag
		);

		$this->assertStringContainsString( "mode:$mode", $result['body'] );

		if ( $etag ) {
			$this->assertStringContainsString( "abcdef", $result['body'] );
			$this->assertStringNotContainsString( "etag:\"$mode:", $result['body'] );
		}
	}

}
