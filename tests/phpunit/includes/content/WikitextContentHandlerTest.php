<?php

use MediaWiki\MainConfigNames;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Parser\ParserOutputFlags;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use Wikimedia\TestingAccessWrapper;

/**
 * See also unit tests at \MediaWiki\Tests\Unit\WikitextContentHandlerTest
 *
 * @group ContentHandler
 */
class WikitextContentHandlerTest extends MediaWikiLangTestCase {
	/** @var WikitextContentHandler */
	private $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->handler = $this->getServiceContainer()->getContentHandlerFactory()
			->getContentHandler( CONTENT_MODEL_WIKITEXT );
	}

	public static function dataMerge3() {
		return [
			[
				"first paragraph

					second paragraph\n",

				"FIRST paragraph

					second paragraph\n",

				"first paragraph

					SECOND paragraph\n",

				"FIRST paragraph

					SECOND paragraph\n",
			],

			[ "first paragraph
					second paragraph\n",

				"Bla bla\n",

				"Blubberdibla\n",

				false,
			],
		];
	}

	/**
	 * @dataProvider dataMerge3
	 * @covers WikitextContentHandler::merge3
	 */
	public function testMerge3( $old, $mine, $yours, $expected ) {
		$this->markTestSkippedIfNoDiff3();

		// test merge
		$oldContent = new WikitextContent( $old );
		$myContent = new WikitextContent( $mine );
		$yourContent = new WikitextContent( $yours );

		$merged = $this->handler->merge3( $oldContent, $myContent, $yourContent );

		$this->assertEquals( $expected, $merged ? $merged->getText() : $merged );
	}

	public static function dataGetAutosummary() {
		return [
			[
				'Hello there, world!',
				'#REDIRECT [[Foo]]',
				0,
				'/^Redirected page .*Foo/'
			],

			[
				null,
				'Hello world!',
				EDIT_NEW,
				'/^Created page .*Hello/'
			],

			[
				null,
				'',
				EDIT_NEW,
				'/^Created blank page$/'
			],

			[
				'Hello there, world!',
				'',
				0,
				'/^Blanked/'
			],

			[
				'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy
				eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam
				voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet
				clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
				'Hello world!',
				0,
				'/^Replaced .*Hello/'
			],

			[
				'foo',
				'bar',
				0,
				'/^$/'
			],
		];
	}

	/**
	 * @dataProvider dataGetAutosummary
	 * @covers WikitextContentHandler::getAutosummary
	 */
	public function testGetAutosummary( $old, $new, $flags, $expected ) {
		$oldContent = $old === null ? null : new WikitextContent( $old );
		$newContent = $new === null ? null : new WikitextContent( $new );

		$summary = $this->handler->getAutosummary( $oldContent, $newContent, $flags );

		$this->assertTrue(
			(bool)preg_match( $expected, $summary ),
			"Autosummary didn't match expected pattern $expected: $summary"
		);
	}

	public static function dataGetChangeTag() {
		return [
			[
				null,
				'#REDIRECT [[Foo]]',
				0,
				'mw-new-redirect'
			],

			[
				'Lorem ipsum dolor',
				'#REDIRECT [[Foo]]',
				0,
				'mw-new-redirect'
			],

			[
				'#REDIRECT [[Foo]]',
				'Lorem ipsum dolor',
				0,
				'mw-removed-redirect'
			],

			[
				'#REDIRECT [[Foo]]',
				'#REDIRECT [[Bar]]',
				0,
				'mw-changed-redirect-target'
			],

			[
				null,
				'Lorem ipsum dolor',
				EDIT_NEW,
				null // mw-newpage is not defined as a tag
			],

			[
				null,
				'',
				EDIT_NEW,
				null // mw-newblank is not defined as a tag
			],

			[
				'Lorem ipsum dolor',
				'',
				0,
				'mw-blank'
			],

			[
				'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy
				eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam
				voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet
				clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
				'Ipsum',
				0,
				'mw-replace'
			],

			[
				'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy
				eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam
				voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet
				clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
				'Duis purus odio, rhoncus et finibus dapibus, facilisis ac urna. Pellentesque
				arcu, tristique nec tempus nec, suscipit vel arcu. Sed non dolor nec ligula
				congue tempor. Quisque pellentesque finibus orci a molestie. Nam maximus, purus
				euismod finibus mollis, dui ante malesuada felis, dignissim rutrum diam sapien.',
				0,
				null
			],
		];
	}

	/**
	 * @dataProvider dataGetChangeTag
	 * @covers WikitextContentHandler::getChangeTag
	 */
	public function testGetChangeTag( $old, $new, $flags, $expected ) {
		$this->overrideConfigValue( MainConfigNames::SoftwareTags, [
			'mw-new-redirect' => true,
			'mw-removed-redirect' => true,
			'mw-changed-redirect-target' => true,
			'mw-newpage' => true,
			'mw-newblank' => true,
			'mw-blank' => true,
			'mw-replace' => true,
		] );
		$oldContent = $old === null ? null : new WikitextContent( $old );
		$newContent = $new === null ? null : new WikitextContent( $new );

		$tag = $this->handler->getChangeTag( $oldContent, $newContent, $flags );

		$this->assertSame( $expected, $tag );
	}

	/**
	 * @covers WikitextContentHandler::getDataForSearchIndex
	 */
	public function testDataIndexFieldsFile() {
		$mockEngine = $this->createMock( SearchEngine::class );
		$title = Title::makeTitle( NS_FILE, 'Somefile.jpg' );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		// Mark the page as not existent to avoid DB queries.
		$pageWrapper = TestingAccessWrapper::newFromObject( $page );
		$pageWrapper->mLatest = null;
		$pageWrapper->mId = 0;
		$pageWrapper->mDataLoadedFrom = WikiPage::READ_NORMAL;

		$fileHandler = $this->getMockBuilder( FileContentHandler::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getDataForSearchIndex' ] )
			->getMock();

		$handler = $this->getMockBuilder( WikitextContentHandler::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getFileHandler' ] )
			->getMock();

		$handler->method( 'getFileHandler' )->willReturn( $fileHandler );
		$fileHandler->expects( $this->once() )
			->method( 'getDataForSearchIndex' )
			->willReturn( [ 'file_text' => 'This is file content' ] );

		$data = $handler->getDataForSearchIndex( $page, new ParserOutput( '' ), $mockEngine );
		$this->assertArrayHasKey( 'file_text', $data );
		$this->assertEquals( 'This is file content', $data['file_text'] );
	}

	/**
	 * @covers WikitextContentHandler::fillParserOutput
	 */
	public function testHadSignature() {
		$services = $this->getServiceContainer();

		$pageObj = PageReferenceValue::localReference( NS_MAIN, __CLASS__ );
		// Force a content model in the converted Title to avoid DB queries.
		$title = $services->getTitleFactory()->newFromPageReference( $pageObj );
		$title->setContentModel( CONTENT_MODEL_WIKITEXT );
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'newFromPageReference' )
			->with( $pageObj )
			->willReturn( $title );
		$this->setService( 'TitleFactory', $titleFactory );

		$contentTransformer = $services->getContentTransformer();
		$contentRenderer = $services->getContentRenderer();
		$this->hideDeprecated( 'AbstractContent::preSaveTransform' );

		$content = new WikitextContent( '~~~~' );
		$pstContent = $contentTransformer->preSaveTransform(
			$content,
			$pageObj,
			$this->createMock( UserIdentity::class ),
			ParserOptions::newFromAnon()
		);

		$this->assertTrue( $contentRenderer->getParserOutput( $pstContent, $pageObj )->getOutputFlag(
			ParserOutputFlags::USER_SIGNATURE
		) );
	}
}
