<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Filter;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\LastEditInfo;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\Filter\Specs;
use MediaWiki\Extension\AbuseFilter\FilterStore;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Test
 * @group AbuseFilter
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\FilterStore
 */
class FilterStoreTest extends MediaWikiIntegrationTestCase {

	private const DEFAULT_VALUES = [
		'rules' => '/**/',
		'user' => 0,
		'user_text' => 'FilterTester',
		'timestamp' => '20190826000000',
		'enabled' => 1,
		'comments' => '',
		'name' => 'Mock filter',
		'privacy' => Flags::FILTER_PUBLIC,
		'hit_count' => 0,
		'throttled' => 0,
		'deleted' => 0,
		'actions' => [],
		'global' => 0,
		'group' => 'default'
	];

	/**
	 * @param int $id
	 */
	private function createFilter( int $id ): void {
		$row = self::DEFAULT_VALUES;
		$row['timestamp'] = $this->getDb()->timestamp( $row['timestamp'] );
		$filter = $this->getFilterFromSpecs( [ 'id' => $id ] + $row );
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

	/**
	 * @param array $filterSpecs
	 * @param array $actions
	 * @return Filter
	 */
	private function getFilterFromSpecs( array $filterSpecs, array $actions = [] ): Filter {
		$filterSpecs += self::DEFAULT_VALUES;
		return new Filter(
			new Specs(
				$filterSpecs['rules'],
				$filterSpecs['comments'],
				$filterSpecs['name'],
				array_keys( $filterSpecs['actions'] ),
				$filterSpecs['group']
			),
			new Flags(
				$filterSpecs['enabled'],
				$filterSpecs['deleted'],
				$filterSpecs['privacy'],
				$filterSpecs['global']
			),
			$actions,
			new LastEditInfo(
				$filterSpecs['user'],
				$filterSpecs['user_text'],
				$filterSpecs['timestamp']
			),
			$filterSpecs['id'],
			$filterSpecs['hit_count'],
			$filterSpecs['throttled']
		);
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
		];
		$actions = [
			'degroup' => []
		];

		// We use restricted actions because that's the last check
		$expectedError = 'abusefilter-edit-restricted';

		$origFilter = MutableFilter::newDefault();
		$newFilter = $this->getFilterFromSpecs( $row, $actions );

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

	public function testSaveFilter_usesProtectedVars() {
		$row = [
			'id' => '2',
			'rules' => "ip_in_range( user_unnamed_ip, '1.2.3.4' )",
			'name' => 'Mock filter with protected variable used'
		];

		$origFilter = MutableFilter::newDefault();
		$newFilter = $this->getFilterFromSpecs( $row );

		// Try to save filter without right to use protected variables
		$user = $this->getTestUser()->getUser();
		$status = AbuseFilterServices::getFilterStore()->saveFilter( $user, $row['id'], $newFilter, $origFilter );
		$expectedError = 'abusefilter-edit-protected-variable';
		$this->assertStatusWarning( $expectedError, $status );

		// Add right and try to save filter without setting the 'protected' flag
		$this->overrideUserPermissions( $user, [ 'abusefilter-access-protected-vars', 'abusefilter-modify' ] );
		$status = AbuseFilterServices::getFilterStore()->saveFilter( $user, $row['id'], $newFilter, $origFilter );
		$expectedError = 'abusefilter-edit-protected-variable-not-protected';
		$this->assertStatusWarning( $expectedError, $status );

		// Save filter with right, with 'protected' flag enabled
		$row = [
			'id' => '3',
			'rules' => "ip_in_range( user_unnamed_ip, '1.2.3.4' )",
			'name' => 'Mock filter with protected variable used',
			'privacy' => Flags::FILTER_USES_PROTECTED_VARS,
		];
		$newFilter = $this->getFilterFromSpecs( $row );
		$status = AbuseFilterServices::getFilterStore()->saveFilter(
			$user, $row['id'], $newFilter, $origFilter
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
