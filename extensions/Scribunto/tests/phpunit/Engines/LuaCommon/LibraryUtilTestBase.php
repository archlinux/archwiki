<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @coversNothing
 */
abstract class LibraryUtilTestBase extends LuaEngineUnitTestBase {
	/** @inheritDoc */
	protected static $moduleName = 'LibraryUtilTests';

	protected function getTestModules() {
		return parent::getTestModules() + [
			'LibraryUtilTests' => __DIR__ . '/LibraryUtilTests.lua',
		];
	}
}
