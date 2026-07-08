<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\HashLibrary
 * @group Lua
 * @group LuaSandbox
 */
class HashLibrarySandboxTest extends HashLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
