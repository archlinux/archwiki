<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\MessageLibrary
 * @group Lua
 * @group LuaSandbox
 */
class MessageLibrarySandboxTest extends MessageLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
