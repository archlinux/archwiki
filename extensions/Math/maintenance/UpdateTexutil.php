<?php

require_once __DIR__ . '/../../../maintenance/Maintenance.php';
use MediaWiki\Maintenance\Maintenance;

class UpdateTexutil extends Maintenance {

	private const LEGACY_CONCEPTS = [ // Implemented elements have [something, true] for custom parsing
		'!' => [ "1, 0, TEXCLASS.CLOSE, null" ], // exclamation mark
		'!=' => [ " exports.MO.BIN4" ],
		'#' => [ " exports.MO.ORD" ],
		'$' => [ " exports.MO.ORD" ],
		'%' => [ " [3, 3, MmlNode_js_1.TEXCLASS.ORD], null]" ],
		'&&' => [ " exports.MO.BIN4" ],
		'' => [ " exports.MO.ORD" ],
		'*' => [ " exports.MO.BIN3" ],
		'**' => [ " OPDEF(1\"], 1)" ],
		'*=' => [ " exports.MO.BIN4" ],
		'+' => [ " exports.MO.BIN4" ],
		'+=' => [ " exports.MO.BIN4" ],
		',' => [ " [0, 3], MmlNode_js_1.TEXCLASS.PUNCT\"]," .
			"{ linebreakstyle=> [\" 'after'\"], separator=> [\" true }]", true ],
		'-' => [ " exports.MO.BIN4" ],
		'-=' => [ " exports.MO.BIN4" ],
		'->' => [ " exports.MO.BIN5" ],
		'.' => [ " [0, 3], MmlNode_js_1.TEXCLASS.PUNCT\"], { separator=> [ true }]" ],
		':' => [ " [1, 2], MmlNode_js_1.TEXCLASS.REL\"], null]" ],
		'/' => [ " exports.MO.ORD11", true ],
		'//' => [ " OPDEF(1\"], 1)" ],
		'/=' => [ " exports.MO.BIN4" ],
		'=>' => [ " [1, 2], MmlNode_js_1.TEXCLASS.REL\"], null]" ],
		'=>=' => [ " exports.MO.BIN4" ],
		';' => [ " [0, 3], MmlNode_js_1.TEXCLASS.PUNCT]," .
			"{ linebreakstyle=> ['after'], separator=> [ true }]", true ],
		'<' => [ " exports.MO.REL", true ],
		'<=' => [ " exports.MO.BIN5" ],
		'<>' => [ " OPDEF(1, 1)" ],
		'=' => [ " exports.MO.REL" ],
		'==' => [ " exports.MO.BIN4" ],
		'>' => [ " exports.MO.REL", true ],
		'>=' => [ " exports.MO.BIN5" ],
		'?' => [ " [1, 1], MmlNode_js_1.TEXCLASS.CLOSE], null]" ],
		'@' => [ " exports.MO.ORD11" ],
		'\\' => [ " exports.MO.ORD", true ],
		'^' => [ " exports.MO.ORD11" ],
		'_' => [ " exports.MO.ORD11" ],
		'|' => [ " [2, 2], MmlNode_js_1.TEXCLASS.ORD]," .
			"{ fence=> [\"true\"], stretchy=> [\"true\"], symmetric=> [\" true }]" ],
		'||' => [ " [2, 2], MmlNode_js_1.TEXCLASS.ORD]," .
			"{ fence=> [\"true\"], stretchy=> [\"true\"], symmetric=> [\" true }]" ],
		'|||' => [ " [2, 2], MmlNode_js_1.TEXCLASS.ORD]," .
			"{ fence=> [\"true\"], stretchy=> [\"true\"], symmetric=> [\" true }]" ]
	];

	public function execute() {
		$jsonFilePath = './src/WikiTexVC/texutil.json';

		$jsonContent = json_decode( file_get_contents( $jsonFilePath ), true );

		if ( $jsonContent === null ) {
			die( "Failed to decode texutil.json. Please check the file format.\n" );
		}

		foreach ( self::LEGACY_CONCEPTS as $key => $value ) {
			if ( isset( $jsonContent["$key"] ) ) {
				// Preserve existing attributes and only add or update the identifier
				$jsonContent["$key"]['operator_infix'] = $value;
			} else {
				// Create a new entry if it doesn't exist
				$jsonContent["$key"] = [
					'operator_infix' => $value
				];
			}

			// Sort the entry alphabetically
			ksort( $jsonContent["$key"] );
		}
		// Sort the entire file
		ksort( $jsonContent );
		$jsonString = json_encode( $jsonContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		$jsonStringWithTabs = preg_replace_callback( '/^( +)/m', static function ( $matches ) {
				// Convert spaces to tabs (assuming 4 spaces per tab level)
				return str_repeat( "\t", strlen( $matches[1] ) / 4 );
		}, $jsonString ) . "\n";
		// prevent eslint error  Unnecessary escape character: \/  no-useless-escape
		$jsonStringWithTabs = str_replace( '\/', '/', $jsonStringWithTabs );

		file_put_contents( $jsonFilePath, $jsonStringWithTabs );

		echo "texutil.json successfully updated.\n";
	}
}
$maintClass = UpdateTexutil::class;
require_once RUN_MAINTENANCE_IF_MAIN;
