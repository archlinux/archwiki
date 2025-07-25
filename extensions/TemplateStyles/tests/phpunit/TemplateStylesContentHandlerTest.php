<?php

use MediaWiki\Content\ValidationParams;
use MediaWiki\Extension\TemplateStyles\TemplateStylesContent;
use MediaWiki\Extension\TemplateStyles\TemplateStylesContentHandler;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;

/**
 * @group TemplateStyles
 * @covers \MediaWiki\Extension\TemplateStyles\TemplateStylesContentHandler
 */
class TemplateStylesContentHandlerTest extends MediaWikiLangTestCase {

	public function testBasics() {
		$handler = new TemplateStylesContentHandler();

		$this->assertSame( 'sanitized-css', $handler->getModelID() );
		$this->assertSame( [ 'text/css' ], $handler->getSupportedFormats() );
		$this->assertInstanceOf( TemplateStylesContent::class, $handler->makeEmptyContent() );

		$this->assertFalse( $handler->supportsRedirects() );

		$title = Title::newFromText( 'Template:Example/styles.css' );
		$this->assertNull( $handler->makeRedirectContent( $title ) );
	}

	public function testValidateSave() {
		$content = new TemplateStylesContent( '.foo { bogus: bogus; }' );
		$handler = new TemplateStylesContentHandler();
		$page = new PageIdentityValue( 0, 1, 'Foo', PageIdentity::LOCAL );
		$validationParams = new ValidationParams( $page, 0, 123 );

		$this->assertEquals(
			$handler->validateSave(
				$content,
				$validationParams
			),
			Status::newFatal( 'templatestyles-error-unrecognized-property', 1, 8 )
		);
	}

	public static function provideSanitize() {
		$status1 = Status::newGood( '.mw-parser-output .foo{}' );
		$status1->warning( 'templatestyles-error-bad-value-for-property', 1, 15, 'color' );

		return [
			'flip' => [
				'.foo { margin-left: 10px; /*@noflip*/ padding-left: 1em; }',
				[ 'flip' => true ],
				Status::newGood( '.mw-parser-output .foo{margin-right:10px;padding-left:1em}' )
			],
			'no minify' => [
				'.foo { margin-left: 10px }',
				[ 'minify' => false ],
				Status::newGood( '.mw-parser-output .foo { margin-left:10px; }' )
			],
			'With warnings' => [
				'.foo { color: bogus; }',
				[],
				$status1
			],
			'With warnings, fatal and no value' => [
				'.foo { bogus: bogus; }',
				[ 'severity' => 'fatal', 'novalue' => true ],
				Status::newFatal( 'templatestyles-error-unrecognized-property', 1, 8 ),
			],
			'With overridden class prefix' => [
				'.foo { margin-left: 10px }',
				[ 'class' => 'foo bar', 'minify' => false ],
				Status::newGood( '.foo\ bar .foo { margin-left:10px; }' )
			],
			'With boolean false as a class prefix' => [
				'.foo { margin-left: 10px }',
				[ 'class' => false, 'minify' => false ],
				Status::newGood( '.mw-parser-output .foo { margin-left:10px; }' )
			],
			'With an extra wrapper' => [
				'.foo { margin-left: 10px }',
				[ 'extraWrapper' => 'div.class' ],
				Status::newGood( '.mw-parser-output div.class .foo{margin-left:10px}' )
			],
			'Escaping U+007F' => [
				".foo\\\x7f { content: '\x7f'; }",
				[],
				Status::newGood(
					'.mw-parser-output .foo\\7f {content:"\\7f "}'
				)
			],
			'@font-face prefixing' => [
				'@font-face { font-family: nope; }',
				[ 'severity' => 'fatal', 'novalue' => true ],
				Status::newFatal( 'templatestyles-error-bad-value-for-property', 1, 27, 'font-family' ),
			],
			'</style> in string' => [
				'.foo { content: "</style>"; }',
				[],
				Status::newGood( '.mw-parser-output .foo{content:"\3c /style\3e "}' )
			],
			'</style> via identifiers' => [
				'.foo { grid-area: \< / style 0 / \>; }',
				[],
				Status::newGood( '.mw-parser-output .foo{grid-area:\3c /style 0/\3e }' ),
			],
		];
	}

	/**
	 * @dataProvider provideSanitize
	 * @param string $text Input text
	 * @param array $options
	 * @param Status $expect
	 */
	public function testSanitize( $text, $options, $expect ) {
		$content = new TemplateStylesContent( $text );
		$contentHandler = new TemplateStylesContentHandler( $content->getModel() );
		$this->assertEquals( $expect, $contentHandler->sanitize( $content, $options ) );
	}

	public function testInvalidWrapper() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid value for $extraWrapper: .foo>.bar' );
		$content = new TemplateStylesContent( '.foo { margin-left: 10px }' );
		$contentHandler = new TemplateStylesContentHandler( $content->getModel() );
		$contentHandler->sanitize(
			$content,
			[ 'extraWrapper' => '.foo>.bar' ]
		);
	}

	public function testCrazyBrokenSanitizer() {
		// Big hack: Make a Token that returns a bad string, and a Sanitizer
		// that returns that bad Token, just so we can test a code path that
		// handles such bad output.
		$this->setTemporaryHook(
			'TemplateStylesStylesheetSanitizer',
			function ( &$sanitizer ) {
				$badToken = $this->getMockBuilder( Wikimedia\CSS\Objects\Token::class )
					->disableOriginalConstructor()
					->onlyMethods( [ '__toString' ] )
					->getMock();
				$badToken->method( '__toString' )->willReturn( '"</style>"' );

				$sanitizer = $this->getMockBuilder( Wikimedia\CSS\Sanitizer\StylesheetSanitizer::class )
					->disableOriginalConstructor()
					->onlyMethods( [ 'sanitize' ] )
					->getMock();
				$sanitizer->method( 'sanitize' )->willReturn( $badToken );
				return false;
			}
		);

		$content = new TemplateStylesContent( '.foo {}' );
		$contentHandler = new TemplateStylesContentHandler( $content->getModel() );
		$this->assertEquals(
			Status::newFatal( 'templatestyles-tag-injection' ),
			$contentHandler->sanitize( $content, [ 'class' => 'testCrazyBrokenSanitizer' ] )
		);
	}

	public static function provideSizeLimit() {
		$long = str_repeat( 'X', 102400 );

		return [
			'Good' => [
				new TemplateStylesContent( '.foobar {}' ),
				Status::newGood( '.mw-parser-output .foobar{}' ),
				10
			],
			'Size Exceeded' => [
				new TemplateStylesContent( '.foobar2 {}' ),
				Status::newFatal( wfMessage( 'templatestyles-size-exceeded', 10, Message::sizeParam( 10 ) ) ),
				10
			],
			'Long' => [
				new TemplateStylesContent( ".{$long} {}" ),
				Status::newGood( ".mw-parser-output .{$long}{}" ),
				null
			],
		];
	}

	/**
	 * @dataProvider provideSizeLimit
	 * @param TemplateStylesContent $content
	 * @param StatusValue $expectedStatus
	 * @param int|null $size
	 */
	public function testSizeLimit( TemplateStylesContent $content, StatusValue $expectedStatus, $size = null ) {
		$this->overrideConfigValue( 'TemplateStylesMaxStylesheetSize', $size );

		$contentHandler = new TemplateStylesContentHandler( $content->getModel() );
		$this->assertEquals(
			$expectedStatus,
			$contentHandler->sanitize( $content )
		);
	}
}
