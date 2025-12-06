<?php
/**
 * Create a standalone, executable 'pygmentize' bundle.
 *
 * This maintenance script downloads the latest version of Pygments from PyPI,
 * creates an executable ZIP bundle, and updates related files.
 *
 * @ingroup Maintenance
 */

namespace MediaWiki\SyntaxHighlight\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use ZipArchive;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' ) ?: __DIR__ . '/../../..';

require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class CreatePygmentizeBundle extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Create a standalone, executable pygmentize bundle.' );
	}

	public function execute() {
		// Pygmentize launcher template
		$pygmentizeLauncher = <<<EOD
			#!/usr/bin/env python3
			
			import sys
			import pygments.cmdline
			try:
			    sys.exit(pygments.cmdline.main(sys.argv))
			except KeyboardInterrupt:
			    sys.exit(1)
			EOD;

		$this->output( "Querying PyPI for the latest Pygments release...\n" );

		// Get latest Pygments version from PyPI
		$pypiData = file_get_contents( 'https://pypi.python.org/pypi/Pygments/json' );
		if ( $pypiData === false ) {
			$this->fatalError( 'Failed to fetch data from PyPI' );
		}

		$data = json_decode( $pypiData, true );
		if ( !$data ) {
			$this->fatalError( 'Failed to parse PyPI response' );
		}

		$latestVersion = $data['info']['version'];
		$url = null;
		$digest = null;

		// Find suitable package
		foreach ( $data['releases'][$latestVersion] as $release ) {
			if ( $release['packagetype'] === 'bdist_wheel' &&
				strpos( $release['python_version'], 'py3' ) !== false ) {
				$url = $release['url'];
				$digest = $release['digests']['sha256'];
				break;
			}
		}

		if ( !$url ) {
			$this->fatalError( 'No suitable package found.' );
		}

		$this->output( sprintf( "Retrieving version %s (%s)...\n", $latestVersion, $url ) );

		// Download package
		$packageData = file_get_contents( $url );
		if ( $packageData === false ) {
			$this->fatalError( 'Failed to download package' );
		}

		$this->output( "Verifying...\n" );
		if ( hash( 'sha256', $packageData ) !== $digest ) {
			$this->fatalError( 'Checksum mismatch!' );
		}

		$this->output( "Creating executable ZIP bundle...\n" );

		// Create temporary file for ZIP operations
		$tempFile = tempnam( sys_get_temp_dir(), 'pygmentize' );
		file_put_contents( $tempFile, $packageData );

		// Open ZIP and add launcher
		$zip = new ZipArchive();
		if ( $zip->open( $tempFile ) !== true ) {
			unlink( $tempFile );
			$this->fatalError( 'Failed to open ZIP file' );
		}

		$zip->addFromString( '__main__.py', $pygmentizeLauncher );
		$zip->close();

		// Read modified ZIP data
		$finalData = file_get_contents( $tempFile );
		unlink( $tempFile );

		// Write executable
		$pygmentsDir = __DIR__ . '/../pygments';
		$executablePath = $pygmentsDir . '/pygmentize';

		$success = file_put_contents( $executablePath, "#!/usr/bin/env python3\n" . $finalData );
		if ( $success === false ) {
			$this->fatalError( 'Failed to write executable' );
		}

		// Make file executable
		chmod( $executablePath, 0755 );

		// Write VERSION file
		file_put_contents( $pygmentsDir . '/VERSION', $latestVersion . "\n" );

		// Write AUTHORS file
		$authorsUrl = 'https://raw.githubusercontent.com/pygments/pygments/refs/tags/' .
			$latestVersion . '/AUTHORS';
		$authorsText = file_get_contents( $authorsUrl );
		if ( $authorsText !== false ) {
			file_put_contents( $pygmentsDir . '/AUTHORS', $authorsText );
		}

		$this->output( sprintf( "Done. Wrote %d bytes to %s\n", strlen( $finalData ), $executablePath ) );

		$this->createChild( UpdateCSS::class )->execute();
		$this->createChild( UpdateLexerList::class )->execute();
	}
}

// @codeCoverageIgnoreStart
$maintClass = CreatePygmentizeBundle::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
