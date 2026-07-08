<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\SiteLibrary
 * @group Lua
 * @group LuaSandbox
 */
class SiteLibrarySandboxTest extends SiteLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
