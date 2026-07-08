<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\TitleLibrary
 * @group Lua
 * @group LuaSandbox
 * @group Database
 */
class TitleLibrarySandboxTest extends TitleLibraryTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
