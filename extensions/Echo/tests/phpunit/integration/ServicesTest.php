<?php

namespace MediaWiki\Extension\Notifications\Test\Integration;

use MediaWiki\Extension\Notifications\AttributeManager;
use MediaWiki\Extension\Notifications\Push\NotificationServiceClient;
use MediaWiki\Extension\Notifications\Push\SubscriptionManager;
use MediaWiki\Extension\Notifications\Services;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Notifications\Services
 * @group Database
 */
class ServicesTest extends MediaWikiIntegrationTestCase {

	public function testWrap(): void {
		$services = Services::wrap( $this->getServiceContainer() );
		$this->assertInstanceOf( Services::class, $services );
	}

	public function testGetPushNotificationServiceClient(): void {
		$serviceClient = Services::getInstance()->getPushNotificationServiceClient();
		$this->assertInstanceOf( NotificationServiceClient::class, $serviceClient );
	}

	public function testGetPushSubscriptionManager(): void {
		$subscriptionManager = Services::getInstance()->getPushSubscriptionManager();
		$this->assertInstanceOf( SubscriptionManager::class, $subscriptionManager );
	}

	public function testGetAttributeManager(): void {
		$attributeManager = Services::getInstance()->getAttributeManager();
		$this->assertInstanceOf( AttributeManager::class, $attributeManager );
	}

}
