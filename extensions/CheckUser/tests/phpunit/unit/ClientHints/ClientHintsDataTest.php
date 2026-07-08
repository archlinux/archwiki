<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\ClientHints;

use MediaWiki\Extension\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\Extension\CheckUser\Tests\CheckUserClientHintsCommonTestTrait;
use MediaWiki\Request\FauxRequest;
use MediaWikiUnitTestCase;
use TypeError;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\Extension\CheckUser\ClientHints\ClientHintsData
 */
class ClientHintsDataTest extends MediaWikiUnitTestCase {
	use CheckUserClientHintsCommonTestTrait;

	/** @dataProvider provideNewFromJsApi */
	public function testNewFromJsApiAndJsonSerialize( array $dataFromJsApi, array $expectedValues ) {
		$objectToTest = ClientHintsData::newFromJsApi( $dataFromJsApi );
		$this->assertArrayEquals(
			$expectedValues,
			$objectToTest->jsonSerialize(),
			false,
			true,
			"Data stored by ClientHintsData class not as expected."
		);
	}

	private static function getExampleJsApiData(): array {
		return [
			'No client hint data' => [],
			'Example Windows device using Chrome' => [
				'architecture' => 'x86',
				'bitness' => '64',
				'brands' => [
					[
						"brand" => "Not.A/Brand",
						"version" => "8",
					],
					[
						"brand" => "Chromium",
						"version" => "114",
					],
					[
						"version" => "114",
						"brand" => "Google Chrome",
					],
				],
				'fullVersionList' => [
					[
						"brand" => "Not.A/Brand",
						"version" => "8.0.0.0",
					],
					[
						"brand" => "Chromium",
						"version" => "114.0.5735.199",
					],
					[
						"version" => "114.0.5735.199",
						"brand" => "Google Chrome",
					],
				],
				'mobile' => false,
				'model' => "",
				'platform' => "Windows",
				'platformVersion' => "15.0.0",
			],
			'Example Windows device using Chrome with duplicated data' => [
				'architecture' => 'x86',
				'bitness' => '64',
				'brands' => [
					[
						"brand" => " Not.A/Brand",
						"version" => "8",
					],
					[
						"brand" => "Chromium",
						"version" => "114",
					],
					[
						"brand" => "Chromium",
						"version" => "114",
					],
					[
						"version" => "114",
						"brand" => "Google Chrome",
					],
				],
				'formFactor' => null,
				'fullVersionList' => [
					[
						"brand" => " Not.A/Brand",
						"version" => "8.0.0.0",
					],
					[
						"brand" => "Chromium",
						"version" => "114.0.5735.199",
					],
					[
						"brand" => "Google Chrome",
						"version" => "114.0.5735.199",
					],
					[
						"version" => "114.0.5735.199",
						"brand" => "Google Chrome",
					],
				],
				'mobile' => false,
				'model' => "",
				'platform' => "Windows",
				'platformVersion' => "15.0.0",
				'woW64' => null,
			],
		];
	}

