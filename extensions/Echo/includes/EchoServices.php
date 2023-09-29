<?php

use MediaWiki\Extension\Notifications\Push\NotificationServiceClient;
use MediaWiki\Extension\Notifications\Push\SubscriptionManager;
use MediaWiki\MediaWikiServices;

class EchoServices {

	/** @var MediaWikiServices */
	private $services;

	/** @return EchoServices */
	public static function getInstance(): EchoServices {
		return new self( MediaWikiServices::getInstance() );
	}

	/**
	 * @param MediaWikiServices $services
	 * @return EchoServices
	 */
	public static function wrap( MediaWikiServices $services ): EchoServices {
		return new self( $services );
	}

	/** @param MediaWikiServices $services */
	public function __construct( MediaWikiServices $services ) {
		$this->services = $services;
	}

	/** @return NotificationServiceClient */
	public function getPushNotificationServiceClient(): NotificationServiceClient {
		return $this->services->getService( 'EchoPushNotificationServiceClient' );
	}

	/** @return SubscriptionManager */
	public function getPushSubscriptionManager(): SubscriptionManager {
		return $this->services->getService( 'EchoPushSubscriptionManager' );
	}

	/** @return EchoAttributeManager */
	public function getAttributeManager(): EchoAttributeManager {
		return $this->services->getService( 'EchoAttributeManager' );
	}

}
