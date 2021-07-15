<?php

namespace Vector;

use MediaWiki\MediaWikiServices;
use Vector\FeatureManagement\FeatureManager;

/**
 * A service locator for services specific to Vector.
 *
 * @package Vector
 * @internal
 */
final class VectorServices {

	/**
	 * Gets the feature manager service.
	 *
	 * Per its definition in ServiceWiring.php, the feature manager service is bound to the global
	 * request and user objects and to the _Vector.Config_ service.
	 *
	 * @return FeatureManager
	 */
	public static function getFeatureManager(): FeatureManager {
		return MediaWikiServices::getInstance()->getService( Constants::SERVICE_FEATURE_MANAGER );
	}
}
