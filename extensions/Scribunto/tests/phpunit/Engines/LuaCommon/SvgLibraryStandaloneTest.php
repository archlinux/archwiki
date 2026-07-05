<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\SvgLibrary
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 */
class SvgLibraryStandaloneTest extends SvgLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}
}
