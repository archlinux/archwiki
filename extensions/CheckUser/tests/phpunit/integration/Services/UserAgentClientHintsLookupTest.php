<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\UserAgentClientHintsLookup;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Services\UserAgentClientHintsLookup
 */
class UserAgentClientHintsLookupTest extends MediaWikiIntegrationTestCase {
	use CheckUserClientHintsCommonTraitTest;

	/**
	 * Tests that ::getClientHintsByReferenceIds finds
	 * and returns the appropriate ClientHintsData.
	 *
	 * @dataProvider provideExampleClientHintData
	 */
	public function testLookupLoop(
		$clientHintDataItems,
		$referenceIdsToInsert,
		$referenceIdsToLookup
	) {
		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = $this->getServiceContainer()->get( 'UserAgentClientHintsManager' );
		foreach ( $clientHintDataItems as $key => $clientHintData ) {
			$userAgentClientHintsManager->insertClientHintValues(
				$clientHintData, $referenceIdsToInsert[$key], 'revision'
			);
		}
		$referenceIds = new ClientHintsReferenceIds( [
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => $referenceIdsToLookup
		] );
		/** @var UserAgentClientHintsLookup $userAgentClientHintsLookup */
		$userAgentClientHintsLookup = $this->getServiceContainer()->get( 'UserAgentClientHintsLookup' );
		$lookupResult = $userAgentClientHintsLookup->getClientHintsByReferenceIds( $referenceIds );
		foreach ( $referenceIdsToLookup as $key => $referenceId ) {
			$lookupResultForReferenceId = $lookupResult->getClientHintsDataForReferenceId(
				$referenceId, UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES
			);
			$this->assertNotNull(
				$lookupResultForReferenceId,
				"A ClientHintsData object should have been returned in the results for reference ID $referenceId"
			);
			$this->assertClientHintsDataObjectsEqual( $clientHintDataItems[$key], $lookupResultForReferenceId, true );
		}
	}

	public static function provideExampleClientHintData() {
		yield 'One set of client hint data' => [
			[ 1 => self::getExampleClientHintsDataObjectFromJsApi() ],
			// Reference IDs for the client hint data
			[ 1 => 1234 ],
			// Reference IDs to be looked up
			[ 1 => 1234 ],
		];

		yield 'Two client hint mapping data items' => [
			[
				1 => self::getExampleClientHintsDataObjectFromJsApi(),
				2 => self::getExampleClientHintsDataObjectFromJsApi(
					"x86",
					"64",
					[
						[
							"brand" => "Not.A/Brand",
							"version" => "8"
						],
						[
							"brand" => "Chromium",
							"version" => "114"
						],
						[
							"brand" => "Edge",
							"version" => "114"
						]
					],
					[
						[
							"brand" => "Not.A/Brand",
							"version" => "8.0.0.0"
						],
						[
							"brand" => "Chromium",
							"version" => "114.0.5735.199"
						],
						[
							"brand" => "Edge",
							"version" => "114.0.5735.198"
						]
					],
					true,
					"",
					"Windows",
					"14.0.0"
				),
				3 => self::getExampleClientHintsDataObjectFromJsApi(
					"x86",
					"32"
				),
			],
			// Reference IDs for the client hint data
			[ 1 => 123, 2 => 12345, 3 => 234562323 ],
			// Reference IDs to be looked up
			[ 1 => 123, 2 => 12345 ],
		];
	}
}
