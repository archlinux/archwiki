<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\UstringLibrary
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 */
class UstringLibraryStandaloneTest extends UstringLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}
}
