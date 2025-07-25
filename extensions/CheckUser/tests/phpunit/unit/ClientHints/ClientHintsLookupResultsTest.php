<?php

namespace MediaWiki\CheckUser\Tests\Unit\ClientHints;

use LogicException;
use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\CheckUser\ClientHints\ClientHintsLookupResults;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWikiUnitTestCase;
use TypeError;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\ClientHints\ClientHintsLookupResults
 */
class ClientHintsLookupResultsTest extends MediaWikiUnitTestCase {
	use CheckUserClientHintsCommonTraitTest;

	/** @dataProvider provideGetClientHintsByReferenceIds */
	public function testGetClientHintsByReferenceIds(
		$referenceIdsToIndexMap, $clientHintsDataArray, $referenceId, $referenceType, $expectedReturnValue
	) {
		$objectUnderTest = new ClientHintsLookupResults( $referenceIdsToIndexMap, $clientHintsDataArray );
		$result = $objectUnderTest->getClientHintsDataForReferenceId( $referenceId, $referenceType );
		if ( $expectedReturnValue instanceof ClientHintsData ) {
			$this->assertInstanceOf(
				ClientHintsData::class,
				$result,
				'Return value was not an instance of the ClientHintsData class.'
			);
			$this->assertArrayEquals(
				$expectedReturnValue->jsonSerialize(),
				$result->jsonSerialize(),
				false,
				true,
				'Returned ClientHintsData object not as expected.'
			);
		} else {
			$this->assertSame(
				$expectedReturnValue,
				$result,
				'Return value was not as expected.'
			);
		}
	}

	public static function provideGetClientHintsByReferenceIds() {
		yield 'Empty result list for cu_changes reference ID' => [
			[], [],
			2,
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			null,
		];

		$emptyClientHintsDataObject = ClientHintsData::newFromJsApi( [] );
		yield 'Missing reference ID' => [
			[ 0 => [ 1 => 0 ] ],
			[ $emptyClientHintsDataObject ],
			2,
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			null,
		];

		yield 'Missing mapping ID' => [
			[ 1 => [ 2 => 0 ] ],
			[ $emptyClientHintsDataObject ],
			2,
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			null,
		];

		yield 'Missing ClientHintsData object' => [
			[ 0 => [ 2 => 1 ] ],
			[ $emptyClientHintsDataObject ],
			2,
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			null,
		];

		yield 'Present reference ID for empty ClientHintsData object' => [
			[ 1 => [ 2 => 0 ] ],
			[ $emptyClientHintsDataObject ],
			2,
			UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT,
			$emptyClientHintsDataObject,
		];

		$exampleClientHintsDataObject = self::getExampleClientHintsDataObjectFromJsApi();
		yield 'Present reference ID for example ClientHintsData object' => [
			[ 2 => [ 123 => 0, 1234 => 1 ] ],
			[ $exampleClientHintsDataObject, $emptyClientHintsDataObject ],
			123,
			UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
			$exampleClientHintsDataObject,
		];
	}

	/** @dataProvider provideInvalidReferenceTypes */
	public function testGetClientHintsByReferenceIdsOnInvalidReferenceType( $invalidMapId, $expectedExceptionName ) {
		$this->expectException( $expectedExceptionName );
		$this->testGetClientHintsByReferenceIds( [], [], 1, $invalidMapId, null );
	}

	public static function provideInvalidReferenceTypes() {
		return [
			'String type' => [ "testing", TypeError::class ],
			'Negative number' => [ -1, LogicException::class ],
			'Out of bounds integer' => [ 121212312, LogicException::class ],
		];
	}

	/** @dataProvider provideGetGroupedClientHintsDataForReferenceIds */
	public function testGetGroupedClientHintsDataForReferenceIds(
		$referenceIdsToIndexMap, $clientHintsDataArray, $referenceIds, $expectedReturnArray
	) {
		$objectUnderTest = new ClientHintsLookupResults( $referenceIdsToIndexMap, $clientHintsDataArray );
		$this->assertArrayEquals(
			$expectedReturnArray,
			$objectUnderTest->getGroupedClientHintsDataForReferenceIds( $referenceIds ),
			true,
			true,
			'Return array from ::getGroupedClientHintsDataForReferenceIds was not as expected.'
		);
	}

