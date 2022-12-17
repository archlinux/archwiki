<?php
/**
 * MediaWiki math extension
 *
 * @copyright 2002-2015 various MediaWiki contributors
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\Math;

use DatabaseUpdater;
use Exception;
use ExtensionRegistry;
use Maintenance;
use MediaWiki\MediaWikiServices;
use RequestContext;

class Hooks {

	/**
	 * MaintenanceRefreshLinksInit handler; optimize settings for refreshLinks batch job.
	 *
	 * @param Maintenance $maint
	 */
	public static function onMaintenanceRefreshLinksInit( $maint ) {
		$user = RequestContext::getMain()->getUser();

		// Don't parse LaTeX to improve performance
		MediaWikiServices::getInstance()->getUserOptionsManager()
			->setOption( $user, 'math', MathConfig::MODE_SOURCE );
	}

	/**
	 * LoadExtensionSchemaUpdates handler; set up math table on install/upgrade.
	 *
	 * @param DatabaseUpdater $updater
	 * @throws Exception
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$type = $updater->getDB()->getType();
		if ( !in_array( $type, [ 'mysql', 'sqlite', 'postgres' ] ) ) {
			throw new Exception( "Math extension does not currently support $type database." );
		}

		foreach ( [ 'mathoid', 'mathlatexml' ] as $mode ) {
			$updater->addExtensionTable(
				$mode,
				__DIR__ . "/../sql/$type/$mode.sql"
			);
		}

		if ( $type === 'mysql' ) {
			$updater->addExtensionField(
				'mathoid',
				'math_png',
				__DIR__ . '/../sql/' . $type . '/patch-mathoid.add_png.sql'
			);
		}
	}

	/**
	 * Remove Special:MathWikibase if the Wikibase client extension isn't loaded
	 *
	 * @param array &$list
	 */
	public static function onSpecialPageInitList( &$list ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseClient' ) ) {
			unset( $list['MathWikibase'] );
		}
	}

}
