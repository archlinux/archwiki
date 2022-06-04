<?php

namespace PageImages\Tests;

use IContextSource;
use MediaWikiIntegrationTestCase;
use OutputPage;
use PageImages\PageImages;
use SkinTemplate;
use Title;

/**
 * @covers \PageImages\PageImages
 *
 * @group PageImages
 * @group Database
 *
 * @license WTFPL
 * @author Thiemo Kreuz
 */
class PageImagesTest extends MediaWikiIntegrationTestCase {

	public function testPagePropertyNames() {
		$this->assertSame( 'page_image', PageImages::PROP_NAME );
		$this->assertSame( 'page_image_free', PageImages::PROP_NAME_FREE );
	}

	public function testConstructor() {
		$pageImages = new PageImages();
		$this->assertInstanceOf( PageImages::class, $pageImages );
	}

	public function testGivenNonExistingPageGetPageImageReturnsFalse() {
		$title = $this->newTitle();
		$this->assertFalse( PageImages::getPageImage( $title ) );
	}

	public function testGetPropName() {
		$this->assertSame( 'page_image', PageImages::getPropName( false ) );
		$this->assertSame( 'page_image_free', PageImages::getPropName( true ) );
	}

	public function testGetPropNames() {
		$this->assertSame(
			[ PageImages::PROP_NAME_FREE, PageImages::PROP_NAME ],
			PageImages::getPropNames( PageImages::LICENSE_ANY )
		);
		$this->assertSame(
			PageImages::PROP_NAME_FREE,
			PageImages::getPropNames( PageImages::LICENSE_FREE )
		);
	}

	public function testGivenNonExistingPageOnBeforePageDisplayDoesNotAddMeta() {
		$outputPage = $this->mockOutputPage( [
			'PageImagesOpenGraphFallbackImage' => false
		] );
		$outputPage->expects( $this->never() )
			->method( 'addMeta' );

		$skinTemplate = new SkinTemplate();
		PageImages::onBeforePageDisplay( $outputPage, $skinTemplate );
	}

	public static function provideFallbacks() {
		return [
			[ 'http://wiki.test/example.png', '/example.png' ],
			[ 'http://wiki.test/img/default.png', '/img/default.png' ],
			[ 'https://example.org/example.png', 'https://example.org/example.png' ],
		];
	}

	/**
	 * @dataProvider provideFallbacks
	 */
	public function testGivenFallbackImageOnBeforePageDisplayAddMeta( $expected, $fallback ) {
		$this->setMwGlobals( [ 'wgCanonicalServer' => 'http://wiki.test' ] );
		$outputPage = $this->mockOutputPage( [
			'PageImagesOpenGraphFallbackImage' => $fallback
		] );
		$outputPage->expects( $this->once() )
			->method( 'addMeta' )
			->with( $this->equalTo( 'og:image' ), $this->equalTo( $expected ) );

		$skinTemplate = new SkinTemplate();
		PageImages::onBeforePageDisplay( $outputPage, $skinTemplate );
	}

	/**
	 * @param array $config
	 * @return OutputPage
	 */
	private function mockOutputPage( $config ) {
		$context = $this->createMock( IContextSource::class );
		$context->method( 'getTitle' )
			->willReturn( $this->newTitle() );
		$fauxRequest = new \FauxRequest();
		$config = new \HashConfig( $config );
		$context->method( 'getRequest' )
			->willReturn( $fauxRequest );
		$context->method( 'getConfig' )
			->willReturn( $config );

		$outputPage = $this->getMockBuilder( OutputPage::class )
			->onlyMethods( [ 'addMeta' ] )
			->setConstructorArgs( [ $context ] )
			->getMock();
		return $outputPage;
	}

	/**
	 * @return Title
	 */
	private function newTitle() {
		$title = Title::newFromText( 'New' );
		$title->resetArticleID( 0 );
		return $title;
	}

}
