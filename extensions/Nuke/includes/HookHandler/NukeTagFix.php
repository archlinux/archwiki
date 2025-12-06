<?php

namespace MediaWiki\Extension\Nuke\HookHandler;

use MediaWiki\Extension\Nuke\Maintenance\NormalizeNukeTags;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class NukeTagFix implements LoadExtensionSchemaUpdatesHook {
	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		// Currently, we're not actually making any schema change
		// Just running some fixes to the database.

		// T381598
		$updater->addExtensionUpdate( [
			'runMaintenance',
			NormalizeNukeTags::class,
		] );
	}
}
