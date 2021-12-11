<?php

namespace MediaWiki\Extensions\ParserFunctions;

use Config;
use Parser;

class Hooks implements
	\MediaWiki\Hook\ParserFirstCallInitHook,
	\MediaWiki\Hook\ParserTestGlobalsHook
{

	/** @var Config */
	private $config;

	/**
	 * @param Config $config
	 */
	public function __construct(
		Config $config
	) {
		$this->config = $config;
	}

	/**
	 * Enables string functions during parser tests.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserTestGlobals
	 *
	 * @param array &$globals
	 */
	public function onParserTestGlobals( &$globals ) {
		$globals['wgPFEnableStringFunctions'] = true;
	}

	/**
	 * Registers our parser functions with a fresh parser.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 *
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		// These functions accept DOM-style arguments
		$class = ParserFunctions::class;
		$parser->setFunctionHook( 'if', "$class::if", Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'ifeq', "$class::ifeq", Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'switch', "$class::switch", Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'ifexist', "$class::ifexist", Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'ifexpr', "$class::ifexpr", Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'iferror', "$class::iferror", Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'time', "$class::time", Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'timel', "$class::localTime", Parser::SFH_OBJECT_ARGS );

		$parser->setFunctionHook( 'expr', "$class::expr" );
		$parser->setFunctionHook( 'rel2abs', "$class::rel2abs" );
		$parser->setFunctionHook( 'titleparts', "$class::titleparts" );

		// String Functions: enable if configured
		if ( $this->config->get( 'PFEnableStringFunctions' ) ) {
			$parser->setFunctionHook( 'len',       "$class::runLen" );
			$parser->setFunctionHook( 'pos',       "$class::runPos" );
			$parser->setFunctionHook( 'rpos',      "$class::runRPos" );
			$parser->setFunctionHook( 'sub',       "$class::runSub" );
			$parser->setFunctionHook( 'count',     "$class::runCount" );
			$parser->setFunctionHook( 'replace',   "$class::runReplace" );
			$parser->setFunctionHook( 'explode',   "$class::runExplode" );
			$parser->setFunctionHook( 'urldecode', "$class::runUrlDecode" );
		}
	}

	/**
	 * Registers ParserFunctions' lua function with Scribunto
	 *
	 * @see https://www.mediawiki.org/wiki/Extension:Scribunto/ScribuntoExternalLibraries
	 *
	 * @param string $engine
	 * @param string[] &$extraLibraries
	 */
	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ) {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.ext.ParserFunctions'] = LuaLibrary::class;
		}
	}
}
