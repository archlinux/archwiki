<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\UstringLibrary
 * @group Lua
 * @group LuaSandbox
 */
class UstringLibrarySandboxTest extends UstringLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
