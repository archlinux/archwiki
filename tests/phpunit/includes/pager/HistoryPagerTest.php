<?php

use MediaWiki\Revision\MutableRevisionRecord;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for HistoryPager methods.
 *
 * @group Pager
 */
class HistoryPagerTest extends MediaWikiLangTestCase {

	/**
	 * @param array $results for passing to FakeResultWrapper and deriving
	 *  RevisionRecords and formatted comments.
	 * @return HistoryPager
	 */
	private function getHistoryPager( array $results ) {
		$wikiPageMock = $this->getMockBuilder( WikiPage::class )
			->disableOriginalConstructor()
			->getMock();
		$contextMock = $this->getMockBuilder( RequestContext::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getRequest', 'getWikiPage', 'getTitle' ] )
			->getMock();
		$contextMock->method( 'getRequest' )->willReturn(
			new FauxRequest( [] )
		);
		$title = Title::newFromText( 'HistoryPagerTest' );
		$contextMock->method( 'getTitle' )->willReturn( $title );

		$contextMock->method( 'getWikiPage' )->willReturn( $wikiPageMock );
		$articleMock = $this->getMockBuilder( Article::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getContext' ] )
			->getMock();
		$articleMock->method( 'getContext' )->willReturn( $contextMock );

		$actionMock = $this->getMockBuilder( HistoryAction::class )
			->setConstructorArgs( [
				$articleMock,
				$contextMock
			] )
			->getMock();
		$actionMock->method( 'getArticle' )->willReturn( $articleMock );
		$actionMock->message = [
			'cur' => 'cur',
			'last' => 'last',
			'tooltip-cur' => '',
			'tooltip-last' => '',
		];

		$outputMock = $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'wrapWikiMsg' ] )
			->getMock();

		$pager = $this->getMockBuilder( HistoryPager::class )
			->onlyMethods( [ 'reallyDoQuery', 'doBatchLookups',
				'getOutput' ] )
			->setConstructorArgs( [
				$actionMock
			] )
			->getMock();

		$pager->method( 'getOutput' )->willReturn( $outputMock );
		$pager->method( 'reallyDoQuery' )->willReturn(
			new FakeResultWrapper( $results )
		);

		// make all the methods in our mock public
		$pager = TestingAccessWrapper::newFromObject( $pager );
		// and update the private properties...
		$pager->formattedComments = array_map( static function ( $result ) {
			return 'dummy comment';
		}, $results );

		$pager->revisions = array_map( static function ( $result ) {
			$title = Title::newFromText( 'HistoryPagerTest' );
			$r = new MutableRevisionRecord( $title );
			$r->setId( $result[ 'rev_id' ] );
			return $r;
		}, $results );

		return $pager;
	}

	/**
	 * @covers HistoryPager::getBody
	 */
	public function testGetBodyEmpty() {
		$pager = $this->getHistoryPager( [] );
		$html = $pager->getBody();
		$this->assertStringContainsString( 'No matching revisions were found.', $html );
		$this->assertStringNotContainsString( '<h4', $html );
	}

	/**
	 * @covers HistoryPager::getBody
	 */
	public function testGetBodyOneHeading() {
		$pager = $this->getHistoryPager(
			[
				[
					'rev_page' => 'title',
					'ts_tags' => '',
					'rev_deleted' => false,
					'rev_minor_edit' => false,
					'rev_parent_id' => 1,
					'user_name' => 'Jdlrobson',
					'rev_id' => 2,
					'rev_comment_data' => '{}',
					'rev_comment_cid' => '1',
					'rev_comment_text' => 'Created page',
					'rev_timestamp' => '20220101001122',
				]
			]
		);
		$html = $pager->getBody();
		$this->assertStringContainsString( '<h4', $html );
	}

	/**
	 * @covers HistoryPager::getBody
	 */
	public function testGetBodyTwoHeading() {
		$pagerData = [
			'rev_page' => 'title',
			'rev_deleted' => false,
			'rev_minor_edit' => false,
			'ts_tags' => '',
			'rev_parent_id' => 1,
			'user_name' => 'Jdlrobson',
			'rev_comment_data' => '{}',
			'rev_comment_cid' => '1',
			'rev_comment_text' => 'Fixed typo',
		];
		$pager = $this->getHistoryPager(
			[
				$pagerData + [
					'rev_id' => 3,
					'rev_timestamp' => '20220301001122',
				],
				$pagerData + [
					'rev_id' => 2,
					'rev_timestamp' => '20220101001122',
				],
			]
		);
		$html = preg_replace( "/\n+/", '', $pager->getBody() );
		$firstHeader = '<h4 class="mw-index-pager-list-header-first mw-index-pager-list-header">1 March 2022</h4>'
			. '<ul class="mw-contributions-list">'
			. '<li data-mw-revid="3">';
		$secondHeader = '<h4 class="mw-index-pager-list-header">1 January 2022</h4>'
			. '<ul class="mw-contributions-list">'
			. '<li data-mw-revid="2">';

		// Check that the undo links are correct in the topmost displayed row (for rev_id=3).
		// This is tricky because the other rev number (in this example, '2') is magically
		// pulled from the next row, before we've processed that row.
		$this->assertStringContainsString( '&amp;undoafter=2&amp;undo=3', $html );

		$this->assertStringContainsString( $firstHeader, $html );
		$this->assertStringContainsString( $secondHeader, $html );
		$this->assertStringContainsString( '<section id="pagehistory"', $html );
	}

	/**
	 * @covers HistoryPager::getBody
	 */
	public function testGetBodyLastItem() {
		$pagerData = [
			'rev_page' => 'title',
			'rev_deleted' => false,
			'rev_minor_edit' => false,
			'ts_tags' => '',
			'rev_parent_id' => 1,
			'user_name' => 'Jdlrobson',
			'rev_comment_data' => '{}',
			'rev_comment_cid' => '1',
			'rev_comment_text' => 'Fixed typo',
		];
		$pager = $this->getHistoryPager(
			[
				$pagerData + [
					'rev_id' => 2,
					'rev_timestamp' => '20220301001111',
				],
				$pagerData + [
					'rev_id' => 3,
					'rev_timestamp' => '20220301001122',
				],
			]
		);
		$html = preg_replace( "/\n+/", '', $pager->getBody() );
		$finalList = '</ul><ul class="mw-contributions-list">';
		$this->assertStringContainsString( $finalList, $html,
			'The last item is always in its own list and there is no header if the date is the same.' );
	}
}
