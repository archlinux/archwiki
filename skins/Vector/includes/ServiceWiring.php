<?php

/**
 * Service Wirings for Vector skin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @since 1.35
 */

use MediaWiki\MediaWikiServices;
use Vector\Constants;
use Vector\FeatureManagement\FeatureManager;
use Vector\FeatureManagement\Requirements\DynamicConfigRequirement;
use Vector\FeatureManagement\Requirements\LatestSkinVersionRequirement;
use Vector\FeatureManagement\Requirements\OverridableConfigRequirement;
use Vector\SkinVersionLookup;

return [
	Constants::SERVICE_CONFIG => static function ( MediaWikiServices $services ) {
		return $services->getService( 'ConfigFactory' )->makeConfig( Constants::SKIN_NAME_LEGACY );
	},
	Constants::SERVICE_FEATURE_MANAGER => static function ( MediaWikiServices $services ) {
		$featureManager = new FeatureManager();

		$featureManager->registerRequirement(
			new DynamicConfigRequirement(
				$services->getMainConfig(),
				Constants::CONFIG_KEY_FULLY_INITIALISED,
				Constants::REQUIREMENT_FULLY_INITIALISED
			)
		);

		// Feature: Latest skin
		// ====================
		$context = RequestContext::getMain();

		$featureManager->registerRequirement(
			new LatestSkinVersionRequirement(
				new SkinVersionLookup(
					$context->getRequest(),
					$context->getUser(),
					$services->getService( Constants::SERVICE_CONFIG ),
					$services->getUserOptionsLookup()
				)
			)
		);

		$featureManager->registerFeature(
			Constants::FEATURE_LATEST_SKIN,
			[
				Constants::REQUIREMENT_FULLY_INITIALISED,
				Constants::REQUIREMENT_LATEST_SKIN_VERSION,
			]
		);

		// Feature: Languages in sidebar
		// ================================
		$featureManager->registerRequirement(
			new OverridableConfigRequirement(
				$services->getMainConfig(),
				$context->getUser(),
				$context->getRequest(),
				$services->getCentralIdLookupFactory()->getNonLocalLookup(),
				Constants::CONFIG_KEY_LANGUAGE_IN_HEADER,
				Constants::REQUIREMENT_LANGUAGE_IN_HEADER,
				Constants::QUERY_PARAM_LANGUAGE_IN_HEADER,
				Constants::CONFIG_LANGUAGE_IN_HEADER_TREATMENT_AB_TEST
			)
		);

		// ---

		// Temporary T286932 - remove after languages A/B test is finished.
		$requirementName = 'T286932';

		// MultiConfig checks each config in turn, allowing us to override the main config for specific keys. In this
		// case, override the "VectorLanguageInHeaderABTest" configuration value so that the following requirement
		// always buckets the user as if the language treatment A/B test were running.
		$config = new MultiConfig( [
			new HashConfig( [
				Constants::CONFIG_LANGUAGE_IN_HEADER_TREATMENT_AB_TEST => true,
			] ),
			$services->getMainConfig(),
		] );

		$featureManager->registerRequirement(
			new OverridableConfigRequirement(
				$config,
				$context->getUser(),
				$context->getRequest(),
				$services->getCentralIdLookupFactory()->getNonLocalLookup(),
				Constants::CONFIG_KEY_LANGUAGE_IN_HEADER,
				$requirementName,
				/* $overrideName = */ '',
				Constants::CONFIG_LANGUAGE_IN_HEADER_TREATMENT_AB_TEST
			)
		);

		// ---

		$featureManager->registerFeature(
			Constants::FEATURE_LANGUAGE_IN_HEADER,
			[
				Constants::REQUIREMENT_FULLY_INITIALISED,
				Constants::REQUIREMENT_LATEST_SKIN_VERSION,
				Constants::REQUIREMENT_LANGUAGE_IN_HEADER,
			]
		);

		// Feature: T293470: Language in main page header
		// ================================
		$featureManager->registerRequirement(
			new OverridableConfigRequirement(
				$services->getMainConfig(),
				$context->getUser(),
				$context->getRequest(),
				null,
				Constants::CONFIG_LANGUAGE_IN_MAIN_PAGE_HEADER,
				Constants::REQUIREMENT_LANGUAGE_IN_MAIN_PAGE_HEADER,
				Constants::QUERY_PARAM_LANGUAGE_IN_MAIN_PAGE_HEADER,
				null
			)
		);

		$featureManager->registerSimpleRequirement(
			Constants::REQUIREMENT_IS_MAIN_PAGE,
			$context->getTitle() ? $context->getTitle()->isMainPage() : false
		);

		$featureManager->registerFeature(
			Constants::FEATURE_LANGUAGE_IN_MAIN_PAGE_HEADER,
			[
				Constants::REQUIREMENT_FULLY_INITIALISED,
				Constants::REQUIREMENT_LATEST_SKIN_VERSION,
				Constants::REQUIREMENT_IS_MAIN_PAGE,
				Constants::REQUIREMENT_LANGUAGE_IN_HEADER,
				Constants::REQUIREMENT_LANGUAGE_IN_MAIN_PAGE_HEADER
			]
		);

		// Feature: T295555: Language switch alert in sidebar
		// ================================
		$featureManager->registerRequirement(
			new OverridableConfigRequirement(
				$services->getMainConfig(),
				$context->getUser(),
				$context->getRequest(),
				null,
				Constants::CONFIG_LANGUAGE_ALERT_IN_SIDEBAR,
				Constants::REQUIREMENT_LANGUAGE_ALERT_IN_SIDEBAR,
				Constants::QUERY_PARAM_LANGUAGE_ALERT_IN_SIDEBAR,
				null
			)
		);

		$featureManager->registerFeature(
			Constants::FEATURE_LANGUAGE_ALERT_IN_SIDEBAR,
			[
				Constants::REQUIREMENT_FULLY_INITIALISED,
				Constants::REQUIREMENT_LATEST_SKIN_VERSION,
				Constants::REQUIREMENT_LANGUAGE_IN_HEADER,
				Constants::REQUIREMENT_LANGUAGE_ALERT_IN_SIDEBAR
			]
		);

		// Feature: T297610: Table of Contents
		// ================================
		$featureManager->registerRequirement(
			new OverridableConfigRequirement(
				$services->getMainConfig(),
				$context->getUser(),
				$context->getRequest(),
				null,
				Constants::CONFIG_TABLE_OF_CONTENTS,
				Constants::REQUIREMENT_TABLE_OF_CONTENTS,
				Constants::QUERY_PARAM_TABLE_OF_CONTENTS,
				null
			)
		);

		$featureManager->registerFeature(
			Constants::FEATURE_TABLE_OF_CONTENTS,
			[
				Constants::REQUIREMENT_FULLY_INITIALISED,
				Constants::REQUIREMENT_TABLE_OF_CONTENTS
			]
		);

		// Feature: Sticky header
		// ================================
		$featureManager->registerRequirement(
			new OverridableConfigRequirement(
				$services->getMainConfig(),
				$context->getUser(),
				$context->getRequest(),
				null,
				Constants::CONFIG_STICKY_HEADER,
				Constants::REQUIREMENT_STICKY_HEADER,
				Constants::QUERY_PARAM_STICKY_HEADER,
				null
			)
		);

		$featureManager->registerRequirement(
			new OverridableConfigRequirement(
				$services->getMainConfig(),
				$context->getUser(),
				$context->getRequest(),
				null,
				Constants::CONFIG_STICKY_HEADER_EDIT,
				Constants::REQUIREMENT_STICKY_HEADER_EDIT,
				Constants::QUERY_PARAM_STICKY_HEADER_EDIT,
				null
			)
		);

		$featureManager->registerFeature(
			Constants::FEATURE_STICKY_HEADER,
			[
				Constants::REQUIREMENT_FULLY_INITIALISED,
				Constants::REQUIREMENT_LATEST_SKIN_VERSION,
				Constants::REQUIREMENT_STICKY_HEADER
			]
		);

		$featureManager->registerFeature(
			Constants::FEATURE_STICKY_HEADER_EDIT,
			[
				Constants::REQUIREMENT_FULLY_INITIALISED,
				Constants::REQUIREMENT_LATEST_SKIN_VERSION,
				Constants::REQUIREMENT_STICKY_HEADER,
				Constants::REQUIREMENT_STICKY_HEADER_EDIT,
			]
		);

		return $featureManager;
	}
];
