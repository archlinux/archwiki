<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @coversNothing
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 */
class LibraryUtilStandaloneTest extends LibraryUtilTestBase {
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}
}
