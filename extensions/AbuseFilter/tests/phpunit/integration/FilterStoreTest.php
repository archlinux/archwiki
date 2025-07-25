<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\FilterStore;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Test
 * @group AbuseFilter
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\FilterStore
 */
class FilterStoreTest extends MediaWikiIntegrationTestCase {
	use FilterFromSpecsTestTrait;
	use MockAuthorityTrait;

	/**
	 * @param int $id
	 */
	private function createFilter( int $id ): void {
		$filter = $this->getFilterFromSpecs( [ 'id' => $id ] );
		$oldFilter = MutableFilter::newDefault();
		// Use some black magic to bypass checks
		/** @var FilterStore $filterStore */
		$filterStore = TestingAccessWrapper::newFromObject( AbuseFilterServices::getFilterStore() );
		$row = $filterStore->filterToDatabaseRow( $filter, $oldFilter );
		$row['af_actor'] = $this->getServiceContainer()->getActorNormalization()->acquireActorId(
			$this->getTestUser()->getUserIdentity(),
			$this->getDb()
		);
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'abuse_filter' )
			->row( $row )
			->caller( __METHOD__ )
			->execute();
	}

	public function testSaveFilter_valid() {
		$row = [
			'id' => null,
			'rules' => '/* My rules */',
			'name' => 'Some new filter',
			'enabled' => false,
			'deleted' => true
		];

		$origFilter = MutableFilter::newDefault();
		$newFilter = $this->getFilterFromSpecs( $row );

		$status = AbuseFilterServices::getFilterStore()->saveFilter(
			$this->getTestSysop()->getUser(), $row['id'], $newFilter, $origFilter
		);

		$this->assertStatusGood( $status );
		$value = $status->getValue();
		$this->assertIsArray( $value );
		$this->assertCount( 2, $value );
		$this->assertContainsOnly( 'int', $value );
	}

	public function testSaveFilter_invalid() {
		$row = [
			'id' => null,
			'rules' => '1==1',
			'name' => 'Restricted action',
			'actions' => [
				'degroup' => [],
			]
		];

		// We use restricted actions because that's the last check
		$expectedError = 'abusefilter-edit-restricted';

		$origFilter = MutableFilter::newDefault();
		$newFilter = $this->getFilterFromSpecs( $row );

		$user = $this->getTestUser()->getUser();
		// Assign -modify and -modify-global, but not -modify-restricted
		$this->overrideUserPermissions( $user, [ 'abusefilter-modify' ] );
		$status = AbuseFilterServices::getFilterStore()->saveFilter( $user, $row['id'], $newFilter, $origFilter );

		$this->assertStatusWarning( $expectedError, $status );
	}

	public function testSaveFilter_noChange() {
		$row = [
			'id' => '1',
			'rules' => '/**/',
			'name' => 'Mock filter'
		];

		$filter = $row['id'];
		$this->createFilter( $filter );
		$origFilter = AbuseFilterServices::getFilterLookup()->getFilter( $filter, false );
		$newFilter = $this->getFilterFromSpecs( $row );

		$status = AbuseFilterServices::getFilterStore()->saveFilter(
			$this->getTestSysop()->getUser(), $filter, $newFilter, $origFilter
		);

		$this->assertStatusGood( $status );
		$this->assertFalse( $status->getValue(), 'Status value should be false' );
	}

	public function testSaveFilter__usesProtectedVarsButUserLacksRight() {
		$row = [
			'id' => '2',
			'rules' => "ip_in_range( user_unnamed_ip, '1.2.3.4' )",
			'name' => 'Mock filter with protected variable used'
		];

		$origFilter = MutableFilter::newDefault();
		$newFilter = $this->getFilterFromSpecs( $row );

		// Try to save filter without right to use protected variables
		$status = AbuseFilterServices::getFilterStore()->saveFilter(
			$this->mockRegisteredNullAuthority(), $row['id'], $newFilter, $origFilter
		);
		$expectedError = 'abusefilter-edit-protected-variable';
		$this->assertStatusWarning( $expectedError, $status );
	}

	public function testSaveFilter__usesProtectedVarsButFilterNotConfiguredToBeProtected() {
		$row = [
			'id' => '2',
			'rules' => "ip_in_range( user_unnamed_ip, '1.2.3.4' )",
			'name' => 'Mock filter with protected variable used'
		];

		$origFilter = MutableFilter::newDefault();
		$newFilter = $this->getFilterFromSpecs( $row );

		// Add right and try to save filter without setting the 'protected' flag
		$status = AbuseFilterServices::getFilterStore()->saveFilter(
			$this->mockRegisteredUltimateAuthority(), $row['id'], $newFilter, $origFilter
		);
		$expectedError = 'abusefilter-edit-protected-variable-not-protected';
		$this->assertStatusWarning( $expectedError, $status );
	}

	public function testSaveFilter__usesProtectedVarsAndSaveIsSuccessful() {
		// Save filter with right, with 'protected' flag enabled
		$row = [
			'id' => '3',
			'rules' => "ip_in_range( user_unnamed_ip, '1.2.3.4' )",
			'name' => 'Mock filter with protected variable used',
			'privacy' => Flags::FILTER_USES_PROTECTED_VARS,
		];
		$newFilter = $this->getFilterFromSpecs( $row );
		$status = AbuseFilterServices::getFilterStore()->saveFilter(
			$this->mockRegisteredUltimateAuthority(), $row['id'], $newFilter, MutableFilter::newDefault()
		);
		$this->assertStatusGood( $status );
	}

	public function testSaveFilter__cannotRemoveProtectedFlag() {
		$row = [
			'id' => null,
			'rules' => "ip_in_range( user_unnamed_ip, '1.2.3.4' )",
			'name' => 'Uses protected variable',
			'enabled' => true,
			'privacy' => Flags::FILTER_USES_PROTECTED_VARS,
		];

		$origFilter = $this->getFilterFromSpecs( $row );
		$newFilter = MutableFilter::newFromParentFilter( $origFilter );
		$newFilter->setRules( '1 + 1 === 2' );
		$newFilter->setProtected( false );

		$status = AbuseFilterServices::getFilterStore()->saveFilter(
			$this->getTestSysop()->getUser(), null, $newFilter, $origFilter
		);

		$this->assertStatusGood( $status );
		$filterID = $status->getValue()[0];
		$storedFilter = AbuseFilterServices::getFilterLookup()->getFilter( $filterID, false );
		$this->assertTrue( $storedFilter->isProtected() );
	}
}