	public static function provideNewFromJsApi() {
		$exampleJsApiData = self::getExampleJsApiData();
		return [
			'No client hint data' => [
				$exampleJsApiData['No client hint data'],
				[
					'architecture' => null,
					'bitness' => null,
					'brands' => null,
					'formFactor' => null,
					'fullVersionList' => null,
					'mobile' => null,
					'model' => null,
					'platform' => null,
					'platformVersion' => null,
					'woW64' => null,
					'isBrowser' => null,
					'ja3n' => null,
					'ja4h' => null,
				],
			],
			'Example Windows device using Chrome' => [
				$exampleJsApiData['Example Windows device using Chrome'],
				[
					'architecture' => 'x86',
					'bitness' => '64',
					'brands' => [
						[
							"brand" => "Not.A/Brand",
							"version" => "8",
						],
						[
							"brand" => "Chromium",
							"version" => "114",
						],
						[
							"version" => "114",
							"brand" => "Google Chrome",
						],
					],
					'formFactor' => null,
					'fullVersionList' => [
						[
							"brand" => "Not.A/Brand",
							"version" => "8.0.0.0",
						],
						[
							"brand" => "Chromium",
							"version" => "114.0.5735.199",
						],
						[
							"version" => "114.0.5735.199",
							"brand" => "Google Chrome",
						],
					],
					'mobile' => false,
					'model' => "",
					'platform' => "Windows",
					'platformVersion' => "15.0.0",
					'woW64' => null,
					'isBrowser' => null,
					'ja3n' => null,
					'ja4h' => null,
				],
			],
			'Example Windows device using Chrome with duplicated data' => [
				$exampleJsApiData['Example Windows device using Chrome with duplicated data'],
				[
					'architecture' => 'x86',
					'bitness' => '64',
					'brands' => [
						[
							"brand" => " Not.A/Brand",
							"version" => "8",
						],
						[
							"brand" => "Chromium",
							"version" => "114",
						],
						[
							"brand" => "Chromium",
							"version" => "114",
						],
						[
							"version" => "114",
							"brand" => "Google Chrome",
						],
					],
					'formFactor' => null,
					'fullVersionList' => [
						[
							"brand" => " Not.A/Brand",
							"version" => "8.0.0.0",
						],
						[
							"brand" => "Chromium",
							"version" => "114.0.5735.199",
						],
						[
							"brand" => "Google Chrome",
							"version" => "114.0.5735.199",
						],
						[
							"version" => "114.0.5735.199",
							"brand" => "Google Chrome",
						],
					],
					'mobile' => false,
					'model' => "",
					'platform' => "Windows",
					'platformVersion' => "15.0.0",
					'woW64' => null,
					'isBrowser' => null,
					'ja3n' => null,
					'ja4h' => null,
				],
			],
			'Client Hints data contains deprecated uaFullVersion' => [
				[ 'uaFullVersion' => '1.2.3.4' ],
				[
					'fullVersionList' => [ '1.2.3.4' ],
					'architecture' => null,
					'bitness' => null,
					'brands' => null,
					'formFactor' => null,
					'mobile' => null,
					'model' => null,
					'platform' => null,
					'platformVersion' => null,
					'woW64' => null,
					'isBrowser' => null,
					'ja3n' => null,
					'ja4h' => null,
				],
			],
			'Client Hints data contains deprecated uaFullVersion and empty array fullVersionList' => [
				[ 'uaFullVersion' => '1.2.3.4', 'fullVersionList' => [] ],
				[
					'fullVersionList' => [ '1.2.3.4' ],
					'architecture' => null,
					'bitness' => null,
					'brands' => null,
					'formFactor' => null,
					'mobile' => null,
					'model' => null,
					'platform' => null,
					'platformVersion' => null,
					'woW64' => null,
					'isBrowser' => null,
					'ja3n' => null,
					'ja4h' => null,
				],
			],
		];
	}

	/** @dataProvider provideNewFromRequestHeaders */
	public function testNewFromRequestHeaders(
		array $requestHeaders,
		array $collectOnly,
		array $expectedJsonArray
	): void {
		$request = new FauxRequest();
		$request->setHeaders( $requestHeaders );
		$objectToTest = ClientHintsData::newFromRequestHeaders( $request, $collectOnly );
		$this->assertArrayEquals(
			$expectedJsonArray,
			$objectToTest->jsonSerialize(),
			false,
			true,
			"Data stored by ClientHintsData class not as expected."
		);
	}

