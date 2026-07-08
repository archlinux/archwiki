<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaStandalone;

use MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon\LuaStandaloneUnitTestBase;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaStandalone\LuaStandaloneEngine
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 */
class StandaloneTest extends LuaStandaloneUnitTestBase {
	/** @inheritDoc */
	protected static $moduleName = 'StandaloneTests';

	protected function setUp(): void {
		parent::setUp();

		$interpreter = $this->getEngine()->getInterpreter();
		$func = $interpreter->wrapPhpFunction( static function ( $v ) {
			return [ preg_replace( '/\s+/', ' ', trim( var_export( $v, 1 ) ) ) ];
		} );
		$interpreter->callFunction(
			$interpreter->loadString( 'mw.var_export = ...', 'fortest' ), $func
		);
	}

	protected function getTestModules() {
		return parent::getTestModules() + [
			'StandaloneTests' => __DIR__ . '/StandaloneTests.lua',
		];
	}
}
