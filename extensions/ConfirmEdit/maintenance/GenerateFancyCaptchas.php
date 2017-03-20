<?php
/**
 * Generate captchas using a python script and copy them into storage.
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
 * @author Aaron Schulz
 * @ingroup Maintenance
 */
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__.'/../../..';
}

require_once ( "$IP/maintenance/Maintenance.php" );

/**
 * Maintenance script to change the password of a given user.
 *
 * @ingroup Maintenance
 */
class GenerateFancyCaptchas extends Maintenance {
	public function __construct() {
		parent::__construct();
		// See captcha.py for argument usage
		$this->addOption( "wordlist", 'A list of words', true, true );
		$this->addOption( "font", "The font to use", true, true );
		$this->addOption( "font-size", "The font size ", false, true );
		$this->addOption( "blacklist", "A blacklist of words that should not be used", false, true );
		$this->addOption( "fill", "Fill the captcha container to N files", true, true );
		$this->addOption( "verbose", "Show debugging information" );
		$this->mDescription = "Generate new captchas and move them into storage";
	}

	public function execute() {
		global $wgCaptchaSecret, $wgCaptchaDirectoryLevels;

		$instance = ConfirmEditHooks::getInstance();
		if ( !( $instance instanceof FancyCaptcha ) ) {
			$this->error( "\$wgCaptchaClass is not FancyCaptcha.\n", 1 );
		}
		$backend = $instance->getBackend();

		$countAct = $instance->estimateCaptchaCount();
		$this->output( "Estimated number of captchas is $countAct.\n" );

		$countGen = (int)$this->getOption( 'fill' ) - $countAct;
		if ( $countGen <= 0 ) {
			$this->output( "No need to generate anymore captchas.\n" );
			return;
		}

		$tmpDir = wfTempDir() . '/mw-fancycaptcha-' . time() . '-' . wfRandomString( 6 );
		if ( !wfMkdirParents( $tmpDir ) ) {
			$this->error( "Could not create temp directory.\n", 1 );
		}

		$e = null; // exception
		try {
			$cmd = sprintf( "python %s --key %s --output %s --count %s --dirs %s",
				wfEscapeShellArg( __DIR__ . '/../captcha.py' ),
				wfEscapeShellArg( $wgCaptchaSecret ),
				wfEscapeShellArg( $tmpDir ),
				wfEscapeShellArg( $countGen ),
				wfEscapeShellArg( $wgCaptchaDirectoryLevels )
			);
			foreach ( [ 'wordlist', 'font', 'font-size', 'blacklist', 'verbose' ] as $par ) {
				if ( $this->hasOption( $par ) ) {
					$cmd .= " --$par " . wfEscapeShellArg( $this->getOption( $par ) );
				}
			}

			$this->output( "Generating $countGen new captchas...\n" );
			$retVal = 1;
			wfShellExec( $cmd, $retVal, [], [ 'time' => 0 ] );
			if ( $retVal != 0 ) {
				wfRecursiveRemoveDir( $tmpDir );
				$this->error( "Could not run generation script.\n", 1 );
			}

			$flags = FilesystemIterator::SKIP_DOTS;
			$iter = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $tmpDir, $flags ),
				RecursiveIteratorIterator::CHILD_FIRST // include dirs
			);

			$this->output( "Copying the new captchas to storage...\n" );
			foreach ( $iter as $fileInfo ) {
				if ( !$fileInfo->isFile() ) {
					continue;
				}
				list( $salt, $hash ) = $instance->hashFromImageName( $fileInfo->getBasename() );
				$dest = $instance->imagePath( $salt, $hash );
				$backend->prepare( [ 'dir' => dirname( $dest ) ] );
				$status = $backend->quickStore( [
					'src' => $fileInfo->getPathname(),
					'dst' => $dest
				] );
				if ( !$status->isOK() ) {
					$this->error( "Could not save file '{$fileInfo->getPathname()}'.\n" );
				}
			}
		} catch ( Exception $e ) {
			wfRecursiveRemoveDir( $tmpDir );
			throw $e;
		}

		$this->output( "Removing temporary files...\n" );
		wfRecursiveRemoveDir( $tmpDir );
		$this->output( "Done.\n" );
	}
}

$maintClass = "GenerateFancyCaptchas";
require_once ( RUN_MAINTENANCE_IF_MAIN );
