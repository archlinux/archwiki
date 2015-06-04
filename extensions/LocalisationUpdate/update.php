<?php

$IP = strval( getenv( 'MW_INSTALL_PATH' ) ) !== ''
	? getenv( 'MW_INSTALL_PATH' )
	: realpath( dirname( __FILE__ ) . "/../../" );
// Can use __DIR__ once we drop support for MW 1.19

require "$IP/maintenance/Maintenance.php";

class LU extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Fetches translation updates to MediaWiki core, skins and extensions.';
		$this->addOption(
			'repoid',
			'Fetch translations from repositories identified by this',
			false, /*required*/
			true /*has arg*/
		);
	}

	public function execute() {
		// Prevent the script from timing out
		set_time_limit( 0 );
		ini_set( "max_execution_time", 0 );
		ini_set( 'memory_limit', -1 );

		global $wgExtensionMessagesFiles, $IP;
		global $wgLocalisationUpdateRepositories;
		global $wgLocalisationUpdateRepository;

		$dir = LocalisationUpdate::getDirectory();
		if ( !$dir ) {
			$this->error( "No cache directory configured", true );
			return;
		}

		$lc = Language::getLocalisationCache();
		if ( is_callable( array( $lc, 'getMessagesDirs' ) ) ) { // Introduced in 1.25
			$messagesDirs = $lc->getMessagesDirs();
		} else {
			global $wgMessagesDirs;
			$messagesDirs = $wgMessagesDirs;
		}

		$finder = new LU_Finder( $wgExtensionMessagesFiles, $messagesDirs, $IP );
		$readerFactory = new LU_ReaderFactory();
		$fetcherFactory = new LU_FetcherFactory();

		$repoid = $this->getOption( 'repoid', $wgLocalisationUpdateRepository );
		if ( !isset( $wgLocalisationUpdateRepositories[$repoid] ) ) {
			$known = implode( ', ', array_keys( $wgLocalisationUpdateRepositories ) );
			$this->error( "Unknown repoid $repoid; known: $known", true );
			return;
		}
		$repos = $wgLocalisationUpdateRepositories[$repoid];

		// Do it ;)
		$updater = new LU_Updater();
		$updatedMessages = $updater->execute(
			$finder,
			$readerFactory,
			$fetcherFactory,
			$repos
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
}

$maintClass = 'LU';
require_once RUN_MAINTENANCE_IF_MAIN;
