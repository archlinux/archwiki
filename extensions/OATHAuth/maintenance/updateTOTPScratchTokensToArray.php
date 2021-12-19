<?php
/**
 * Updates TOTP Scratch Tokens to an array
 *
 * Usage: php updateTOTPScratchTokensToArray.php
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
 * @ingroup Maintenance
 */

use MediaWiki\Extension\OATHAuth\Hook\LoadExtensionSchemaUpdates\UpdateTables;
use MediaWiki\MediaWikiServices;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class UpdateTOTPScratchTokensToArray extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Script to update TOTP Scratch Tokens to an array' );
		$this->requireExtension( 'OATHAuth' );
	}

	public function execute() {
		global $wgOATHAuthDatabase;
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getMainLB( $wgOATHAuthDatabase );
		$dbw = $lb->getConnectionRef( DB_PRIMARY, [], $wgOATHAuthDatabase );

		if ( !UpdateTables::switchTOTPScratchTokensToArray( $dbw ) ) {
			$this->error( "Failed to update TOTP Scratch Tokens.\n", 1 );
		}
		$this->output( "Done.\n" );
	}
}

$maintClass = UpdateTOTPScratchTokensToArray::class;
require_once RUN_MAINTENANCE_IF_MAIN;
