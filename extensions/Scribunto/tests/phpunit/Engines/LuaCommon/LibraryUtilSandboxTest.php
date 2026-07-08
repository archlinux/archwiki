<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @coversNothing
 * @group Lua
 * @group LuaSandbox
 */
class LibraryUtilSandboxTest extends LibraryUtilTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
