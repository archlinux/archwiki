<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\SvgLibrary
 */
class SvgLibraryTest extends LuaEngineUnitTestBase {
	/** @inheritDoc */
	protected static $moduleName = 'SvgLibraryTests';

	protected function getTestModules() {
		return parent::getTestModules() + [
			'SvgLibraryTests' => __DIR__ . '/SvgLibraryTests.lua',
		];
	}
}
