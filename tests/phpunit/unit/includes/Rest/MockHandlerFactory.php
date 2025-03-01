<?php

namespace MediaWiki\Tests\Rest;

use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RedirectException;
use MediaWiki\Rest\ResponseException;
use RuntimeException;

class MockHandlerFactory {

	public static function throwHandlerFactory() {
		return new class extends Handler {
			public function execute() {
				throw new HttpException( 'Mock error', 555 );
			}
		};
	}

	public static function fatalHandlerFactory() {
		return new class extends Handler {
			public function execute() {
				throw new RuntimeException( 'Fatal mock error', 12345 );
			}
		};
	}

	public static function throwRedirectHandlerFactory() {
		return new class extends Handler {
			public function execute() {
				throw new RedirectException( 301, 'http://example.com' );
			}
		};
	}

	public static function throwWrappedHandlerFactory() {
		return new class extends Handler {
			public function execute() {
				$response = $this->getResponseFactory()->create();
				$response->setStatus( 200 );
				throw new ResponseException( $response );
			}
		};
	}

}
