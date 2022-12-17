<?php

use MediaWiki\Extension\Scribunto\Engines\LuaSandbox\LuaSandboxEngine;
use MediaWiki\Extension\Scribunto\Engines\LuaSandbox\LuaSandboxInterpreter;

if ( !wfIsCLI() ) {
	exit;
}

require_once __DIR__ . '/../LuaCommon/LuaInterpreterTest.php';

/**
 * @group Lua
 * @group LuaSandbox
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaSandbox\LuaSandboxInterpreter
 */
class LuaSandboxInterpreterTest extends Scribunto_LuaInterpreterTest {
	/** @var array */
	public $stdOpts = [
		'memoryLimit' => 50000000,
		'cpuLimit' => 30,
	];

	protected function newInterpreter( $opts = [] ) {
		$opts += $this->stdOpts;
		$engine = new LuaSandboxEngine( $this->stdOpts );
		return new LuaSandboxInterpreter( $engine, $opts );
	}

	public function testGetMemoryUsage() {
		$interpreter = $this->newInterpreter();
		$chunk = $interpreter->loadString( 's = string.rep("x", 1000000)', 'mem' );
		$interpreter->callFunction( $chunk );
		$mem = $interpreter->getPeakMemoryUsage();
		$this->assertGreaterThan( 1000000, $mem, 'memory usage' );
		$this->assertLessThan( 10000000, $mem, 'memory usage' );
	}
}
