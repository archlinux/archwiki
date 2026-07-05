<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 */
abstract class LuaStandaloneUnitTestBase extends LuaEngineUnitTestBase {
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}
}
