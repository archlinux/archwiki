<?php

namespace MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService
 * @group Database
 */
class SuggestedInvestigationsSignalMatchServiceTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	public function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
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
			$this->createMock( UserIdentity::class ), 'test-event'
		);
	}

	/** @dataProvider provideMatchSignalsAgainstUserWhenFeatureEnabled */
	public function testMatchSignalsAgainstUserWhenFeatureEnabled( $mergeable ) {
		// Users with two different groups to get different ids
		$user1 = $this->getTestUser()->getUser();
		$user2 = $this->getTestSysop()->getUser();
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'test-signal', 'test-value', $mergeable );

		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
		$openCase = $caseManager->createCase( [ $user1 ], [ $signal ] );
		$closedCase = $caseManager->createCase( [ $user1 ], [ $signal ] );
		$caseManager->setCaseStatus( $closedCase, CaseStatus::Resolved );

		$eventType = 'test-event';

		$hookCalled = false;
		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsSignalMatch',
			static function (
				UserIdentity $userIdentity, string $eventType, array &$hookProvidedSignalMatchResults
			) use ( &$hookCalled, $signal ) {
				$hookProvidedSignalMatchResults[] = $signal;
				$hookCalled = true;
			}
		);

		$this->getObjectUnderTest()->matchSignalsAgainstUser( $user2, $eventType );
		$this->assertTrue( $hookCalled );

		/** @var SuggestedInvestigationsCaseLookupService $caseLookup */
		$caseLookup = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseLookup' );

		$openCases = $caseLookup->getCasesForSignal( $signal, [ CaseStatus::Open ] );
		$this->assertCount( $mergeable ? 1 : 2, $openCases );

		// The user should be added to a single case: either $openCase (if mergeable) or a new case (if not).
		// They should not be added to the closed case.
		$caseIds = $this->getDb()->newSelectQueryBuilder()
			->select( 'siu_sic_id' )
			->from( 'cusi_user' )
			->where( [ 'siu_user_id' => $user2->getId() ] )
			->caller( __METHOD__ )
			->fetchFieldValues();
		$this->assertCount( 1, $caseIds );

		if ( $mergeable ) {
			$this->assertSame( $openCase, (int)$caseIds[0] );
		} else {
			$this->assertNotContains( $closedCase, $caseIds );
			$this->assertNotContains( $openCase, $caseIds );
		}
	}

	public function provideMatchSignalsAgainstUserWhenFeatureEnabled() {
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

		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
		$caseManager->createCase( [ $user1 ], [ $signal ] );

		$hookCalled = false;
		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsSignalMatch',
			static function (
				UserIdentity $userIdentity, string $eventType, array &$hookProvidedSignalMatchResults
			) use ( &$hookCalled ) {
				$negativeResult = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'test-signal' );
				$hookProvidedSignalMatchResults[] = $negativeResult;
				$hookCalled = true;
			}
		);

		$this->getObjectUnderTest()->matchSignalsAgainstUser( $user2, 'test-event' );
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
			/** @var SuggestedInvestigationsCaseManagerService $caseManager */
			$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
			$caseManager->createCase( [ $user2 ], [ $signal ] );
		}

		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsSignalMatch',
			static function (
				UserIdentity $userIdentity, string $eventType, array &$hookProvidedSignalMatchResults
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

		$this->getObjectUnderTest()->matchSignalsAgainstUser( $user1, 'test-event' );

		// Both users should be attached to the case
		$this->newSelectQueryBuilder()
			->select( 'siu_user_id' )
			->from( 'cusi_user' )
			->where( [ 'siu_user_id' => [ $user1->getId(), $user2->getId() ] ] )
			->caller( __METHOD__ )
			->assertFieldValues( [ strval( $user1->getId() ), strval( $user2->getId() ) ] );
	}

	public function provideMatchSignalsWithBeforeCreateHook() {
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

		$service->matchSignalsAgainstUser( $user, 'test-event' );
	}

	/** @dataProvider provideIgnoreIfTheresInvalidCase */
	public function testIgnoreIfTheresInvalidCase( bool $mergeable, int $expectedLogCount, int $expectedCaseCount ) {
		// Users with two different groups to get different ids
		$user1 = $this->getTestUser()->getUser();
		$user2 = $this->getTestSysop()->getUser();
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'test-signal', 'test-value', $mergeable );

		// Create an invalid case with user1
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
		$invalidCaseId = $caseManager->createCase( [ $user1 ], [ $signal ] );
		$caseManager->setCaseStatus( $invalidCaseId, CaseStatus::Invalid );

		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsSignalMatch',
			static function (
				UserIdentity $userIdentity, string $eventType, array &$hookProvidedSignalMatchResults
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
		$this->setLogger( 'CheckUser', $logger );

		// Trigger the invalid signal again, this time with $user2
		$this->getObjectUnderTest()->matchSignalsAgainstUser( $user2, 'test-event' );

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

	public function provideIgnoreIfTheresInvalidCase(): array {
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

	private function getObjectUnderTest(): SuggestedInvestigationsSignalMatchService {
		return $this->getServiceContainer()->get( 'SuggestedInvestigationsSignalMatchService' );
	}
}
