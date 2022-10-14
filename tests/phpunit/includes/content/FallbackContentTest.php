<?php

/**
 * @group ContentHandler
 */
class FallbackContentTest extends MediaWikiLangTestCase {

	private const CONTENT_MODEL = 'xyzzy';

	protected function setUp(): void {
		parent::setUp();
		$this->mergeMwGlobalArrayValue(
			'wgContentHandlers',
			[ self::CONTENT_MODEL => FallbackContentHandler::class ]
		);
	}

	/**
	 * @param string $data
	 * @param string $type
	 *
	 * @return FallbackContent
	 */
	public function newContent( $data, $type = self::CONTENT_MODEL ) {
		return new FallbackContent( $data, $type );
	}

	/**
	 * @covers FallbackContent::getRedirectTarget
	 */
	public function testGetRedirectTarget() {
		$content = $this->newContent( '#REDIRECT [[Horkyporky]]' );
		$this->assertNull( $content->getRedirectTarget() );
	}

	/**
	 * @covers FallbackContent::isRedirect
	 */
	public function testIsRedirect() {
		$content = $this->newContent( '#REDIRECT [[Horkyporky]]' );
		$this->assertFalse( $content->isRedirect() );
	}

	/**
	 * @covers FallbackContent::isCountable
	 */
	public function testIsCountable() {
		$content = $this->newContent( '[[Horkyporky]]' );
		$this->assertFalse( $content->isCountable( true ) );
	}

	/**
	 * @covers FallbackContent::getTextForSummary
	 */
	public function testGetTextForSummary() {
		$content = $this->newContent( 'Horkyporky' );
		$this->assertSame( '', $content->getTextForSummary() );
	}

	/**
	 * @covers FallbackContent::getTextForSearchIndex
	 */
	public function testGetTextForSearchIndex() {
		$content = $this->newContent( 'Horkyporky' );
		$this->assertSame( '', $content->getTextForSearchIndex() );
	}

	/**
	 * @covers FallbackContent::copy
	 */
	public function testCopy() {
		$content = $this->newContent( 'hello world.' );
		$copy = $content->copy();

		$this->assertSame( $content, $copy );
	}

	/**
	 * @covers FallbackContent::getSize
	 */
	public function testGetSize() {
		$content = $this->newContent( 'hello world.' );

		$this->assertEquals( 12, $content->getSize() );
	}

	/**
	 * @covers FallbackContent::getData
	 */
	public function testGetData() {
		$content = $this->newContent( 'hello world.' );

		$this->assertEquals( 'hello world.', $content->getData() );
	}

	/**
	 * @covers FallbackContent::getNativeData
	 */
	public function testGetNativeData() {
		$content = $this->newContent( 'hello world.' );

		$this->assertEquals( 'hello world.', $content->getNativeData() );
	}

	/**
	 * @covers FallbackContent::getWikitextForTransclusion
	 */
	public function testGetWikitextForTransclusion() {
		$content = $this->newContent( 'hello world.' );

		$this->assertFalse( $content->getWikitextForTransclusion() );
	}

	/**
	 * @covers FallbackContent::getModel
	 */
	public function testGetModel() {
		$content = $this->newContent( "hello world.", 'horkyporky' );

		$this->assertEquals( 'horkyporky', $content->getModel() );
	}

	/**
	 * @covers FallbackContent::getContentHandler
	 */
	public function testGetContentHandler() {
		$this->mergeMwGlobalArrayValue(
			'wgContentHandlers',
			[ 'horkyporky' => FallbackContentHandler::class ]
		);

		$content = $this->newContent( "hello world.", 'horkyporky' );

		$this->assertInstanceOf( FallbackContentHandler::class, $content->getContentHandler() );
		$this->assertEquals( 'horkyporky', $content->getContentHandler()->getModelID() );
	}

	public static function dataIsEmpty() {
		return [
			[ '', true ],
			[ '  ', false ],
			[ '0', false ],
			[ 'hallo welt.', false ],
		];
	}

	/**
	 * @dataProvider dataIsEmpty
	 * @covers FallbackContent::isEmpty
	 */
	public function testIsEmpty( $text, $empty ) {
		$content = $this->newContent( $text );

		$this->assertEquals( $empty, $content->isEmpty() );
	}

	public function provideEquals() {
		return [
			[ new FallbackContent( "hallo", 'horky' ), null, false ],
			[ new FallbackContent( "hallo", 'horky' ), new FallbackContent( "hallo", 'horky' ), true ],
			[ new FallbackContent( "hallo", 'horky' ), new FallbackContent( "hallo", 'xyzzy' ), false ],
			[ new FallbackContent( "hallo", 'horky' ), new JavaScriptContent( "hallo" ), false ],
			[ new FallbackContent( "hallo", 'horky' ), new WikitextContent( "hallo" ), false ],
		];
	}

	/**
	 * @dataProvider provideEquals
	 * @covers FallbackContent::equals
	 */
	public function testEquals( Content $a, Content $b = null, $equal = false ) {
		$this->assertEquals( $equal, $a->equals( $b ) );
	}

	public static function provideConvert() {
		return [
			[ // #0
				'Hallo Welt',
				CONTENT_MODEL_WIKITEXT,
				'lossless',
				'Hallo Welt'
			],
			[ // #1
				'Hallo Welt',
				CONTENT_MODEL_WIKITEXT,
				'lossless',
				'Hallo Welt'
			],
			[ // #1
				'Hallo Welt',
				CONTENT_MODEL_CSS,
				'lossless',
				'Hallo Welt'
			],
			[ // #1
				'Hallo Welt',
				CONTENT_MODEL_JAVASCRIPT,
				'lossless',
				'Hallo Welt'
			],
		];
	}

	/**
	 * @covers FallbackContent::convert
	 */
	public function testConvert() {
		$content = $this->newContent( 'More horkyporky?' );

		$this->assertFalse( $content->convert( CONTENT_MODEL_TEXT ) );
	}

	/**
	 * @covers FallbackContent::__construct
	 * @covers FallbackContentHandler::serializeContent
	 */
	public function testSerialize() {
		$content = $this->newContent( 'Hörkypörky', 'horkyporky' );

		$this->assertSame( 'Hörkypörky', $content->serialize() );
	}

}
