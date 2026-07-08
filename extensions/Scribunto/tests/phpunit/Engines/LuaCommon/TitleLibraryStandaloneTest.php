<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\TitleLibrary
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 * @group Database
 */
class TitleLibraryStandaloneTest extends TitleLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}
}
