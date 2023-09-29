<?php
/**
 * Move text from the text table to external storage
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
 * @ingroup Maintenance ExternalStorage
 */

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\UndoLog;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\SqlBlobStore;
use Wikimedia\AtEase\AtEase;

require_once __DIR__ . '/../Maintenance.php';

class MoveToExternal extends Maintenance {
	/** @var ResolveStubs */
	private $resolveStubs;
	/** @var int */
	private $reportingInterval;
	/** @var int */
	private $minID;
	/** @var int */
	private $maxID;
	/** @var string */
	private $esType;
	/** @var string */
	private $esLocation;
	/** @var int */
	private $threshold;
	/** @var bool */
	private $gzip;
	/** @var bool */
	private $skipResolve;
	/** @var string|null */
	private $legacyEncoding;
	/** @var bool */
	private $dryRun;
	/** @var UndoLog */
	private $undoLog;

	public function __construct() {
		parent::__construct();

		$this->setBatchSize( 1000 );

		$this->addOption( 'start', 'start old_id', false, true, 's' );
		$this->addOption( 'end', 'end old_id', false, true, 'e' );
		$this->addOption( 'threshold', 'minimum size in bytes', false, true );
		$this->addOption( 'reporting-interval',
			'show a message after this many revisions', false, true );
		$this->addOption( 'undo', 'filename for undo SQL', false, true );

		$this->addOption( 'skip-gzip', 'Don\'t compress individual revisions' );
		$this->addOption( 'skip-resolve',
			'Don\'t replace HistoryBlobStub objects with direct external store pointers' );
		$this->addOption( 'iconv', 'Resolve legacy character encoding' );
		$this->addOption( 'dry-run', 'Don\'t modify any rows' );

		$this->addArg( 'type', 'The external store type, e.g. "DB" or "mwstore"' );
		$this->addArg( 'location', 'e.g. "cluster12" or "global-swift"' );
	}

	public function execute() {
		$this->resolveStubs = new ResolveStubs;
		$this->esType = $this->getArg( 0 ); // e.g. "DB" or "mwstore"
		$this->esLocation = $this->getArg( 1 ); // e.g. "cluster12" or "global-swift"
		$dbw = $this->getDB( DB_PRIMARY );

		$maxID = $this->getOption( 'end' );
		if ( $maxID === null ) {
			$maxID = $dbw->selectField( 'text', 'MAX(old_id)', '', __METHOD__ );
		}
		$this->maxID = (int)$maxID;
		$this->minID = (int)$this->getOption( 'start', 1 );

		$this->reportingInterval = $this->getOption( 'reporting-interval', 100 );
		$this->threshold = (int)$this->getOption( 'threshold', 0 );

		if ( $this->getOption( 'skip-gzip' ) ) {
			$this->gzip = false;
		} elseif ( !function_exists( 'gzdeflate' ) ) {
			$this->fatalError( "gzdeflate() not found. " .
				"Please run with --skip-gzip if you don't want to compress revisions." );
		} else {
			$this->gzip = true;
		}

		$this->skipResolve = $this->getOption( 'skip-resolve' );

		if ( $this->getOption( 'iconv' ) ) {
			$legacyEncoding = $this->getConfig()->get( MainConfigNames::LegacyEncoding );
			if ( $legacyEncoding ) {
				$this->legacyEncoding = $legacyEncoding;
			} else {
				$this->output( "iconv requested but the wiki has no legacy encoding\n" );
			}
		}
		$this->dryRun = $this->getOption( 'dry-run', false );

		$undo = $this->getOption( 'undo' );
		try {
			$this->undoLog = new UndoLog( $undo, $dbw );
		} catch ( RuntimeException $e ) {
			$this->fatalError( "Unable to open undo log" );
		}
		$this->resolveStubs->setUndoLog( $this->undoLog );

		$this->doMoveToExternal();
	}

