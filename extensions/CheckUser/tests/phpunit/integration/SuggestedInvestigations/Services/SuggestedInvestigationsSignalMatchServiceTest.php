<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForCaseJob;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService
 * @group Database
 */
class SuggestedInvestigationsSignalMatchServiceTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	private SuggestedInvestigationsCaseManagerService $caseManager;

	public function setUp(): void {
		parent::setUp();

		$this->enableSuggestedInvestigations();
		$this->caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
	}

	public function testMatchSignalsAgainstUserWhenFeatureDisabled() {
		$this->disableSuggestedInvestigations();

		$this->expectNotToPerformAssertions();
		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsSignalMatch',
			function () {
				$this->fail( 'Did not expect call to CheckUserSuggestedInvestigationsSignalMatch hook' );
			}
		);

		$this->getObjectUnderTest()->matchSignalsAgainstUser(
			$this->createMock( UserIdentity::class ),
			'test-event',
			[]
		);
	}

	/** @dataProvider provideMatchSignalsAgainstUserWhenFeatureEnabled */
	public function testMatchSignalsAgainstUserWhenFeatureEnabled( $mergeable ) {
		// Users with two different groups to get different ids
		$user1 = $this->getTestUser()->getUser();
		$user2 = $this->getTestSysop()->getUser();
		$initialSignal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'test-signal',
			'test-value',
			$mergeable
		);
		$signalThatMatchesViaHook = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			name: 'test-signal',
			value: 'test-value',
			allowsMerging: $mergeable,
			triggerId: 123,
			triggerIdTable: 'revision',
			userInfoBitFlags: 2
		);

		$openCase = $this->caseManager->createCase( [ $user1 ], [ $initialSignal ] );
		$closedCase = $this->caseManager->createCase( [ $user1 ], [ $initialSignal ] );
		$this->caseManager->setCaseStatus( $closedCase, CaseStatus::Resolved );

		/** @var SuggestedInvestigationsCaseLookupService $caseLookup */
		$caseLookup = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseLookup' );

		// Check that only one case is open, as we will assert against this after the object under test is called
		$openCases = $caseLookup->getCasesForSignal( $initialSignal, [ CaseStatus::Open ] );
		$this->assertCount( 1, $openCases );

		$eventType = 'test-event';

		$hookCalled = false;
		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsSignalMatch',
			function (
				UserIdentity $userIdentity,
				string $eventType,
				array &$hookProvidedSignalMatchResults,
				array $extraData
			) use ( &$hookCalled, $signalThatMatchesViaHook ) {
				$this->assertArrayEquals( [ 'extra-data' => 'test' ], $extraData, false, true );
				$hookProvidedSignalMatchResults[] = $signalThatMatchesViaHook;
				$hookCalled = true;
			}
		);

		$this->getObjectUnderTest()->matchSignalsAgainstUser( $user2, $eventType, [ 'extra-data' => 'test' ] );
		$this->assertTrue( $hookCalled );

		$openCases = $caseLookup->getCasesForSignal( $initialSignal, [ CaseStatus::Open ] );
		$this->assertCount( $mergeable ? 1 : 2, $openCases );

		// The user should be added to a single case: either $openCase (if mergeable) or a new case (if not).
		// They should not be added to the closed case.
		$caseUserRows = $this->getDb()->newSelectQueryBuilder()
			->select( [ 'siu_sic_id', 'siu_info' ] )
			->from( 'cusi_user' )
			->where( [ 'siu_user_id' => $user2->getId() ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$this->assertSame( 1, $caseUserRows->numRows() );
		$caseUserRow = $caseUserRows->fetchRow();
		$caseIdSecondUserIsIn = (int)$caseUserRow['siu_sic_id'];

		if ( $mergeable ) {
			$this->assertSame( $openCase, $caseIdSecondUserIsIn );
		} else {
			$this->assertNotSame( $closedCase, $caseIdSecondUserIsIn );
			$this->assertNotSame( $openCase, $caseIdSecondUserIsIn );
		}

		$this->assertSame( 2, (int)$caseUserRow['siu_info'] );

		// Check that the signals are correctly added to the case, using the same logic as above for
		// what case the matched signal should be on
		$newSignalExpectedCaseId = $mergeable ? $openCase : $closedCase + 1;

		$this->newSelectQueryBuilder()
			->select( [ 'sis_trigger_id', 'sis_trigger_type', 'sis_sic_id' ] )
			->from( 'cusi_signal' )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ 0, 0, $openCase ],
				[ 0, 0, $closedCase ],
				[ 123, SuggestedInvestigationsCaseManagerService::TRIGGER_TYPE_REVISION, $newSignalExpectedCaseId ],
			] );
	}

	public static function provideMatchSignalsAgainstUserWhenFeatureEnabled() {
		return [
			'Signal allows merging' => [ 'mergeable' => true ],
			'Signal does not allow merging' => [ 'mergeable' => false ],
		];
	}

	public function testMatchSignalsAgainstUserWithNegativeResult() {
		// Users with two different groups to get different ids
		$user1 = $this->getTestUser()->getUser();
		$user2 = $this->getTestSysop()->getUser();
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'test-signal', 'test-value', true );

		$this->caseManager->createCase( [ $user1 ], [ $signal ] );

		$hookCalled = false;
		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsSignalMatch',
			static function (
				UserIdentity $userIdentity,
				string $eventType,
				array &$hookProvidedSignalMatchResults
			) use ( &$hookCalled ) {
				$negativeResult = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'test-signal' );
				$hookProvidedSignalMatchResults[] = $negativeResult;
				$hookCalled = true;
			}
		);

		$this->getObjectUnderTest()->matchSignalsAgainstUser( $user2, 'test-event', [] );
		$this->assertTrue( $hookCalled );

		// The user should not be added to any case
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cusi_user' )
			->where( [ 'siu_user_id' => $user2->getId() ] )
			->caller( __METHOD__ )
			->assertFieldValue( 0 );
	}

	/** @dataProvider provideMatchSignalsWithBeforeCreateHook */
	public function testMatchSignalsWithBeforeCreateHook( $isUpdating ) {
		// Create scenario: match on $user1 and then add $user2 from hook
		// Update scenario: match on $user1, attach to existing case with $user2 already there

		// Users with two different groups to get different ids
		$user1 = $this->getTestUser()->getUser();
		$user2 = $this->getTestSysop()->getUser();
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'test-signal', 'test-value', true );

		if ( $isUpdating ) {
			$this->caseManager->createCase( [ $user2 ], [ $signal ] );
		}

		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsSignalMatch',
			static function (
				UserIdentity $userIdentity,
				string $eventType,
				array &$hookProvidedSignalMatchResults
			) use ( $signal ) {
				$hookProvidedSignalMatchResults[] = $signal;
			}
		);
		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsBeforeCaseCreated',
			function ( array $signals, array &$users ) use ( $isUpdating, $user2 ) {
				if ( $isUpdating ) {
					// This hook should be called only when creating a new case
					$this->fail( 'Did not expect to call CheckUserSuggestedInvestigationsBeforeCaseCreated hook' );
				} else {
					// Modify the signal and user passed to case creation
					$users[] = $user2;
				}
			}
		);

		$this->getObjectUnderTest()->matchSignalsAgainstUser( $user1, 'test-event', [] );

		// Both users should be attached to the case
		$this->newSelectQueryBuilder()
			->select( 'siu_user_id' )
			->from( 'cusi_user' )
			->where( [ 'siu_user_id' => [ $user1->getId(), $user2->getId() ] ] )
			->caller( __METHOD__ )
			->assertFieldValues( [ strval( $user1->getId() ), strval( $user2->getId() ) ] );
	}

	public static function provideMatchSignalsWithBeforeCreateHook() {
		return [
			'Hook should run on create' => [ 'isUpdating' => false ],
			'Hook should not run on update' => [ 'isUpdating' => true ],
		];
	}

	public function testIgnoreUnregisteredUsers() {
		$service = $this->getObjectUnderTest();
		$user = UserIdentityValue::newAnonymous( 'Anon' );

		$this->expectNotToPerformAssertions();
		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsSignalMatch',
			function () {
				$this->fail( 'Did not expect call to CheckUserSuggestedInvestigationsSignalMatch hook' );
			}
		);

		$service->matchSignalsAgainstUser( $user, 'test-event', [] );
	}

	/** @dataProvider provideIgnoreIfTheresInvalidCase */
	public function testIgnoreIfTheresInvalidCase( bool $mergeable, int $expectedLogCount, int $expectedCaseCount ) {
		// Users with two different groups to get different ids
		$user1 = $this->getTestUser()->getUser();
		$user2 = $this->getTestSysop()->getUser();
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'test-signal',
			'test-value',
			$mergeable
		);

		// Create an invalid case with user1
		$invalidCaseId = $this->caseManager->createCase( [ $user1 ], [ $signal ] );
		$this->caseManager->setCaseStatus( $invalidCaseId, CaseStatus::Invalid );

		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsSignalMatch',
			static function (
				UserIdentity $userIdentity,
				string $eventType,
				array &$hookProvidedSignalMatchResults
			) use ( $signal ) {
				$hookProvidedSignalMatchResults[] = $signal;
			}
		);

		// Ensure that a message is logged only if we skip creating a case
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->exactly( $expectedLogCount ) )
			->method( 'info' )
			->with(
				'Not creating a Suggested Investigations case for signal "{signal}" with value "{value}",'
				 . ' because there is already an invalid case for this signal.',
				[
					'signal' => 'test-signal',
					'value' => 'test-value',
				]
			);
		$this->setService( 'CheckUserLogger', $logger );

		// Trigger the invalid signal again, this time with $user2
		$this->getObjectUnderTest()->matchSignalsAgainstUser( $user2, 'test-event', [] );

		// If the signal is mergeable, there should be only one case, with only one user
		// Otherwise, two cases, each with one user
		/** @var SuggestedInvestigationsCaseLookupService $caseLookup */
		$caseLookup = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseLookup' );
		$cases = $caseLookup->getCasesForSignal( $signal, [ CaseStatus::Invalid, CaseStatus::Open ] );
		$this->assertCount( $expectedCaseCount, $cases );

		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cusi_user' )
			->where( [ 'siu_user_id' => [ $user1->getId(), $user2->getId() ] ] )
			->caller( __METHOD__ )
			// We expect one user per case, so case count == user count
			->assertFieldValue( $expectedCaseCount );
	}

	public static function provideIgnoreIfTheresInvalidCase(): array {
		return [
			'Signal allows merging' => [
				'mergeable' => true,
				'expectedLogCount' => 1,
				'expectedCaseCount' => 1,
			],
			'Signal does not allow merging' => [
				'mergeable' => false,
				'expectedLogCount' => 0,
				'expectedCaseCount' => 2,
			],
		];
	}

	public static function provideTouchCasesCalledOnFirstEdit(): array {
		return [
			'signal matches' => [
				'hookResult' => SuggestedInvestigationsSignalMatchResult::newPositiveResult(
					'test-signal',
					'test-value',
					false
				),
			],
			'no signal match' => [
				'hookResult' => SuggestedInvestigationsSignalMatchResult::newNegativeResult(
					'test-signal'
				),
			],
		];
	}

	/** @dataProvider provideTouchCasesCalledOnFirstEdit */
	public function testTimestampChangedAfterFirstEdit( SuggestedInvestigationsSignalMatchResult $hookResult ): void {
		ConvertibleTimestamp::setFakeTime( '20000000000000' );

		$user = $this->getTestUser()->getUser();

		$initialSignal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'test-signal',
			'test-value',
			false
		);
		$caseId1 = $this->caseManager->createCase( [ $user ], [ $initialSignal ] );
		$caseId2 = $this->caseManager->createCase( [ $user ], [ $initialSignal ] );

		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsSignalMatch',
			static function (
				UserIdentity $userIdentity,
				string $eventType,
				array &$hookProvidedSignalMatchResults
			) use ( $hookResult ) {
				$hookProvidedSignalMatchResults[] = $hookResult;
			}
		);

		ConvertibleTimestamp::setFakeTime( '20211111111111' );

		$status = $this->editPage( 'TestPageFirstEdit', 'content', '', NS_MAIN, $user );
		$revId = $status->getNewRevision()->getId();

		$this->getObjectUnderTest()->matchSignalsAgainstUser(
			$user,
			SuggestedInvestigationsSignalMatchService::EVENT_SUCCESSFUL_EDIT,
			[ 'revId' => $revId ]
		);

		$this->newSelectQueryBuilder()
			->select( [ 'sic_id', 'sic_updated_timestamp' ] )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => [ $caseId1, $caseId2 ] ] )
			->orderBy( 'sic_id', SelectQueryBuilder::SORT_ASC )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ $caseId1, $this->getDb()->timestamp( '20211111111111' ) ],
				[ $caseId2, $this->getDb()->timestamp( '20211111111111' ) ],
			] );
	}

	public static function provideCasesWhenNotToUpdateTheTimestamp(): array {
		return [
			'no revId in extraData' => [
				'revisionCountToCreate' => 0,
				'useRevId' => false,
				'eventType' => SuggestedInvestigationsSignalMatchService::EVENT_SUCCESSFUL_EDIT,
			],
			'2 revisions before revId' => [
				'revisionCountToCreate' => 2,
				'useRevId' => true,
				'eventType' => SuggestedInvestigationsSignalMatchService::EVENT_SUCCESSFUL_EDIT,
			],
			'wrong event type' => [
				'revisionCountToCreate' => 0,
				'useRevId' => false,
				'eventType' => SuggestedInvestigationsSignalMatchService::EVENT_CREATE_ACCOUNT,
			],
		];
	}

	/** @dataProvider provideCasesWhenNotToUpdateTheTimestamp */
	public function testTimestampIsNotChangedEditCountIsNotOne(
		int $revisionCountToCreate,
		bool $useRevId,
		string $eventType
	): void {
		ConvertibleTimestamp::setFakeTime( '20111111111111' );

		$user = $this->getTestUser()->getUser();

		$initialSignal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'test-signal',
			'test-value',
			false
		);
		$caseId = $this->caseManager->createCase( [ $user ], [ $initialSignal ] );

		$revId = null;
		for ( $i = 0; $i < $revisionCountToCreate; $i++ ) {
			$status = $this->editPage( 'TestPage' . $i, 'content ' . $i, '', NS_MAIN, $user );
			$revId = $status->getNewRevision()->getId();
		}

		$extraData = ( $useRevId && $revId !== null ) ? [ 'revId' => $revId ] : [];
		$this->getObjectUnderTest()->matchSignalsAgainstUser( $user, $eventType, $extraData );

		$this->newSelectQueryBuilder()
			->select( 'sic_updated_timestamp' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->assertFieldValue( $this->getDb()->timestamp( '20111111111111' ) );
	}

	public static function provideAutoCloseJobIsQueuedOnlyOnCaseCreation(): array {
		return [
			'new case created (non-mergeable signal)' => [
				'mergeable' => false,
				'preCreateExistingCase' => false,
				'expectedJobCount' => 1,
			],
			'new case created (mergeable signal, no existing case)' => [
				'mergeable' => true,
				'preCreateExistingCase' => false,
				'expectedJobCount' => 1,
			],
			'merged into existing case' => [
				'mergeable' => true,
				'preCreateExistingCase' => true,
				'expectedJobCount' => 0,
			],
		];
	}

	/** @dataProvider provideAutoCloseJobIsQueuedOnlyOnCaseCreation */
	public function testAutoCloseJobIsQueuedOnlyOnCaseCreation(
		bool $mergeable,
		bool $preCreateExistingCase,
		int $expectedJobCount
	): void {
		$user = $this->getTestUser()->getUser();
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'test-signal',
			'test-value',
			$mergeable
		);

		if ( $preCreateExistingCase ) {
			// Pre-create a case so the signal merges into it instead of creating a new one
			$this->caseManager->createCase( [ $user ], [ $signal ] );
		}

		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsSignalMatch',
			static function (
				UserIdentity $userIdentity,
				string $eventType,
				array &$hookProvidedSignalMatchResults
			) use ( $signal ) {
				$hookProvidedSignalMatchResults[] = $signal;
			}
		);

		$this->getObjectUnderTest()->matchSignalsAgainstUser( $user, 'test-event', [] );

		$jobQueue = $this->getServiceContainer()->getJobQueueGroup()
			->get( SuggestedInvestigationsAutoCloseForCaseJob::TYPE );
		$jobs = iterator_to_array( $jobQueue->getAllQueuedJobs() );

		$this->assertCount( $expectedJobCount, $jobs );
		if ( $expectedJobCount > 0 ) {
			$this->assertArrayHasKey( 'caseId', $jobs[0]->getParams() );
			$this->assertArrayNotHasKey( 'jobReleaseTimestamp', $jobs[0]->getParams() );
		}
	}

	private function getObjectUnderTest(): SuggestedInvestigationsSignalMatchService {
		return $this->getServiceContainer()->get( 'SuggestedInvestigationsSignalMatchService' );
	}

}
