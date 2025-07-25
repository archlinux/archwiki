<?php

namespace MediaWiki\CheckUser\Tests\Integration\GlobalContributions;

use LogicException;
use MediaWiki\CheckUser\GlobalContributions\CheckUserApiRequestAggregator;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Site\MediaWikiSite;
use MediaWiki\Site\SiteLookup;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\Http\MultiHttpClient;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\GlobalContributions\CheckUserApiRequestAggregator
 * @group CheckUser
 * @group Database
 */
class CheckUserApiRequestAggregatorTest extends MediaWikiIntegrationTestCase {
	private string $localWiki;
	private string $externalWiki;

	protected function setUp(): void {
		parent::setUp();
		$this->localWiki = (string)WikiMap::getCurrentWikiDbDomain();
		$this->externalWiki = 'otherwiki';
	}

	private function getMockHttpRequestFactory( int $authMode = CheckUserApiRequestAggregator::AUTHENTICATE_NONE ) {
		$multiHttpClient = $this->createMock( MultiHttpClient::class );
		$multiHttpClient->method( 'runMulti' )
			->willReturnCallback( function ( array $reqs ) use ( $authMode ): array {
				$authTokens = array_filter(
					array_map( static fn ( $req ) => $req['query']['centralauthtoken'] ?? null, $reqs )
				);

				if ( $authMode === CheckUserApiRequestAggregator::AUTHENTICATE_CENTRAL_AUTH ) {
					$this->assertSameSize( $reqs, $authTokens, 'All requests should have a CentralAuth token' );
					$this->assertSame(
						array_unique( $authTokens ),
						$authTokens,
						'All requests should have unique CentralAuth tokens'
					);
				} else {
					$this->assertSame( [], $authTokens, 'No requests should have a CentralAuth token' );
				}

				return [
					$this->localWiki => [
						'response' => [
							'code' => 200,
							'body' => '"local results"'
						]
					],
					$this->externalWiki => [
						'response' => [
							'code' => 200,
							'body' => '"external results"'
						]
					],
				];
			} );

		$httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$httpRequestFactory->method( 'createMultiClient' )
			->willReturn( $multiHttpClient );

		return $httpRequestFactory;
	}

	private function getMockSiteLookup() {
		$site = $this->createMock( MediaWikiSite::class );
		$site->method( 'getFileUrl' )
			->willReturn( 'test' );

		$siteLookup = $this->createMock( SiteLookup::class );
		$siteLookup->method( 'getSite' )
			->willReturn( $site );

		return $siteLookup;
	}

	public function testExecuteAuthenticateCentralAuthUnavailable() {
		$user = $this->getTestUser()->getUser();
		$params = [ 'testParamName' => 'testValue' ];
		$wikis = [ $this->localWiki, $this->externalWiki ];

		$this->expectException( LogicException::class );

		$apiRequestAggregator = new CheckUserApiRequestAggregator(
			$this->getMockHttpRequestFactory(),
			$this->createMock( CentralIdLookup::class ),
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getMockSiteLookup(),
			$this->createMock( LoggerInterface::class )
		);

		$results = $apiRequestAggregator->execute(
			$user,
			$params,
			$wikis,
			RequestContext::getMain()->getRequest(),
			CheckUserApiRequestAggregator::AUTHENTICATE_CENTRAL_AUTH
		);

		$this->assertArrayHasKey( $this->localWiki, $results );
		$this->assertSame( 'local results', $results[$this->localWiki] );
		$this->assertSame( 'external results', $results[$this->externalWiki] );
	}

	public function testExecuteAuthenticateCentralAuth() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$testUser = $this->getTestUser();
		$user = $testUser->getUser();
		$centralUser = CentralAuthUser::getPrimaryInstance( $user );
		$centralUser->register( $testUser->getPassword(), null );
		$centralUser->attach( WikiMap::getCurrentWikiId() );

		$params = [ 'testParamName' => 'testValue' ];
		$wikis = [ $this->localWiki, $this->externalWiki ];

		$apiRequestAggregator = new CheckUserApiRequestAggregator(
			$this->getMockHttpRequestFactory( CheckUserApiRequestAggregator::AUTHENTICATE_CENTRAL_AUTH ),
			$this->getServiceContainer()->getCentralIdLookup(),
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getMockSiteLookup(),
			$this->createMock( LoggerInterface::class )
		);

		$results = $apiRequestAggregator->execute(
			$user,
			$params,
			$wikis,
			RequestContext::getMain()->getRequest(),
			CheckUserApiRequestAggregator::AUTHENTICATE_CENTRAL_AUTH
		);

		$wrappedAggregator = TestingAccessWrapper::newFromObject( $apiRequestAggregator );

		$this->assertArrayHasKey( $this->localWiki, $results );
		$this->assertSame( 'local results', $results[$this->localWiki] );
		$this->assertSame( 'external results', $results[$this->externalWiki] );
	}

	public function testExecuteAuthenticateNone() {
		$user = $this->getTestUser()->getUser();
		$params = [ 'testParamName' => 'testValue' ];
		$wikis = [ $this->localWiki, $this->externalWiki ];

		$apiRequestAggregator = new CheckUserApiRequestAggregator(
			$this->getMockHttpRequestFactory(),
			$this->createMock( CentralIdLookup::class ),
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getMockSiteLookup(),
			$this->createMock( LoggerInterface::class )
		);

		$results = $apiRequestAggregator->execute(
			$user,
			$params,
			$wikis,
			RequestContext::getMain()->getRequest(),
			CheckUserApiRequestAggregator::AUTHENTICATE_NONE
		);

		$this->assertArrayHasKey( $this->localWiki, $results );
		$this->assertSame( 'local results', $results[$this->localWiki] );
		$this->assertSame( 'external results', $results[$this->externalWiki] );
	}

	/**
	 * @dataProvider provideWikis
	 */
	public function testExecuteNoWikis( $wikis ) {
		$user = $this->getTestUser()->getUser();
		$params = [ 'testParamName' => 'testValue' ];

		$apiRequestAggregator = new CheckUserApiRequestAggregator(
			$this->getMockHttpRequestFactory(),
			$this->createMock( CentralIdLookup::class ),
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->createMock( SiteLookup::class ),
			$this->createMock( LoggerInterface::class )
		);

		$results = $apiRequestAggregator->execute(
			$user,
			$params,
			$wikis,
			RequestContext::getMain()->getRequest(),
			CheckUserApiRequestAggregator::AUTHENTICATE_NONE
		);

		$this->assertSame( [], $results );
	}

	public static function provideWikis() {
		return [
			'No wikis' => [ [] ],
			'No wikis in the sites table' => [ [ 'unknownWiki' ] ],
		];
	}
}
