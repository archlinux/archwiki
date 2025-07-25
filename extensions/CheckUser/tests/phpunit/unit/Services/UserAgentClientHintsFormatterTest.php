<?php

namespace MediaWiki\CheckUser\Tests\Unit\Services;

use MediaWiki\CheckUser\ClientHints\ClientHintsLookupResults;
use MediaWiki\CheckUser\Services\UserAgentClientHintsFormatter;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Message\Message;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use MessageLocalizer;
use ReflectionClass;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Services\UserAgentClientHintsFormatter
 */
class UserAgentClientHintsFormatterTest extends MediaWikiUnitTestCase {
	use MockServiceDependenciesTrait;
	use CheckUserClientHintsCommonTraitTest;

	private function getObjectUnderTest(): UserAgentClientHintsFormatter {
		$mockMessageLocalizer = $this->createMock( MessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )
			->willReturn( $this->createMock( Message::class ) );
		return $this->newServiceInstance( UserAgentClientHintsFormatter::class, [
			'messageLocalizer' => $mockMessageLocalizer
		] );
	}

	/** @dataProvider provideTestArrayItems */
	public function testGetBrandAsStringForArray( $item, $expectedString ) {
		$objectUnderTest = $this->getObjectUnderTest();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertSame(
			$expectedString,
			$objectUnderTest->getBrandAsString( $item, false ),
			'::getBrandAsString result was not as expected.'
		);
	}

	public static function provideTestArrayItems() {
		return [
			'Empty array' => [ [], '' ],
			'Array with integer keys' => [ [ 0 => 'test', 1 => 'testing' ], 'test testing' ],
			'Array with string keys' => [ [ 'test' => 'testing', 'test-test' => 'test1234' ], 'testing test1234' ],
			'Array with unordered keys' => [ [ 1 => 'abc', 0 => 'test' ], 'test abc' ],
			'Array with "brand" and "version" keys' => [ [ 'brand' => 'test', 'version' => '15.0' ], 'test 15.0' ]
		];
	}

	/** @dataProvider provideTestArrayItemsForSignificantOnly */
	public function testGetBrandAsStringForArrayAndSignificantOnly( $item, $expectedString ) {
		$objectUnderTest = $this->getObjectUnderTest();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertSame(
			$expectedString,
			$objectUnderTest->getBrandAsString( $item, true ),
			'::getBrandAsString result was not as expected.'
		);
	}

	public static function provideTestArrayItemsForSignificantOnly() {
		return [
			'Empty array' => [ [], '' ],
			'Array with integer keys' => [ [ 0 => 'test', 1 => 'testing' ], 'test testing' ],
			'Array with string keys' => [ [ 'test' => 'testing', 'test-test' => 'test1234' ], 'testing test1234' ],
			'Array with unordered keys' => [ [ 1 => 'abc', 0 => '12.0.0' ], '12.0.0 abc' ],
			'Array with "brand" and "version" keys' => [ [ 'brand' => 'test', 'version' => '15.0.0' ], 'test 15' ],
			'Array with "brand" and significant "version" value' => [
				[ 'brand' => 'test', 'version' => '14' ], 'test 14'
			],
		];
	}

	public function testGetBrandAsStringForString() {
		$objectUnderTest = $this->getObjectUnderTest();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertSame(
			'test',
			$objectUnderTest->getBrandAsString( 'test', false ),
			'::getBrandAsString should return the first parameter unchanged if the first parameter is a string.'
		);
	}

	public function testInvalidTypeForItemParameter() {
		$objectUnderTest = $this->getObjectUnderTest();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertNull(
			$objectUnderTest->getBrandAsString( false, false ),
			'::getBrandAsString should return null if the first parameter is a boolean.'
		);
		$this->assertNull(
			$objectUnderTest->getBrandAsString( 1, false ),
			'::getBrandAsString should return null if the first parameter is an integer.'
		);
	}

	/** @dataProvider provideGenerateClientHintsListItemForInvalidValue */
	public function testGenerateClientHintsListItemForInvalidValue( $invalidValue ) {
		$objectUnderTest = $this->getObjectUnderTest();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertSame(
			'',
			$objectUnderTest->generateClientHintsListItem( 'model', $invalidValue ),
			'Result from ::generateClientHintsListItem was not as expected for invalid value.'
		);
	}

	public static function provideGenerateClientHintsListItemForInvalidValue() {
		return [
			'Null value' => [ null ],
			'Empty string' => [ '' ],
		];
	}

