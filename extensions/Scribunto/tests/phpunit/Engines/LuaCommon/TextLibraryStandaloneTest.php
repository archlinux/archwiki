<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\TextLibrary
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 */
class TextLibraryStandaloneTest extends TextLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}
}
