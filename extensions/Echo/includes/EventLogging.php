<?php

use MediaWiki\Extension\EventLogging\EventLogging;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;

/**
 * Static class for handling all kinds of event logging
 *
 * TODO: consider making this a service with dependencies injected
 */
class MWEchoEventLogging {

	/**
	 * This is the only function that interacts with EventLogging
	 *
	 * Adds common fields, and logs if logging is enabled for the given $schema.
	 *
	 * @param string $schema
	 * @param array $data
	 */
	protected static function logEvent( $schema, array $data ) {
		global $wgEchoEventLoggingSchemas, $wgEchoEventLoggingVersion;

		$schemaConfig = $wgEchoEventLoggingSchemas[$schema];
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' )
			|| !$schemaConfig['enabled']
		) {
			// If logging for this schema is disabled, it's a no-op.
			return;
		}

		$data['version'] = $wgEchoEventLoggingVersion;

		// NOTE: The 'EchoMail' and 'EchoInteraction' events were migrated to the Event Platform
		//  and are no longer using the legacy EventLogging schema from metawiki. $revId is actually
		//  overridden by the EventLoggingSchemas extension attribute in extension.json.
		EventLogging::logEvent( $schema, -1, $data );
	}

	/**
	 * Function for logging the event for Schema:EchoEmail
	 * @param UserIdentity $userIdentity
	 * @param string $emailDeliveryMode 'single' (default), 'daily_digest', or 'weekly_digest'
	 */
	public static function logSchemaEchoMail( UserIdentity $userIdentity, $emailDeliveryMode = 'single' ) {
		$data = [
			'recipientUserId' => $userIdentity->getId(),
			'emailDeliveryMode' => $emailDeliveryMode
		];

		self::logEvent( 'EchoMail', $data );
	}

	/**
	 * @param UserIdentity $userIdentity
	 * @param string $skinName
	 */
	public static function logSpecialPageVisit( UserIdentity $userIdentity, $skinName ) {
		$userEditCount = (int)MediaWikiServices::getInstance()
			->getUserEditTracker()
			->getUserEditCount( $userIdentity );
		self::logEvent(
			'EchoInteraction',
			[
				'context' => 'archive',
				'action' => 'special-page-visit',
				'userId' => $userIdentity->getId(),
				'editCount' => $userEditCount,
				'notifWiki' => WikiMap::getCurrentWikiId(),
				// Hack: Figure out if we are in the mobile skin
				'mobile' => $skinName === 'minerva',
			]
		);
	}

}
