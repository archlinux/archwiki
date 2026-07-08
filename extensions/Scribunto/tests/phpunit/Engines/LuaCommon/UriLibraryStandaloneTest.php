<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\UriLibrary
 * @group Lua
 * @group LuaStandalone
 * @group Standalone
 * @group Database
 */
class UriLibraryStandaloneTest extends UriLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}
}
