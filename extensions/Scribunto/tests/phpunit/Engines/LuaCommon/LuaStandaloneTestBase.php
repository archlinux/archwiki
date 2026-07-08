<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 */
abstract class LuaStandaloneTestBase extends LuaEngineTestBase {
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}
}
