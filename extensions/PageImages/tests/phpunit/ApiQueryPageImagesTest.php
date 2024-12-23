<?php

namespace PageImages\Tests;

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiPageSet;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\IContextSource;
use MediaWiki\Page\PageReferenceValue;
use MediaWikiIntegrationTestCase;
use PageImages\ApiQueryPageImages;
use PageImages\PageImages;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \PageImages\ApiQueryPageImages
 *
 * @group PageImages
 *
 * @license WTFPL
 * @author Sam Smith
 * @author Thiemo Kreuz
 */
class ApiQueryPageImagesTest extends MediaWikiIntegrationTestCase {

	private function newInstance() {
		$config = new HashConfig( [
			'PageImagesAPIDefaultLicense' => 'free'
		] );

		$context = $this->createMock( IContextSource::class );

		$context->method( 'getConfig' )
			->willReturn( $config );

		$main = $this->createMock( ApiMain::class );
		$main->expects( $this->once() )
			->method( 'getContext' )
			->willReturn( $context );

		$query = $this->createMock( ApiQuery::class );
		$query->expects( $this->once() )
			->method( 'getMain' )
			->willReturn( $main );

		return new ApiQueryPageImages( $query, '', $this->getServiceContainer()->getRepoGroup() );
	}

	public function testConstructor() {
		$instance = $this->newInstance();
		$this->assertInstanceOf( ApiQueryPageImages::class, $instance );
	}

	public function testGetCacheMode() {
		$instance = $this->newInstance();
		$this->assertSame( 'public', $instance->getCacheMode( [] ) );
	}

	public function testGetAllowedParams() {
		$instance = $this->newInstance();
		$params = $instance->getAllowedParams();

		$this->assertIsArray( $params );
		$this->assertNotEmpty( $params );
		$this->assertContainsOnly( 'array', $params );
		$this->assertArrayHasKey( 'limit', $params );
		$this->assertSame( 50, $params['limit'][ParamValidator::PARAM_DEFAULT] );
		$this->assertSame( 'limit', $params['limit'][ParamValidator::PARAM_TYPE] );
		$this->assertSame( 1, $params['limit'][IntegerDef::PARAM_MIN] );
		$this->assertSame( 50, $params['limit'][IntegerDef::PARAM_MAX] );
		$this->assertSame( 100, $params['limit'][IntegerDef::PARAM_MAX2] );
		$this->assertArrayHasKey( 'license', $params );
		$this->assertSame( [ 'free', 'any' ], $params['license'][ParamValidator::PARAM_TYPE] );
		$this->assertSame( 'free', $params['license'][ParamValidator::PARAM_DEFAULT] );
		$this->assertFalse( $params['license'][ParamValidator::PARAM_ISMULTI] );
	}

	/**
	 * @dataProvider provideGetTitles
	 */
	public function testGetTitles( $pageNames, $missingTitlesByNamespace, $expectedNames ) {
		$titleParser = $this->getServiceContainer()->getTitleParser();
		$titles = [];
		foreach ( $pageNames as $pageName ) {
			$titleValue = $titleParser->parseTitle( $pageName );
			$titles[] = PageReferenceValue::localReference( $titleValue->getNamespace(), $titleValue->getDBkey() );
		}
		$expected = [];
		foreach ( $expectedNames as $id => $expectedName ) {
			$titleValue = $titleParser->parseTitle( $expectedName );
			$expected[$id] = PageReferenceValue::localReference( $titleValue->getNamespace(), $titleValue->getDBkey() );
		}

		$pageSet = $this->createMock( ApiPageSet::class );
		$pageSet->method( 'getGoodPages' )
			->willReturn( $titles );
		$pageSet->method( 'getMissingTitlesByNamespace' )
			->willReturn( $missingTitlesByNamespace );
		$queryPageImages = new ApiQueryPageImagesProxyMock( $pageSet );

		$this->assertEquals( $expected, $queryPageImages->getTitles() );
	}

