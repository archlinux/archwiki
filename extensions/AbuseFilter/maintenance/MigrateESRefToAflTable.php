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
 * @ingroup Maintenance ExternalStorage
 */

namespace MediaWiki\Extension\AbuseFilter\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use InvalidArgumentException;
use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Storage\SqlBlobStore;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Migrates references of the text table which again references external storage in the abuse_filter_log
 * table to the abuse_filter_log table directly referencing external storage and getting rid of the row
 * in the text table.
 */
class MigrateESRefToAflTable extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addOption( 'start', 'start afl_id', false, true, 's' );
		$this->addOption( 'end', 'end afl_id', false, true, 'e' );
		$this->addOption( 'dry-run', 'Don\'t modify any rows' );
		$this->addOption(
			'sleep',
			'Sleep time (in seconds) between every batch. Default: 0',
			false,
			true
		);
		$this->addOption(
			'deletedump',
			'Filename to dump the list of text table rows which can later be deleted',
			true,
			true
		);
		$this->addOption(
			'dump',
			'Filename to dump tt: -> es:DB references to.',
			false,
			true
		);

		$this->setBatchSize( 100 );
	}

	public function execute() {
		if ( !in_array( 'DB', $this->getConfig()->get( MainConfigNames::ExternalStores ) ) ) {
			$this->fatalError( 'This maintenance script is for use with external storage.' );
		}

		$dbw = $this->getPrimaryDB();
		$batchSize = $this->getBatchSize();
		$dryRun = $this->getOption( 'dry-run', false );
		$sleep = (float)$this->getOption( 'sleep', 0 );

		$maxID = $this->getOption( 'end' );
		if ( $maxID === null ) {
			$maxID = $dbw->newSelectQueryBuilder()
				->select( 'afl_id' )
				->from( 'abuse_filter_log' )
				->orderBy( 'afl_id', SelectQueryBuilder::SORT_DESC )
				->limit( 1 )
				->caller( __METHOD__ )
				->fetchField();
		}
		$maxID = (int)$maxID;
		$minID = (int)$this->getOption( 'start', 1 );

		$diff = $maxID - $minID + 1;

		$dump = $this->getOption( 'dump', false );
		if ( $dump ) {
			$dumpfile = fopen( $dump, 'a' );
			if ( !$dumpfile ) {
				$this->fatalError( "Failed to open file {$dump}." );
			}
		} else {
			$dumpfile = false;
		}

		$deletedump = $this->getOption( 'deletedump' );
		$deletedumpfile = fopen( $deletedump, 'a' );
		if ( !$deletedumpfile ) {
			$this->fatalError( "Failed to open file {$deletedump}." );
		}

		while ( true ) {
			$res = $dbw->newSelectQueryBuilder()
				->select( [ 'afl_id', 'afl_var_dump' ] )
				->from( 'abuse_filter_log' )
				->conds( [
					$dbw->expr( 'afl_id', '>=', $minID ),
					$dbw->expr( 'afl_id', '<', $minID + $batchSize ),
				] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $res as $row ) {
				try {
					[ $schema, $id, ] = SqlBlobStore::splitBlobAddress( $row->afl_var_dump );
				} catch ( InvalidArgumentException $ex ) {
					$this->output( $ex->getMessage() . ". Use findBadBlobs.php to remedy.\n" );
					continue;
				}

				// Skip blobs which already reference external storage directly
				if ( $schema === 'es' ) {
					continue;
				}

				// Skip bad blobs
				if ( $schema !== 'tt' ) {
					$this->output( "abuse filter log id {$row->afl_id} has special stuff: {$row->afl_var_dump}\n" );
					continue;
				}

				$oldId = intval( $id );

				if ( !$oldId ) {
					$this->output( "Malformed text_id: $oldId\n" );
					continue;
				}

				$textRow = $dbw->newSelectQueryBuilder()
					->select( [ 'old_text', 'old_flags' ] )
					->from( 'text' )
					->where( [ 'old_id' => $oldId ] )
					->caller( __METHOD__ )
					->fetchRow();

				if ( !$textRow ) {
					$this->output( "Text row for a blob of metadata of {$row->afl_id} is missing.\n" );
					continue;
				}

				$flags = SqlBlobStore::explodeFlags( $textRow->old_flags );

				if ( !in_array( 'external', $flags ) ) {
					$this->output( "old_id {$oldId} is not external.\n" );
					continue;
				}

				$newFlags = implode( ',', array_filter(
					$flags,
					static function ( $v ) {
						return $v !== 'external';
					}
				) );
				$newBlobAddress = 'es:' . $textRow->old_text . '?flags=' . $newFlags;

				if ( !$dryRun ) {
					$dbw->newUpdateQueryBuilder()
						->update( 'abuse_filter_log' )
						->set( [ 'afl_var_dump' => $newBlobAddress ] )
						->where( [ 'afl_id' => $row->afl_id ] )
						->caller( __METHOD__ )
						->execute();

					if ( $deletedumpfile ) {
						fwrite( $deletedumpfile, $row->afl_var_dump . "\n" );
					}

					if ( $dumpfile ) {
						fwrite( $dumpfile, $row->afl_var_dump . " => " . $newBlobAddress . ";\n" );
					}
				} else {
					$this->output( "DRY-RUN: Would set blob address for {$row->afl_id} to "
						. "{$newBlobAddress} and mark text row {$oldId} for deletion.\n" );
				}
			}

			$this->waitForReplication();
			if ( $sleep > 0 ) {
				if ( $sleep >= 1 ) {
					sleep( (int)$sleep );
				} else {
					usleep( (int)( $sleep * 1000000 ) );
				}
			}

			$this->output( "Processed {$res->numRows()} rows out of $diff.\n" );

			$minID += $batchSize;
			if ( $minID > $maxID ) {
				break;
			}
		}

		if ( $dumpfile ) {
			fclose( $dumpfile );
		}

		if ( $deletedumpfile ) {
			fclose( $deletedumpfile );
		}
	}
}

$maintClass = MigrateESRefToAflTable::class;
require_once RUN_MAINTENANCE_IF_MAIN;
