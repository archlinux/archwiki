<?php
/**
 * Move a batch of pages.
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
 * @author Tim Starling
 *
 *
 * This will print out error codes from Title::moveTo() if something goes wrong,
 * e.g. immobile_namespace for namespaces which can't be moved
 */

use MediaWiki\MediaWikiServices;

require_once __DIR__ . '/Maintenance.php';

/**
 * Maintenance script to move a batch of pages.
 *
 * @ingroup Maintenance
 */
class MoveBatch extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Moves a batch of pages' );
		$this->addOption( 'u', "User to perform move", false, true );
		$this->addOption( 'r', "Reason to move page", false, true );
		$this->addOption( 'i', "Interval to sleep between moves" );
		$this->addOption( 'noredirects', "Suppress creation of redirects" );
		$this->addArg( 'listfile', 'List of pages to move, newline delimited', false );
	}

	public function execute() {
		# Options processing
		$username = $this->getOption( 'u', false );
		$reason = $this->getOption( 'r', '' );
		$interval = $this->getOption( 'i', 0 );
		$noRedirects = $this->hasOption( 'noredirects' );
		if ( $this->hasArg( 0 ) ) {
			$file = fopen( $this->getArg( 0 ), 'r' );
		} else {
			$file = $this->getStdin();
		}

		# Setup
		if ( !$file ) {
			$this->fatalError( "Unable to read file, exiting" );
		}
		if ( $username === false ) {
			$user = User::newSystemUser( 'Move page script', [ 'steal' => true ] );
		} else {
			$user = User::newFromName( $username );
		}
		if ( !$user ) {
			$this->fatalError( "Invalid username" );
		}
		StubGlobalUser::setUser( $user );

		# Setup complete, now start
		$dbw = $this->getDB( DB_PRIMARY );
		for ( $lineNum = 1; !feof( $file ); $lineNum++ ) {
			$line = fgets( $file );
			if ( $line === false ) {
				break;
			}
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) !== 2 ) {
				$this->error( "Error on line $lineNum, no pipe character" );
				continue;
			}
			$source = Title::newFromText( $parts[0] );
			$dest = Title::newFromText( $parts[1] );
			if ( $source === null || $dest === null ) {
				$this->error( "Invalid title on line $lineNum" );
				continue;
			}

			$this->output( $source->getPrefixedText() . ' --> ' . $dest->getPrefixedText() );
			$this->beginTransaction( $dbw, __METHOD__ );
			$mp = MediaWikiServices::getInstance()->getMovePageFactory()
				->newMovePage( $source, $dest );
			$status = $mp->move( $user, $reason, !$noRedirects );
			if ( !$status->isOK() ) {
				$this->output( "\nFAILED: " . $status->getMessage( false, false, 'en' )->text() );
			}
			$this->commitTransaction( $dbw, __METHOD__ );
			$this->output( "\n" );

			if ( $interval ) {
				sleep( $interval );
			}
		}
	}
}

$maintClass = MoveBatch::class;
require_once RUN_MAINTENANCE_IF_MAIN;
