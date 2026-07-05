<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\MessageLibrary
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 */
class MessageLibraryStandaloneTest extends MessageLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}
}
