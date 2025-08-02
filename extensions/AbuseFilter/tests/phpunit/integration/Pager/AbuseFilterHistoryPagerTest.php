<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Pager;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\Pager\AbuseFilterHistoryPager;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\FilterFromSpecsTestTrait;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Pager\AbuseFilterHistoryPager
 * @group Database
 */
class AbuseFilterHistoryPagerTest extends MediaWikiIntegrationTestCase {
	use FilterFromSpecsTestTrait;
	use MockAuthorityTrait;

	/**
	 * @inheritDoc
	 */
	public function addDBDataOnce() {
		$filterStore = AbuseFilterServices::getFilterStore();
		$performer = $this->getTestSysop()->getUserIdentity();
		$authority = new UltimateAuthority( $performer );

		// Create a test filter with two revisions that is protected, where the first revision was not
		// protected and the filter uses user_unnamed_ip.
		ConvertibleTimestamp::setFakeTime( '20190825000000' );
		$firstFilterRevision = $this->getFilterFromSpecs( [
			'id' => '1',
			'rules' => 'user_name = "1.2.3.5"',
			'name' => 'Filter to be converted',
			'privacy' => Flags::FILTER_PUBLIC,
			'userIdentity' => $performer,
			'timestamp' => $this->getDb()->timestamp( '20190825000000' ),
		] );
		$this->assertStatusGood( $filterStore->saveFilter(
			$authority, null, $firstFilterRevision, MutableFilter::newDefault()
		) );
		ConvertibleTimestamp::setFakeTime( '20190827000000' );
		$this->assertStatusGood( $filterStore->saveFilter(
			$authority, 1,
			$this->getFilterFromSpecs( [
				'id' => '1',
				'rules' => 'user_unnamed_ip = "1.2.3.5"',
				'name' => 'Filter with protected variables',
				'privacy' => Flags::FILTER_USES_PROTECTED_VARS,
				'userIdentity' => $performer,
				'timestamp' => $this->getDb()->timestamp( '20190827000000' ),
				'hitCount' => 1,
			], [ 'tags' => [ 'test' ] ] ),
			$firstFilterRevision
		) );

		// Create a filter which is protected but does not use user_unnamed_ip
		ConvertibleTimestamp::setFakeTime( '20180828000000' );
		$this->assertStatusGood( $filterStore->saveFilter(
			$authority, null,
			$this->getFilterFromSpecs( [
				'id' => '2',
				'rules' => 'user_name = "1.2.3.5"',
				'name' => 'Protected filter without user_unnamed_ip',
				'privacy' => Flags::FILTER_USES_PROTECTED_VARS,
				'userIdentity' => $performer,
				'timestamp' => $this->getDb()->timestamp( '20180828000000' ),
				'hitCount' => 1,
			], [ 'tags' => [ 'test' ] ] ),
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
			->assertFieldValue( 3 );
	}

	public function testFilterHistoryWhenUserCanSeeAllFilterVersions() {
		$this->setUserLang( 'qqx' );
		// Get an instance of the pager with our mock AbuseFilterPermissionManager which has no filtering applied.
		$pager = new AbuseFilterHistoryPager(
			RequestContext::getMain(),
			$this->getServiceContainer()->getLinkRenderer(),
			$this->getServiceContainer()->getLinkBatchFactory(),
			AbuseFilterServices::getFilterLookup( $this->getServiceContainer() ),
			AbuseFilterServices::getSpecsFormatter( $this->getServiceContainer() ),
			AbuseFilterServices::getPermissionManager( $this->getServiceContainer() ),
			null,
			null,
			true
		);
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );
		$pager->setContext( $context );

		// Get the HTML returned by the pager, and expect that a fully privileged user can see all filter versions.
		$html = $pager->getBody();
		$this->assertStringContainsString( 'Protected filter without user_unnamed_ip', $html );
		$this->assertStringContainsString( 'Filter with protected variables', $html );
		$this->assertStringContainsString( 'Filter to be converted', $html );
	}

	/**
	 * Returns a mock AbuseFilterPermissionManager that fakes the user has access to protected filters
	 * but not to the user_unnamed_ip protected variable.
	 *
	 * @return AbuseFilterPermissionManager&MockObject
	 */
	private function getMockAFPermissionManager() {
		$mockPermissionCallback = static function ( $authority, $variables ) {
			if ( count( $variables ) && in_array( 'user_unnamed_ip', $variables ) ) {
				return AbuseFilterPermissionStatus::newFatal( 'test' );
			} else {
				return AbuseFilterPermissionStatus::newGood();
			}
		};
		$mockAbuseFilterPermissionManager = $this->createMock( AbuseFilterPermissionManager::class );
		$mockAbuseFilterPermissionManager->method( 'canViewProtectedVariables' )
			->willReturnCallback( $mockPermissionCallback );
		$mockAbuseFilterPermissionManager->method( 'canViewProtectedVariablesInFilter' )
			->willReturnCallback( function ( $authority, $filter ) use ( $mockPermissionCallback ) {
				$ruleChecker = $this->getServiceContainer()->get( RuleCheckerFactory::SERVICE_NAME )->newRuleChecker();
				$variables = $ruleChecker->getUsedVars( $filter->getRules() );
				return $mockPermissionCallback( $authority, $variables );
			} );
		return $mockAbuseFilterPermissionManager;
	}

	/** @dataProvider provideLimitValues */
	public function testFilterHistoryWhenUserLacksAccessToOneProtectedVariable( $limit ) {
		$this->setUserLang( 'qqx' );
		// Get an instance of the pager with our mock AbuseFilterPermissionManager which has no filtering applied.
		$pager = new AbuseFilterHistoryPager(
			RequestContext::getMain(),
			$this->getServiceContainer()->getLinkRenderer(),
			$this->getServiceContainer()->getLinkBatchFactory(),
			AbuseFilterServices::getFilterLookup( $this->getServiceContainer() ),
			AbuseFilterServices::getSpecsFormatter( $this->getServiceContainer() ),
			$this->getMockAFPermissionManager(),
			null,
			null,
			true
		);

		// Set the limit at one, so that we get to check that the row is displayed even if all rows in the current
		// page were excluded.
		$pager->setLimit( $limit );

		// Get the HTML returned by the pager, and expect that only the protected filter without user_unnamed_ip
		// is displayed (as all other filter versions are associated with a protected filter that uses
		// user_unnamed_ip).
		$html = $pager->getBody();
		$this->assertStringContainsString( 'Protected filter without user_unnamed_ip', $html );
		$this->assertStringNotContainsString( 'Filter with protected variables', $html );
		$this->assertStringNotContainsString( 'Filter to be converted', $html );
	}

	public static function provideLimitValues() {
		return [
			'Limit of 1' => [ 1 ],
			'Limit of 10' => [ 10 ],
		];
	}

	public function testExecuteWhenFilteringByUserWhoHasNotEditedAnAbuseFilter() {
		$this->setUserLang( 'qqx' );
		$pager = new AbuseFilterHistoryPager(
			RequestContext::getMain(),
			$this->getServiceContainer()->getLinkRenderer(),
			$this->getServiceContainer()->getLinkBatchFactory(),
			AbuseFilterServices::getFilterLookup( $this->getServiceContainer() ),
			AbuseFilterServices::getSpecsFormatter( $this->getServiceContainer() ),
			AbuseFilterServices::getPermissionManager( $this->getServiceContainer() ),
			null,
			$this->getMutableTestUser()->getUserIdentity()->getName(),
			true
		);

		// Check that the output indicates no results.
		$html = $pager->getBody();
		$this->assertStringContainsString( '(table_pager_empty)', $html );
	}
}
