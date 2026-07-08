<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\LanguageLibrary
 * @group Lua
 * @group LuaSandbox
 */
class LanguageLibrarySandboxTest extends LanguageLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
