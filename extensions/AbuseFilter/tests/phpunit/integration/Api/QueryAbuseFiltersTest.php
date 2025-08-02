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
				'userIdentity' => $performer,
				'timestamp' => $this->getDb()->timestamp( '20190827000000' ),
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
				'userIdentity' => $performer,
				'timestamp' => '20000101000000',
			] ),
			MutableFilter::newDefault()
		) );

		// Verify that the expected number of DB rows were created
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter' )
			->caller( __METHOD__ )
			->assertFieldValue( 2 );
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter_history' )
			->caller( __METHOD__ )
			->assertFieldValue( 2 );
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
		$this->assertSame(
			[
				[
					'id' => 1,
					'description' => 'Filter with protected variables',
					'pattern' => 'user_unnamed_ip = "1.2.3.4"',
					'actions' => 'tags',
					'hits' => 1,
					'comments' => '',
					'lasteditor' => 'UTSysop',
					'lastedittime' => '2019-08-27T00:00:00Z',
					'protected' => '',
					'enabled' => '',
				],
				[
					'id' => 2,
					'description' => 'Filter without protected variables',
					'pattern' => 'user_name = "1.2.3.4"',
					'actions' => '',
					'hits' => 0,
					'comments' => '',
					'lasteditor' => 'UTSysop',
					'lastedittime' => '2000-01-01T00:00:00Z',
					'enabled' => '',
				]
			],
			$result['query']['abusefilters']
		);
	}

	public function testExecuteForUserWhoCannotSeeProtectedVariables() {
		[ $result ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'abusefilters',
			'abfprop' => 'id|description|pattern|actions|hits|comments|' .
				'lasteditor|lastedittime|status|private|protected',
		], null, false, $this->authorityCannotUseProtectedVar );
		$this->assertSame(
			[
				[
					'id' => 1,
					'description' => 'Filter with protected variables',
					'actions' => 'tags',
					'hits' => 1,
					'lasteditor' => 'UTSysop',
					'lastedittime' => '2019-08-27T00:00:00Z',
					'protected' => '',
					'enabled' => '',
				],
				[
					'id' => 2,
					'description' => 'Filter without protected variables',
					'pattern' => 'user_name = "1.2.3.4"',
					'actions' => '',
					'hits' => 0,
					'comments' => '',
					'lasteditor' => 'UTSysop',
					'lastedittime' => '2000-01-01T00:00:00Z',
					'enabled' => '',
				]
			],
			$result['query']['abusefilters']
		);
	}
}