	public static function provideNewFromRequestHeaders() {
		return [
			'No client hint data' => [
				'requestHeaders' => [],
				'collectOnly' => [],
				'expectedJsonArray' => [
					'architecture' => null,
					'bitness' => null,
					'brands' => null,
					'formFactor' => null,
					'fullVersionList' => null,
					'mobile' => null,
					'model' => null,
					'platform' => null,
					'platformVersion' => null,
					'woW64' => null,
					'isBrowser' => null,
					'ja3n' => null,
					'ja4h' => null,
				],
			],
			'Example Windows device using Chrome' => [
				'requestHeaders' => [
					'SEC-CH-UA' => '"Chromium";v="114", "Google Chrome";v="114", "Not.A/Brand";v="8"',
					'SEC-CH-UA-ARCH' => '"x86"',
					'SEC-CH-UA-BITNESS' => '"64"',
					'SEC-CH-UA-FULL-VERSION-LIST' =>
						'"Chromium";v="114.0.5735.199", "Google Chrome";v="114.0.5735.199", "Not.A/Brand";v="8.0.0.0"',
					'SEC-CH-UA-MOBILE' => '?0',
					'SEC-CH-UA-MODEL' => '""',
					'SEC-CH-UA-PLATFORM' => '"Windows"',
					'SEC-CH-UA-PLATFORM-VERSION' => '"15.0.0"',
					'SEC-CH-UA-WOW64' => '?0',
					'x-is-browser' => '30',
					'x-ja3n' => 'testing',
					'x-ja4h' => 'testing2',
				],
				'collectOnly' => [],
				'expectedJsonArray' => [
					'architecture' => 'x86',
					'bitness' => '64',
					'brands' => [
						[
							"brand" => "Chromium",
							"version" => "114",
						],
						[
							"version" => "114",
							"brand" => "Google Chrome",
						],
						[
							"brand" => "Not.A/Brand",
							"version" => "8",
						],
					],
					'formFactor' => null,
					'fullVersionList' => [
						[
							"brand" => "Chromium",
							"version" => "114.0.5735.199",
						],
						[
							"brand" => "Google Chrome",
							"version" => "114.0.5735.199",
						],
						[
							"brand" => "Not.A/Brand",
							"version" => "8.0.0.0",
						],
					],
					'mobile' => false,
					'model' => "",
					'platform' => "Windows",
					'platformVersion' => "15.0.0",
					'woW64' => false,
					'isBrowser' => 30,
					'ja3n' => 'testing',
					'ja4h' => 'testing2',
				],
			],
			'Example Android device using Chrome' => [
				'requestHeaders' => [
					'SEC-CH-UA' => '"Chromium";v="114", "Google Chrome";v="114", "Not.A/Brand";v="99"',
					'SEC-CH-UA-ARCH' => '""',
					'SEC-CH-UA-BITNESS' => '"64"',
					'SEC-CH-UA-FULL-VERSION-LIST' =>
						'"Chromium";v="114.0.5735.199", "Google Chrome";v="114.0.5735.199", "Not.A/Brand";v="99.0.0.0"',
					'SEC-CH-UA-MOBILE' => '?1',
					'SEC-CH-UA-MODEL' => '"SM-G955U"',
					'SEC-CH-UA-PLATFORM' => '"Android"',
					'SEC-CH-UA-PLATFORM-VERSION' => '"8.0.0"',
					'SEC-CH-UA-WOW64' => '?0',
					'x-is-browser' => '123',
				],
				'collectOnly' => [],
				'expectedJsonArray' => [
					'architecture' => '',
					'bitness' => '64',
					'brands' => [
						[
							"brand" => "Chromium",
							"version" => "114",
						],
						[
							"version" => "114",
							"brand" => "Google Chrome",
						],
						[
							"brand" => "Not.A/Brand",
							"version" => "99",
						],
					],
					'formFactor' => null,
					'fullVersionList' => [
						[
							"brand" => "Chromium",
							"version" => "114.0.5735.199",
						],
						[
							"brand" => "Google Chrome",
							"version" => "114.0.5735.199",
						],
						[
							"brand" => "Not.A/Brand",
							"version" => "99.0.0.0",
						],
					],
					'mobile' => true,
					'model' => "SM-G955U",
					'platform' => "Android",
					'platformVersion' => "8.0.0",
					'woW64' => false,
					'isBrowser' => 123,
					'ja3n' => null,
					'ja4h' => null,
				],
			],
			'Example Windows device using Chrome when only collecting some headers' => [
				'requestHeaders' => [
					'SEC-CH-UA' => '"Chromium";v="114", "Google Chrome";v="114", "Not.A/Brand";v="8"',
					'SEC-CH-UA-ARCH' => '"x86"',
					'SEC-CH-UA-BITNESS' => '"64"',
					'SEC-CH-UA-FULL-VERSION-LIST' =>
						'"Chromium";v="114.0.5735.199", "Google Chrome";v="114.0.5735.199", "Not.A/Brand";v="8.0.0.0"',
					'SEC-CH-UA-MOBILE' => '?0',
					'SEC-CH-UA-MODEL' => '""',
					'SEC-CH-UA-PLATFORM' => '"Windows"',
					'SEC-CH-UA-PLATFORM-VERSION' => '"15.0.0"',
					'SEC-CH-UA-WOW64' => '?0',
					'x-is-browser' => '30',
					'x-ja3n' => 'testing',
					'x-ja4h' => 'testing2',
				],
				'collectOnly' => [ 'isBrowser', 'bitness', 'brands' ],
				'expectedJsonArray' => [
					'architecture' => null,
					'bitness' => '64',
					'brands' => [
						[
							"brand" => "Chromium",
							"version" => "114",
						],
						[
							"version" => "114",
							"brand" => "Google Chrome",
						],
						[
							"brand" => "Not.A/Brand",
							"version" => "8",
						],
					],
					'formFactor' => null,
					'fullVersionList' => null,
					'mobile' => false,
					'model' => null,
					'platform' => null,
					'platformVersion' => null,
					'woW64' => null,
					'isBrowser' => 30,
					'ja3n' => null,
					'ja4h' => null,
				],
			],
		];
	}

