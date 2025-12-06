<?php

namespace MediaWiki\Extension\Notifications;

use MediaWiki\Extension\Notifications\Cache\RevisionLocalCache;
use MediaWiki\Extension\Notifications\Cache\TitleLocalCache;
use MediaWiki\Extension\Notifications\Push\NotificationServiceClient;
use MediaWiki\Extension\Notifications\Push\SubscriptionManager;
use MediaWiki\MediaWikiServices;

class Services {

	public static function getInstance(): Services {
		return new self( MediaWikiServices::getInstance() );
	}

	public static function wrap( MediaWikiServices $services ): Services {
		return new self( $services );
	}

	public function __construct( private readonly MediaWikiServices $services ) {
	}

	public function getPushNotificationServiceClient(): NotificationServiceClient {
		return $this->services->getService( 'EchoPushNotificationServiceClient' );
	}

	public function getPushSubscriptionManager(): SubscriptionManager {
		return $this->services->getService( 'EchoPushSubscriptionManager' );
	}

	public function getAttributeManager(): AttributeManager {
		return $this->services->getService( 'EchoAttributeManager' );
	}

	public function getTitleLocalCache(): TitleLocalCache {
		return $this->services->getService( 'EchoTitleLocalCache' );
	}

	public function getRevisionLocalCache(): RevisionLocalCache {
		return $this->services->getService( 'EchoRevisionLocalCache' );
	}
}
