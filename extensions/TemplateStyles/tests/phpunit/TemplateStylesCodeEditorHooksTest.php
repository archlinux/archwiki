<?php

use MediaWiki\Extension\TemplateStyles\CodeEditorHooks;
use MediaWiki\Title\Title;

/**
 * @group TemplateStyles
 * @covers \MediaWiki\Extension\TemplateStyles\CodeEditorHooks
 */
class TemplateStylesCodeEditorHooksTest extends MediaWikiLangTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'CodeEditor' );
	}

	/**
	 * @dataProvider provideOnCodeEditorGetPageLanguage
	 */
	public function testOnCodeEditorGetPageLanguage( $useCodeEditor, $model, $expect ) {
		$this->overrideConfigValue( 'TemplateStylesUseCodeEditor', $useCodeEditor );

		$title = Title::makeTitle( NS_TEMPLATE, 'Test.css' );
		$lang = 'unchanged';
		$ret = ( new CodeEditorHooks )->onCodeEditorGetPageLanguage(
			$title, $lang, $model, 'text/x-whatever'
		);
		$this->assertSame( !$expect, $ret );
		$this->assertSame( $expect ? 'css' : 'unchanged', $lang );
	}

	public static function provideOnCodeEditorGetPageLanguage() {
		return [
			[ true, 'wikitext', false ],
			[ true, 'css', false ],
			[ true, 'sanitized-css', true ],
			[ false, 'sanitized-css', false ],
		];
	}

}
