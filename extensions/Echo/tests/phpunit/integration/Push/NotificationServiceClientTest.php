<?php

namespace MediaWiki\Extension\Notifications\Test\Integration\Push;

use MediaWiki\Extension\Notifications\Services;
use MediaWiki\Http\MWHttpRequest;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;
use Wikimedia\TestingAccessWrapper;

/** @covers \MediaWiki\Extension\Notifications\Push\NotificationServiceClient */
class NotificationServiceClientTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;

	public function testConstructRequest(): void {
		$this->installMockHttp( 'hi' );

		$client = Services::getInstance()->getPushNotificationServiceClient();
		$client = TestingAccessWrapper::newFromObject( $client );
		$payload = [ 'deviceTokens' => [ 'foo' ], 'messageType' => 'checkEchoV1' ];
		$request = $client->constructRequest( 'fcm', $payload );
		$this->assertInstanceOf( MWHttpRequest::class, $request );
	}

}