	public static function provideGetTitles() {
		return [
			[
				[ 'Foo' ],
				[],
				[ 'Foo' ],
			],
			[
				[ 'Foo' ],
				[
					NS_TALK => [
						'Bar' => -1,
					],
				],
				[ 'Foo' ],
			],
			[
				[ 'Foo' ],
				[
					NS_FILE => [
						'Bar' => -1,
					],
				],
				[
					0 => 'Foo',
					-1 => 'File:Bar',
				],
			],
		];
	}

	/**
	 * @dataProvider provideExecute
	 * @param array $requestParams Request parameters to the API
	 * @param array $pageNames Page titles passed to the API
	 * @param array $queryPageIds Page IDs that will be used for querying the DB.
	 * @param array $queryResults Results of the DB select query
	 * @param int $setResultValueCount The number results the API returned
	 */
	public function testExecute( $requestParams, $pageNames, $queryPageIds,
		$queryResults, $setResultValueCount
	) {
		$titles = [];
		foreach ( $pageNames as $pageName ) {
			$titles[] = PageReferenceValue::localReference( NS_MAIN, $pageName );
		}
		$mock = TestingAccessWrapper::newFromObject(
			$this->getMockBuilder( ApiQueryPageImages::class )
				->disableOriginalConstructor()
				->onlyMethods( [ 'extractRequestParams', 'getTitles', 'dieWithError',
					'addTables', 'addFields', 'addWhere', 'select', 'setResultValues' ] )
				->getMock()
		);
		$mock->method( 'extractRequestParams' )
			->willReturn( $requestParams );
		$mock->method( 'getTitles' )
			->willReturn( $titles );
		$mock->method( 'select' )
			->willReturn( new FakeResultWrapper( $queryResults ) );

		// continue page ID is not found
		if ( isset( $requestParams['continue'] )
			&& $requestParams['continue'] > count( $titles )
		) {
			$mock->expects( $this->once() )
				->method( 'dieWithError' );
		}

		$originalRequested = in_array( 'original', $requestParams['prop'] );
		$this->assertTrue( $this->hasExpectedProperties( $queryResults, $originalRequested ) );

		$license = $requestParams['license'] ?? 'free';
		if ( $license == PageImages::LICENSE_ANY ) {
			$propName = [ PageImages::getPropName( true ), PageImages::getPropName( false ) ];
		} else {
			$propName = PageImages::getPropName( true );
		}
		$mock->expects( $this->exactly( count( $queryPageIds ) > 0 ? 1 : 0 ) )
			->method( 'addWhere' )
			->with( [ 'pp_page' => $queryPageIds, 'pp_propname' => $propName ] );

		$mock->expects( $this->exactly( $setResultValueCount ) )
			->method( 'setResultValues' );

		$mock->execute();
	}

