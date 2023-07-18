<?php

use MediaWiki\Extension\Notifications\Push\NotificationServiceClient;
use MediaWiki\Extension\Notifications\Push\SubscriptionManager;
use MediaWiki\MediaWikiServices;

/** @covers EchoServices */
class EchoServicesTest extends MediaWikiIntegrationTestCase {

	/** @var EchoServices */
	private $echoServices;

	protected function setUp(): void {
		parent::setUp();
		$this->echoServices = EchoServices::getInstance();
	}

	public function testWrap(): void {
		$services = EchoServices::wrap( MediaWikiServices::getInstance() );
		$this->assertInstanceOf( EchoServices::class, $services );
	}

	public function testGetPushNotificationServiceClient(): void {
		$serviceClient = $this->echoServices->getPushNotificationServiceClient();
		$this->assertInstanceOf( NotificationServiceClient::class, $serviceClient );
	}

	public function testGetPushSubscriptionManager(): void {
		$subscriptionManager = $this->echoServices->getPushSubscriptionManager();
		$this->assertInstanceOf( SubscriptionManager::class, $subscriptionManager );
	}

	public function testGetAttributeManager(): void {
		$attributeManager = $this->echoServices->getAttributeManager();
		$this->assertInstanceOf( EchoAttributeManager::class, $attributeManager );
	}

}
