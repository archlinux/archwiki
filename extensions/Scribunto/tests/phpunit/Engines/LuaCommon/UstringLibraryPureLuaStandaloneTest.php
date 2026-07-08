<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @coversNothing
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 */
class UstringLibraryPureLuaStandaloneTest extends UstringLibraryPureLuaTestBase {
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}
}
