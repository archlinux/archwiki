<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\CheckUser\HookHandler\RecentChangeSaveHandler;
use MediaWiki\CheckUser\Maintenance\PopulateCheckUserTablesWithSimulatedData;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\User\User;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\Maintenance\PopulateCheckUserTablesWithSimulatedData
 */
class PopulateCheckUserTablesWithSimulatedDataTest extends MaintenanceBaseTestCase {
	use CheckUserCommonTraitTest;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateCheckUserTablesWithSimulatedData::class;
	}

	/**
	 * Get the maintenance object defined as the type
	 * TestingAccessWrapper to prevent IDE errors about
	 * the type.
	 *
	 * @param ?array $randomFloatValues Mocked return values for ::getRandomFloat. Provide null to not mock the
	 * method. Provide an empty array to assert it is never called.
	 * @return TestingAccessWrapper
	 */
	private function getMockedMaintenance( ?array $randomFloatValues = null ): TestingAccessWrapper {
		if ( $randomFloatValues !== null ) {
			$mockObject = $this->createPartialMock( $this->getMaintenanceClass(), [ 'getRandomFloat' ] );
			if ( !count( $randomFloatValues ) ) {
				$mockObject->expects( $this->never() )
					->method( 'getRandomFloat' );
			} else {
				$mockObject->expects( $this->atMost( count( $randomFloatValues ) ) )
					->method( 'getRandomFloat' )
					->willReturnOnConsecutiveCalls( ...$randomFloatValues );
			}
			$mockObject = TestingAccessWrapper::newFromObject( $mockObject );
		} else {
			/** @var TestingAccessWrapper $mockObject */
			$mockObject = $this->maintenance;
		}
		$mockObject->mainRequest = new FauxRequest();
		RequestContext::getMain()->setRequest( $mockObject->mainRequest );
		$mockObject->recentChangeSaveHandler = new RecentChangeSaveHandler(
			$this->getServiceContainer()->get( 'CheckUserInsert' ),
			$this->getServiceContainer()->getJobQueueGroup(),
			$this->getServiceContainer()->getConnectionProvider()
		);
		return $mockObject;
	}

	/** @dataProvider provideCreateRegisteredUser */
	public function testCreateRegisteredUser( $username ) {
		$this->assertInstanceOf(
			User::class,
			$this->getMockedMaintenance()->createRegisteredUser( $username ),
			'::createRegisteredUser should return a valid user.'
		);
	}

	public static function provideCreateRegisteredUser() {
		return [
			'Username unprovided (using default of null)' => [ null ],
			'Username provided' => [ wfRandomString() ],
		];
	}

	public function testCreateRegisteredUserProvidingExistingUsername() {
		$this->testCreateRegisteredUser( $this->getTestUser()->getUserIdentity()->getName() );
	}

	/** @dataProvider provideRandomlyAssignXFFHeader */
	public function testRandomlyAssignXFFHeader( $randomFloatValues, $ipsToUse, $currentIp, $expectedXffIp ) {
		$mockObject = $this->getMockedMaintenance( $randomFloatValues );
		$mockObject->ipsToUse = $ipsToUse;
		$mockObject->randomlyAssignXFFHeader( $currentIp );
		$this->assertSame(
			$expectedXffIp,
			RequestContext::getMain()->getRequest()->getHeader( 'X-Forwarded-For' ),
			'XFF was not as expected.'
		);
	}

	public static function provideRandomlyAssignXFFHeader() {
		return [
			'One IP to be used in XFF header' => [
				[ 0.1, 0.8 ], [ '1.2.3.4', '1.2.3.5' ], '1.2.3.4', '1.2.3.5'
			],
		];
	}

	/** @dataProvider provideGetNewIp */
	public function testGetNewIp( $ipsToUse ) {
		$mockObject = $this->getMockedMaintenance();
		$mockObject->ipsToUse = $ipsToUse;
		$ip = $mockObject->getNewIp();
		$this->assertSame(
			$ip,
			RequestContext::getMain()->getRequest()->getIP(),
			'Request IP was not set correctly.'
		);
		$this->assertContains(
			$ip,
			$ipsToUse,
			'IP chosen was not from the IPs to use.'
		);
	}

	public static function provideGetNewIp() {
		return [
			'Three IPs' => [ [ '128.0.4.1', '127.0.0.1', '2001:0db8:85a3:0000:0000:8a2e:0370:7334' ] ],
		];
	}

	/** @dataProvider provideGetNewUserAgentAndAssociatedClientHints */
	public function testGetNewUserAgentAndAssociatedClientHints( $userAgentClientHintsMap ) {
		$mockObject = $this->getMockedMaintenance();
		$mockObject->userAgentsToClientHintsMap = $userAgentClientHintsMap;
		// Insert some Client Hints headers to ensure Client Hints headers are removed.
		$mockObject->mainRequest->setHeader( 'Sec-Ch-Ua-Arch', 'test' );
		$mockObject->mainRequest->setHeader( 'Sec-Ch-Ua', 'testing' );
		// Call the method to test.
		$mockObject->getNewUserAgentAndAssociatedClientHints();
		// Assert that the User-Agent header is correct.
		$userAgentInRequest = $mockObject->mainRequest->getHeader( 'User-Agent' );
		$this->assertContains(
			$userAgentInRequest,
			array_keys( $mockObject->userAgentsToClientHintsMap ),
			'The User-Agent header was not correctly set.'
		);
		// Assert Client Hints data was correctly present or not present as determined
		// by the map.
		if ( $mockObject->userAgentsToClientHintsMap[$userAgentInRequest] === null ) {
			$this->assertNull(
				$mockObject->currentClientHintsData,
				'No Client Hints data should exist for this user agent.'
			);
			$this->assertCount(
				0,
				array_filter(
					array_keys( array_filter( $mockObject->mainRequest->getAllHeaders() ) ),
					static fn ( $headerName ) => str_starts_with( $headerName, 'SEC-CH-UA' )
				),
				'Client Hints headers were set for the request when the client does not support Client Hints.'
			);
		} else {
			$this->assertInstanceOf(
				ClientHintsData::class,
				$mockObject->currentClientHintsData,
				'Client Hints data should exist for this user agent.'
			);
			$this->assertArrayEquals(
				array_map(
					'strtoupper',
					array_filter(
						array_keys( $mockObject->getConfig()->get( 'CheckUserClientHintsHeaders' ) ),
						static function ( $headerName ) use ( $mockObject ) {
							if ( !$headerName ) {
								return false;
							}
							$propertyName = ClientHintsData::HEADER_TO_CLIENT_HINTS_DATA_PROPERTY_NAME[$headerName];
							return $mockObject->currentClientHintsData->jsonSerialize()[$propertyName];
						}
					)
				),
				array_filter(
					array_keys( array_filter( $mockObject->mainRequest->getAllHeaders() ) ),
					static fn ( $headerName ) => str_starts_with( $headerName, 'SEC-CH-UA' )
				),
				false,
				false,
				'Client Hints headers were not set correctly.'
			);
		}
	}

	public static function provideGetNewUserAgentAndAssociatedClientHints() {
		return [
			'No Client Hints data' => [ [
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/116.0' => null,
			] ],
			'With Client hints data' => [ [
				'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) ' .
				'Chrome/115.0.0.0 Mobile Safari/537.36' =>
					new ClientHintsData(
						"",
						"64",
						[
							[ "brand" => "Not/A)Brand", "version" => "99" ],
							[ "brand" => "Google Chrome", "version" => "115" ],
							[ "brand" => "Chromium", "version" => "115" ],
						],
						null,
						[
							[ "brand" => "Not/A)Brand", "version" => "99.0.0.0" ],
							[ "brand" => "Google Chrome", "version" => "115.0.5790.171" ],
							[ "brand" => "Chromium", "version" => "115.0.5790.171" ],
						],
						true,
						"SM-G965U",
						"Android",
						"10.0.0",
						false
					)
			] ],
		];
	}

	/** @dataProvider provideLogActions */
	public function testSimulateLogAction( $type, $action ) {
		$mockObject = $this->getMockedMaintenance();
		$testUser = $this->getTestUser()->getUserIdentity();
		$mockObject->simulateLogAction( $type, $action, $testUser );
		$this->assertRowCount(
			1,
			'logging',
			'*',
			'Should be one log event in the logging table.'
		);
		$this->assertRowCount(
			1,
			'cu_log_event',
			'*',
			'There should one log event in cu_log_event.'
		);
	}

	public static function provideLogActions() {
		return [
			'Move a page' => [ 'move', 'move' ],
			'Merge a page' => [ 'merge', 'merge' ],
			'Undelete a page' => [ 'delete', 'undelete' ],
		];
	}

	/** @dataProvider provideShouldBeMinorEdit */
	public function testPerformEdit( $shouldBeMinorEdit ) {
		$title = $this->getNonexistingTestPage()->getTitle();
		$randomValue = $shouldBeMinorEdit ? 0.3 : 0.7;
		$mockObject = $this->createPartialMock( PopulateCheckUserTablesWithSimulatedData::class, [ 'getRandomFloat' ] );
		$mockObject->expects( $this->once() )
			->method( 'getRandomFloat' )
			->willReturn( $randomValue );
		$mockObject = TestingAccessWrapper::newFromObject( $mockObject );
		$testUser = $this->getTestUser()->getUser();
		$revisionId = $mockObject->performEdit( $testUser, $title );
		$this->assertNotNull(
			$revisionId,
			'Revision ID should not be null.'
		);
		$revisionRecord = $this->getServiceContainer()->getRevisionStore()->getRevisionById( $revisionId );
		$this->assertTrue(
			$revisionRecord->getUser()->equals( $testUser ),
			'Revision was not saved using the correct user.'
		);
		$this->assertSame(
			$shouldBeMinorEdit,
			$revisionRecord->isMinor(),
			'Minor edit status was incorrect.'
		);
	}

	public static function provideShouldBeMinorEdit() {
		return [
			'Should be a minor edit' => [ true ],
			'Should not be a minor edit' => [ false ],
		];
	}

	public function testExecuteFailsWhenDevelopmentModeIsOff() {
		$this->overrideConfigValue( 'CheckUserDeveloperMode', false );
		$objectUnderTest = $this->createPartialMock( $this->getMaintenanceClass(), [ 'fatalError' ] );
		$objectUnderTest->expects( $this->once() )
			->method( 'fatalError' )
			->with(
				"CheckUser development mode must be enabled to use this script. To do this, set " .
				"wgCheckUserDeveloperMode to true. Only do this on localhost testing wikis."
			)
			->willThrowException( new \Exception( "Script will have exited." ) );
		$this->expectExceptionMessage( "Script will have exited." );
		$objectUnderTest->execute();
		$this->assertRowCount(
			0, 'cu_changes', 'cuc_id',
			'No actions should be inserted to cu_changes.'
		);
		$this->assertRowCount(
			0, 'cu_log_event', 'cule_id',
			'No actions should be inserted to cu_log_event.'
		);
		$this->assertRowCount(
			0, 'cu_private_event', 'cupe_id',
			'No actions should be inserted to cu_private_event.'
		);
	}
}
