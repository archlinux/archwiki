<?php

// @codeCoverageIgnoreStart
require_once __DIR__ . '/../../../maintenance/Maintenance.php';
// @codeCoverageIgnoreEnd
use MediaWiki\Json\FormatJson;
use MediaWiki\Maintenance\Maintenance;

class SortTexutil extends Maintenance {

	public function execute() {
		$jsonFilePath = './src/WikiTexVC/texutil.json';

		$jsonContent = json_decode( file_get_contents( $jsonFilePath ), true );

		if ( $jsonContent === null ) {
			$this->fatalError( "Failed to decode texutil.json. Please check the file format.\n" );
		}

		foreach ( $jsonContent as $key => $value ) {
			// Sort the entry alphabetically
			ksort( $jsonContent["$key"] );
		}
		// Sort the entire file
		ksort( $jsonContent );
		file_put_contents(
			$jsonFilePath,
			FormatJson::encode( $jsonContent, "\t", FormatJson::ALL_OK ) . "\n"
		);
		$this->output( "texutil.json successfully sorted.\n" );
	}

}
// @codeCoverageIgnoreStart
$maintClass = SortTexutil::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
