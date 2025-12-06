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

// @codeCoverageIgnoreStart
require_once __DIR__ . '/../../../maintenance/Maintenance.php';
// @codeCoverageIgnoreEnd

class FixNativeReferences extends Maintenance {

	private const REFERENCE_PATH = __DIR__ . '/../tests/phpunit/integration/WikiTexVC/data/reference.json';
	private const RNG_PATH = __DIR__ . '/../tests/phpunit/integration/WikiTexVC/mathml4-core.rng';

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Update reference rendering for regression tests.
		 Changes should be investigated manually.' );
	}

	/**
	 * @throws \MediaWiki\Maintenance\MaintenanceFatalError
	 */
	public function execute() {
		$file = file_get_contents( self::REFERENCE_PATH );
		$json = json_decode( $file, true );
		$success = true;
		$allEntries = [];
		foreach ( $json as $entry ) {
			$success = $success &&
				MathNativeMML::renderReferenceEntry( $entry, null, null, null, self::RNG_PATH );
			$allEntries[] = $entry;
		}

		$jsonData = json_encode( $allEntries, JSON_PRETTY_PRINT + JSON_UNESCAPED_UNICODE );
		file_put_contents( self::REFERENCE_PATH, $jsonData );
		if ( !$success ) {
			$this->fatalError( "Some entries were skipped. Please investigate.\n" );
		}
		$this->output( "Regression.json successfully updated.\n" );
	}

}

// @codeCoverageIgnoreStart
$maintClass = FixNativeReferences::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
