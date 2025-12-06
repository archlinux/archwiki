<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Notifications\AttributeManager;
use MediaWiki\Extension\Notifications\Cache\RevisionLocalCache;
use MediaWiki\Extension\Notifications\Cache\TitleLocalCache;
use MediaWiki\Extension\Notifications\Mapper\EventMapper;
use MediaWiki\Extension\Notifications\Mapper\NotificationMapper;
use MediaWiki\Extension\Notifications\Push\NotificationServiceClient;
use MediaWiki\Extension\Notifications\Push\SubscriptionManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableStore;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file.
// Whether the services return without error is checked in ServiceWiringTest.php
// @codeCoverageIgnoreStart
/** @phpcs-require-sorted-array */
return [

	'EchoAttributeManager' => static function ( MediaWikiServices $services ): AttributeManager {
		$echoConfig = $services->getConfigFactory()->makeConfig( 'Echo' );

		return new AttributeManager(
			$echoConfig->get( 'EchoNotifications' ),
			$echoConfig->get( 'EchoNotificationCategories' ),
			$echoConfig->get( 'DefaultNotifyTypeAvailability' ),
			$echoConfig->get( 'NotifyTypeAvailabilityByCategory' ),
			$services->getUserGroupManager(),
			$services->getUserOptionsLookup()
		);
	},

	'EchoEventMapper' => static function ( MediaWikiServices $services ): EventMapper {
		return new EventMapper();
	},

	'EchoNotificationMapper' => static function ( MediaWikiServices $services ): NotificationMapper {
		return new NotificationMapper();
	},

	'EchoPushNotificationServiceClient' => static function (
		MediaWikiServices $services
	): NotificationServiceClient {
		$echoConfig = $services->getConfigFactory()->makeConfig( 'Echo' );
		$client = new NotificationServiceClient(
			$services->getHttpRequestFactory(),
			$services->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() ),
			$echoConfig->get( 'EchoPushServiceBaseUrl' )
		);
		$client->setLogger( LoggerFactory::getInstance( 'Echo' ) );
		return $client;
	},

	'EchoPushSubscriptionManager' => static function ( MediaWikiServices $services ): SubscriptionManager {
		$echoConfig = $services->getConfigFactory()->makeConfig( 'Echo' );
		// Use shared DB/cluster for push subscriptions
		$cluster = $echoConfig->get( 'EchoSharedTrackingCluster' );
		$database = $echoConfig->get( 'EchoSharedTrackingDB' ) ?: false;
		$loadBalancerFactory = $services->getDBLoadBalancerFactory();
		$loadBalancer = $cluster
			? $loadBalancerFactory->getExternalLB( $cluster )
			: $loadBalancerFactory->getMainLB( $database );
		$dbw = $loadBalancer->getConnection( DB_PRIMARY, [], $database );
		$dbr = $loadBalancer->getConnection( DB_REPLICA, [], $database );

		$pushProviderStore = new NameTableStore(
			$loadBalancer,
			$services->getMainWANObjectCache(),
			LoggerFactory::getInstance( 'Echo' ),
			'echo_push_provider',
			'epp_id',
			'epp_name',
			null,
			$database
		);

		$pushTopicStore = new NameTableStore(
			$loadBalancer,
			$services->getMainWANObjectCache(),
			LoggerFactory::getInstance( 'Echo' ),
			'echo_push_topic',
			'ept_id',
			'ept_text',
			null,
			$database
		);

		$maxSubscriptionsPerUser = $echoConfig->get( 'EchoPushMaxSubscriptionsPerUser' );

		return new SubscriptionManager(
			$dbw,
			$dbr,
			$pushProviderStore,
			$pushTopicStore,
			$maxSubscriptionsPerUser
		);
	},

	'EchoRevisionLocalCache' => static function ( MediaWikiServices $services ): RevisionLocalCache {
		return new RevisionLocalCache(
			$services->getConnectionProvider(),
			$services->getRevisionStore()
		);
	},

	'EchoTitleLocalCache' => static function ( MediaWikiServices $services ): TitleLocalCache {
		return new TitleLocalCache(
			$services->getPageStore(),
			$services->getTitleFactory()
		);
	},
];

// @codeCoverageIgnoreEnd