	public function testNewFromRequestHeadersOnInvalidFullVersionListHeader() {
		$this->expectException( TypeError::class );
		$this->expectExceptionMessage( "Invalid header Sec-CH-UA-Full-Version-List" );
		$this->testNewFromRequestHeaders( [ 'SEC-CH-UA-FULL-VERSION-LIST' => '"abc"' ], [], [] );
	}

	/** @dataProvider provideToDatabaseRows */
	public function testToDatabaseRows( $dataFromJsApi, $expectedDatabaseRows ) {
		$objectToTest = ClientHintsData::newFromJsApi( $dataFromJsApi );
		$this->assertArrayEquals(
			$expectedDatabaseRows,
			$objectToTest->toDatabaseRows(),
			false,
			true,
			"Database rows for the client hint data not as expected."
		);
	}

	public static function provideToDatabaseRows() {
		$exampleJsApiData = self::getExampleJsApiData();
		return [
			'No client hint data' => [
				$exampleJsApiData['No client hint data'],
				[],
			],
			'Example Windows device using Chrome' => [
				$exampleJsApiData['Example Windows device using Chrome'],
				[
					[ 'uach_name' => 'architecture', 'uach_value' => 'x86' ],
					[ 'uach_name' => 'bitness', 'uach_value' => '64' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'Not.A/Brand 8' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'Chromium 114' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'Google Chrome 114' ],
					[ 'uach_name' => 'fullVersionList', 'uach_value' => 'Not.A/Brand 8.0.0.0' ],
					[ 'uach_name' => 'fullVersionList', 'uach_value' => 'Chromium 114.0.5735.199' ],
					[ 'uach_name' => 'fullVersionList', 'uach_value' => 'Google Chrome 114.0.5735.199' ],
					[ 'uach_name' => 'mobile', 'uach_value' => '0' ],
					[ 'uach_name' => 'platform', 'uach_value' => "Windows" ],
					[ 'uach_name' => 'platformVersion', 'uach_value' => "15.0.0" ],
				],
			],
			'Example Windows device using Chrome with duplicated data' => [
				$exampleJsApiData['Example Windows device using Chrome with duplicated data'],
				[
					[ 'uach_name' => 'architecture', 'uach_value' => 'x86' ],
					[ 'uach_name' => 'bitness', 'uach_value' => '64' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'Not.A/Brand 8' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'Chromium 114' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'Google Chrome 114' ],
					[ 'uach_name' => 'fullVersionList', 'uach_value' => 'Not.A/Brand 8.0.0.0' ],
					[ 'uach_name' => 'fullVersionList', 'uach_value' => 'Chromium 114.0.5735.199' ],
					[ 'uach_name' => 'fullVersionList', 'uach_value' => 'Google Chrome 114.0.5735.199' ],
					[ 'uach_name' => 'mobile', 'uach_value' => '0' ],
					[ 'uach_name' => 'platform', 'uach_value' => "Windows" ],
					[ 'uach_name' => 'platformVersion', 'uach_value' => "15.0.0" ],
				],
			],
			'Fake data with too many brands' => [
				[
					'brands' => [
						// 11 Brands in this list. The last brand should not be
						// returned by ::toDatabaseRows.
						[
							"brand" => "Not.A/Brand",
							"version" => "8",
						],
						[
							"brand" => "Chromium",
							"version" => "114",
						],
						[
							"brand" => "Chromium1234",
							"version" => "114",
						],
						[
							"brand" => "Google Chrome",
							"version" => "113",
						],
						[
							"brand" => "Not.A/Brand",
							"version" => "9",
						],
						[
							"brand" => "Chromium",
							"version" => "113",
						],
						[
							"brand" => "A.Different.Browser",
							"version" => "113",
						],
						[
							"brand" => "Google Chrome",
							"version" => "114",
						],
						[
							"version" => "10",
							"brand" => "Not.A/Brand",
						],
						[
							"brand" => "Chromiumabc",
							"version" => "12345",
						],
						[
							"brand" => "Test.Should.not.be.added",
							"version" => "132323",
						],
					],
					'fullVersionList' => [],
				],
				[
					[ 'uach_name' => 'brands', 'uach_value' => 'Not.A/Brand 8' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'Chromium 114' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'Chromium1234 114' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'Google Chrome 113' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'Not.A/Brand 9' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'Chromium 113' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'A.Different.Browser 113' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'Google Chrome 114' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'Not.A/Brand 10' ],
					[ 'uach_name' => 'brands', 'uach_value' => 'Chromiumabc 12345' ],
				],
			],
			'Non-array fullVersionList data that includes both valid and invalid types' => [
				[
					'fullVersionList' => [
						// Strings should be stored.
						'test',
						// Integers and floats should be stored
						1,
						1.1,
						// Checks de-duplication occurs after string conversion.
						'1.1',
						// False and null should be ignored.
						false,
						null,
						// Key should be ignored and string value should be saved
						'testkey' => 'testvalue',
					],
				],
				[
					[ 'uach_name' => 'fullVersionList', 'uach_value' => 'test' ],
					[ 'uach_name' => 'fullVersionList', 'uach_value' => '1' ],
					[ 'uach_name' => 'fullVersionList', 'uach_value' => '1.1' ],
					[ 'uach_name' => 'fullVersionList', 'uach_value' => 'testvalue' ],
				],
			],
		];
	}

