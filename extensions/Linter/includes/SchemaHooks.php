<?php
/**
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
 */

namespace MediaWiki\Linter;

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHooks implements LoadExtensionSchemaUpdatesHook {
	/**
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dbType = $updater->getDB()->getType();
		if ( $dbType === 'mysql' ) {
			$updater->addExtensionTable( 'linter',
				dirname( __DIR__ ) . '/sql/tables-generated.sql'
			);
			$updater->addExtensionField( 'linter', 'linter_namespace',
				dirname( __DIR__ ) . '/sql/patch-linter-add-namespace.sql'
			);
			$updater->addExtensionField( 'linter', 'linter_template',
				dirname( __DIR__ ) . '/sql/patch-linter-template-tag-fields.sql'
			);
			$updater->modifyExtensionField( 'linter', 'linter_params',
				dirname( __DIR__ ) . '/sql/patch-linter-fix-params-null-definition.sql'
			);
		} elseif ( $dbType === 'sqlite' ) {
			$updater->addExtensionTable( 'linter',
				dirname( __DIR__ ) . '/sql/sqlite/tables-generated.sql'
			);
			$updater->addExtensionField( 'linter', 'linter_namespace',
				dirname( __DIR__ ) . '/sql/sqlite/patch-linter-add-namespace.sql'
			);
			$updater->addExtensionField( 'linter', 'linter_template',
				dirname( __DIR__ ) . '/sql/sqlite/patch-linter-template-tag-fields.sql'
			);
			$updater->modifyExtensionField( 'linter', 'linter_params',
				dirname( __DIR__ ) . '/sql/sqlite/patch-linter-fix-params-null-definition.sql'
			);
		} elseif ( $dbType === 'postgres' ) {
			$updater->addExtensionTable( 'linter',
				dirname( __DIR__ ) . '/sql/postgres/tables-generated.sql'
			);
			$updater->addExtensionField( 'linter', 'linter_namespace',
				dirname( __DIR__ ) . '/sql/postgres/patch-linter-add-namespace.sql'
			);
			$updater->addExtensionField( 'linter', 'linter_template',
				dirname( __DIR__ ) . '/sql/postgres/patch-linter-template-tag-fields.sql'
			);
			$updater->modifyExtensionField( 'linter', 'linter_params',
				dirname( __DIR__ ) . '/sql/postgres/patch-linter-fix-params-null-definition.sql'
			);
		}
	}
}
