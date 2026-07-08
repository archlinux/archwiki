<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\HtmlLibrary
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 */
class HtmlLibraryStandaloneTest extends HtmlLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}
}
