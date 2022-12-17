<?php
/**
 * MediaWiki parser test suite
 *
 * Copyright © 2004 Brion Vibber <brion@pobox.com>
 * https://www.mediawiki.org/
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
 * @ingroup Testing
 */

require_once __DIR__ . '/../../maintenance/Maintenance.php';

use MediaWiki\MediaWikiServices;
use MediaWiki\Settings\SettingsBuilder;
use MediaWiki\Tests\AnsiTermColorer;
use MediaWiki\Tests\DummyTermColorer;

class ParserTestsMaintenance extends Maintenance {
	/**
	 * Copied over from the Parsoid repo: (tools/ScriptUtils.php)
	 *
	 * Parse a boolean option returned by our opts processor.
	 * The strings 'false' and 'no' are also treated as false values.
	 * This allows `--debug=no` and `--debug=false` to mean the same as
	 * `--no-debug`.
	 *
	 * @param bool|string $val
	 *   a boolean, or a string naming a boolean value.
	 * @return bool
	 */
	private function booleanOption( $val ): bool {
		if ( !$val ) {
			return false;
		}
		if ( is_string( $val ) && preg_match( '/^(no|false)$/D', $val ) ) {
			return false;
		}
		return true;
	}

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Run parser tests' );

		$this->addOption( 'quick', 'Suppress diff output of failed tests' );
		$this->addOption( 'quiet', 'Suppress notification of passed tests (shows only failed tests)' );
		$this->addOption( 'show-output', 'Show expected and actual output' );
		$this->addOption( 'color', '[=yes|no] Override terminal detection and force ' .
			'color output on or off. Use wgCommandLineDarkBg = true; if your term is dark',
			false, true );
		$this->addOption( 'regex', 'Only run tests whose descriptions which match given regex',
			false, true );
		$this->addOption( 'filter', 'Alias for --regex', false, true );
		$this->addOption( 'file', 'Run test cases from a custom file instead of parserTests.txt',
			false, true, false, true );
		$this->addOption( 'record', 'Record tests in database' );
		$this->addOption( 'compare', 'Compare with recorded results, without updating the database.' );
		$this->addOption( 'setversion', 'When using --record, set the version string to use (useful' .
			'with "git rev-parse HEAD" to get the exact revision)',
			false, true );
		$this->addOption( 'keep-uploads', 'Re-use the same upload directory for each ' .
			'test, don\'t delete it' );
		$this->addOption( 'file-backend', 'Use the file backend with the given name,' .
			'and upload files to it, instead of creating a mock file backend.', false, true );
		$this->addOption( 'upload-dir', 'Specify the upload directory to use. Useful in ' .
			'conjunction with --keep-uploads. Causes a real (non-mock) file backend to ' .
			'be used.', false, true );
		$this->addOption( 'run-disabled', 'run disabled tests' );
		$this->addOption( 'disable-save-parse', 'Don\'t run the parser when ' .
			'inserting articles into the database' );
		$this->addOption( 'dwdiff', 'Use dwdiff to display diff output' );
		$this->addOption( 'mark-ws', 'Mark whitespace in diffs by replacing it with symbols' );
		$this->addOption( 'norm', 'Apply a comma-separated list of normalization functions to ' .
			'both the expected and actual output in order to resolve ' .
			'irrelevant differences. The accepted normalization functions ' .
			'are: removeTbody to remove <tbody> tags; and trimWhitespace ' .
			'to trim whitespace from the start and end of text nodes.',
			false, true );
		$this->addOption( 'wt2html', 'Parsoid: Wikitext -> HTML' );
		$this->addOption( 'wt2wt',
			'Parsoid Roundtrip testing: Wikitext -> HTML(DOM) -> Wikitext' );
		$this->addOption( 'html2wt', 'Parsoid: HTML -> Wikitext' );
		$this->addOption( 'numchanges',
			'Max different selser edit tests to generate from the Parsoid DOM' );
		$this->addOption( 'html2html',
			'Parsoid Roundtrip testing: HTML -> Wikitext -> HTML' );
		$this->addOption( 'selser',
			'Parsoid Roundtrip testing: Wikitext -> DOM(HTML) -> Wikitext (with selective serialization). ' .
					'Set to "noauto" to just run the tests with manual selser changes.',
			false, true );
		$this->addOption( 'changetree',
			'Changes to apply to Parsoid HTML to generate new HTML to be serialized (use with selser)',
			false, true );
		$this->addOption( 'parsoid', 'Run Parsoid tests' );
		$this->addOption( 'updateKnownFailures', 'Update knownFailures.json with failing tests' );
		$this->addOption( 'knownFailures',
			'Compare against known failures (default: true). If false, ignores knownFailures.json file',
			false, true );
	}

	public function finalSetup( SettingsBuilder $settingsBuilder = null ) {
		// Some methods which are discouraged for normal code throw exceptions unless
		// we declare this is just a test.
		define( 'MW_PARSER_TEST', true );

		parent::finalSetup( $settingsBuilder );
		ExtensionRegistry::getInstance()->setLoadTestClassesAndNamespaces( true );
		self::requireTestsAutoloader();
		TestSetup::applyInitialConfig();
	}

	public function execute() {
		global $wgDBtype;

		// Cases of weird db corruption were encountered when running tests on earlyish
		// versions of SQLite
		if ( $wgDBtype == 'sqlite' ) {
			$db = wfGetDB( DB_PRIMARY );
			$version = $db->getServerVersion();
			if ( version_compare( $version, '3.6' ) < 0 ) {
				die( "Parser tests require SQLite version 3.6 or later, you have $version\n" );
			}
		}

		// Print out software version to assist with locating regressions
		$version = SpecialVersion::getVersion( 'nodb' );
		echo "This is MediaWiki version {$version}.\n\n";

		// Only colorize output if stdout is a terminal.
		$color = !wfIsWindows() && Maintenance::posix_isatty( 1 );

		if ( $this->hasOption( 'color' ) ) {
			switch ( $this->getOption( 'color' ) ) {
				case 'no':
					$color = false;
					break;
				case 'yes':
				default:
					$color = true;
					break;
			}
		}

		$record = $this->hasOption( 'record' );
		$compare = $this->hasOption( 'compare' );

		$regex = $this->getOption( 'filter', $this->getOption( 'regex', false ) );
		if ( $regex !== false ) {
			$regex = "/$regex/i";

			if ( $record ) {
				echo "Warning: --record cannot be used with --regex, disabling --record\n";
				$record = false;
			}
		}

		$term = $color
			? new AnsiTermColorer()
			: new DummyTermColorer();

		$recorder = new MultiTestRecorder;

		$recorder->addRecorder( new ParserTestPrinter(
			$term,
			[
				'showDiffs' => !$this->hasOption( 'quick' ),
				'showProgress' => !$this->hasOption( 'quiet' ),
				'showFailure' => !$this->hasOption( 'quiet' )
						|| ( !$record && !$compare ), // redundant output
				'showOutput' => $this->hasOption( 'show-output' ),
				'useDwdiff' => $this->hasOption( 'dwdiff' ),
				'markWhitespace' => $this->hasOption( 'mark-ws' ),
			]
		) );

		$recorderLB = false;
		if ( $record || $compare ) {
			// Make an untracked DB_PRIMARY connection (wiki's table prefix, not parsertest_)
			$recorderLB = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->newMainLB();
			$recorderDB = $recorderLB->getMaintenanceConnectionRef( DB_PRIMARY );
			// Add recorder before previewer because recorder will create the
			// DB table if it doesn't exist
			if ( $record ) {
				$recorder->addRecorder( new DbTestRecorder( $recorderDB ) );
			}
			$recorder->addRecorder( new DbTestPreviewer(
				$recorderDB,
				static function ( $name ) use ( $regex ) {
					// Filter reports of old tests by the filter regex
					return $regex === false || (bool)preg_match( $regex, $name );
				} ) );
		}

		// Default parser tests and any set from extensions or local config
		$files = $this->getOption( 'file', ParserTestRunner::getParserTestFiles() );

		$norm = $this->hasOption( 'norm' ) ? explode( ',', $this->getOption( 'norm' ) ) : [];

		$selserOpt = $this->getOption( 'selser', false ); /* can also be 'noauto' */
		if ( $selserOpt !== 'noauto' ) {
			$selserOpt = $this->booleanOption( $selserOpt );
		}
		$tester = new ParserTestRunner( $recorder, [
			'norm' => $norm,
			'regex' => $regex,
			'keep-uploads' => $this->hasOption( 'keep-uploads' ),
			'run-disabled' => $this->hasOption( 'run-disabled' ),
			'disable-save-parse' => $this->hasOption( 'disable-save-parse' ),
			'file-backend' => $this->getOption( 'file-backend' ),
			'upload-dir' => $this->getOption( 'upload-dir' ),
			// Passing a parsoid-specific option implies --parsoid
			'parsoid' => (
				$this->hasOption( 'parsoid' ) ||
				$this->hasOption( 'wt2html' ) ||
				$this->hasOption( 'wt2wt' ) ||
				$this->hasOption( 'html2wt' ) ||
				$this->hasOption( 'html2html' ) ||
				$selserOpt ),
			'wt2html' => $this->hasOption( 'wt2html' ),
			'wt2wt' => $this->hasOption( 'wt2wt' ),
			'html2wt' => $this->hasOption( 'html2wt' ),
			'html2html' => $this->hasOption( 'html2html' ),
			'numchanges' => $this->getOption( 'numchanges', 20 ),
			'selser' => $selserOpt,
			'changetree' => json_decode( $this->getOption( 'changetree', null ), true ),
			'knownFailures' => $this->booleanOption( $this->getOption( 'knownFailures', true ) ),
			'updateKnownFailures' => $this->hasOption( 'updateKnownFailures' )
		] );

		$ok = $tester->runTestsFromFiles( $files );
		if ( $recorderLB ) {
			$recorderLB->closeAll( __METHOD__ );
		}
		if ( $tester->unexpectedTestPasses ) {
			$recorder->warning( "There were some unexpected passing tests. " .
				"Please rerun with --updateKnownFailures option." );
			$ok = false;
		}
		if ( !$ok ) {
			exit( 1 );
		}
	}
}

$maintClass = ParserTestsMaintenance::class;
require_once RUN_MAINTENANCE_IF_MAIN;
