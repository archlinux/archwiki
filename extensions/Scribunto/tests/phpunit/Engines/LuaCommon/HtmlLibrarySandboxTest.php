<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\HtmlLibrary
 * @group Lua
 * @group LuaSandbox
 */
class HtmlLibrarySandboxTest extends HtmlLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
