<?php

use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Unit\DummyServicesTrait;

/**
 * @group API
 * @group Database
 * @group medium
 * @covers ApiQuery
 */
class ApiQueryTest extends ApiTestCase {
	use DummyServicesTrait;

	protected function setUp(): void {
		parent::setUp();

		// Setup apiquerytestiw: as interwiki prefix
		// DummyServicesTrait::getDummyInterwikiLookup
		$interwikiLookup = $this->getDummyInterwikiLookup( [
			[ 'iw_prefix' => 'apiquerytestiw', 'iw_url' => 'wikipedia' ],
		] );
		$this->setService( 'InterwikiLookup', $interwikiLookup );
	}

	public function testTitlesGetNormalized() {
		$this->overrideConfigValues( [
			MainConfigNames::CapitalLinks => true,
			MainConfigNames::MetaNamespace => 'TestWiki',
		] );

		$data = $this->doApiRequest( [
			'action' => 'query',
			'titles' => 'Project:articleA|article_B' ] );

		$this->assertArrayHasKey( 'query', $data[0] );
		$this->assertArrayHasKey( 'normalized', $data[0]['query'] );

		$this->assertEquals(
			[
				'fromencoded' => false,
				'from' => 'Project:articleA',
				'to' => 'TestWiki:ArticleA',
			],
			$data[0]['query']['normalized'][0]
		);

		$this->assertEquals(
			[
				'fromencoded' => false,
				'from' => 'article_B',
				'to' => 'Article B'
			],
			$data[0]['query']['normalized'][1]
		);
	}

	public function testTitlesAreRejectedIfInvalid() {
		$title = false;
		while ( !$title || Title::newFromText( $title )->exists() ) {
			$title = md5( mt_rand( 0, 100000 ) );
		}

		$data = $this->doApiRequest( [
			'action' => 'query',
			'titles' => $title . '|Talk:' ] );

		$this->assertArrayHasKey( 'query', $data[0] );
		$this->assertArrayHasKey( 'pages', $data[0]['query'] );
		$this->assertCount( 2, $data[0]['query']['pages'] );

		$this->assertArrayHasKey( -2, $data[0]['query']['pages'] );
		$this->assertArrayHasKey( -1, $data[0]['query']['pages'] );

		$this->assertArrayHasKey( 'missing', $data[0]['query']['pages'][-2] );
		$this->assertArrayHasKey( 'invalid', $data[0]['query']['pages'][-1] );
	}

	public function testTitlesWithWhitespaces() {
		$data = $this->doApiRequest( [
			'action' => 'query',
			'titles' => ' '
		] );

		$this->assertArrayHasKey( 'query', $data[0] );
		$this->assertArrayHasKey( 'pages', $data[0]['query'] );
		$this->assertCount( 1, $data[0]['query']['pages'] );
		$this->assertArrayHasKey( -1, $data[0]['query']['pages'] );
		$this->assertArrayHasKey( 'invalid', $data[0]['query']['pages'][-1] );
	}

	/**
	 * Test the ApiBase::titlePartToKey function
	 *
	 * @param string $titlePart
	 * @param int $namespace
	 * @param string $expected
	 * @param string $expectException
	 * @dataProvider provideTestTitlePartToKey
	 */
	public function testTitlePartToKey( $titlePart, $namespace, $expected, $expectException ) {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );

