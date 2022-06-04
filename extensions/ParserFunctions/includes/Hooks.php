<?php

namespace MediaWiki\Extension\ParserFunctions;

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
		$parser->setFunctionHook( 'if', [ ParserFunctions::class, 'if' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'ifeq', [ ParserFunctions::class, 'ifeq' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'switch', [ ParserFunctions::class, 'switch' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'ifexist', [ ParserFunctions::class, 'ifexist' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'ifexpr', [ ParserFunctions::class, 'ifexpr' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'iferror', [ ParserFunctions::class, 'iferror' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'time', [ ParserFunctions::class, 'time' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'timel', [ ParserFunctions::class, 'localTime' ], Parser::SFH_OBJECT_ARGS );

		$parser->setFunctionHook( 'expr', [ ParserFunctions::class, 'expr' ] );
		$parser->setFunctionHook( 'rel2abs', [ ParserFunctions::class, 'rel2abs' ] );
		$parser->setFunctionHook( 'titleparts', [ ParserFunctions::class, 'titleparts' ] );

		// String Functions: enable if configured
		if ( $this->config->get( 'PFEnableStringFunctions' ) ) {
			$parser->setFunctionHook( 'len', [ ParserFunctions::class, 'runLen' ] );
			$parser->setFunctionHook( 'pos', [ ParserFunctions::class, 'runPos' ] );
			$parser->setFunctionHook( 'rpos', [ ParserFunctions::class, 'runRPos' ] );
			$parser->setFunctionHook( 'sub', [ ParserFunctions::class, 'runSub' ] );
			$parser->setFunctionHook( 'count', [ ParserFunctions::class, 'runCount' ] );
			$parser->setFunctionHook( 'replace', [ ParserFunctions::class, 'runReplace' ] );
			$parser->setFunctionHook( 'explode', [ ParserFunctions::class, 'runExplode' ] );
			$parser->setFunctionHook( 'urldecode', [ ParserFunctions::class, 'runUrlDecode' ] );
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
