<?php

namespace MediaWiki\Extension\ParserFunctions;

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError;

class LuaLibrary extends LibraryBase {
	public function register() {
		$lib = [
			'expr' => [ $this, 'expr' ],
		];

		return $this->getEngine()->registerInterface(
			__DIR__ . '/mw.ext.ParserFunctions.lua', $lib, []
		);
	}

	public function expr( $expression = null ) {
		$this->checkType( 'mw.ext.ParserFunctions.expr', 1, $expression, 'string' );
		try {
			$exprParser = new ExprParser();
			return [ $exprParser->doExpression( $expression ) ];
		} catch ( ExprError $e ) {
			throw new LuaError( $e->getMessage() );
		}
	}

}
