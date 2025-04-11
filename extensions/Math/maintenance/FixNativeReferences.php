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
 * @ingroup Maintenance
 */

use MediaWiki\Extension\Math\MathNativeMML;
use MediaWiki\Maintenance\Maintenance;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class FixNativeReferences extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Update reference rendering for regression tests.
		 Changes should be investigated manually.' );
	}

	/**
	 * @throws \MediaWiki\Maintenance\MaintenanceFatalError
	 */
	public function execute() {
		$file = file_get_contents( __DIR__ . "/../tests/phpunit/unit/WikiTexVC/data/reference.json" );
		$json = json_decode( $file, true );
		$success = true;
		$allEntries = [];
		foreach ( $json as $entry ) {
			$success = $success && MathNativeMML::renderReferenceEntry( $entry );
			$allEntries[] = $entry;
		}

		$jsonData = json_encode( $allEntries, JSON_PRETTY_PRINT );
		file_put_contents( __DIR__ . "/../tests/phpunit/unit/WikiTexVC/data/reference.json", $jsonData );
		if ( !$success ) {
			$this->fatalError( "Some entries were skipped. Please investigate.\n" );
		}
		$this->output( "Regression.json successfully updated.\n" );
	}

}

$maintClass = FixNativeReferences::class;
require_once RUN_MAINTENANCE_IF_MAIN;
