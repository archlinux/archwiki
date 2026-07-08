<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Api;

use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\FilterFromSpecsTestTrait;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Api\QueryAbuseFilters
 * @group medium
 * @group Database
 */
class QueryAbuseFiltersTest extends ApiTestCase {
	use MockAuthorityTrait;
	use FilterFromSpecsTestTrait;

	private Authority $authorityCannotUseProtectedVar;

	private Authority $authorityCanUseProtectedVar;

	protected function setUp(): void {
		parent::setUp();

		// Clear the protected access hooks, as in CI other extensions (such as CheckUser) may attempt to
		// define additional restrictions that cause the tests to fail.
		$this->clearHook( 'AbuseFilterCanViewProtectedVariables' );

		// Create an authority who can see private filters but not protected variables
		$this->authorityCannotUseProtectedVar = $this->mockUserAuthorityWithPermissions(
			$this->getTestUser()->getUserIdentity(),
			[
				'abusefilter-log-private',
				'abusefilter-view-private',
				'abusefilter-modify',
				'abusefilter-log-detail',
				'abusefilter-view',
			]
		);

		// Create an authority who can see private and protected variables
		$this->authorityCanUseProtectedVar = $this->mockUserAuthorityWithPermissions(
			$this->getTestUser()->getUserIdentity(),
			[
				'abusefilter-access-protected-vars',
				'abusefilter-log-private',
				'abusefilter-view-private',
				'abusefilter-modify',
				'abusefilter-log-detail',
				'abusefilter-view',
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addDBDataOnce() {
		$filterStore = AbuseFilterServices::getFilterStore();
		$performer = $this->getTestSysop()->getUserIdentity();
		$authority = new UltimateAuthority( $performer );

		// Create a test filter that is protected
		ConvertibleTimestamp::setFakeTime( '20190827000000' );
		$this->assertStatusGood( $filterStore->saveFilter(
			$authority, null,
			$this->getFilterFromSpecs( [
				'id' => '1',
				'rules' => 'user_unnamed_ip = "1.2.3.4"',
				'name' => 'Filter with protected variables',
				'privacy' => Flags::FILTER_USES_PROTECTED_VARS,
				'lastEditor' => $performer,
				'lastEditTimestamp' => '20190827000000',
				'hitCount' => 1,
				'actions' => [ 'tags' => [ 'test' ] ],
			] ),
			MutableFilter::newDefault()
		) );

		// Create a second filter which is public
		ConvertibleTimestamp::setFakeTime( '20000101000000' );
		$this->assertStatusGood( $filterStore->saveFilter(
			$authority, null,
			$this->getFilterFromSpecs( [
				'id' => '2',
				'rules' => 'user_name = "1.2.3.4"',
				'name' => 'Filter without protected variables',
				'privacy' => Flags::FILTER_PUBLIC,
				'lastEditor' => $performer,
				'lastEditTimestamp' => '20000101000000',
			] ),
			MutableFilter::newDefault()
		) );

		// Create a third filter which is private (hidden)
		ConvertibleTimestamp::setFakeTime( '20100601000000' );
		$this->assertStatusGood( $filterStore->saveFilter(
			$authority, null,
			$this->getFilterFromSpecs( [
				'id' => '3',
				'rules' => 'action = "edit"',
				'name' => 'Hidden filter',
				'privacy' => Flags::FILTER_HIDDEN,
				'lastEditor' => $performer,
				'lastEditTimestamp' => '20100601000000',
				'hitCount' => 42,
			] ),
			MutableFilter::newDefault()
		) );

		// Verify that the expected number of DB rows were created
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter' )
			->caller( __METHOD__ )
			->assertFieldValue( 3 );
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter_history' )
			->caller( __METHOD__ )
			->assertFieldValue( 3 );
	}

	public function testExecuteWhenUserMissingPermissionToSeeFilters() {
		$this->expectApiErrorCode( 'permissiondenied' );
		$this->doApiRequest( [
			'action' => 'query',
			'list' => 'abusefilters',
		], null, false, $this->mockRegisteredNullAuthority() );
	}

	public function testExecuteForUserWhoCanSeeProtectedVariables() {
		[ $result ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'abusefilters',
			'abfprop' => 'id|description|pattern|actions|hits|comments|' .
				'lasteditor|lastedittime|status|private|protected',
		], null, false, $this->authorityCanUseProtectedVar );
		$filters = $result['query']['abusefilters'];
		// User with protected var access should see hits for all filters
		$this->assertSame( 1, $filters[0]['id'] );
		$this->assertSame( 1, $filters[0]['hits'] );
		$this->assertSame( 2, $filters[1]['id'] );
		$this->assertSame( 0, $filters[1]['hits'] );
		$this->assertSame( 3, $filters[2]['id'] );
		$this->assertSame( 42, $filters[2]['hits'] );
	}

	public function testExecuteForUserWhoCannotSeeProtectedVariables() {
		[ $result ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'abusefilters',
			'abfprop' => 'id|description|pattern|actions|hits|comments|' .
				'lasteditor|lastedittime|status|private|protected',
		], null, false, $this->authorityCannotUseProtectedVar );
		$filters = $result['query']['abusefilters'];
		// User without protected var access should NOT see hits for the protected filter
		$this->assertSame( 1, $filters[0]['id'] );
		$this->assertArrayNotHasKey( 'hits', $filters[0],
			'Hit count for protected filter should be hidden from users without protected var access' );
		// But should still see hits for the public filter
		$this->assertSame( 2, $filters[1]['id'] );
		$this->assertSame( 0, $filters[1]['hits'] );
		// And should see hits for hidden filter (this user has abusefilter-log-private)
		$this->assertSame( 3, $filters[2]['id'] );
		$this->assertSame( 42, $filters[2]['hits'] );
	}

	/**
	 * Test that hit count is hidden for hidden (private) filters
	 * from users who lack the abusefilter-log-detail permission.
	 * This is the scenario described in T406954.
	 */
	public function testHitCountHiddenForPrivateFiltersFromUnprivilegedUser() {
		$authorityBasic = $this->mockUserAuthorityWithPermissions(
			$this->getTestUser()->getUserIdentity(),
			[
				'abusefilter-view',
			]
		);
		[ $result ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'abusefilters',
			'abfprop' => 'id|hits',
		], null, false, $authorityBasic );
		$filters = $result['query']['abusefilters'];
		// User without abusefilter-log-detail should not see hits for any filter
		foreach ( $filters as $filter ) {
			$this->assertArrayNotHasKey( 'hits', $filter,
				"Hit count for filter {$filter['id']} should be hidden from users without log-detail" );
		}
	}
}
