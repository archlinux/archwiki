<?php

namespace LocalisationUpdate;

use FormatJson;
use Language;
use LocalisationUpdate\Fetcher\FetcherFactory;
use LocalisationUpdate\Reader\ReaderFactory;
use Maintenance;

$IP = strval( getenv( 'MW_INSTALL_PATH' ) ) !== ''
	? getenv( 'MW_INSTALL_PATH' )
	: realpath( __DIR__ . '/../../' );

require "$IP/maintenance/Maintenance.php";

class Update extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Fetches translation updates to MediaWiki core, skins and extensions.' );
		$this->addOption(
			'repoid',
			'Fetch translations from repositories identified by this',
			false, /*required*/
			true /*has arg*/
		);

		$this->requireExtension( 'LocalisationUpdate' );
	}

	public function execute() {
		// Prevent the script from timing out
		set_time_limit( 0 );
		ini_set( 'max_execution_time', '0' );
		ini_set( 'memory_limit', '-1' );

		global $IP;
		global $wgLocalisationUpdateRepositories;
		global $wgLocalisationUpdateRepository;

		$dir = LocalisationUpdate::getDirectory();
		if ( !$dir ) {
			$this->fatalError( 'No cache directory configured' );
		}

		$lc = Language::getLocalisationCache();
		$messagesDirs = $lc->getMessagesDirs();

		$finder = new Finder( $messagesDirs, $IP );
		$readerFactory = new ReaderFactory();
		$fetcherFactory = new FetcherFactory();

		$repoid = $this->getOption( 'repoid', $wgLocalisationUpdateRepository );
		if ( !isset( $wgLocalisationUpdateRepositories[$repoid] ) ) {
			$known = implode( ', ', array_keys( $wgLocalisationUpdateRepositories ) );
			$this->fatalError( "Unknown repoid $repoid; known: $known" );
		}
		$repos = $wgLocalisationUpdateRepositories[$repoid];

		// output and error methods are protected, hence we add logInfo and logError
		// public methods, that hopefully won't conflict in the future with the base class.
		$logger = $this;

		// Do it ;)
		$updater = new Updater();
		$updatedMessages = $updater->execute(
			$finder,
			$readerFactory,
			$fetcherFactory,
			$repos,
			$logger
		);

		// Store it ;)
		$count = array_sum( array_map( 'count', $updatedMessages ) );
		if ( !$count ) {
			$this->output( "Found no new translations\n" );
			return;
		}

		foreach ( $updatedMessages as $language => $messages ) {
			$filename = "$dir/" . LocalisationUpdate::getFilename( $language );
			file_put_contents( $filename, FormatJson::encode( $messages, true ) );
		}
		$this->output( "Saved $count new translations\n" );
	}

	/**
	 * @param string $msg
	 */
	public function logInfo( $msg ) {
		$this->output( $msg . "\n" );
	}

	/**
	 * @param string $msg
	 */
	public function logError( $msg ) {
		$this->error( $msg );
	}
}

$maintClass = Update::class;
require_once RUN_MAINTENANCE_IF_MAIN;
