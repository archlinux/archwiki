<?php
/**
 * Remove any lines from MediaWiki:Common.css which were needed for group styling.
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

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Content\TextContent;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\User;
use Wikimedia\Diff\Diff;
use Wikimedia\Diff\UnifiedDiffFormatter;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Maintenance script to remove deprecated CSS rules.
 *
 * @license GPL-2.0-or-later
 * @ingroup Maintenance
 */
class RemoveParsoidGroupStyling extends Maintenance {
	private const SUMMARY = 'Remove unused Parsoid Cite group styles, see https://phabricator.wikimedia.org/T386182';

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Remove deprecated Parsoid Cite CSS rules' );
		$this->addOption( 'dry-run', 'Don\'t make changes' );
		$this->addOption( 'remove-comments', 'Also remove specific known comments' );
		$this->addOption( 'title', 'Title to edit, defaults to MediaWiki:Common.css', false, true );
		$this->addOption( 'user', 'Username', false, true, 'u' );
	}

	public function execute() {
		$userName = $this->getOption( 'user', false );
		$dryRun = $this->getOption( 'dry-run', false );
		$titleText = $this->getOption( 'title', 'MediaWiki:Common.css' );
		$removeKnownComments = $this->getOption( 'remove-comments', false );

		if ( $userName === false ) {
			$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
		} else {
			$user = User::newFromName( $userName );
		}
		if ( !$user ) {
			$this->fatalError( "Invalid username" );
		}

		$title = $this->getServiceContainer()->getTitleFactory()->newFromTextThrow( $titleText );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		if ( $page->exists() ) {
			$latestRevId = $page->getLatest();
			$revLookup = $this->getServiceContainer()->getRevisionLookup();
			$revRecord = $revLookup->getRevisionById( $latestRevId );
			if ( $revRecord ) {
				$content = $revRecord->getMainContentRaw();
				if ( !$content ) {
					$this->fatalError( "Couldn't get page content!\n" );
				}
				if ( !( $content instanceof TextContent ) ) {
					$this->fatalError( "Page content model is not text!\n" );
				}
				$text = $content->getText();

				$newText = $this->removeStyling( $text );
				if ( $removeKnownComments ) {
					$newText = $this->removeKnownComments( $newText );
				}
				$unifiedDiff = $this->calculateDiff( $text, $newText );
				if ( $unifiedDiff === '' ) {
					$this->output( "Nothing to change.\n" );
				} else {
					$this->output( $unifiedDiff );

					if ( $dryRun ) {
						$this->output( "Dry run mode, no changes made.\n" );
					} else {
						$updater = $page->newPageUpdater( $user );
						$content = ContentHandler::makeContent( $newText, $title );
						$updater->setContent( SlotRecord::MAIN, $content );
						$updater->saveRevision( CommentStoreComment::newUnsavedComment( self::SUMMARY ), 0 );
						$status = $updater->getStatus();
						if ( !$status->isOK() ) {
							$this->fatalError( $status );
						}
						$this->output( "Page edited.\n" );
					}
				}
			}
		} else {
			$this->fatalError( "Page {$titleText} doesn't exist!\n" );
		}
		$this->output( "\nDone.\n" );
	}

	private static function removeStyling( string $text ): string {
		return preg_replace( '/
			^\.mw-ref\s*>\s*a[^{}]*::after # Match all rules on any sort of .mw-ref a::after
			\s*{\s*
			content:[^:{}]+ # Must only set this one attribute.
			\s*}(?:\h*\n|$)
			/msx', '', $text );
	}

	private static function removeKnownComments( string $text ): string {
		return str_replace( [
			"/* T156351: Support for Parsoid's Cite implementation */\n",
			"/* These blocks need review after [[phab:T371839]] or related are complete */\n",
		], '', $text );
	}

	private static function calculateDiff( string $oldText, string $newText ): string {
		$diffs = new Diff( explode( "\n", $oldText ), explode( "\n", $newText ) );
		$formatter = new UnifiedDiffFormatter();
		return $formatter->format( $diffs );
	}
}

// @codeCoverageIgnoreStart
$maintClass = RemoveParsoidGroupStyling::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
