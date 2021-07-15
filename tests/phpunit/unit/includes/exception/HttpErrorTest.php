<?php

/**
 * @todo tests for HttpError::report
 *
 * @covers HttpError
 */
class HttpErrorTest extends MediaWikiUnitTestCase {

	public function testIsLoggable() {
		$httpError = new HttpError( 500, 'server error!' );
		$this->assertFalse( $httpError->isLoggable(), 'http error is not loggable' );
	}

	public function testGetStatusCode() {
		$httpError = new HttpError( 500, 'server error!' );
		$this->assertEquals( 500, $httpError->getStatusCode() );
	}

	/**
	 * @dataProvider getHtmlProvider
	 */
	public function testGetHtml( array $expected, $content, $header ) {
		$httpError = new HttpError( 500, $content, $header );
		$errorHtml = $httpError->getHTML();

		foreach ( $expected as $key => $html ) {
			$this->assertStringContainsString( $html, $errorHtml, $key );
		}
	}

	public function getHtmlProvider() {
		// Avoid parsing logic in real Message class which includes text transformations
		// that require MediaWikiServices
		$content = $this->createMock( Message::class );
		$content->method( 'escaped' )->willReturn( 'suspicious-userlogout' );
		$header = $this->createMock( Message::class );
		$header->method( 'escaped' )->willReturn( 'loginerror' );

		return [
			[
				[
					'head html' => '<head><title>Server Error 123</title></head>',
					'body html' => '<body><h1>Server Error 123</h1>'
						. '<p>a server error!</p></body>'
				],
				'a server error!',
				'Server Error 123'
			],
			[
				[
					'head html' => '<head><title>loginerror</title></head>',
					'body html' => '<body><h1>loginerror</h1>'
					. '<p>suspicious-userlogout</p></body>'
				],
				$content,
				$header
			],
			[
				[
					'head html' => '<html><head><title>Internal Server Error</title></head>',
					'body html' => '<body><h1>Internal Server Error</h1>'
						. '<p>a server error!</p></body></html>'
				],
				'a server error!',
				null
			]
		];
	}
}
