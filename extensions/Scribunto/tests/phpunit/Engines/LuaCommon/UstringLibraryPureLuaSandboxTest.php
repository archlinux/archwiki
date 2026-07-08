<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @coversNothing
 * @group Lua
 * @group LuaSandbox
 */
class UstringLibraryPureLuaSandboxTest extends UstringLibraryPureLuaTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
