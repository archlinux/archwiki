<?php

namespace PageImages\Tests;

use IContextSource;
use MediaWikiTestCase;
use OutputPage;
use PageImages;
use SkinTemplate;
use Title;

/**
 * @covers PageImages
 *
 * @group PageImages
 * @group Database
 *
 * @license WTFPL
 * @author Thiemo Kreuz
 */
class PageImagesTest extends MediaWikiTestCase {

	public function testPagePropertyNames() {
		$this->assertSame( 'page_image', PageImages::PROP_NAME );
		$this->assertSame( 'page_image_free', PageImages::PROP_NAME_FREE );
	}

	public function testConstructor() {
		$pageImages = new PageImages();
		$this->assertInstanceOf( 'PageImages', $pageImages );
	}

	public function testGivenNonExistingPageGetPageImageReturnsFalse() {
		$title = $this->newTitle();
		$this->assertFalse( PageImages::getPageImage( $title ) );
	}

	public function testGetPropName() {
		$this->assertSame( 'page_image', PageImages::getPropName( false ) );
		$this->assertSame( 'page_image_free', PageImages::getPropName( true ) );
	}

	public function testGivenNonExistingPageOnBeforePageDisplayDoesNotAddMeta() {
		$context = $this->getMock( IContextSource::class );
		$context->method( 'getTitle' )
			->will( $this->returnValue( $this->newTitle() ) );

		$outputPage = $this->getMock(
			OutputPage::class, [ 'addMeta' ], [ $context ] );
		$outputPage->expects( $this->never() )
			->method( 'addMeta' );

		$skinTemplate = new SkinTemplate();
		PageImages::onBeforePageDisplay( $outputPage, $skinTemplate );
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
