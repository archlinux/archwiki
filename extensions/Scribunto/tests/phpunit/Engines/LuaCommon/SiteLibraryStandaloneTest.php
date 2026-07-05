<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\SiteLibrary
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 */
class SiteLibraryStandaloneTest extends SiteLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}
}
