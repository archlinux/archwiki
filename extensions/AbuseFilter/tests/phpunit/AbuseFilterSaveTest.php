<?php
/**
 * Tests for validating and saving a filter
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 *
 * @license GPL-2.0-or-later
 */

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Filter;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\LastEditInfo;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\Filter\Specs;
use MediaWiki\Extension\AbuseFilter\FilterValidator;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Test
 * @group AbuseFilter
 * @group AbuseFilterSave
 * @group Database
 * @todo This can probably be removed in favour of unit-testing a class that handles saving filters.
 */
class AbuseFilterSaveTest extends MediaWikiIntegrationTestCase {

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
		$filter = $this->getFilterFromSpecs( [ 'id' => $id ] + self::DEFAULT_VALUES );
		// Use some black magic to bypass checks
		$filterStore = TestingAccessWrapper::newFromObject( AbuseFilterServices::getFilterStore() );
		wfGetDB( DB_PRIMARY )->insert(
			'abuse_filter',
			get_object_vars( $filterStore->filterToDatabaseRow( $filter ) ),
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

	/**
	 * @covers \MediaWiki\Extension\AbuseFilter\FilterStore
	 * @covers \MediaWiki\Extension\AbuseFilter\FilterValidator
	 */
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

	/**
	 * @covers \MediaWiki\Extension\AbuseFilter\FilterStore
	 * @covers \MediaWiki\Extension\AbuseFilter\FilterValidator
	 */
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

	/**
	 * @covers \MediaWiki\Extension\AbuseFilter\FilterStore
	 * @covers \MediaWiki\Extension\AbuseFilter\FilterValidator
	 */
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

	/**
	 * @todo Make this a unit test in AbuseFilterChangeTagValidatorTest once static methods
	 *   in ChangeTags are moved to a service
	 * @todo When the above is possible, use mocks to test canAddTagsAccompanyingChange and canCreateTag
	 * @param string $tag The tag to validate
	 * @param string|null $expectedError
	 * @covers \MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagValidator::validateTag
	 * @dataProvider provideTags
	 */
	public function testValidateTag( string $tag, ?string $expectedError ) {
		$validator = AbuseFilterServices::getChangeTagValidator();
		$status = $validator->validateTag( $tag );
		$actualError = $status->isGood() ? null : $status->getErrors()[0]['message'];
		$this->assertSame( $expectedError, $actualError );
	}

	/**
	 * Data provider for testValidateTag
	 * @return array
	 */
	public function provideTags() {
		return [
			'invalid chars' => [ 'a|b', 'tags-create-invalid-chars' ],
			'core-reserved tag' => [ 'mw-undo', 'abusefilter-edit-bad-tags' ],
			'AF-reserved tag' => [ 'abusefilter-condition-limit', 'abusefilter-tag-reserved' ],
			'valid' => [ 'my_tag', null ],
		];
	}

	/**
	 * @todo Like above, make this a unit test once possible
	 * @param string[] $tags
	 * @param string|null $expected
	 * @covers \MediaWiki\Extension\AbuseFilter\FilterValidator::checkAllTags
	 * @dataProvider provideAllTags
	 */
	public function testCheckAllTags( array $tags, ?string $expected ) {
		$validator = new FilterValidator(
			AbuseFilterServices::getChangeTagValidator(),
			$this->createMock( RuleCheckerFactory::class ),
			$this->createMock( AbuseFilterPermissionManager::class ),
			new ServiceOptions(
				FilterValidator::CONSTRUCTOR_OPTIONS,
				[
					'AbuseFilterActionRestrictions' => [],
					'AbuseFilterValidGroups' => [ 'default' ]
				]
			)
		);

		$status = $validator->checkAllTags( $tags );
		$actualError = $status->isGood() ? null : $status->getErrors()[0]['message'];
		$this->assertSame( $expected, $actualError );
	}

	public function provideAllTags() {
		$providedTagsInvalid = $this->provideTags();
		$invalidtags = array_column( $providedTagsInvalid, 0 );
		$allTagErrors = array_filter( array_column( $providedTagsInvalid, 1 ) );
		$expectedError = reset( $allTagErrors );
		yield 'invalid' => [ $invalidtags, $expectedError ];

		yield 'valid' => [ [ 'fooooobar', 'foooobaz' ], null ];
	}
}
