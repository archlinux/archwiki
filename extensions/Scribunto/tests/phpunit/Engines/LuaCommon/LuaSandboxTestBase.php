<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @group Lua
 * @group LuaSandbox
 */
abstract class LuaSandboxTestBase extends LuaEngineTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
