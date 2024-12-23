<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

use MediaWiki\MainConfigNames;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\UriLibrary
 */
class UriLibraryTest extends LuaEngineTestBase {
	/** @inheritDoc */
	protected static $moduleName = 'UriLibraryTests';

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			MainConfigNames::Server => '//wiki.local',
			MainConfigNames::CanonicalServer => 'http://wiki.local',
			MainConfigNames::UsePathInfo => true,
			MainConfigNames::ActionPaths => [],
			MainConfigNames::Script => '/w/index.php',
			MainConfigNames::ScriptPath => '/w',
			MainConfigNames::ArticlePath => '/wiki/$1',
			MainConfigNames::FragmentMode => [ 'legacy', 'html5' ],
		] );
	}

	protected function getTestModules() {
		return parent::getTestModules() + [
			'UriLibraryTests' => __DIR__ . '/UriLibraryTests.lua',
		];
	}
}
