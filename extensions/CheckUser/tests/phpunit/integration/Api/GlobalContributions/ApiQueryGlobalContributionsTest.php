<?php
namespace MediaWiki\CheckUser\Tests\Integration\Api\GlobalContributions;

use MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPager;
use MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPagerFactory;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\FakeResultWrapper;

/**
 * @group Database
 * @covers \MediaWiki\CheckUser\Api\GlobalContributions\ApiQueryGlobalContributions
 */
class ApiQueryGlobalContributionsTest extends ApiTestCase {
	private GlobalContributionsPagerFactory $pagerFactory;

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );

		$this->pagerFactory = $this->createMock( GlobalContributionsPagerFactory::class );

		$this->setService( 'CheckUserGlobalContributionsPagerFactory', $this->pagerFactory );

		$this->overrideConfigValue(
			'CheckUserGlobalContributionsCentralWikiId',
			WikiMap::getCurrentWikiId()
		);
	}

	/**
	 * @dataProvider provideNonRegisteredUserNames
	 */
	public function testShouldRejectNonRegisteredUserName( string $userName ): void {
		$this->expectApiErrorCode( 'invaliduser' );

		$this->pagerFactory->expects( $this->never() )
			->method( 'createPager' );

		$this->doApiRequest( [
			'action' => 'query',
			'list' => 'globalcontributions',
			'guctarget' => $userName,
		] );
	}

	public static function provideNonRegisteredUserNames(): iterable {
		yield 'IP address' => [ '127.0.0.1' ];
		yield 'invalid user name' => [ '#invalid' ];
	}

	public function testShouldRejectLoggedOutPerformer(): void {
		$this->expectApiErrorCode( 'mustbeloggedin-generic' );

		$this->pagerFactory->expects( $this->never() )
			->method( 'createPager' );

		$performer = $this->getServiceContainer()
			->getUserFactory()
			->newAnonymous( '127.0.0.1' );

		$this->doApiRequest( [
			'action' => 'query',
			'list' => 'globalcontributions',
			'guctarget' => 'TestUser',
		], null, null, $performer );
	}

	/**
	 * @dataProvider provideContributionsData
	 *
	 * @param int|null $limit Limit to set in the API request, or `null` to set no limit parameter
	 * @param string|null $offset Offset to set in the API request, or `null` to set no offset parameter
	 * @param string|null $nextOffset Continuation offset to return from the pager,
	 * or `null` to not return any continuation
	 * @param bool $hasExternalApiLookupError Whether the pager should report an external API lookup error
	 */
	public function testShouldReturnContributions(
		?int $limit,
		?string $offset,
		?string $nextOffset,
		bool $hasExternalApiLookupError
	): void {
		$pager = $this->createMock( GlobalContributionsPager::class );
		$pager->expects( $this->once() )
			->method( 'setLimit' )
			->with( $limit ?? 50 );
		$pager->expects( $this->once() )
			->method( 'setOffset' )
			->with( $offset ?? '' );
		$pager->expects( $this->once() )
			->method( 'doQuery' );

		$pager->method( 'hasExternalApiLookupError' )
			->willReturn( $hasExternalApiLookupError );
		$pager->method( 'getPagingQueries' )
			->willReturn( $nextOffset ? [ 'next' => [ 'offset' => $nextOffset ] ] : [ 'next' => false ] );

		$pager->method( 'getResult' )
			->willReturn( new FakeResultWrapper( [
				(object)[
					'sourcewiki' => 'testwiki',
					'rev_id' => 123,
					'rev_timestamp' => '20210101000000',
				],
			] ) );

		$this->pagerFactory->method( 'createPager' )
			->willReturn( $pager );

		[ $res ] = $this->doApiRequest( array_filter( [
			'action' => 'query',
			'list' => 'globalcontributions',
			'guctarget' => 'TestUser',
			'guclimit' => $limit,
			'gucoffset' => $offset,
		] ) );

		$this->assertCount( 1, $res['query']['globalcontributions']['entries'] );
		$this->assertSame( 'testwiki', $res['query']['globalcontributions']['entries'][0]['wikiid'] );

		if ( $hasExternalApiLookupError ) {
			// NB: It doesn't seem possible to force the API to output a message key here,
			// even with uselang=qqx, so compare the canonical text instead.
			$msg = strip_tags( wfMessage( 'checkuser-global-contributions-api-lookup-error' )->text() );
			$this->assertSame( $msg, $res['warnings']['globalcontributions']['warnings'] );
		}

		if ( $nextOffset ) {
			$this->assertSame( $nextOffset, $res['continue']['gucoffset'] );
		} else {
			$this->assertArrayNotHasKey( 'continue', $res );
		}
	}

	public static function provideContributionsData(): iterable {
		yield 'default limit and offset, no API error, no continuation' => [
			null, null, null, false
		];
		yield 'custom limit and offset, no API error, with continuation' => [
			10, '20210101000000', '20210101010000', false
		];
		yield 'default limit and offset, with API error, no continuation' => [
			null, null, null, true
		];
		yield 'custom limit and offset, with API error, with continuation' => [
			10, '20210101000000', '20210101010000', true
		];
		yield 'default limit, custom offset, no API error, no continuation' => [
			null, '20210101000000', null, false
		];
	}
}
