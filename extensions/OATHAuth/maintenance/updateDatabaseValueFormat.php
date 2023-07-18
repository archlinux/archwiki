<?php
/**
 * Converts old, TOTP specific, column values to new structure
 *
 * Usage: php updateDatabaseValueFormat.php
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
 * @author Dejan Savuljesku
 * @ingroup Maintenance
 */

use MediaWiki\Extension\OATHAuth\Hook\UpdateTables;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class UpdateDatabaseValueFormat extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Script to convert old, TOTP specific, column values to new structure' );
		$this->requireExtension( 'OATHAuth' );
	}

	/**
	 * @throws ConfigException
	 */
	public function execute() {
		$database = OATHAuthServices::getInstance()->getDatabase();
		$dbw = $database->getDB( DB_PRIMARY );

		// @phan-suppress-next-line PhanTypeMismatchArgumentSuperType
		if ( !UpdateTables::convertToGenericFields( $dbw ) ) {
			$this->fatalError( "Failed to update the data structure rows.\n" );
		}
		$this->output( "Done.\n" );
	}
}

$maintClass = UpdateDatabaseValueFormat::class;
require_once RUN_MAINTENANCE_IF_MAIN;
