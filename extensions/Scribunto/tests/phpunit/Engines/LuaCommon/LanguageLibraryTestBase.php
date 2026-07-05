<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\Scribunto\Engines\LuaCommon\LanguageLibrary
 */
abstract class LanguageLibraryTestBase extends LuaEngineUnitTestBase {
	/** @inheritDoc */
	protected static $moduleName = 'LanguageLibraryTests';

	protected function setUp(): void {
		parent::setUp();

		// Skip certain tests if something isn't providing translated language names
		// (bug 67343)
		if ( MediaWikiServices::getInstance()->getLanguageNameUtils()->getLanguageName( 'en', 'fr' ) === 'English' ) {
			$msg = 'Language name translations are unavailable; ' .
				'install Extension:CLDR or something similar';
			$this->skipTests += [
				'fetchLanguageName (en,ru)' => $msg,
				'fetchLanguageName (ru,en)' => $msg,
				'fetchLanguageNames (de)' => $msg,
				'fetchLanguageNames ([[bogus]])' => $msg,
			];
		}
	}

	protected function getTestModules() {
		return parent::getTestModules() + [
			'LanguageLibraryTests' => __DIR__ . '/LanguageLibraryTests.lua',
		];
	}

	public function testFormatDateTTLs() {
		$engine = $this->getEngine();
		$pp = $engine->getParser()->getPreprocessor();
		$po = $engine->getParser()->getOutput();

		$ttl = null;
		MediaWikiServices::getInstance()->getContentLanguage()
			->sprintfDate( 's', '20130101000000', null, $ttl );
		if ( $ttl === null ) {
			$this->markTestSkipped( "Language::sprintfDate does not set a TTL" );
		}

		// sprintfDate has its own unit tests for making sure its output is right,
		// so all we need to test here is we get TTLs when we're supposed to
		$this->extraModules['Module:FormatDate'] = '
		local p = {}
		function p.formatCurrentDate()
			return mw.getContentLanguage():formatDate( "s" )
		end
		function p.formatSpecificDate()
			return mw.getContentLanguage():formatDate( "s", "20130101000000" )
		end
		return p
		';

		$title = Title::makeTitle( NS_MODULE, 'FormatDate' );
		$module = $engine->fetchModuleFromParser( $title );

		$frame = $pp->newFrame();
		$module->invoke( 'formatSpecificDate', $frame );
		$this->assertTtl( null, $po,
			'TTL must not be set when lang:formatDate is called with a specific date' );

		$frame = $pp->newFrame();
		$module->invoke( 'formatCurrentDate', $frame );
		$this->assertTtl( 1, $po,
			'TTL must be equal to 1 second when lang:formatDate( \'s\' ) is called' );
		$this->assertSame( 'Module:FormatDate (formatDate)',
			$po->getCacheExpirySource() );
	}
}
