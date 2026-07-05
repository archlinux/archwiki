<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\LanguageLibrary
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 */
class LanguageLibraryStandaloneTest extends LanguageLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}
}
