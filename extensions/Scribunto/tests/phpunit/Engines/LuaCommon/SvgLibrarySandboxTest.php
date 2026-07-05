<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\SvgLibrary
 * @group Lua
 * @group LuaSandbox
 */
class SvgLibrarySandboxTest extends SvgLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