	public function commonTestGenerateClientHintsListItem( $toHideArray ): UserAgentClientHintsFormatter {
		$mockMessageLocalizer = $this->createMock( MessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )
			->willReturn( $this->createMock( Message::class ) );
		return $this->newServiceInstance( UserAgentClientHintsFormatter::class, [
			'messageLocalizer' => $mockMessageLocalizer,
			'options' => new ServiceOptions(
				UserAgentClientHintsFormatter::CONSTRUCTOR_OPTIONS,
				new HashConfig( [
					'CheckUserClientHintsValuesToHide' => $toHideArray,
					'CheckUserClientHintsForDisplay' => []
				] )
			)
		] );
	}

	/** @dataProvider provideGenerateClientHintsListItemForHiddenValue */
	public function testGenerateClientHintsListItemForHiddenValue( $toHideArray, $clientHintName, $clientHintValue ) {
		$objectUnderTest = $this->commonTestGenerateClientHintsListItem( $toHideArray );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertSame(
			'',
			$objectUnderTest->generateClientHintsListItem( $clientHintName, $clientHintValue ),
			'Result from ::generateClientHintsListItem should be the empty string if the ' .
			'value is excluded from display.'
		);
	}

	public static function provideGenerateClientHintsListItemForHiddenValue() {
		return [
			'One item in to hide array that matches the name-value pair being passed' => [
				[ 'bitness' => [ '64' ] ], 'bitness', '64'
			],
			'Multiple items in to hide array that matches the name-value pair being passed' => [
				[ 'bitness' => [ '64' ], 'model' => [ 'test', 'testing' ] ], 'model', 'testing'
			],
		];
	}

	/** @dataProvider provideGenerateClientHintsListItem */
	public function testGenerateClientHintsListItem(
		$clientHintName, $clientHintValue, $expectedClientHintValueUsed, $msgCache
	) {
		// Use mock method under test with disabled constructor to avoid
		// having to mock the MessageLocalizer twice.
		$objectUnderTest = $this->getMockBuilder( UserAgentClientHintsFormatter::class )
			->disableOriginalConstructor()
			->getMock();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		// Set options as empty arrays.
		$objectUnderTest->options = new ServiceOptions(
			UserAgentClientHintsFormatter::CONSTRUCTOR_OPTIONS,
			new HashConfig( [
				'CheckUserClientHintsValuesToHide' => [],
				'CheckUserClientHintsForDisplay' => []
			] )
		);
		// Mock the message class to expect the ::msg call to make the formatted string.
		$messageMock = $this->createMock( Message::class );
		$messageMock->expects( $this->once() )
			->method( 'rawParams' )
			->with( $msgCache[UserAgentClientHintsFormatter::NAME_TO_MESSAGE_KEY[$clientHintName]] )
			->willReturnSelf();
		$messageMock->expects( $this->once() )
			->method( 'params' )
			->with( $expectedClientHintValueUsed )
			->willReturnSelf();
		$messageMock->expects( $this->once() )
			->method( 'escaped' )
			->willReturn( 'mocked return message' );
		// Create a mock MessageLocalizer to return the mock Message class on a call to ::msg.
		$messageLocalizerMock = $this->createMock( MessageLocalizer::class );
		$messageLocalizerMock->expects( $this->once() )
			->method( 'msg' )
			->willReturn( $messageMock );
		$objectUnderTest->messageLocalizer = $messageLocalizerMock;
		$objectUnderTest->msgCache = $msgCache;
		$this->assertSame(
			'mocked return message',
			$objectUnderTest->generateClientHintsListItem( $clientHintName, $clientHintValue ),
			'Result from ::generateClientHintsListItem was not as expected.'
		);
	}

	public static function provideGenerateClientHintsListItem() {
		$exampleMsgCache = [
			'checkuser-clienthints-name-brand' => 'Brand:',
			'checkuser-clienthints-name-model' => 'Model:',
			'checkuser-clienthints-name-mobile' => 'Mobile:',
			'checkuser-clienthints-value-yes' => 'Yes',
			'checkuser-clienthints-value-no' => 'No',
		];
		return [
			'Brand Client Hint' => [
				'brands', 'test 15.0', 'test 15.0', $exampleMsgCache,
			],
			'Mobile Client Hint as false' => [
				'mobile', false, 'No', $exampleMsgCache,
			],
			'Mobile Client Hint as true' => [
				'mobile', true, 'Yes', $exampleMsgCache,
			],
		];
	}

