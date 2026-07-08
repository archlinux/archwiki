<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\CheckUser\Pagers;

use LogicException;
use MediaWiki\Extension\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager;
use MediaWiki\Extension\CheckUser\CheckUser\Pagers\CheckUsernameResultInterface;
use MediaWiki\Extension\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsLookup;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\LinkBatch;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for CheckUserGetUsersPager class
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\Extension\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager
 */
class CheckUserGetUsersPagerTest extends CheckUserPagerUnitTestBase {

	protected function getPagerClass(): string {
		return CheckUserGetUsersPager::class;
	}

	/** @dataProvider provideFormatRow */
	public function testFormatRow( $rowArgument ) {
		$objectUnderTest = $this->getMockBuilder( CheckUserGetUsersPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$this->assertSame(
			'',
			$objectUnderTest->formatRow( $rowArgument ),
			'::formatRow should return the empty string as it is not called.'
		);
	}

	public static function provideFormatRow() {
		return [
			'Empty array' => [ [] ],
			'Empty object' => [ (object)[] ],
			'Array with items' => [ [ 'user_text' => 'test' ] ],
			'Object with items' => [ (object)[ 'user' => 0 ] ],
		];
	}

	public function testGetQueryInfoThrowsExceptionWithNullTable() {
		$object = $this->getMockBuilder( CheckUserGetUsersPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$this->expectException( LogicException::class );
		$object->getQueryInfo( null );
	}

	/** @dataProvider provideGetQueryInfoForCuChanges */
	public function testGetQueryInfoForCuChanges( $expectedQueryInfo ) {
		$this->commonGetQueryInfoForTableSpecificMethod(
			'getQueryInfoForCuChanges',
			[],
			$expectedQueryInfo
		);
	}

	public static function provideGetQueryInfoForCuChanges() {
		return [
			'Returns expected keys to arrays and includes cu_changes in tables' => [ [
				// Fields should be an array
				'fields' => [
					'client_hints_reference_id' => 'cuc_this_oldid',
					'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
				],
				// Assert at least cu_changes in the table list
				'tables' => [ 'cu_changes' ],
				// Should be all of these as arrays
				'conds' => [],
				'options' => [],
				'join_conds' => [],
			] ],
		];
	}

	/** @dataProvider provideGetQueryInfoForCuLogEvent */
	public function testGetQueryInfoForCuLogEvent( $expectedQueryInfo ) {
		$this->commonGetQueryInfoForTableSpecificMethod(
			'getQueryInfoForCuLogEvent',
			[],
			$expectedQueryInfo
		);
	}

	public static function provideGetQueryInfoForCuLogEvent() {
		return [
			'Returns expected keys to arrays and includes cu_log_event in tables' => [ [
				# Fields should be an array
				'fields' => [
					'client_hints_reference_id' => 'cule_log_id',
					'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT,
				],
				# Tables array should have at least cu_private_event
				'tables' => [ 'cu_log_event' ],
				# All other values should be arrays
				'conds' => [],
				'options' => [],
				'join_conds' => [],
			] ],
		];
	}

	/** @dataProvider provideGetQueryInfoForCuPrivateEvent */
	public function testGetQueryInfoForCuPrivateEvent( $expectedQueryInfo ) {
		$this->commonGetQueryInfoForTableSpecificMethod(
			'getQueryInfoForCuPrivateEvent',
			[],
			$expectedQueryInfo
		);
	}

	public static function provideGetQueryInfoForCuPrivateEvent() {
		return [
			'Returns expected keys to arrays and includes cu_private_event in tables' => [ [
				# Fields should be an array
				'fields' => [
					'client_hints_reference_id' => 'cupe_id',
					'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
				],
				# Tables array should have at least cu_private_event
				'tables' => [ 'cu_private_event' ],
				# All other values should be arrays
				'conds' => [],
				'options' => [],
				// The actor table should be joined using a LEFT JOIN
				'join_conds' => [ 'actor_cupe_actor' => [ 'LEFT JOIN', 'actor_cupe_actor.actor_id=cupe_actor' ] ],
			] ],
		];
	}

	/** @dataProvider providePreprocessResults */
	public function testPreprocessResults(
		$results,
		$expectedReferenceIdsForLookup,
		$expectedUserSets
	) {
		// Get the object to test with
		$objectUnderTest = $this->getMockBuilder( CheckUserGetUsersPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );

		// Expect that the method under test looks up the Client Hints data objects using the
		// UserAgentClientHintsLookup service and also that the reference IDs being used are as expected.
		$mockClientHintsLookup = $this->createMock( UserAgentClientHintsLookup::class );
		$mockClientHintsLookup->expects( $this->once() )
			->method( 'getClientHintsByReferenceIds' )
			->with( $this->callback( function ( $referenceIds ) use ( $expectedReferenceIdsForLookup ) {
				// Assert that the ClientHintsReferenceIds object passed has the
				// correct reference IDs
				// If this is the case, then return true.
				$this->assertArrayEquals(
					$expectedReferenceIdsForLookup->getReferenceIds(),
					$referenceIds->getReferenceIds(),
					false,
					true,
					'::preprocessResults did use the expected reference IDs to lookup the Client Hints data.'
				);
				return true;
			} ) );
		$objectUnderTest->clientHintsLookup = $mockClientHintsLookup;

		$linkBatch = $this->createMock( LinkBatch::class );

		// Each unique user should be added to LinkBatch exactly once.
		$uniqueUserCount = count( $expectedUserSets['edits'] );
		$linkBatch->expects( $this->exactly( $uniqueUserCount ) )
			->method( 'addUser' )
			->willReturnCallback( function ( UserIdentity $user ) use ( $results ): void {
				$row = $results->current();
				$this->assertSame( $row->user_text, $user->getName() );
				$this->assertSame( $row->user ?? 0, $user->getId() );
			} );

		$linkBatchFactory = $this->createMock( LinkBatchFactory::class );
		$linkBatchFactory->method( 'newLinkBatch' )
			->willReturn( $linkBatch );
		$objectUnderTest->linkBatchFactory = $linkBatchFactory;

		$mockSiCaseLookup = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$mockSiCaseLookup->method( 'areSuggestedInvestigationsEnabled' )
			->willReturn( false );
		$objectUnderTest->siCaseLookupService = $mockSiCaseLookup;

		// Call the method under test.
		$objectUnderTest->preprocessResults( $results );
		// Assert that the userSets array contains the expected items.
		$actualWithoutClientHints = $objectUnderTest->userSets;
		unset( $actualWithoutClientHints['clienthints'] );
		$this->assertArrayContains(
			$actualWithoutClientHints,
			$expectedUserSets,
			'::preprocessResults did not set the "userSets" property to the expected array.'
		);
		// Check that the expected and actual 'clienthints' arrays have the same keys.
		$this->assertArrayEquals(
			array_keys( $expectedUserSets['clienthints'] ),
			array_keys( $objectUnderTest->userSets['clienthints'] ),
			false,
			false,
			'::preprocessResults did not set the "clienthints" array of the "userSets" property ' .
			'to the expected array.'
		);
		// Check the ClientHintsReferenceIds objects have the same reference IDs for each name.
		foreach ( $objectUnderTest->userSets['clienthints'] as $name => $referenceIds ) {
			$this->assertArrayEquals(
				$expectedUserSets['clienthints'][$name]->getReferenceIds(),
				$referenceIds->getReferenceIds(),
				false,
				true,
				'::preprocessResults did not set the "clienthints" array of the "userSets" property ' .
				'to the expected array.'
			);
		}
	}

	public function testImplementsCheckUsernameResultInterface(): void {
		$pager = $this->createPartialMock( CheckUserGetUsersPager::class, [] );
		$this->assertInstanceOf( CheckUsernameResultInterface::class, $pager );
	}

	public static function providePreprocessResults() {
		$smallestFakeTimestamp = ConvertibleTimestamp::convert(
			TS_MW,
			ConvertibleTimestamp::time() - 1600
		);
		$middleFakeTimestamp = ConvertibleTimestamp::convert(
			TS_MW,
			ConvertibleTimestamp::time() - 400
		);
		$largestFakeTimestamp = ConvertibleTimestamp::now();
		// TODO: Test that the user agents are cut off at 10 + IP/XFF combos are cut off.
		return [
			'No rows in the result' => [
				new FakeResultWrapper( [] ),
				// Expected ClientHintsReferenceIds used for lookup
				new ClientHintsReferenceIds( [] ),
				[
					'first' => [],
					'last' => [],
					'edits' => [],
					'ids' => [],
					'infosets' => [],
					'agentsets' => [],
					'clienthints' => [],
				],
			],
			'One row in the result' => [
				new FakeResultWrapper( [
					[
						'user_text' => 'Test',
						'user' => 1,
						'actor' => 1,
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'xff' => null,
						'agent' => 'Testing user agent',
						'timestamp' => $largestFakeTimestamp,
						'client_hints_reference_id' => 1,
						'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
					],
				] ),
				new ClientHintsReferenceIds( [
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1 ],
				] ),
				[
					'first' => [ 'Test' => $largestFakeTimestamp ],
					'last' => [ 'Test' => $largestFakeTimestamp ],
					'edits' => [ 'Test' => 1 ],
					'ids' => [ 'Test' => 1 ],
					'infosets' => [ 'Test' => [ [ '127.0.0.1', null ] ] ],
					'agentsets' => [ 'Test' => [ 'Testing user agent' ] ],
					'clienthints' => [
						'Test' => new ClientHintsReferenceIds( [
							UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1 ],
						] ),
					],
				],
			],
			'Multiple rows in the result' => [
				new FakeResultWrapper( [
					[
						'user_text' => 'Test',
						'user' => 1,
						'actor' => 1,
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'xff' => '125.6.5.4',
						'agent' => 'Testing user agent',
						'timestamp' => $largestFakeTimestamp,
						'client_hints_reference_id' => 1,
						'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
					],
					[
						'user_text' => 'Testing',
						'user' => 2,
						'actor' => 2,
						'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
						'xff' => null,
						'agent' => 'Testing user agent',
						'timestamp' => $middleFakeTimestamp,
						'client_hints_reference_id' => 123,
						'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
					],
					[
						'user_text' => 'Test',
						'user' => 1,
						'actor' => 1,
						'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
						'xff' => null,
						'agent' => 'Testing user agent1234',
						'timestamp' => $middleFakeTimestamp,
						'client_hints_reference_id' => 2,
						'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT,
					],
					[
						'user_text' => 'Test',
						'user' => 1,
						'actor' => 1,
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'xff' => null,
						'agent' => 'Testing user agent',
						'timestamp' => $smallestFakeTimestamp,
						'client_hints_reference_id' => 456,
						'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
					],
					// A row with the actor ID column as null
					[
						'user_text' => null,
						'user' => null,
						'actor' => null,
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'xff' => null,
						'agent' => 'Testing user agent',
						'timestamp' => $smallestFakeTimestamp,
						'client_hints_reference_id' => 456,
						'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
					],
				] ),
				// Expected ClientHintsReferenceIds used for lookup
				new ClientHintsReferenceIds( [
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1, 123 ],
					UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [ 2 ],
					UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [ 456 ],
				] ),
				[
					'first' => [
						'Test' => $smallestFakeTimestamp,
						'Testing' => $middleFakeTimestamp,
						'127.0.0.1' => $smallestFakeTimestamp,
					],
					'last' => [
						'Test' => $largestFakeTimestamp,
						'Testing' => $middleFakeTimestamp,
						'127.0.0.1' => $smallestFakeTimestamp,
					],
					'edits' => [ 'Test' => 3, 'Testing' => 1, '127.0.0.1' => 1 ],
					'ids' => [ 'Test' => 1, 'Testing' => 2, '127.0.0.1' => 0 ],
					'infosets' => [
						'Test' => [
							[ '127.0.0.1', '125.6.5.4' ],
							[ '127.0.0.2', null ],
							[ '127.0.0.1', null ],
						],
						'Testing' => [ [ '127.0.0.2', null ] ],
						'127.0.0.1' => [ [ '127.0.0.1', null ] ],
					],
					'agentsets' => [
						'Test' => [ 'Testing user agent', 'Testing user agent1234' ],
						'Testing' => [ 'Testing user agent' ],
						'127.0.0.1' => [ 'Testing user agent' ],
					],
					'clienthints' => [
						'Test' => new ClientHintsReferenceIds( [
							UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1 ],
							UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [ 2 ],
							UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [ 456 ],
						] ),
						'Testing' => new ClientHintsReferenceIds( [
							UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 123 ],
						] ),
						'127.0.0.1' => new ClientHintsReferenceIds( [
							UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [ 456 ],
						] ),
					],
				],
			],
		];
	}

	public function testPreprocessResultsBatchLookupsSiCases(): void {
		$fakeTimestamp = ConvertibleTimestamp::now();

		$results = new FakeResultWrapper( [
			[
				'user_text' => 'UserInCase',
				'user' => 1,
				'actor' => 1,
				'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
				'xff' => null,
				'agent' => 'agent',
				'timestamp' => $fakeTimestamp,
				'client_hints_reference_id' => 0,
				'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			],
			[
				'user_text' => 'UserNotInCase',
				'user' => 2,
				'actor' => 2,
				'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
				'xff' => null,
				'agent' => 'agent',
				'timestamp' => $fakeTimestamp,
				'client_hints_reference_id' => 0,
				'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			],
		] );

		$objectUnderTest = $this->createMockPagerForPreprocessResults(
			new UserIdentityValue( 100, 'CheckUserAdmin' ),
			true
		);

		$mockSiCaseLookup = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$mockSiCaseLookup->method( 'areSuggestedInvestigationsEnabled' )->willReturn( true );
		$mockSiCaseLookup->expects( $this->once() )
			->method( 'getUserIdsWithCases' )
			->with( [ 1, 2 ] )
			->willReturn( [ 1 ] );
		$objectUnderTest->siCaseLookupService = $mockSiCaseLookup;

		$objectUnderTest->preprocessResults( $results );

		$this->assertSame( [ 1 ], $objectUnderTest->usersInSiCases );
	}

	public function testPreprocessResultsSkipsSiLookupWithoutPermission(): void {
		$fakeTimestamp = ConvertibleTimestamp::now();

		$results = new FakeResultWrapper( [
			[
				'user_text' => 'SomeUser',
				'user' => 1,
				'actor' => 1,
				'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
				'xff' => null,
				'agent' => 'agent',
				'timestamp' => $fakeTimestamp,
				'client_hints_reference_id' => 0,
				'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			],
		] );

		$objectUnderTest = $this->createMockPagerForPreprocessResults(
			new UserIdentityValue( 100, 'RegularUser' ),
			false
		);

		$mockSiCaseLookup = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$mockSiCaseLookup->method( 'areSuggestedInvestigationsEnabled' )->willReturn( true );
		$mockSiCaseLookup->expects( $this->never() )
			->method( 'getUserIdsWithCases' );
		$objectUnderTest->siCaseLookupService = $mockSiCaseLookup;

		$objectUnderTest->preprocessResults( $results );

		$this->assertSame( [], $objectUnderTest->usersInSiCases );
	}

	/**
	 * Creates a mock CheckUserGetUsersPager with common setup for preprocessResults tests.
	 *
	 * @param UserIdentityValue $mockUser The user to return from ::getUser
	 * @param bool $hasSiRight Whether the authority has the checkuser-suggested-investigations right
	 * @return TestingAccessWrapper Wrapping the mock pager
	 */
	private function createMockPagerForPreprocessResults(
		UserIdentityValue $mockUser,
		bool $hasSiRight
	): TestingAccessWrapper {
		$objectUnderTest = $this->createPartialMock(
			CheckUserGetUsersPager::class,
			[ 'getUser', 'getAuthority', 'getOutput' ]
		);
		$objectUnderTest->method( 'getUser' )->willReturn( $mockUser );

		$mockAuthority = $this->createMock( Authority::class );
		$mockAuthority->method( 'isAllowed' )
			->with( 'checkuser-suggested-investigations' )
			->willReturn( $hasSiRight );
		$objectUnderTest->method( 'getAuthority' )->willReturn( $mockAuthority );

		$mockOutput = $this->createMock( OutputPage::class );
		$objectUnderTest->method( 'getOutput' )->willReturn( $mockOutput );

		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );

		$objectUnderTest->clientHintsLookup = $this->createMock( UserAgentClientHintsLookup::class );

		$linkBatch = $this->createMock( LinkBatch::class );
		$linkBatchFactory = $this->createMock( LinkBatchFactory::class );
		$linkBatchFactory->method( 'newLinkBatch' )->willReturn( $linkBatch );
		$objectUnderTest->linkBatchFactory = $linkBatchFactory;

		return $objectUnderTest;
	}
}
