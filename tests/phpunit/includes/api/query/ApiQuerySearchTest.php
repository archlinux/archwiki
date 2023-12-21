<?php

use MediaWiki\MainConfigNames;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\Title;

/**
 * @group medium
 * @covers ApiQuerySearch
 */
class ApiQuerySearchTest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();
		MockSearchEngine::clearMockResults();
		$this->registerMockSearchEngine();
		$this->setService( 'RevisionLookup', $this->createMock( RevisionLookup::class ) );
	}

	private function registerMockSearchEngine() {
		$this->overrideConfigValue( MainConfigNames::SearchType, MockSearchEngine::class );
	}

	public function provideSearchResults() {
		return [
			'empty search result' => [ [], [] ],
			'has search results' => [
				[ 'Zomg' ],
				[ $this->mockResultClosure( 'Zomg' ) ],
			],
			'filters broken search results' => [
				[ 'A', 'B' ],
				[
					$this->mockResultClosure( 'a' ),
					$this->mockResultClosure( 'Zomg', [ 'setBrokenTitle' => true ] ),
					$this->mockResultClosure( 'b' ),
				],
			],
			'filters results with missing revision' => [
				[ 'B', 'A' ],
				[
					$this->mockResultClosure( 'Zomg', [ 'setMissingRevision' => true ] ),
					$this->mockResultClosure( 'b' ),
					$this->mockResultClosure( 'a' ),
				],
			],
		];
	}

	/**
	 * @dataProvider provideSearchResults
	 */
	public function testSearchResults( $expect, $hits, array $params = [] ) {
		MockSearchEngine::addMockResults( 'my query', $hits );
		[ $response, $request ] = $this->doApiRequest( $params + [
			'action' => 'query',
			'list' => 'search',
			'srsearch' => 'my query',
		] );
		$titles = array_column( $response['query']['search'], 'title' );
		$this->assertEquals( $expect, $titles );
	}

	public function provideInterwikiResults() {
		return [
			'empty' => [ [], [] ],
			'one wiki response' => [
				[ 'utwiki' => [ 'Qwerty' ] ],
				[
					ISearchResultSet::SECONDARY_RESULTS => [
						'utwiki' => new MockSearchResultSet( [
							$this->mockResultClosure(
								'Qwerty',
								[ 'setInterwikiPrefix' => 'utwiki' ]
							),
						] ),
					],
				]
			],
		];
	}

	/**
	 * @dataProvider provideInterwikiResults
	 */
	public function testInterwikiResults( $expect, $hits, array $params = [] ) {
		MockSearchEngine::setMockInterwikiResults( $hits );
		[ $response, $request ] = $this->doApiRequest( $params + [
			'action' => 'query',
			'list' => 'search',
			'srsearch' => 'my query',
			'srinterwiki' => true,
		] );
		if ( !$expect ) {
			$this->assertArrayNotHasKey( 'interwikisearch', $response['query'] );
			return;
		}
		$results = [];
		$this->assertArrayHasKey( 'interwikisearchinfo', $response['query'] );
		foreach ( $response['query']['interwikisearch'] as $wiki => $wikiResults ) {
			$results[$wiki] = [];
			foreach ( $wikiResults as $wikiResult ) {
				$results[$wiki][] = $wikiResult['title'];
			}
		}
		$this->assertEquals( $expect, $results );
	}

	/**
	 * Returns a closure that evaluates to a MockSearchResult, to be resolved by
	 * MockSearchEngine::addMockResults() or MockresultSet::extractResults().
	 *
	 * This is needed because MockSearchResults cannot be instantiated in a data provider,
	 * since they load revisions. This would hit the "real" database instead of the mock
	 * database, which in turn may cause cache pollution and other inconsistencies, see T202641.
	 *
	 * @param string $titleText
	 * @param array $setters
	 * @return callable function(): MockSearchResult
	 */
	private function mockResultClosure( $titleText, $setters = [] ) {
		return static function () use ( $titleText, $setters ) {
			$title = Title::newFromText( $titleText );
			$title->resetArticleID( 0 );
			$result = new MockSearchResult( $title );

			foreach ( $setters as $method => $param ) {
				$result->$method( $param );
			}

			return $result;
		};
	}

}
