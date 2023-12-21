<?php

namespace MediaWiki\Extension\Math\HookHandlers;

use DatabaseUpdater;
use LogicException;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * Hook handler for schema hook
 */
class SchemaHooksHandler implements LoadExtensionSchemaUpdatesHook {

	/**
	 * LoadExtensionSchemaUpdates handler; set up math table on install/upgrade.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();
		if ( !in_array( $type, [ 'mysql', 'sqlite', 'postgres' ], true ) ) {
			throw new LogicException( "Math extension does not currently support $type database." );
		}

		foreach ( [ 'mathoid', 'mathlatexml' ] as $mode ) {
			$updater->addExtensionTable(
				$mode,
				__DIR__ . "/../../sql/$type/$mode.sql"
			);
		}

		if ( $type === 'mysql' ) {
			$updater->addExtensionField(
				'mathoid',
				'math_png',
				__DIR__ . '/../../sql/' . $type . '/patch-mathoid.add_png.sql'
			);
		}
	}
}
