<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

use EchoAttributeManager;
use EchoUserLocator;
use MediaWiki\Extension\AbuseFilter\EchoNotifier;
use MediaWiki\Extension\AbuseFilter\ThrottleFilterPresentationModel;

/**
 * @todo Use new hook system once Echo is updated
 */
class EchoHandler {

	/**
	 * @param array &$notifications
	 * @param array &$notificationCategories
	 * @param array &$icons
	 */
	public static function onBeforeCreateEchoEvent(
		array &$notifications,
		array &$notificationCategories,
		array &$icons
	) {
		$notifications[ EchoNotifier::EVENT_TYPE ] = [
			'category' => 'system',
			'section' => 'alert',
			'group' => 'negative',
			'presentation-model' => ThrottleFilterPresentationModel::class,
			EchoAttributeManager::ATTR_LOCATORS => [
				[
					[ EchoUserLocator::class, 'locateFromEventExtra' ],
					[ 'user' ]
				]
			],
		];
	}

}