		$api = new MockApiQueryBase();
		$exceptionCaught = false;
		try {
			$this->assertEquals( $expected, $api->titlePartToKey( $titlePart, $namespace ) );
		} catch ( ApiUsageException $e ) {
			$exceptionCaught = true;
		}
		$this->assertEquals( $expectException, $exceptionCaught,
			'ApiUsageException thrown by titlePartToKey' );
	}

	public function provideTestTitlePartToKey() {
		return [
			[ 'a  b  c', NS_MAIN, 'A_b_c', false ],
			[ 'x', NS_MAIN, 'X', false ],
			[ 'y ', NS_MAIN, 'Y_', false ],
			[ 'template:foo', NS_CATEGORY, 'Template:foo', false ],
			[ 'apiquerytestiw:foo', NS_CATEGORY, 'Apiquerytestiw:foo', false ],
			[ "\xF7", NS_MAIN, null, true ],
			[ 'template:foo', NS_MAIN, null, true ],
			[ 'apiquerytestiw:foo', NS_MAIN, null, true ],
		];
	}

	/**
	 * Test if all classes in the query module manager exists
	 */
	public function testClassNamesInModuleManager() {
		$api = new ApiMain(
			new FauxRequest( [ 'action' => 'query', 'meta' => 'siteinfo' ] )
		);
		$queryApi = $api->getModuleManager()->getModule( 'query' );
		$modules = $queryApi->getModuleManager()->getNamesWithClasses();

		foreach ( $modules as $name => $class ) {
			$this->assertTrue(
				class_exists( $class ),
				'Class ' . $class . ' for api module ' . $name . ' does not exist (with exact case)'
			);
		}
	}

	public function testShouldNotExportPagesThatUserCanNotRead() {
		$title = Title::makeTitle( NS_MAIN, 'Test article' );
		$this->insertPage( $title );

		$this->setTemporaryHook( 'getUserPermissionsErrors',
			static function ( Title $page, &$user, $action, &$result ) use ( $title ) {
				if ( $page->equals( $title ) && $action === 'read' ) {
					$result = false;
					return false;
				}
			} );

		$data = $this->doApiRequest( [
			'action' => 'query',
			'titles' => $title->getPrefixedText(),
			'export' => 1,
		] );

		$this->assertArrayHasKey( 'query', $data[0] );
		$this->assertArrayHasKey( 'export', $data[0]['query'] );
		// This response field contains an XML document even if no pages were exported
		$this->assertStringNotContainsString( $title->getPrefixedText(), $data[0]['query']['export'] );
	}

	public function testIsReadMode() {
		$api = new ApiMain(
			new FauxRequest( [ 'action' => 'query', 'meta' => 'tokens', 'type' => 'login' ] )
		);
		$queryApi = $api->getModuleManager()->getModule( 'query' );
		$this->assertFalse( $queryApi->isReadMode(),
			'isReadMode() => false when meta=tokens is the only module' );

		$api = new ApiMain( new FauxRequest( [
			'action' => 'query', 'meta' => 'tokens', 'type' => 'login', 'rawcontinue' => 1,
			'indexpageids' => 1
		] )
		);
		$queryApi = $api->getModuleManager()->getModule( 'query' );
		$this->assertFalse( $queryApi->isReadMode(),
			'rawcontinue and indexpageids are also allowed' );

		$api = new ApiMain(
			new FauxRequest( [ 'action' => 'query', 'meta' => 'tokens|siteinfo', 'type' => 'login' ] )
		);
		$queryApi = $api->getModuleManager()->getModule( 'query' );
		$this->assertTrue( $queryApi->isReadMode(),
			'isReadMode() => true when other meta modules are present' );

		$api = new ApiMain( new FauxRequest( [
			'action' => 'query', 'meta' => 'tokens', 'type' => 'login', 'list' => 'allpages'
		] ) );
		$queryApi = $api->getModuleManager()->getModule( 'query' );
		$this->assertTrue( $queryApi->isReadMode(),
			'isReadMode() => true when other modules are present' );

		$api = new ApiMain( new FauxRequest( [
			'action' => 'query', 'meta' => 'tokens', 'type' => 'login', 'titles' => 'Foo'
		] ) );
		$queryApi = $api->getModuleManager()->getModule( 'query' );
		$this->assertTrue( $queryApi->isReadMode(),
			'isReadMode() => true when other ApiQuery parameters are present' );

		$api = new ApiMain( new FauxRequest( [ 'action' => 'query' ] ) );
		$queryApi = $api->getModuleManager()->getModule( 'query' );
		$this->assertTrue( $queryApi->isReadMode(),
			'isReadMode() => true when no modules are requested' );
	}
}
