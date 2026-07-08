<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\UriLibrary
 * @group Lua
 * @group LuaSandbox
 * @group Database
 */
class UriLibrarySandboxTest extends UriLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
