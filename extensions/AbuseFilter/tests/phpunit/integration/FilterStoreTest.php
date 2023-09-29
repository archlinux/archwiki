<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Filter;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\LastEditInfo;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\Filter\Specs;
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
		'hidden' => 0,
		'hit_count' => 0,
		'throttled' => 0,
		'deleted' => 0,
		'actions' => [],
		'global' => 0,
		'group' => 'default'
	];

	/** @inheritDoc */
	protected $tablesUsed = [ 'abuse_filter' ];

	/**
	 * @param int $id
	 */
	private function createFilter( int $id ): void {
		$row = self::DEFAULT_VALUES;
		$row['timestamp'] = $this->db->timestamp( $row['timestamp'] );
		$filter = $this->getFilterFromSpecs( [ 'id' => $id ] + $row );
		// Use some black magic to bypass checks
		$filterStore = TestingAccessWrapper::newFromObject( AbuseFilterServices::getFilterStore() );
		$this->db->insert(
			'abuse_filter',
			$filterStore->filterToDatabaseRow( $filter ),
			__METHOD__
		);
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
				$filterSpecs['hidden'],
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

		$this->assertTrue( $status->isGood(), "Save failed with status: $status" );
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

		$this->assertFalse( $status->isGood(), 'The filter validation returned a valid status.' );
		$actual = $status->getErrors()[0]['message'];
		$this->assertSame( $expectedError, $actual );
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

		$this->assertTrue( $status->isGood(), "Got a non-good status: $status" );
		$this->assertFalse( $status->getValue(), 'Status value should be false' );
	}
}
