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
 * @ingroup Maintenance
 */

declare( strict_types = 1 );

namespace MediaWiki\Extension\OATHAuth\Maintenance;

use JsonSerializable;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Json\FormatJson;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use Wikimedia\Rdbms\IDatabase;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Moves TOTP scratch_tokens to their own recoverykeys rows
 *
 * Usage: php MoveRecoveryCodesFromTOTP.php
 */
class MoveRecoveryCodesFromTOTP extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'OATHAuth' );
		$this->addDescription( 'Moves TOTP scratch_tokens to their own recoverykeys rows' );
	}

	/** @inheritDoc */
	protected function doDBUpdates() {
		$startTime = time();
		$updatedCount = 0;
		$totalRows = 0;

		$services = $this->getServiceContainer();

		$moduleRegistry = OATHAuthServices::getInstance()->getModuleRegistry();
		$recoveryModuleId = $moduleRegistry->getModuleId( RecoveryCodes::MODULE_NAME );
		$totpModuleId = $moduleRegistry->getModuleId( TOTP::MODULE_NAME );

		$dbw = $services
			->getDBLoadBalancerFactory()
			->getPrimaryDatabase( 'virtual-oathauth' );
		$res = $dbw->newSelectQueryBuilder()
			->select( [ 'oad_id', 'oad_data', 'oad_user', 'oad_created' ] )
			->from( 'oathauth_devices' )
			->where( [ 'oad_type' => $totpModuleId ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$totalRows++;
			$data = FormatJson::decode( $row->oad_data, true );

			if ( !isset( $data['scratch_tokens'] ) ) {
				// No scratch_tokens in oad_data to move, skip the row
				continue;
			}

			$recoveryData = [
				'recoverycodekeys' => $data['scratch_tokens'],
			];

			// Remove scratch_tokens from oad_data because they're being transferred
			unset( $data['scratch_tokens'] );

			$recoveryCodesRow = $dbw->newSelectQueryBuilder()
				->select( [ 'oad_id', 'oad_data' ] )
				->from( 'oathauth_devices' )
				->where( [ 'oad_user' => $row->oad_user, 'oad_type' => $recoveryModuleId ] )
				->caller( __METHOD__ )
				->fetchRow();

			$this->beginTransactionRound( __METHOD__ );

			// Update totp row without scratch_tokens
			// T406953 - Also explicitly remove the empty scratch_tokens array from oad_data; these were incorrectly
			// added for a while. This means we don't need to keep WMF back compat code around for a long time.
			$this->updateRow(
				$dbw,
				(int)$row->oad_id,
				TOTPKey::newFromArray( $data )
			);

			if ( $recoveryData['recoverycodekeys'] === [] ) {
				// No rows to actually migrate, but we've cleaned up the TOTP row, so we can skip to the next
				$this->commitTransactionRound( __METHOD__ );
				continue;
			}

			if ( $recoveryCodesRow ) {
				$keys = FormatJson::decode( $recoveryCodesRow->oad_data, true );
				// Prepend new style recovery codes to existing from TOTP row
				$recoveryData['recoverycodekeys'] = array_merge(
					$keys['recoverycodekeys'],
					$recoveryData['recoverycodekeys']
				);

				$this->updateRow(
					$dbw,
					(int)$recoveryCodesRow->oad_id,
					RecoveryCodeKeys::newFromArray( $recoveryData )
				);
			} else {
				// Insert a new recoverykeys row for this user
				$dbw->newInsertQueryBuilder()
					->insertInto( 'oathauth_devices' )
					->row( [
						'oad_user' => $row->oad_user,
						'oad_type' => $recoveryModuleId,
						'oad_data' => FormatJson::encode( RecoveryCodeKeys::newFromArray( $recoveryData ) ),
						// Use the existing timestamp if available, otherwise use the current timestamp
						'oad_created' => $row->oad_created ?? $dbw->timestamp(),
					] )
					->caller( __METHOD__ )
					->execute();
			}

			$this->commitTransactionRound( __METHOD__ );
			$updatedCount++;
			if ( $updatedCount % 50 === 0 ) {
				$this->output( "{$updatedCount}\n" );
			}
		}

		$totalTimeInSeconds = time() - $startTime;
		$this->output( "Done. Updated {$updatedCount} of {$totalRows} rows in {$totalTimeInSeconds} seconds.\n" );
		return true;
	}

	private function updateRow( IDatabase $dbw, int $id, JsonSerializable $data ): void {
		$dbw->newUpdateQueryBuilder()
			->update( 'oathauth_devices' )
			->set( [ 'oad_data' => FormatJson::encode( $data->jsonSerialize() ) ] )
			->where( [ 'oad_id' => $id ] )
			->caller( __METHOD__ )
			->execute();
	}

	/** @return string */
	protected function getUpdateKey() {
		return __CLASS__;
	}
}

// @codeCoverageIgnoreStart
$maintClass = MoveRecoveryCodesFromTOTP::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