	private function doMoveToExternal() {
		$dbr = $this->getDB( DB_REPLICA );

		$count = $this->maxID - $this->minID + 1;
		$blockSize = $this->getBatchSize();
		$numBlocks = ceil( $count / $blockSize );
		print "Moving text rows from {$this->minID} to {$this->maxID} to external storage\n";

		$esFactory = MediaWikiServices::getInstance()->getExternalStoreFactory();
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$extStore = $esFactory->getStore( $this->esType );
		$numMoved = 0;
		$stubIDs = [];

		for ( $block = 0; $block < $numBlocks; $block++ ) {
			$blockStart = $block * $blockSize + $this->minID;
			$blockEnd = $blockStart + $blockSize - 1;

			if ( $this->reportingInterval && !( $block % $this->reportingInterval ) ) {
				$this->output( "oldid=$blockStart, moved=$numMoved\n" );
				$lbFactory->waitForReplication();
			}

			$res = $dbr->select( 'text', [ 'old_id', 'old_flags', 'old_text' ],
				[
					"old_id BETWEEN $blockStart AND $blockEnd",
					'old_flags NOT ' . $dbr->buildLike( $dbr->anyString(), 'external', $dbr->anyString() ),
				], __METHOD__
			);
			foreach ( $res as $row ) {
				$text = $row->old_text;
				$id = $row->old_id;
				$flags = SqlBlobStore::explodeFlags( $row->old_flags );

				if ( in_array( 'error', $flags ) ) {
					continue;
				} elseif ( in_array( 'object', $flags ) ) {
					$obj = unserialize( $text );
					if ( $obj instanceof HistoryBlobStub ) {
						// Handle later, after CGZ resolution
						if ( !$this->skipResolve ) {
							$stubIDs[] = $id;
						}
						continue;
					} elseif ( $obj instanceof HistoryBlobCurStub ) {
						// Copy cur text to ES
						[ $text, $flags ] = $this->compress( $obj->getText(), [ 'utf-8' ] );
					} elseif ( $obj instanceof ConcatenatedGzipHistoryBlob ) {
						// Store as is
					} else {
						$className = get_class( $obj );
						print "Warning: old_id=$id unrecognised object class \"$className\"\n";
						continue;
					}
				} elseif ( strlen( $text ) < $this->threshold ) {
					// Don't move small revisions
					continue;
				} else {
					[ $text, $flags ] = $this->resolveLegacyEncoding( $text, $flags );
					[ $text, $flags ] = $this->compress( $text, $flags );
				}
				$flags[] = 'external';
				$flagsString = implode( ',', $flags );

				if ( $this->dryRun ) {
					$this->output( "Move $id => $flagsString " .
						addcslashes( substr( $text, 0, 30 ), "\0..\x1f\x7f..\xff" ) .
						"\n"
					);
					continue;
				}

				$url = $extStore->store( $this->esLocation, $text );
				if ( !$url ) {
					$this->fatalError( "Error writing to external storage" );
				}
				$moved = $this->undoLog->update(
					'text',
					[ 'old_flags' => $flagsString, 'old_text' => $url ],
					(array)$row,
					__METHOD__
				);
				if ( $moved ) {
					$numMoved++;
				} else {
					print "Update of old_id $id failed, affected zero rows\n";
				}
			}
		}

		if ( count( $stubIDs ) ) {
			$this->resolveStubs( $stubIDs );
		}
	}

	private function compress( $text, $flags ) {
		if ( $this->gzip && !in_array( 'gzip', $flags ) ) {
			$flags[] = 'gzip';
			$text = gzdeflate( $text );
		}
		return [ $text, $flags ];
	}

	private function resolveLegacyEncoding( $text, $flags ) {
		if ( $this->legacyEncoding !== null
			&& !in_array( 'utf-8', $flags )
			&& !in_array( 'utf8', $flags )
		) {
			AtEase::suppressWarnings();
			$text = iconv( $this->legacyEncoding, 'UTF-8//IGNORE', $text );
			AtEase::restoreWarnings();
			$flags[] = 'utf-8';
		}
		return [ $text, $flags ];
	}

	private function resolveStubs( $stubIDs ) {
		if ( $this->dryRun ) {
			print "Note: resolving stubs in dry run mode is expected to fail, " .
				"because the main blobs have not been moved to external storage.\n";
		}

		$dbr = $this->getDB( DB_REPLICA );
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$this->output( "Resolving " . count( $stubIDs ) . " stubs\n" );
		$numResolved = 0;
		$numTotal = 0;
		foreach ( array_chunk( $stubIDs, $this->getBatchSize() ) as $stubBatch ) {
			$res = $dbr->select(
				'text',
				[ 'old_id', 'old_flags', 'old_text' ],
				[ 'old_id' => $stubBatch ],
				__METHOD__
			);
			foreach ( $res as $row ) {
				$numResolved += $this->resolveStubs->resolveStub( $row, $this->dryRun ) ? 1 : 0;
				$numTotal++;
				if ( $this->reportingInterval
					&& $numTotal % $this->reportingInterval == 0
				) {
					$this->output( "$numTotal stubs processed\n" );
					$lbFactory->waitForReplication();
				}
			}
		}
		$this->output( "$numResolved of $numTotal stubs resolved\n" );
	}
}

$maintClass = MoveToExternal::class;
require_once RUN_MAINTENANCE_IF_MAIN;
