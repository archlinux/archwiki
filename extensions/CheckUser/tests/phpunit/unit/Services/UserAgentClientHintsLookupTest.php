<?php

namespace MediaWiki\CheckUser\Tests\Unit\Services;

use MediaWiki\CheckUser\ClientHints\ClientHintsLookupResults;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\UserAgentClientHintsLookup;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use ReflectionClass;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Services\UserAgentClientHintsLookup
 */
class UserAgentClientHintsLookupTest extends MediaWikiUnitTestCase {
	use MockServiceDependenciesTrait;

	/** @dataProvider providePrepareFirstResultsArray */
	public function testPrepareFirstResultsArray( $referenceIds, $expectedReturnArray ) {
		/** @var UserAgentClientHintsLookup $objectUnderTest */
		$objectUnderTest = $this->newServiceInstance( UserAgentClientHintsLookup::class, [] );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertArrayEquals(
			$expectedReturnArray,
			$objectUnderTest->prepareFirstResultsArray( $referenceIds ),
			false,
			true,
			'Return result from ::prepareFirstResultsArray was not as expected.'
		);
	}

	public static function providePrepareFirstResultsArray() {
		return [
			'Empty reference IDs list' => [
				new ClientHintsReferenceIds(),
				[]
			],
			'Reference IDs for just cu_changes' => [
				new ClientHintsReferenceIds( [
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 123, 456 ],
					UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => []
				] ),
				// Expected array returned by ::prepareFirstResultsArray
				[ UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 123 => [], 456 => [] ] ]
			],
			'Reference IDs for all three reference types' => [
				new ClientHintsReferenceIds( [
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 123, 4567 ],
					UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [ 678, 101 ],
					UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [ 1234, 56 ],
				] ),
				// Expected array returned by ::prepareFirstResultsArray. Should be the reference IDs
				// in $referenceIds with empty arrays as the value.
				[
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 123 => [], 4567 => [] ],
					UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [ 678 => [], 101 => [] ],
					UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [ 1234 => [], 56 => [] ],
				]
			]
		];
	}

	/** @dataProvider provideGenerateUniqueClientHintsIdCombinations */
	public function testGenerateUniqueClientHintsIdCombinations(
		$referenceIdsToClientHintIds, $expectedReferenceIdsToClientHintIdsAfterCall, $expectedReturnArray
	) {
		/** @var UserAgentClientHintsLookup $objectUnderTest */
		$objectUnderTest = $this->newServiceInstance( UserAgentClientHintsLookup::class, [] );
		// T287318 - TestingAccessWrapper::__call does not support pass-by-reference
		$classReflection = new ReflectionClass( $objectUnderTest );
		$methodReflection = $classReflection->getMethod( 'generateUniqueClientHintsIdCombinations' );
		$methodReflection->setAccessible( true );

		$this->assertArrayEquals(
			$expectedReturnArray,
			$methodReflection->invokeArgs( $objectUnderTest, [ &$referenceIdsToClientHintIds ] ),
			false,
			true,
			'The returned array from ::generateUniqueClientHintsIdCombinations was not as expected.'
		);
		$this->assertArrayEquals(
			$expectedReferenceIdsToClientHintIdsAfterCall,
			$referenceIdsToClientHintIds,
			false,
			true,
			'The reference IDs map passed by reference to ::generateUniqueClientHintsIdCombinations was not ' .
			'as expected after the call.'
		);
	}

	public static function provideGenerateUniqueClientHintsIdCombinations() {
		return [
			'Empty reference IDs map' => [
				// Initial array value for $referenceIdsToClientHintIds
				// that is passed by reference
				[],
				// Value of $referenceIdsToClientHintIds after the call
				// to ::generateUniqueClientHintsIdCombinations
				[],
				// Expected return array from ::generateUniqueClientHintsIdCombinations
				[]
			],
			'Reference IDs for just cu_log_event' => [
				// Initial array value for $referenceIdsToClientHintIds
				// that is passed by reference
				[ UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [
					123 => [ 1, 2, 3 ], 456 => [ 1, 3, 4 ], 567 => [ 1, 2, 3 ]
				] ],
				// Value of $referenceIdsToClientHintIds after the call
				// to ::generateUniqueClientHintsIdCombinations
				[ UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [
					123 => 0, 456 => 1, 567 => 0
				] ],
				// Expected return array from ::generateUniqueClientHintsIdCombinations
				// which is the sorted and unique combinations of uach_ids from the initial
				// $referenceIdsToClientHintIds array.
				[ [ 1, 2, 3 ], [ 1, 3, 4 ] ]
			],
			'Reference IDs for all reference types' => [
				// Initial array value for $referenceIdsToClientHintIds
				// that is passed by reference
				[
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [
						123 => [ 1, 2, 4 ], 456 => [ 1, 3, 4 ], 567 => [ 1, 2, 3 ]
					],
					UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [
						123 => [ 1, 2, 5 ], 4567 => [ 1, 3, 4 ], 567 => [ 4, 2, 3 ]
					],
					UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [
						123 => [ 3, 2, 4 ], 234 => [ 1, 3, 4 ], 567 => [ 1, 2, 3 ]
					],
				],
				// Value of $referenceIdsToClientHintIds after the call
				// to ::generateUniqueClientHintsIdCombinations
				[
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [
						123 => 0, 456 => 1, 567 => 2
					],
					UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [
						123 => 3, 4567 => 1, 567 => 4
					],
					UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [
						123 => 4, 234 => 1, 567 => 2
					],
				],
				// Expected return array from ::generateUniqueClientHintsIdCombinations
				// which is the sorted and unique combinations of uach_ids from the initial
				// $referenceIdsToClientHintIds array.
				[
					[ 1, 2, 4 ],
					[ 1, 3, 4 ],
					[ 1, 2, 3 ],
					[ 1, 2, 5 ],
					[ 2, 3, 4 ],
				]
			],
		];
	}

	public function testGetClientHintsByReferenceIdsOnEmptyReferenceIds() {
		/** @var UserAgentClientHintsLookup $objectUnderTest */
		$objectUnderTest = $this->newServiceInstance( UserAgentClientHintsLookup::class, [] );
		$returnValue = $objectUnderTest->getClientHintsByReferenceIds( new ClientHintsReferenceIds() );
		$this->assertInstanceOf(
			ClientHintsLookupResults::class,
			$returnValue,
			'The return value of ::getClientHintsByReferenceIds should be a ClientHintsLookupResults object.'
		);
		$returnValue = TestingAccessWrapper::newFromObject( $returnValue );
		$this->assertCount(
			0,
			$returnValue->referenceIdsToClientHintsDataIndex,
			'The returned ClientHintsLookupResults object should have no results.'
		);
		$this->assertCount(
			0,
			$returnValue->clientHintsDataObjects,
			'The returned ClientHintsLookupResults object should have no results.'
		);
	}

	public function testGetClientHintsByReferenceIdsOnNoMappingRows() {
		$referenceIds = new ClientHintsReferenceIds();
		$referenceIds->addReferenceIds( [ 1, 23 ], UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES );
		$referenceIds->addReferenceIds( 123, UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT );
		$dbrMock = $this->createMock( IReadableDatabase::class );
		$dbrMock->method( 'newSelectQueryBuilder' )
			->willReturnCallback( static fn () => new SelectQueryBuilder( $dbrMock ) );
		$dbrMock->method( 'select' )
			->with(
				[ 'cu_useragent_clienthints_map' ],
				[ '*' ],
				[ 'makeWhereFrom2d result' ],
				'MediaWiki\CheckUser\Services\UserAgentClientHintsLookup::getClientHintsByReferenceIds',
				[],
				[]
			)
			->willReturn( new FakeResultWrapper( [] ) );
		$dbrMock->method( 'makeWhereFrom2d' )
			->with(
				[
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1 => [], 23 => [] ],
					UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [ 123 => [] ],
				],
				'uachm_reference_type',
				'uachm_reference_id'
			)
			->willReturn( 'makeWhereFrom2d result' );
		/** @var UserAgentClientHintsLookup $objectUnderTest */
		$objectUnderTest = $this->newServiceInstance( UserAgentClientHintsLookup::class, [
			'dbr' => $dbrMock
		] );
		$returnValue = $objectUnderTest->getClientHintsByReferenceIds( $referenceIds );
		$this->assertInstanceOf(
			ClientHintsLookupResults::class,
			$returnValue,
			'The return value of ::getClientHintsByReferenceIds should be a ClientHintsLookupResults object.'
		);
		$returnValue = TestingAccessWrapper::newFromObject( $returnValue );
		$this->assertCount(
			0,
			$returnValue->referenceIdsToClientHintsDataIndex,
			'The returned ClientHintsLookupResults object should have no results as there were no matching ' .
			' map rows.'
		);
		$this->assertCount(
			0,
			$returnValue->clientHintsDataObjects,
			'The returned ClientHintsLookupResults object should have no results as there were no matching ' .
			' map rows.'
		);
	}
}