	public static function provideExecute() {
		return [
			[
				[ 'prop' => [ 'thumbnail' ], 'thumbsize' => 100, 'limit' => 10,
				  'license' => 'any', 'langcode' => null ],
				[ 'Page 1', 'Page 2' ],
				[ 0, 1 ],
				[
					(object)[ 'pp_page' => 0, 'pp_value' => 'A_Free.jpg',
						'pp_propname' => PageImages::PROP_NAME_FREE ],
					(object)[ 'pp_page' => 0, 'pp_value' => 'A.jpg',
						'pp_propname' => PageImages::PROP_NAME ],
					(object)[ 'pp_page' => 1, 'pp_value' => 'B.jpg',
						'pp_propname' => PageImages::PROP_NAME ],
				],
				2
			],
			[
				[ 'prop' => [ 'thumbnail' ], 'thumbsize' => 200, 'limit' => 10, 'langcode' => null ],
				[],
				[],
				[],
				0
			],
			[
				[ 'prop' => [ 'thumbnail' ], 'continue' => 1, 'thumbsize' => 400,
				  'limit' => 10, 'license' => 'any', 'langcode' => null ],
				[ 'Page 1', 'Page 2' ],
				[ 1 ],
				[
					(object)[ 'pp_page' => 1, 'pp_value' => 'B_Free.jpg',
						'pp_propname' => PageImages::PROP_NAME_FREE ],
					(object)[ 'pp_page' => 1, 'pp_value' => 'B.jpg',
						'pp_propname' => PageImages::PROP_NAME ],
				],
				1
			],
			[
				[ 'prop' => [ 'thumbnail' ], 'thumbsize' => 500, 'limit' => 10,
				  'license' => 'any', 'langcode' => 'en' ],
				[ 'Page 1', 'Page 2' ],
				[ 0, 1 ],
				[
					(object)[ 'pp_page' => 1, 'pp_value' => 'B_Free.jpg',
						'pp_propname' => PageImages::PROP_NAME ],
				],
				1
			],
			[
				[ 'prop' => [ 'thumbnail' ], 'continue' => 1, 'thumbsize' => 500,
				  'limit' => 10, 'license' => 'any', 'langcode' => 'de' ],
				[ 'Page 1', 'Page 2' ],
				[ 1 ],
				[
					(object)[ 'pp_page' => 1, 'pp_value' => 'B_Free.jpg',
						'pp_propname' => PageImages::PROP_NAME_FREE ],
				],
				1
			],
			[
				[ 'prop' => [ 'thumbnail' ], 'thumbsize' => 510, 'limit' => 10,
				  'license' => 'free', 'langcode' => 'de' ],
				[ 'Page 1', 'Page 2' ],
				[ 0, 1 ],
				[],
				0
			],
			[
				[ 'prop' => [ 'thumbnail' ], 'thumbsize' => 510, 'limit' => 10,
				  'license' => 'free', 'langcode' => 'en' ],
				[ 'Page 1', 'Page 2' ],
				[ 0, 1 ],
				[
					(object)[ 'pp_page' => 0, 'pp_value' => 'A_Free.jpg',
						'pp_propname' => PageImages::PROP_NAME_FREE ],
					(object)[ 'pp_page' => 1, 'pp_value' => 'B_Free.jpg',
						'pp_propname' => PageImages::PROP_NAME_FREE ],
				],
				2
			],
			[
				[ 'prop' => [ 'thumbnail', 'original' ], 'thumbsize' => 510,
				  'limit' => 10, 'license' => 'free', 'langcode' => 'en' ],
				[ 'Page 1', 'Page 2' ],
				[ 0, 1 ],
				[
					(object)[
						'pp_page' => 0, 'pp_value' => 'A_Free.jpg',
						'pp_value_original' => 'A_Free_original.jpg', 'pp_original_width' => 80,
						'pp_original_height' => 80, 'pp_propname' => PageImages::PROP_NAME_FREE
					],
					(object)[
						'pp_page' => 1, 'pp_value' => 'B_Free.jpg',
						'pp_value_original' => 'B_Free_original.jpg', 'pp_original_width' => 80,
						'pp_original_height' => 80, 'pp_propname' => PageImages::PROP_NAME_FREE
					],
				],
				2
			],
		];
	}

	private function hasExpectedProperties( $queryResults, $originalRequested ) {
		if ( $originalRequested ) {
			return $this->allResultsHaveProperty( $queryResults, 'pp_value_original' )
				&& $this->allResultsHaveProperty( $queryResults, 'pp_original_width' )
				&& $this->allResultsHaveProperty( $queryResults, 'pp_original_height' );
		} else {
			return $this->noResultsHaveProperty( $queryResults, 'pp_value_original' )
				&& $this->noResultsHaveProperty( $queryResults, 'pp_original_width' )
				&& $this->noResultsHaveProperty( $queryResults, 'pp_original_height' );
		}
	}

	private function noResultsHaveProperty( $queryResults, $propName ) {
		foreach ( $queryResults as $result ) {
			if ( property_exists( $result, $propName ) ) {
				return false;
			}
		}
		return true;
	}

	private function allResultsHaveProperty( $queryResults, $propName ) {
		foreach ( $queryResults as $result ) {
			if ( !property_exists( $result, $propName ) ) {
				return false;
			}
		}
		return true;
	}
}
