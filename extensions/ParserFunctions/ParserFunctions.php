<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is a MediaWiki extension, it is not a valid entry point' );
}

/**
 * CONFIGURATION
 * These variables may be overridden in LocalSettings.php after you include the
 * extension file.
 */

/**
 * Defines the maximum length of a string that string functions are allowed to operate on
 * Prevention against denial of service by string function abuses.
 */
$wgPFStringLengthLimit = 1000;

/**
 * Enable string functions.
 *
 * Set this to true if you want your users to be able to implement their own
 * parsers in the ugliest, most inefficient programming language known to man:
 * MediaWiki wikitext with ParserFunctions.
 *
 * WARNING: enabling this may have an adverse impact on the sanity of your users.
 * An alternative, saner solution for embedding complex text processing in
 * MediaWiki templates can be found at: http://www.mediawiki.org/wiki/Extension:Lua
 */
$wgPFEnableStringFunctions = false;

/** REGISTRATION */
$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => 'ParserFunctions',
	'version' => '1.4.1',
	'url' => 'https://www.mediawiki.org/wiki/Extension:ParserFunctions',
	'author' => array( 'Tim Starling', 'Robert Rohde', 'Ross McClure', 'Juraj Simlovic' ),
	'descriptionmsg' => 'pfunc_desc',
);

$wgAutoloadClasses['ExtParserFunctions'] = dirname( __FILE__ ) . '/ParserFunctions_body.php';
$wgAutoloadClasses['ExprParser'] = dirname( __FILE__ ) . '/Expr.php';

$wgExtensionMessagesFiles['ParserFunctions'] = dirname( __FILE__ ) . '/ParserFunctions.i18n.php';
$wgExtensionMessagesFiles['ParserFunctionsMagic'] = dirname( __FILE__ ) . '/ParserFunctions.i18n.magic.php';

$wgParserTestFiles[] = dirname( __FILE__ ) . "/funcsParserTests.txt";
$wgParserTestFiles[] = dirname( __FILE__ ) . "/stringFunctionTests.txt";

$wgHooks['ParserFirstCallInit'][] = 'wfRegisterParserFunctions';

/**
 * @param $parser Parser
 * @return bool
 */
function wfRegisterParserFunctions( $parser ) {
	global $wgPFEnableStringFunctions;

	// These functions accept DOM-style arguments
	$parser->setFunctionHook( 'if', 'ExtParserFunctions::ifObj', SFH_OBJECT_ARGS );
	$parser->setFunctionHook( 'ifeq', 'ExtParserFunctions::ifeqObj', SFH_OBJECT_ARGS );
	$parser->setFunctionHook( 'switch', 'ExtParserFunctions::switchObj', SFH_OBJECT_ARGS );
	$parser->setFunctionHook( 'ifexist', 'ExtParserFunctions::ifexistObj', SFH_OBJECT_ARGS );
	$parser->setFunctionHook( 'ifexpr', 'ExtParserFunctions::ifexprObj', SFH_OBJECT_ARGS );
	$parser->setFunctionHook( 'iferror', 'ExtParserFunctions::iferrorObj', SFH_OBJECT_ARGS );

	$parser->setFunctionHook( 'expr', 'ExtParserFunctions::expr' );
	$parser->setFunctionHook( 'time', 'ExtParserFunctions::time' );
	$parser->setFunctionHook( 'timel', 'ExtParserFunctions::localTime' );
	$parser->setFunctionHook( 'rel2abs', 'ExtParserFunctions::rel2abs' );
	$parser->setFunctionHook( 'titleparts', 'ExtParserFunctions::titleparts' );

	// String Functions
	if ( $wgPFEnableStringFunctions ) {
		$parser->setFunctionHook( 'len',       'ExtParserFunctions::runLen'       );
		$parser->setFunctionHook( 'pos',       'ExtParserFunctions::runPos'       );
		$parser->setFunctionHook( 'rpos',      'ExtParserFunctions::runRPos'      );
		$parser->setFunctionHook( 'sub',       'ExtParserFunctions::runSub'       );
		$parser->setFunctionHook( 'count',     'ExtParserFunctions::runCount'     );
		$parser->setFunctionHook( 'replace',   'ExtParserFunctions::runReplace'   );
		$parser->setFunctionHook( 'explode',   'ExtParserFunctions::runExplode'   );
		$parser->setFunctionHook( 'urldecode', 'ExtParserFunctions::runUrlDecode' );
	}

	return true;
}