	/** @dataProvider provideCombineClientHintsData */
	public function testCombineClientHintsData(
		$dataAsArray, $clientHintsForDisplay, $expectedReturnValue, $expectedClientHintsForDisplayAfterCall
	) {
		$objectUnderTest = $this->getObjectUnderTest();
		// T287318 - TestingAccessWrapper::__call does not support pass-by-reference
		$classReflection = new ReflectionClass( $objectUnderTest );
		$methodReflection = $classReflection->getMethod( 'combineClientHintsData' );
		$methodReflection->setAccessible( true );
		$this->assertArrayEquals(
			$expectedReturnValue,
			$methodReflection->invokeArgs( $objectUnderTest, [
				$dataAsArray, &$clientHintsForDisplay
			] ),
			false,
			true,
			'Return result from ::combineClientHintsData was not as expected.'
		);
		$this->assertArrayEquals(
			$expectedClientHintsForDisplayAfterCall,
			$clientHintsForDisplay,
			true,
			true,
			'The passed-by-reference $clientHintsForDisplay array value was not as expected after the call ' .
			'to ::combineClientHintsData.'
		);
	}

	public static function provideCombineClientHintsData() {
		return [
			'No data to combine' => [
				[ 'model' => 'test', 'bitness' => '32', 'platform' => 'Test' ],
				[ 'model', 'bitness', 'brands', 'fullVersionList', 'platform', 'platformVersion' ],
				[ 'model' => 'test', 'bitness' => '32', 'platform' => 'Test' ],
				[ 'model', 'bitness', 'brands', 'fullVersionList', 'platform', 'platformVersion' ],
			],
			'Platform and platformVersion to be combined' => [
				[ 'model' => 'test', 'platform' => 'Test', 'platformVersion' => '15.0.0' ],
				[ 'platformVersion', 'model', 'bitness', 'platform' ],
				[ 'platform' => 'Test 15.0.0', 'model' => 'test' ],
				[ 'platform', 'platformVersion', 'model', 'bitness' ],
			],
			'Platform and platformVersion to be combined but no change in display array' => [
				[ 'model' => 'test', 'platform' => 'Test', 'platformVersion' => '15.0.0' ],
				[ 'model', 'platform', 'bitness', 'platformVersion' ],
				[ 'platform' => 'Test 15.0.0', 'model' => 'test' ],
				[ 'model', 'platform', 'bitness', 'platformVersion' ],
			],
			'Brands and fullVersionList to be combined' => [
				[
					'model' => 'test',
					'brands' => [
						[ 'brand' => 'Test brand', 'version' => '10' ],
						[ 'brand' => 'Test other brand', 'version' => '5' ],
					],
					'fullVersionList' => [
						[ 'brand' => 'Test brand', 'version' => '10.5.4.2' ],
						[ 'brand' => 'Test another brand', 'version' => '5.4.4.4' ],
						[ 'brand' => 'Test other brand', 'version' => '7.6.5.5' ],
					]
				],
				[ 'model', 'fullVersionList', 'brands' ],
				[
					'model' => 'test',
					'brands' => [
						[ 'brand' => 'Test other brand', 'version' => '5' ],
					],
					'fullVersionList' => [
						[ 'brand' => 'Test brand', 'version' => '10.5.4.2' ],
						[ 'brand' => 'Test another brand', 'version' => '5.4.4.4' ],
						[ 'brand' => 'Test other brand', 'version' => '7.6.5.5' ],
					]
				],
				[ 'model', 'fullVersionList', 'brands' ],
			],
			'Multiple brands and fullVersionList to be combined' => [
				[
					'model' => 'test',
					'brands' => [
						[ 'brand' => 'Test brand', 'version' => '10' ],
						[ 'brand' => 'Test other brand', 'version' => '6' ],
						[ 'brand' => 'Yet another brand', 'version' => '20' ],
						[ 'brand' => 'Testing brand', 'version' => '4' ],
					],
					'fullVersionList' => [
						[ 'brand' => 'Test brand', 'version' => '10.5.4.2' ],
						[ 'brand' => 'Yet another brand', 'version' => '20.4.4.4' ],
						[ 'brand' => 'Test other brand', 'version' => '7.6.5.5' ],
						[ 'brand' => 'Testing2 brand', 'version' => '4' ],
					]
				],
				[ 'model', 'fullVersionList', 'brands' ],
				[
					'model' => 'test',
					'brands' => [
						[ 'brand' => 'Test other brand', 'version' => '6' ],
						[ 'brand' => 'Testing brand', 'version' => '4' ],
					],
					'fullVersionList' => [
						[ 'brand' => 'Test brand', 'version' => '10.5.4.2' ],
						[ 'brand' => 'Yet another brand', 'version' => '20.4.4.4' ],
						[ 'brand' => 'Test other brand', 'version' => '7.6.5.5' ],
						[ 'brand' => 'Testing2 brand', 'version' => '4' ],
					]
				],
				[ 'model', 'fullVersionList', 'brands' ],
			],
		];
	}

