<?php

use MediaWiki\Content\Content;
use MediaWiki\Content\CssContent;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\TemplateStyles\TemplateStylesContent;
use MediaWiki\MainConfigNames;

/**
 * @group TemplateStyles
 * @covers \MediaWiki\Extension\TemplateStyles\TemplateStylesContent
 */
class TemplateStylesContentTest extends TextContentTest {

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			MainConfigNames::TextModelsToParse => [ 'sanitized-css' ],
			'TemplateStylesMaxStylesheetSize' => 1024000,
		] );
	}

	public function newContent( $text ) {
		return new TemplateStylesContent( $text );
	}

	public static function dataGetParserOutput() {
		return [
			[
				'Template:Test/styles.css',
				'sanitized-css',
				".hello { content: 'world'; color: bogus; }\n\n<ok>\n",
				// phpcs:ignore Generic.Files.LineLength
				"<pre class=\"mw-code mw-css\" dir=\"ltr\">\n.hello { content: 'world'; color: bogus; }\n\n&lt;ok&gt;\n\n</pre>",
				[
					'Warnings' => [
						'Unexpected end of stylesheet in rule at line 4 character 1.',
						'Invalid or unsupported value for property <code>color</code> at line 1 character 35.',
					]
				]
			],
			[
				'Template:Test/styles.css',
				'sanitized-css',
				"/* hello [[world]] */\n",
				"<pre class=\"mw-code mw-css\" dir=\"ltr\">\n/* hello [[world]] */\n\n</pre>",
				[
					'Links' => [
						[ 'World' => 0 ]
					]
				]
			],
		];
	}

	public static function dataPreSaveTransform() {
		return [
			[
				'hello this is ~~~',
				'hello this is ~~~',
			],
			[
				'hello \'\'this\'\' is <nowiki>~~~</nowiki>',
				'hello \'\'this\'\' is <nowiki>~~~</nowiki>',
			],
			[
				" Foo \n ",
				" Foo",
			],
		];
	}

	public static function dataPreloadTransform() {
		return [
			[
				'hello this is ~~~',
				'hello this is ~~~',
			],
			[
				'hello \'\'this\'\' is <noinclude>foo</noinclude><includeonly>bar</includeonly>',
				'hello \'\'this\'\' is <noinclude>foo</noinclude><includeonly>bar</includeonly>',
			],
		];
	}

	public function testGetModel() {
		$content = $this->newContent( 'hello world.' );

		$this->assertEquals( 'sanitized-css', $content->getModel() );
	}

	public function testGetContentHandler() {
		$content = $this->newContent( 'hello world.' );

		$this->assertEquals( 'sanitized-css', $content->getContentHandler()->getModelID() );
	}

	/**
	 * Redirects aren't supported
	 */
	public static function provideUpdateRedirect() {
		// phpcs:disable Generic.Files.LineLength
		return [
			[
				'#REDIRECT [[Someplace]]',
				'#REDIRECT [[Someplace]]',
			],

			// The style supported by CssContent
			[
				'/* #REDIRECT */@import url(//example.org/w/index.php?title=MediaWiki:MonoBook.css&action=raw&ctype=text/css);',
				'/* #REDIRECT */@import url(//example.org/w/index.php?title=MediaWiki:MonoBook.css&action=raw&ctype=text/css);',
			],
		];
		// phpcs:enable
	}

	/**
	 * @dataProvider provideGetRedirectTarget
	 */
	public function testGetRedirectTarget( $title, $text ) {
		$this->overrideConfigValues( [
			MainConfigNames::Server => '//example.org',
			MainConfigNames::ScriptPath => '/w',
			MainConfigNames::Script => '/w/index.php',
		] );
		$content = $this->newContent( $text );
		$target = $content->getRedirectTarget();
		$this->assertEquals( $title, $target ? $target->getPrefixedText() : null );
	}

	public static function provideGetRedirectTarget() {
		// phpcs:disable Generic.Files.LineLength
		return [
			[ null, "/* #REDIRECT */@import url(//example.org/w/index.php?title=MediaWiki:MonoBook.css&action=raw&ctype=text/css);" ],
			[ null, "/* #REDIRECT */@import url(//example.org/w/index.php?title=User:FooBar/common.css&action=raw&ctype=text/css);" ],
			[ null, "/* #REDIRECT */@import url(//example.org/w/index.php?title=Gadget:FooBaz.css&action=raw&ctype=text/css);" ],
			[ null, "@import url(//example.org/w/index.php?title=Gadget:FooBaz.css&action=raw&ctype=text/css);" ],
			[ null, "/* #REDIRECT */@import url(//example.com/w/index.php?title=Gadget:FooBaz.css&action=raw&ctype=text/css);" ],
		];
		// phpcs:enable
	}

	public static function provideDataEquals() {
		return [
			[ new TemplateStylesContent( 'hallo' ), null, false ],
			[ new TemplateStylesContent( 'hallo' ), new TemplateStylesContent( 'hallo' ), true ],
			[ new TemplateStylesContent( 'hallo' ), new CssContent( 'hallo' ), false ],
			[ new TemplateStylesContent( 'hallo' ), new WikitextContent( 'hallo' ), false ],
			[ new TemplateStylesContent( 'hallo' ), new TemplateStylesContent( 'HALLO' ), false ],
		];
	}

	/**
	 * @dataProvider provideDataEquals
	 */
	public function testEquals( Content $a, ?Content $b = null, $equal = false ) {
		$this->assertEquals( $equal, $a->equals( $b ) );
	}
}
