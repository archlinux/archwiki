<?php

namespace MediaWiki\CheckUser\Tests\Unit\ClientHints;

use LogicException;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWikiUnitTestCase;
use TypeError;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds
 */
class ClientHintsReferenceIdsTest extends MediaWikiUnitTestCase {
	public function testConstructorWithNoArgument() {
		$objectUnderTest = new ClientHintsReferenceIds();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertArrayEquals(
			[],
			$objectUnderTest->referenceIds,
			true,
			true,
			'ClientHintsReferenceIds::__construct should set the internal array to the empty array if ' .
			'provided no argument in the constructor.'
		);
	}

	/** @dataProvider provideConstructorArguments */
	public function testConstructorWithArgument( $argument ) {
		$objectUnderTest = new ClientHintsReferenceIds( $argument );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertArrayEquals(
			$argument,
			$objectUnderTest->referenceIds,
			true,
			true,
			'ClientHintsReferenceIds::__construct did not set the internal array value correctly.'
		);
	}

	public static function provideConstructorArguments() {
		return [
			'Default of empty array but provided' => [ [] ],
			'Array with items' => [ [
				UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [ 3 ],
				UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [ 4 ],
				UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 4, 5 ],
			] ],
		];
	}

	/** @dataProvider provideAddReferenceIds */
	public function testAddReferenceIds(
		$initialInternalArrayValue, $referenceIds, $mapId, $expectedInternalArrayValue
	) {
		$objectUnderTest = new ClientHintsReferenceIds( $initialInternalArrayValue );
		$objectUnderTest->addReferenceIds( $referenceIds, $mapId );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertArrayEquals(
			$expectedInternalArrayValue,
			$objectUnderTest->referenceIds,
			true,
			true,
			'Internal array not the expected value after addReferenceIds call.'
		);
	}

	public static function provideAddReferenceIds() {
		return [
			'Mapping ID argument as cu_changes with one reference ID' => [
				[],
				"2",
				UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
				[ UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 2 ] ]
			],
			'Mapping ID argument as cu_changes with array of reference IDs' => [
				[],
				[ 1, "2" ],
				UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
				[ UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1, 2 ] ]
			],
			'Mapping ID argument as cu_changes with array of reference IDs and existing reference IDs' => [
				[ UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 3 ] ],
				[ 1, "2" ],
				UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
				[ UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 3, 1, 2 ] ]
			],
			'Mapping ID argument as cu_log_event with array of reference IDs that duplicate existing reference IDs' => [
				[ UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [ 3 ] ],
				[ 1, "3" ],
				UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT,
				[ UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [ 3, 1 ] ]
			],
			'Mapping ID argument as cu_private_event with multiple existing reference IDs' => [
				[
					UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [ 3 ],
					UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [ 4 ],
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 4, 5 ],
				],
				[ 1, "456789" ],
				UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
				[
					UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [ 3 ],
					UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [ 4, 1, 456789 ],
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 4, 5 ],
				],
			],
		];
	}

	/** @dataProvider provideInvalidMappingIds */
	public function testAddReferenceIdsOnInvalidMapId( $invalidMapId, $expectedExceptionName ) {
		$this->expectException( $expectedExceptionName );
		$this->testAddReferenceIds( [], [ 1, 2 ], $invalidMapId, [] );
	}

	/** @dataProvider provideGetReferenceIds */
	public function testGetReferenceIds( $internalArrayValue, $mappingIdArgument, $expectedReturnArray ) {
		$objectUnderTest = new ClientHintsReferenceIds( $internalArrayValue );
		$this->assertArrayEquals(
			$expectedReturnArray,
			$objectUnderTest->getReferenceIds( $mappingIdArgument ),
			true,
			true,
			'Returned reference IDs are not as expected.'
		);
	}

	public static function provideGetReferenceIds() {
		$defaultInternalArray = [
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1, 2 ],
			UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [ 55 ],
			UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [ 12213, 121, 23232323 ],
		];
		return [
			'Mapping ID argument as null' => [ $defaultInternalArray, null, $defaultInternalArray ],
			'Mapping ID argument as cu_changes map identifier' => [
				$defaultInternalArray,
				UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
				[ 1, 2 ]
			],
			'Mapping ID argument as cu_log_event map identifier' => [
				$defaultInternalArray,
				UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT,
				[ 55 ]
			],
			'Mapping ID argument as cu_private_event map identifier' => [
				$defaultInternalArray,
				UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
				[ 12213, 121, 23232323 ]
			],
			'Mapping ID argument as cu_private_event map identifier with no defined cu_private_event IDs' => [
				[ UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1, 2 ] ],
				UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
				[]
			],
		];
	}

	/** @dataProvider provideInvalidMappingIds */
	public function testGetReferenceIdsInvalidMappingId( $invalidMapId, $expectedExceptionName ) {
		$this->expectException( $expectedExceptionName );
		$this->testGetReferenceIds(
			[ UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1, 2 ] ],
			$invalidMapId,
			[]
		);
	}

	/** @dataProvider provideInvalidMappingIds */
	public function testTypeExistsInvalidMappingId( $invalidMapId, $expectedExceptionName ) {
		$this->expectException( $expectedExceptionName );
		$objectUnderTest = TestingAccessWrapper::newFromObject( new ClientHintsReferenceIds() );
		$this->assertNull(
			$objectUnderTest->mappingIdExists( $invalidMapId ),
			'Should return nothing and throw exception on invalid type.'
		);
	}

	public static function provideInvalidMappingIds() {
		return [
			'String type' => [ "testing", TypeError::class ],
			'Negative number' => [ -1, LogicException::class ],
			'Out of bounds integer' => [ 121212312, LogicException::class ],
		];
	}

	/** @dataProvider provideValidMappingIds */
	public function testTypeExistsMissingMappingId( $validMapId ) {
		$objectUnderTest = TestingAccessWrapper::newFromObject( new ClientHintsReferenceIds() );
		$this->assertArrayNotHasKey(
			$validMapId,
			$objectUnderTest->referenceIds,
			'Internal array should not hold the mapping ID by default.'
		);
		$this->assertFalse(
			$objectUnderTest->mappingIdExists( $validMapId ),
			'Should return false if mapping identifier does not exist in internal array.'
		);
	}

	/** @dataProvider provideValidMappingIds */
	public function testTypeExistsPreExistingMappingId( $validMapId ) {
		$objectUnderTest = TestingAccessWrapper::newFromObject( new ClientHintsReferenceIds() );
		$objectUnderTest->referenceIds = [ $validMapId => [] ];
		$this->assertTrue(
			$objectUnderTest->mappingIdExists( $validMapId ),
			'Should return true if mapping ID is already defined in the internal array.'
		);
	}

	public static function provideValidMappingIds() {
		return [
			'cu_changes map identifier' => [ UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES ],
			'cu_log_event map identifier' => [ UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT ],
			'cu_private_event map identifier' => [ UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT ],
		];
	}
}
