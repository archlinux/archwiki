<?php
namespace MediaWiki\Extension\Math\WikiTexVC\MMLmappings;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLutil;

/**
 * Based on OperatorDictionary.js in MML3
 * Only importing infix atm
 * Singleton
 *
 * Some of the entries are commented since they parse to mi elements, values are not used atm.
 */
class OperatorDictionary {

	private const INFIX = [ // Implemented elements have [something, true] for custom parsing
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

	private function __construct() {
		// Just an empty private constructor, for singleton pattern
	}

	public static function getOperatorByKey( string $key ) {
		$key = MMLutil::uc2xNotation( $key );
		return MMLutil::getMappingByKey( $key, self::INFIX );
	}

}
