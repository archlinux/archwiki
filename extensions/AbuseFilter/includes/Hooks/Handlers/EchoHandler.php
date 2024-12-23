<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

use MediaWiki\Extension\AbuseFilter\EchoNotifier;
use MediaWiki\Extension\AbuseFilter\ThrottleFilterPresentationModel;
use MediaWiki\Extension\Notifications\AttributeManager;
use MediaWiki\Extension\Notifications\Hooks\BeforeCreateEchoEventHook;
use MediaWiki\Extension\Notifications\UserLocator;

class EchoHandler implements BeforeCreateEchoEventHook {

	/**
	 * @param array &$notifications
	 * @param array &$notificationCategories
	 * @param array &$icons
	 */
	public function onBeforeCreateEchoEvent(
		array &$notifications,
		array &$notificationCategories,
		array &$icons
	) {
		$notifications[ EchoNotifier::EVENT_TYPE ] = [
			'category' => 'system',
			'section' => 'alert',
			'group' => 'negative',
			'presentation-model' => ThrottleFilterPresentationModel::class,
			AttributeManager::ATTR_LOCATORS => [
				[
					[ UserLocator::class, 'locateFromEventExtra' ],
					[ 'user' ]
				]
			],
		];
	}

}
