<?php

namespace MediaWiki\Tests\Unit\EditPage;

use MediaWiki\Content\ContentHandler;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\EditPage\PageEditingHelper;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Revision\RevisionStore;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\EditPage\PageEditingHelper
 */
class PageEditingHelperUnitTest extends MediaWikiUnitTestCase {

	private function createPageEditingHelper(
		?IContentHandlerFactory $contentHandlerFactory = null,
	): PageEditingHelper {
		$contentHandlerFactory ??= $this->createMock( IContentHandlerFactory::class );
		return new PageEditingHelper(
			$contentHandlerFactory,
			$this->createMock( ParserFactory::class ),
			$this->createMock( RevisionStore::class ),
		);
	}

	public function testIsSupportedContentModel() {
		$contentHandlerFactory = $this->createMock( IContentHandlerFactory::class );

		$supportedContentHandler = $this->createMock( ContentHandler::class );
		$supportedContentHandler->method( 'supportsDirectEditing' )->willReturn( true );

		$unsupportedContentHandler = $this->createMock( ContentHandler::class );
		$unsupportedContentHandler->method( 'supportsDirectEditing' )->willReturn( false );

		$contentHandlerFactory->method( 'getContentHandler' )->willReturnCallback(
			static fn ( $id ) => $id === 'supported' ? $supportedContentHandler : $unsupportedContentHandler
		);

		$pageEditingHelper = $this->createPageEditingHelper(
			$contentHandlerFactory,
		);
		$this->assertTrue(
			$pageEditingHelper->isSupportedContentModel( 'supported', false ),
			'Should return true if the content model is supported and the API edit override is not enabled.'
		);
		$this->assertTrue(
			$pageEditingHelper->isSupportedContentModel( 'supported', true ),
			'Should return true if the content model is supported and the API edit override is enabled.'
		);
		$this->assertFalse(
			$pageEditingHelper->isSupportedContentModel( 'unsupported', false ),
			'Should return false if the content model is not supported and the API edit override is not enabled.'
		);
		$this->assertTrue(
			$pageEditingHelper->isSupportedContentModel( 'unsupported', true ),
			'Should return true if the content model is not supported but the API edit override is enabled.'
		);
	}

}
