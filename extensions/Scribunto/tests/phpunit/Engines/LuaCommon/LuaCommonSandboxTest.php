<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\ScribuntoEngineBase
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaStandalone\LuaStandaloneEngine
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaSandbox\LuaSandboxEngine
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaInterpreter
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaStandalone\LuaStandaloneInterpreter
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaSandbox\LuaSandboxInterpreter
 * @group Lua
 * @group LuaSandbox
 * @group Database
 */
class LuaCommonSandboxTest extends LuaCommonTestBase {
	protected function getEngineName(): string {
		return 'LuaSandbox';
	}
}
