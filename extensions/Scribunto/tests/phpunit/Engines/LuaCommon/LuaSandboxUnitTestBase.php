<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @group Lua
 * @group LuaSandbox
 */
abstract class LuaSandboxUnitTestBase extends LuaEngineUnitTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
