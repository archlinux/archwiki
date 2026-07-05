<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\HashLibrary
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 */
class HashLibraryStandaloneTest extends HashLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}
}
