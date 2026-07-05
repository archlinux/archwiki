<?php
/**
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
 */

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use InvalidArgumentException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Instrumentation\SuggestedInvestigationsInstrumentationClient;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsCaseUser;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use RuntimeException;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService
 * @group Database
 */
class SuggestedInvestigationsCaseManagerServiceTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	public function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
	}

	public function testCreateCase(): void {
		ConvertibleTimestamp::setFakeTime( '20260101010101' );

		$users = [
			UserIdentityValue::newRegistered( 1, 'Test user 1' ),
			UserIdentityValue::newRegistered( 2, 'Test user 2' ),
		];
		$signals = [
			SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ),
		];

		// Mock SuggestedInvestigationsInstrumentationClient so that we can check the correct event is created
		$client = $this->createMock( SuggestedInvestigationsInstrumentationClient::class );
		$method = __METHOD__;
		$client->expects( $this->once() )
			->method( 'submitInteraction' )
			->willReturnCallback( function ( $context, $action, $interactionData ) use ( $method, $users ) {
				$this->assertSame( RequestContext::getMain(), $context );
				$this->assertSame( 'case_open', $action );

				// We have to compare against the case ID from the database and not what is returned by ::createCase,
				// because ::createCase does not return until after this callback is run
				$caseDetailsFromDatabase = $this->getDb()->newSelectQueryBuilder()
					->select( [ 'sic_id', 'sic_url_identifier' ] )
					->from( 'cusi_case' )
					->caller( $method )
					->fetchRow();
				$this->assertSame(
					[
						'case_id' => (int)$caseDetailsFromDatabase->sic_id,
						'case_url_identifier' => (int)$caseDetailsFromDatabase->sic_url_identifier,
						'signals_in_case' => [ 'Lorem' ],
						'users_in_case' => $users,
						'case_note' => '',
					],
					$interactionData
				);
			} );

		// Mock SuggestedInvestigationsInstrumentationClient::getUserFragmentsArray to just return the array
		// unaltered to make testing easier
		$client->method( 'getUserFragmentsArray' )
			->willReturnArgument( 0 );

		// Mock SuggestedInvestigationsCaseManagerService::generateUrlIdentifier to return a predetermined value
		// so that we can assert against the value of sic_url_identifier in the new row
		$service = $this->getMockBuilder( SuggestedInvestigationsCaseManagerService::class )
			->setConstructorArgs( [
				new ServiceOptions(
					SuggestedInvestigationsCaseManagerService::CONSTRUCTOR_OPTIONS,
					$this->getServiceContainer()->getMainConfig()
				),
				$this->getServiceContainer()->getConnectionProvider(),
				$this->getServiceContainer()->getUserIdentityLookup(),
				$client,
			] )
			->onlyMethods( [ 'generateUrlIdentifier' ] )
			->getMock();
		$service->method( 'generateUrlIdentifier' )
			->willReturn( 123 );

		$caseId = $service->createCase( $users, $signals );

		$caseRows = $this->getDb()->newSelectQueryBuilder()
			->select( [
				'sic_id',
				'sic_url_identifier',
				'sic_created_timestamp',
				'sic_updated_timestamp',
			] )
			->from( 'cusi_case' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$this->assertCount( 1, $caseRows, 'A single new case should be created' );

		$matchingCaseRow = $caseRows->fetchRow();
		$this->assertSame( $caseId, (int)$matchingCaseRow['sic_id'], 'The created case ID should be returned' );
		$this->assertSame(
			123,
			(int)$matchingCaseRow['sic_url_identifier'],
			'The created case should use the mocked random URL identifier'
		);
		$this->assertSame(
			$this->getDb()->timestamp( '20260101010101' ),
			$matchingCaseRow['sic_created_timestamp'],
			'The created case did not have the expected created timestamp'
		);
		$this->assertSame(
			$this->getDb()->timestamp( '20260101010101' ),
			$matchingCaseRow['sic_updated_timestamp'],
			'The created case did not have the expected updated timestamp'
		);

		// Ensure we added users only to the newly created case
		[ $userCountRelevant, $userCountIrrelevant ] = $this->countUsers( $caseId );
		$this->assertSame( 2, $userCountRelevant, 'Two users should be added to the case' );
		$this->assertSame( 0, $userCountIrrelevant, 'No users should be added to any other case' );

		// Ensure we added signals only to the newly created case
		$signalCountRelevant = (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cusi_signal' )
			->where( [ 'sis_sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();
		$signalCountAll = (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cusi_signal' )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertSame( 1, $signalCountRelevant, 'One signal should be added to the case' );
		$this->assertSame( 0, $signalCountAll - $signalCountRelevant, 'No signals should be added to any other case' );

		// Validate the signal does not have an associated trigger ID (because none was set)
		$this->newSelectQueryBuilder()
			->select( [ 'sis_trigger_id', 'sis_trigger_type' ] )
			->from( 'cusi_signal' )
			->caller( __METHOD__ )
			->assertRowValue( [ 0, 0 ] );
	}

	public function testCreateCaseOnUrlIdentifierConflict(): void {
		$users = [
			UserIdentityValue::newRegistered( 1, 'Test user 1' ),
			UserIdentityValue::newRegistered( 2, 'Test user 2' ),
		];
		$signals = [
			SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ),
		];

		// Mock SuggestedInvestigationsCaseManagerService::generateUrlIdentifier to return
		// the same ID in a row, and then choose a new ID.
		// This tests that the creation of a new case validates that a new URL identifier value is not
		// used in any existing cusi_case row.
		$service = $this->getMockBuilder( SuggestedInvestigationsCaseManagerService::class )
			->setConstructorArgs( [
				new ServiceOptions(
					SuggestedInvestigationsCaseManagerService::CONSTRUCTOR_OPTIONS,
					$this->getServiceContainer()->getMainConfig()
				),
				$this->getServiceContainer()->getConnectionProvider(),
				$this->getServiceContainer()->getUserIdentityLookup(),
				$this->createMock( SuggestedInvestigationsInstrumentationClient::class ),
			] )
			->onlyMethods( [ 'generateUrlIdentifier' ] )
			->getMock();
		$service->expects( $this->exactly( 3 ) )
			->method( 'generateUrlIdentifier' )
			->willReturnOnConsecutiveCalls( 123, 123, 1234 );

		$firstCaseId = $service->createCase( $users, $signals );
		$secondCaseId = $service->createCase( $users, $signals );

		$this->newSelectQueryBuilder()
			->select( 'sic_url_identifier' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $firstCaseId ] )
			->caller( __METHOD__ )
			->assertFieldValue( '123' );
		$this->newSelectQueryBuilder()
			->select( 'sic_url_identifier' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $secondCaseId ] )
			->caller( __METHOD__ )
			->assertFieldValue( '1234' );
	}

	public function testCreateCaseWithUserInfoBitFlags(): void {
		$users = [
			new SuggestedInvestigationsCaseUser(
				UserIdentityValue::newRegistered( 1, 'Test user 1' ),
				2
			),
			new SuggestedInvestigationsCaseUser(
				UserIdentityValue::newRegistered( 2, 'Test user 1' ),
				4
			),
		];
		$signals = [
			SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ),
		];

		$service = $this->createService();
		$caseId = $service->createCase( $users, $signals );

		// Check that the user info bit flags are as expected
		$this->newSelectQueryBuilder()
			->select( [ 'siu_user_id', 'siu_info' ] )
			->from( 'cusi_user' )
			->where( [ 'siu_sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ 1, 2 ],
				[ 2, 4 ],
			] );
	}

	/** @dataProvider provideDisallowCreateCase */
	public function testDisallowCreateCase( array $users, array $signals ): void {
		$service = $this->createService();
		$this->expectException( InvalidArgumentException::class );
		$service->createCase( $users, $signals );
	}

	public static function provideDisallowCreateCase(): array {
		return [
			'Disallow no users' => [
				[],
				[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ) ],
			],
			'Disallow no signals' => [
				[ UserIdentityValue::newRegistered( 1, 'Test user 1' ) ],
				[],
			],
			'Disallow multiple signals' => [
				[ UserIdentityValue::newRegistered( 1, 'Test user 1' ) ],
				[
					SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ),
					SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Dolor', 'sit amet', false ),
				],
			],
			'Disallow unknown trigger ID table' => [
				[ UserIdentityValue::newRegistered( 1, 'Test user 1' ) ],
				[
					SuggestedInvestigationsSignalMatchResult::newPositiveResult(
						'Lorem',
						'ipsum',
						false,
						123,
						'unknown'
					),
				],
			],
		];
	}

	public function testUpdateCaseToAddUsers(): void {
		ConvertibleTimestamp::setFakeTime( '20260101010101' );

		// Have to use real users as we fetch $user1 from the real UserIdentityLookup
		$user1 = $this->getTestUser()->getUserIdentity();
		$user2 = $this->getTestSysop()->getUserIdentity();
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );

		$service = $this->createService();
		$caseId = $service->createCase( [ $user1 ], [ $signal ] );

		$this->newSelectQueryBuilder()
			->select( [ 'sic_updated_timestamp', 'sic_created_timestamp' ] )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->assertRowValue( [
				$this->getDb()->timestamp( '20260101010101' ),
				$this->getDb()->timestamp( '20260101010101' ),
			] );

		[ $userCountRelevant, $userCountIrrelevant ] = $this->countUsers( $caseId );
		$this->assertSame( 1, $userCountRelevant, 'There should be an initial user' );
		$this->assertSame( 0, $userCountIrrelevant, 'There should be no other initial user' );

		// The first is already added to this case
		$usersToAdd = [ $user1, $user2 ];

		// Mock SuggestedInvestigationsInstrumentationClient so that we can check the correct event is created
		$client = $this->createMock( SuggestedInvestigationsInstrumentationClient::class );
		$client->expects( $this->exactly( 2 ) )
			->method( 'submitInteraction' )
			->with(
				RequestContext::getMain(),
				'case_updated',
				[
					'case_id' => $caseId,
					'signals_in_case' => [ 'Lorem' ],
					'users_in_case' => $usersToAdd,
				]
			);
		$client->method( 'getUserFragmentsArray' )
			->willReturnArgument( 0 );
		$this->setService( 'CheckUserSuggestedInvestigationsInstrumentationClient', $client );

		ConvertibleTimestamp::setFakeTime( '20260102010101' );

		$service = $this->createService();
		$service->updateCase( $caseId, $usersToAdd, [] );

		[ $userCountRelevant, $userCountIrrelevant ] = $this->countUsers( $caseId );
		$this->assertSame( 2, $userCountRelevant, 'Second user should be added to the case' );
		$this->assertSame( 0, $userCountIrrelevant, 'No user should be added to any other case' );

		// Invoking the method again should not add any more users
		$service->updateCase( $caseId, $usersToAdd, [] );
		[ $userCountRelevant, $userCountIrrelevant ] = $this->countUsers( $caseId );
		$this->assertSame( 2, $userCountRelevant, 'No users should be added to the case again' );
		$this->assertSame( 0, $userCountIrrelevant, 'Again, No users should be added to any other case' );

		// Check that only the updated timestamp has changed in the case
		$this->newSelectQueryBuilder()
			->select( [ 'sic_updated_timestamp', 'sic_created_timestamp' ] )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->assertRowValue( [
				$this->getDb()->timestamp( '20260102010101' ),
				$this->getDb()->timestamp( '20260101010101' ),
			] );
	}

	public function testUpdateCaseToAddUsersAndSignals(): void {
		$user1 = UserIdentityValue::newRegistered( 1, 'Test user 1' );
		$user2 = UserIdentityValue::newRegistered( 2, 'Test user 2' );
		$signal1 = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );
		$signal2 = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'Lorem2',
			'ipsum',
			false,
			123,
			'revision'
		);

		$service = $this->createService();
		$caseId = $service->createCase( [ $user1 ], [ $signal1 ] );

		[ $userCountRelevant, $userCountIrrelevant ] = $this->countUsers( $caseId );
		$this->assertSame( 1, $userCountRelevant, 'There should be an initial user' );
		$this->assertSame( 0, $userCountIrrelevant, 'There should be no other initial user' );

		// Assert that only one signal was added at first
		$this->newSelectQueryBuilder()
			->select( [ 'sis_sic_id', 'sis_name', 'sis_value' ] )
			->from( 'cusi_signal' )
			->caller( __METHOD__ )
			->assertRowValue( [ $caseId, 'Lorem', 'ipsum' ] );

		// The first is already added to this case
		$usersToAdd = [ $user1, $user2 ];

		// Mock SuggestedInvestigationsInstrumentationClient so that we can check the correct event is created
		$client = $this->createMock( SuggestedInvestigationsInstrumentationClient::class );
		$client->expects( $this->once() )
			->method( 'submitInteraction' )
			->with(
				RequestContext::getMain(),
				'case_updated',
				[
					'case_id' => $caseId,
					'signals_in_case' => [ 'Lorem', 'Lorem2' ],
					'users_in_case' => $usersToAdd,
				]
			);
		$client->method( 'getUserFragmentsArray' )
			->willReturnArgument( 0 );
		$this->setService( 'CheckUserSuggestedInvestigationsInstrumentationClient', $client );

		$service = $this->createService();
		$service->updateCase( $caseId, $usersToAdd, [ $signal2 ] );

		[ $userCountRelevant, $userCountIrrelevant ] = $this->countUsers( $caseId );
		$this->assertSame( 2, $userCountRelevant, 'Second user should be added to the case' );
		$this->assertSame( 0, $userCountIrrelevant, 'No user should be added to any other case' );

		// Assert that the second signal was added to the case
		$this->newSelectQueryBuilder()
			->select( [ 'sis_sic_id', 'sis_name', 'sis_value', 'sis_trigger_id', 'sis_trigger_type' ] )
			->from( 'cusi_signal' )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ $caseId, 'Lorem', 'ipsum', 0, 0 ],
				[ $caseId, 'Lorem2', 'ipsum', 123, 1 ],
			] );
	}

	public function testUpdateCaseWithNoSignalsOrUsers(): void {
		$user = UserIdentityValue::newRegistered( 1, 'Test user 1' );
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );

		$service = $this->createService();
		$caseId = $service->createCase( [ $user ], [ $signal ] );

		[ $userCountRelevant, $userCountIrrelevant ] = $this->countUsers( $caseId );
		$this->assertSame( 1, $userCountRelevant, 'There should be an initial user' );
		$this->assertSame( 0, $userCountIrrelevant, 'There should be no other initial user' );

		// Assert that only one signal was added at first
		$this->newSelectQueryBuilder()
			->select( [ 'sis_sic_id', 'sis_name', 'sis_value' ] )
			->from( 'cusi_signal' )
			->caller( __METHOD__ )
			->assertRowValue( [ $caseId, 'Lorem', 'ipsum' ] );

		// Mock SuggestedInvestigationsInstrumentationClient so that we can check event is never created (as no
		// update should be performed)
		$client = $this->createNoOpMock( SuggestedInvestigationsInstrumentationClient::class );
		$this->setService( 'CheckUserSuggestedInvestigationsInstrumentationClient', $client );

		$service = $this->createService();
		$service->updateCase( $caseId, [], [] );

		[ $userCountRelevant, $userCountIrrelevant ] = $this->countUsers( $caseId );
		$this->assertSame( 1, $userCountRelevant, 'There should be only be the initial user' );
		$this->assertSame( 0, $userCountIrrelevant, 'There should be no other users' );

		// Assert that no new signals were added
		$this->newSelectQueryBuilder()
			->select( [ 'sis_sic_id', 'sis_name', 'sis_value' ] )
			->from( 'cusi_signal' )
			->caller( __METHOD__ )
			->assertRowValue( [ $caseId, 'Lorem', 'ipsum' ] );
	}

	public function testUpdateCaseWithUserInfoBitFlagsForExistingUser(): void {
		$user = UserIdentityValue::newRegistered( 1, 'Test user 1' );
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );

		$service = $this->createService();
		$caseId = $service->createCase( [ $user ], [ $signal ] );

		// Assert that only one user with no user info bit flags exists at first
		$this->newSelectQueryBuilder()
			->select( [ 'siu_sic_id', 'siu_user_id', 'siu_info' ] )
			->from( 'cusi_user' )
			->caller( __METHOD__ )
			->assertRowValue( [ $caseId, '1', '0' ] );

		// Update the case, adding a siu_info field value
		$service->updateCase(
			$caseId,
			[ new SuggestedInvestigationsCaseUser( $user, 2 ) ],
			[]
		);

		$this->newSelectQueryBuilder()
			->select( [ 'siu_sic_id', 'siu_user_id', 'siu_info' ] )
			->from( 'cusi_user' )
			->caller( __METHOD__ )
			->assertRowValue( [ $caseId, '1', '2' ] );

		// Update the case again, adding adding more bits to the bit flag which should
		// be inclusive bitwise OR'd to the current value of siu_info
		$service->updateCase(
			$caseId,
			[ new SuggestedInvestigationsCaseUser( $user, 5 ) ],
			[]
		);

		$this->newSelectQueryBuilder()
			->select( [ 'siu_sic_id', 'siu_user_id', 'siu_info' ] )
			->from( 'cusi_user' )
			->caller( __METHOD__ )
			->assertRowValue( [ $caseId, '1', '7' ] );
	}

	public function testUpdateCaseWithUserInfoBitFlagsForNewUser(): void {
		$user = UserIdentityValue::newRegistered( 1, 'Test user 1' );
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );

		$service = $this->createService();
		$caseId = $service->createCase( [ $user ], [ $signal ] );

		// Assert that only one user with no user info bit flags exists at first
		$this->newSelectQueryBuilder()
			->select( [ 'siu_sic_id', 'siu_user_id', 'siu_info' ] )
			->from( 'cusi_user' )
			->caller( __METHOD__ )
			->assertRowValue( [ $caseId, '1', '0' ] );

		// Update the case, adding a new user with a user info bit flag
		$service->updateCase(
			$caseId,
			[ new SuggestedInvestigationsCaseUser(
				UserIdentityValue::newRegistered( 2, 'Test user 1' ),
				2
			) ],
			[]
		);

		$this->newSelectQueryBuilder()
			->select( [ 'siu_sic_id', 'siu_user_id', 'siu_info' ] )
			->from( 'cusi_user' )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ $caseId, '1', '0' ],
				[ $caseId, '2', '2' ],
			] );
	}

	/**
	 * @dataProvider setCaseStatusDataProvider
	 */
	public function testSetCaseStatus(
		CaseStatus $oldStatus,
		CaseStatus $newStatus,
		string $reason,
		bool $shouldCreateInstrumentationEvent,
		string $expectedActionSubtype
	): void {
		// Use a real test user as we fetch them from the real UserIdentityLookup service
		$user1 = $this->getMutableTestUser()->getUserIdentity();
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );

		$service = $this->createService();
		$caseId = $service->createCase( [ $user1 ], [ $signal ] );
		$service->setCaseStatus( $caseId, $oldStatus );

		$performer = $this->getTestUser( [ 'checkuser' ] )->getUser();
		RequestContext::getMain()->setUser( $performer );

		// Mock SuggestedInvestigationsInstrumentationClient so that we can check the correct event is created
		$client = $this->createMock( SuggestedInvestigationsInstrumentationClient::class );
		if ( $shouldCreateInstrumentationEvent ) {
			$client->expects( $this->once() )
				->method( 'submitInteraction' )
				->with(
					RequestContext::getMain(),
					'case_status_change',
					[
						'case_id' => $caseId,
						'case_note' => $reason,
						'action_subtype' => $expectedActionSubtype,
						'performer' => [ 'id' => $performer->getId() ],
					]
				);
		} else {
			$client->expects( $this->never() )
				->method( 'submitInteraction' );
		}
		$this->setService( 'CheckUserSuggestedInvestigationsInstrumentationClient', $client );

		$service = $this->createService();
		$service->setCaseStatus( $caseId, $newStatus, $reason, $performer->getId() );

		// Assert the new state has been persisted to the DB
		$this->assertEquals( $newStatus, $this->getCaseStatus( $caseId ) );

		// Assert the performer user ID has been persisted to the DB
		$this->newSelectQueryBuilder()
			->select( 'sic_status_changed_by' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->assertFieldValue( (string)$performer->getId() );
	}

	public function testSetCaseStatusWithSystemPerformer(): void {
		$user1 = $this->getMutableTestUser()->getUserIdentity();
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );

		$service = $this->createService();
		$caseId = $service->createCase( [ $user1 ], [ $signal ] );

		$service->setCaseStatus( $caseId, CaseStatus::Resolved, 'auto-closed' );

		// Assert that sic_status_changed_by is 0 when performer is system action
		$this->newSelectQueryBuilder()
			->select( 'sic_status_changed_by' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->assertFieldValue( '0' );
	}

	/**
	 * @dataProvider provideSetCaseStatusPerformerUserId
	 */
	public function testSetCaseStatusWritesPerformerUserId(
		int $performerUserId,
		string $expectedPerformerUserId
	): void {
		$user1 = $this->getMutableTestUser()->getUserIdentity();
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );

		$service = $this->createService();
		$caseId = $service->createCase( [ $user1 ], [ $signal ] );

		$service->setCaseStatus( $caseId, CaseStatus::Resolved, 'test reason', $performerUserId );

		$actualPerformerUserId = $this->getDb()->newSelectQueryBuilder()
			->select( 'sic_status_changed_by' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();

		$this->assertSame(
			$expectedPerformerUserId,
			$actualPerformerUserId,
			'sic_status_changed_by should match expected value'
		);
	}

	public static function provideSetCaseStatusPerformerUserId(): array {
		return [
			'Writes performer user ID' => [
				'performerUserId' => 42,
				'expectedPerformerUserId' => '42',
			],
			'Zero performer user ID writes 0' => [
				'performerUserId' => 0,
				'expectedPerformerUserId' => '0',
			],
		];
	}

	public static function setCaseStatusDataProvider(): array {
		return [
			'From Resolved to Open' => [
				'oldStatus' => CaseStatus::Resolved,
				'newStatus' => CaseStatus::Open,
				'reason' => '  ',
				'shouldCreateInstrumentationEvent' => true,
				'expectedActionSubtype' => 'open',
			],
			'From Open to Resolved' => [
				'oldStatus' => CaseStatus::Open,
				'newStatus' => CaseStatus::Resolved,
				'reason' => 'case closed',
				'shouldCreateInstrumentationEvent' => true,
				'expectedActionSubtype' => 'resolved',
			],
			'From Open to Invalid' => [
				'oldStatus' => CaseStatus::Open,
				'newStatus' => CaseStatus::Invalid,
				'reason' => '',
				'shouldCreateInstrumentationEvent' => true,
				'expectedActionSubtype' => 'invalid',
			],
			'From Resolved to Resolved' => [
				'oldStatus' => CaseStatus::Resolved,
				'newStatus' => CaseStatus::Resolved,
				'reason' => 'case closed',
				'shouldCreateInstrumentationEvent' => false,
				'expectedActionSubtype' => '',
			],
		];
	}

	public function testTouchCasesMultipleCases(): void {
		$service = $this->createService();
		$user = UserIdentityValue::newRegistered( 1, 'Test user 1' );
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'test-signal', 'test-value', false );

		ConvertibleTimestamp::setFakeTime( '20211111111111' );
		$caseId1 = $service->createCase( [ $user ], [ $signal ] );
		$caseId2 = $service->createCase( [ $user ], [ $signal ] );
		$caseId3 = $service->createCase( [ $user ], [ $signal ] );

		ConvertibleTimestamp::setFakeTime( '20222222222222' );
		$service->updateCasesUpdatedAtTimestamps( [ $caseId1, $caseId2 ] );

		$this->newSelectQueryBuilder()
			->select( [ 'sic_id', 'sic_updated_timestamp' ] )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => [ $caseId1, $caseId2, $caseId3 ] ] )
			->orderBy( 'sic_id', SelectQueryBuilder::SORT_ASC )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ $caseId1, $this->getDb()->timestamp( '20222222222222' ) ],
				[ $caseId2, $this->getDb()->timestamp( '20222222222222' ) ],
				[ $caseId3, $this->getDb()->timestamp( '20211111111111' ) ],
			] );
	}

	public function testUpdateCasesUpdatedAtTimestampsThrowsOnNonExistentCaseId(): void {
		$service = $this->createService();
		$user = UserIdentityValue::newRegistered( 1, 'Test user 1' );
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'test-signal', 'test-value', false );

		$caseId = $service->createCase( [ $user ], [ $signal ] );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( '99999' );
		$service->updateCasesUpdatedAtTimestamps( [ $caseId, 99999 ] );
	}

	private function countUsers( int $caseId ): array {
		$userCountRelevant = (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cusi_user' )
			->where( [ 'siu_sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();
		$userCountAll = (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cusi_user' )
			->caller( __METHOD__ )
			->fetchField();

		return [ $userCountRelevant, $userCountAll - $userCountRelevant ];
	}

	public function getCaseStatus( int $caseId ): CaseStatus {
		$rawStatus = $this->getDb()->newSelectQueryBuilder()
			->select( 'sic_status' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();

		return CaseStatus::from( $rawStatus );
	}

	private function createService(): SuggestedInvestigationsCaseManagerService {
		return $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );
	}
}
