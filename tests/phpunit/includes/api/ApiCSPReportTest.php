<?php

use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;

/**
 * @group API
 * @group medium
 * @covers ApiCSPReport
 */
class ApiCSPReportTest extends MediaWikiIntegrationTestCase {

	public function testInternalReportonly() {
		$params = [
			'reportonly' => '1',
			'source' => 'internal',
		];
		$cspReport = [
			'document-uri' => 'https://doc.test/path',
			'referrer' => 'https://referrer.test/path',
			'violated-directive' => 'connet-src',
			'disposition' => 'report',
			'blocked-uri' => 'https://blocked.test/path?query',
			'line-number' => 4,
			'column-number' => 2,
			'source-file' => 'https://source.test/path?query',
		];

		$log = $this->doExecute( $params, $cspReport );

		$this->assertEquals(
			[
				[
					'[report-only] Received CSP report: ' .
						'<https://blocked.test> blocked from being loaded on <https://doc.test/path>:4',
					[
						'method' => 'ApiCSPReport::execute',
						'user_id' => 'logged-out',
						'user-agent' => 'Test/0.0',
						'source' => 'internal'
					]
				],
			],
			$log,
			'logged messages'
		);
	}

	public function testFalsePositiveOriginMatch() {
		$params = [
			'reportonly' => '1',
			'source' => 'internal',
		];
		$cspReport = [
			'document-uri' => 'https://doc.test/path',
			'referrer' => 'https://referrer.test/path',
			'violated-directive' => 'connet-src',
			'disposition' => 'report',
			'blocked-uri' => 'https://blocked.test/path/file?query',
			'line-number' => 4,
			'column-number' => 2,
			'source-file' => 'https://source.test/path/file?query',
		];

		$this->overrideConfigValue(
			MainConfigNames::CSPFalsePositiveUrls,
			[ 'https://blocked.test/path/' => true ]
		);
		$log = $this->doExecute( $params, $cspReport );

		$this->assertSame(
			[],
			$log,
			'logged messages'
		);
	}

	private function doExecute( array $params, array $cspReport ) {
		$log = [];
		$logger = $this->createMock( Psr\Log\AbstractLogger::class );
		$logger->method( 'warning' )->willReturnCallback(
			static function ( $msg, $ctx ) use ( &$log ) {
				unset( $ctx['csp-report'] );
				$log[] = [ $msg, $ctx ];
			}
		);
		$this->setLogger( 'csp-report-only', $logger );

		$postBody = json_encode( [ 'csp-report' => $cspReport ] );
		$req = $this->getMockBuilder( FauxRequest::class )
			->onlyMethods( [ 'getRawInput' ] )
			->setConstructorArgs( [ $params, /* $wasPosted */ true ] )
			->getMock();
		$req->method( 'getRawInput' )->willReturn( $postBody );
		$req->setHeaders( [
			'Content-Type' => 'application/csp-report',
			'User-Agent' => 'Test/0.0'
		] );

		$api = $this->getMockBuilder( ApiCSPReport::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getParameter', 'getRequest', 'getResult' ] )
			->getMock();
		$api->method( 'getParameter' )->willReturnCallback(
			static function ( $key ) use ( $req ) {
				return $req->getRawVal( $key );
			}
		);
		$api->method( 'getRequest' )->willReturn( $req );
		$api->method( 'getResult' )->willReturn( new ApiResult( false ) );

		$api->execute();
		return $log;
	}
}