	public static function provideGetGroupedClientHintsDataForReferenceIds() {
		$emptyClientHintsDataObject = ClientHintsData::newFromJsApi( [] );
		yield 'Empty reference IDs list' => [
			[ 0 => [ 1 => 0 ] ],
			[ $emptyClientHintsDataObject ],
			new ClientHintsReferenceIds(),
			[ [], [] ]
		];

		$referenceIdsForOneCuChangesReference = new ClientHintsReferenceIds();
		$referenceIdsForOneCuChangesReference->addReferenceIds(
			2, UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES
		);
		yield 'Empty lookup results list and is provided cu_changes reference ID' => [
			[], [],
			$referenceIdsForOneCuChangesReference,
			[ [], [] ],
		];

		yield 'Missing reference ID' => [
			[ 0 => [ 1 => 0 ] ],
			[ $emptyClientHintsDataObject ],
			$referenceIdsForOneCuChangesReference,
			[ [], [] ],
		];

		yield 'Missing mapping ID' => [
			[ 1 => [ 2 => 0 ] ],
			[ $emptyClientHintsDataObject ],
			$referenceIdsForOneCuChangesReference,
			[ [], [] ],
		];

		yield 'Missing ClientHintsData object' => [
			[ 0 => [ 2 => 1 ] ],
			[ $emptyClientHintsDataObject ],
			$referenceIdsForOneCuChangesReference,
			[ [], [] ],
		];

		yield 'Matching ClientHintsData object for one reference ID from cu_changes' => [
			[ 0 => [ 2 => 0 ] ],
			[ $emptyClientHintsDataObject ],
			$referenceIdsForOneCuChangesReference,
			[ [ 0 => 1 ], [ $emptyClientHintsDataObject ] ],
		];

		$exampleClientHintsDataObject = self::getExampleClientHintsDataObjectFromJsApi();
		$otherExampleClientHintsDataObject = self::getExampleClientHintsDataObjectFromJsApi( 'arm' );
		$anotherExampleClientHintsDataObject = self::getExampleClientHintsDataObjectFromJsApi( 'arm', '32' );
		$referenceIds = new ClientHintsReferenceIds();
		$referenceIds->addReferenceIds( [ 123, 1234 ], UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT );
		$referenceIds->addReferenceIds( 456, UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT );
		$referenceIds->addReferenceIds( [ 123 ], UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES );
		yield 'Multiple reference IDs present and requesting selected reference IDs' => [
			[ 2 => [ 123 => 0, 1234 => 1, 12345 => 1 ], 1 => [ 123 => 2, 456 => 2 ], 0 => [ 123 => 2, 33445 => 3 ] ],
			[
				$exampleClientHintsDataObject, $emptyClientHintsDataObject,
				$otherExampleClientHintsDataObject, $anotherExampleClientHintsDataObject
			],
			$referenceIds,
			[
				[ 0 => 1, 1 => 1, 2 => 2 ],
				[
					$exampleClientHintsDataObject, $emptyClientHintsDataObject,
					$otherExampleClientHintsDataObject
				]
			],
		];

		yield 'Multiple reference IDs present with null as reference IDs' => [
			[ 2 => [ 123 => 0, 1234 => 1, 12345 => 0 ], 1 => [ 123 => 2, 456 => 2 ], 0 => [ 123 => 2, 3344 => 3 ] ],
			[
				$exampleClientHintsDataObject, $emptyClientHintsDataObject,
				$otherExampleClientHintsDataObject, $anotherExampleClientHintsDataObject
			],
			null,
			[
				[ 0 => 2, 1 => 1, 2 => 3, 3 => 1 ],
				[
					$exampleClientHintsDataObject, $emptyClientHintsDataObject,
					$otherExampleClientHintsDataObject, $anotherExampleClientHintsDataObject
				]
			],
		];
	}

	public function testGetRawData() {
		$rawData = [
			[ UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [ 1 => 0 ] ],
			[ ClientHintsData::newFromJsApi( [] ) ]
		];
		$objectUnderTest = new ClientHintsLookupResults( ...$rawData );
		$this->assertArrayEquals(
			$rawData,
			$objectUnderTest->getRawData(),
			true,
			true,
			'Return array from ::getGroupedClientHintsDataForReferenceIds was not as expected.'
		);
	}
}
