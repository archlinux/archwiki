<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\SiteLibrary
 */
abstract class SiteLibraryTestBase extends LuaEngineUnitTestBase {
	/** @inheritDoc */
	protected static $moduleName = 'SiteLibraryTests';

	protected function getTestModules() {
		return parent::getTestModules() + [
			'SiteLibraryTests' => __DIR__ . '/SiteLibraryTests.lua',
		];
	}
}