	/** @dataProvider provideBatchFormatClientHintsData */
	public function testBatchFormatClientHintsData( $clientHintsLookupResults ) {
		$objectUnderTest = $this->getMockBuilder( UserAgentClientHintsFormatter::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'formatClientHintsDataObject' ] )
			->getMock();
		[ $referenceIdsToClientHintsDataIndex, $clientHintsDataObjects ] = $clientHintsLookupResults->getRawData();

		$expectedFormattedClientHints = [];
		$returnMap = [];
		foreach ( $clientHintsDataObjects as $key => $clientHintsDataObject ) {
			$retVal = 'formatted result for key ' . $key;
			$expectedFormattedClientHints[] = $retVal;
			$returnMap[] = [ $clientHintsDataObject, $retVal ];
		}
		$objectUnderTest->method( 'formatClientHintsDataObject' )
			->willReturnMap( $returnMap );
		$returnedBatchFormatterResults = $objectUnderTest->batchFormatClientHintsData( $clientHintsLookupResults );
		$returnedBatchFormatterResults = TestingAccessWrapper::newFromObject( $returnedBatchFormatterResults );
		$this->assertArrayEquals(
			$expectedFormattedClientHints,
			$returnedBatchFormatterResults->formattedClientHints,
			false,
			true,
			'Result from ::batchFormatClientHintsData did not generate the correct formatted Client Hints.'
		);
		$this->assertArrayEquals(
			$referenceIdsToClientHintsDataIndex,
			$returnedBatchFormatterResults->referenceIdsToFormattedClientHintsIndex,
			false,
			true,
			'Result from ::batchFormatClientHintsData did not generate the correct reference IDs map.'
		);
	}

	public static function provideBatchFormatClientHintsData() {
		return [
			'Empty lookup results' => [ new ClientHintsLookupResults( [], [] ) ],
			'Lookup results with one ClientHintsData object' => [
				new ClientHintsLookupResults(
					[ UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1 => 0 ] ],
					[ self::getExampleClientHintsDataObjectFromJsApi() ]
				)
			],
			'Lookup results with two ClientHintsData object' => [
				new ClientHintsLookupResults(
					[
						UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1 => 0 ],
						UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [ 23 => 1 ],
					],
					[
						self::getExampleClientHintsDataObjectFromJsApi(),
						self::getExampleClientHintsDataObjectFromJsApi( 'arm' )
					]
				)
			],
		];
	}

	public function testListToTextWithoutAndForEmptyArray() {
		$objectUnderTest = $this->getObjectUnderTest();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertSame(
			'',
			$objectUnderTest->listToTextWithoutAnd( [] ),
			'::listToTextWithoutAnd should return an empty string for no items.'
		);
	}

	/** @dataProvider provideListToTextWithoutAnd */
	public function testListToTextWithoutAnd( $array, $expectedReturnValue ) {
		$mockMessage = $this->createMock( Message::class );
		$mockMessage->method( 'escaped' )
			->willReturn( ', ' );
		$mockMessageLocalizer = $this->createMock( MessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )
			->with( 'comma-separator' )
			->willReturn( $mockMessage );
		$objectUnderTest = $this->getMockBuilder( UserAgentClientHintsFormatter::class )
			->disableOriginalConstructor()
			->getMock();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$objectUnderTest->messageLocalizer = $mockMessageLocalizer;
		$this->assertSame(
			$expectedReturnValue,
			$objectUnderTest->listToTextWithoutAnd( $array ),
			'::listToTextWithoutAnd should return an empty string for no items.'
		);
	}

	public static function provideListToTextWithoutAnd() {
		return [
			'Array with one item' => [
				[ 'test' ], 'test'
			],
			'Array with two items' => [
				[ 'test', 'testing' ], 'test, testing'
			],
			'Array with multiple items' => [
				[ 'testing1244', 'testing121212', 'test(,)test', '1234' ],
				'testing1244, testing121212, test(,)test, 1234'
			]
		];
	}
}
