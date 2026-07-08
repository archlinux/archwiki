<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\TextLibrary
 * @group Lua
 * @group LuaSandbox
 */
class TextLibrarySandboxTest extends TextLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