	/** @dataProvider provideClientHintsJsApiDataForLoop */
	public function testNewFromDatabaseRowsLoop( $dataFromJsApi ) {
		// Tests that a ClientHintsData object from the JS API is not
		// corrupted by converting to database rows and then converting
		// to a new ClientHintsData object from those database rows.
		$initialClientHintsData = ClientHintsData::newFromJsApi( $dataFromJsApi );
		$databaseRows = $initialClientHintsData->toDatabaseRows();
		$objectToTest = ClientHintsData::newFromDatabaseRows( $databaseRows );
		$this->assertClientHintsDataObjectsEqual( $initialClientHintsData, $objectToTest, true );
	}

	public static function provideClientHintsJsApiDataForLoop() {
		$exampleJsApiData = self::getExampleJsApiData();
		return [
			'No client hint data' => [
				$exampleJsApiData['No client hint data'],
			],
			'Example Windows device using Chrome' => [
				$exampleJsApiData['Example Windows device using Chrome'],
			],
			'Example Windows device using Chrome with duplicated data' => [
				$exampleJsApiData['Example Windows device using Chrome with duplicated data'],
			],
		];
	}

	/** @dataProvider provideClientHintsJsApiDataForLoop */
	public function testJsonSerialiseLoop( $dataFromJsApi ) {
		$initialClientHintsData = ClientHintsData::newFromJsApi( $dataFromJsApi );
		$jsonSerialised = $initialClientHintsData->jsonSerialize();
		$otherClientHintsData = ClientHintsData::newFromSerialisedJsonArray( $jsonSerialised );
		$this->assertClientHintsDataObjectsEqual( $initialClientHintsData, $otherClientHintsData );
	}
}
