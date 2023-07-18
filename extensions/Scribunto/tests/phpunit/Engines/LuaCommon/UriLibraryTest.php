<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\UriLibrary
 */
class UriLibraryTest extends LuaEngineTestBase {
	/** @inheritDoc */
	protected static $moduleName = 'UriLibraryTests';

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgServer' => '//wiki.local',
			'wgCanonicalServer' => 'http://wiki.local',
			'wgUsePathInfo' => true,
			'wgActionPaths' => [],
			'wgScript' => '/w/index.php',
			'wgScriptPath' => '/w',
			'wgArticlePath' => '/wiki/$1',
			'wgFragmentMode' => [ 'legacy', 'html5' ],
		] );
	}

	protected function getTestModules() {
		return parent::getTestModules() + [
			'UriLibraryTests' => __DIR__ . '/UriLibraryTests.lua',
		];
	}
}
