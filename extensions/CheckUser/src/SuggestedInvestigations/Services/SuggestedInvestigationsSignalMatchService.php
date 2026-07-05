<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CheckUser\Hook\HookRunner;
use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForCaseJob;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsCase;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsCaseUser;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;

/**
 * Service that matches signals against users when events occur.
 */
class SuggestedInvestigationsSignalMatchService {

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserSuggestedInvestigationsEnabled',
	];

	public const EVENT_CREATE_ACCOUNT = 'createaccount';
	public const EVENT_AUTOCREATE_ACCOUNT = 'autocreateaccount';
	public const EVENT_SET_EMAIL = 'setemail';
	public const EVENT_CONFIRM_EMAIL = 'confirmemail';
	public const EVENT_SUCCESSFUL_EDIT = 'successfuledit';
	public const EVENT_CHECKUSER_PRIVATE_EVENT = 'checkuser-private-event';

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly HookRunner $hookRunner,
		private readonly SuggestedInvestigationsCaseLookupService $caseLookup,
		private readonly SuggestedInvestigationsCaseManagerService $caseManager,
		private readonly JobQueueGroup $jobQueueGroup,
		private readonly LoggerInterface $logger,
		private readonly SuggestedInvestigationsUserRevisionLookup $userRevisionLookup,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Matches signals against a provided user when a given event occurs.
	 *
	 * This method will create or modify any suggested investigation cases based on the results of matching against
	 * the signals. The caller just needs to call this method to initiate the process.
	 *
	 * NOTE: Private code handles may handle this hook, so updating its signature may break code not visible
	 * in codesearch.
	 *
	 * @since 1.45
	 *
	 * @param UserIdentity $userIdentity
	 * @param string $eventType The type of event that has occurred to trigger signals being matched.
	 *   One of the EVENT_* constants defined in this class, though custom event types may be triggered
	 *   by private code.
	 * @param array $extraData An array of extra data associated with the event that is set based on the
	 *   $eventType. Currently the following values are supported:
	 *   * 'revId' - Set when event type is {@link self::EVENT_SUCCESSFUL_EDIT}. The revision ID of the edit.
	 */
	public function matchSignalsAgainstUser( UserIdentity $userIdentity, string $eventType, array $extraData ): void {
		// Don't attempt to evaluate signals unless the feature is enabled, as we may not have database tables
		// to save suggested investigation cases to.
		if ( !$this->options->get( 'CheckUserSuggestedInvestigationsEnabled' ) ) {
			return;
		}

		if ( !$userIdentity->isRegistered() ) {
			// Make sure we only process registered users
			return;
		}

		$signalMatchResults = [];
		$this->hookRunner->onCheckUserSuggestedInvestigationsSignalMatch(
			$userIdentity,
			$eventType,
			$signalMatchResults,
			$extraData
		);

		foreach ( $signalMatchResults as $signalMatchResult ) {
			/** @var SuggestedInvestigationsSignalMatchResult $signalMatchResult */
			if ( !$signalMatchResult->isMatch() ) {
				continue;
			}

			$caseUserIdentity = new SuggestedInvestigationsCaseUser(
				$userIdentity,
				$signalMatchResult->getUserInfoBitFlags()
			);

			if ( $signalMatchResult->valueMatchAllowsMerging() ) {
				$this->processMergeableSignal( $caseUserIdentity, $signalMatchResult );
			} else {
				$this->createNewCase( $caseUserIdentity, $signalMatchResult );
			}
		}

		$this->bumpCaseTimestampForUserIfFirstEdit( $eventType, $userIdentity, $extraData );
	}

	/**
	 * Checks if there are any existing open SI cases with the same signal.
	 * * If there's at least one invalid case, does nothing.
	 * * If there are only open ones, attaches the user to all of them.
	 * * If there aren't, creates a new SI case for the user and signal.
	 */
	private function processMergeableSignal(
		SuggestedInvestigationsCaseUser $user,
		SuggestedInvestigationsSignalMatchResult $signal
	): void {
		$invalidCasesWithExactMatch = $this->caseLookup->getCasesForSignal( $signal, [ CaseStatus::Invalid ] );
		if ( $invalidCasesWithExactMatch ) {
			// Ignore the signal if there's an invalid case already
			$this->logger->info(
				'Not creating a Suggested Investigations case for signal "{signal}" with value "{value}", because'
					. ' there is already an invalid case for this signal.',
				[
					'signal' => $signal->getName(),
					'value' => $signal->getValue(),
				]
			);
			return;
		}

		$mergeableCases = $this->caseLookup->getMergeableCasesForSignal( $signal );
		if ( count( $mergeableCases ) === 0 ) {
			$this->createNewCase( $user, $signal );
		} else {
			$this->updateCases( $user, $signal, $mergeableCases );
		}
	}

	/**
	 * Creates a new SI case for the user and signal.
	 */
	private function createNewCase(
		SuggestedInvestigationsCaseUser $user,
		SuggestedInvestigationsSignalMatchResult $signal
	): void {
		$signals = [ $signal ];
		$users = [ $user ];
		$this->hookRunner->onCheckUserSuggestedInvestigationsBeforeCaseCreated(
			$signals,
			$users
		);
		$caseId = $this->caseManager->createCase( $users, $signals );
		$this->jobQueueGroup->lazyPush(
			SuggestedInvestigationsAutoCloseForCaseJob::newSpec( $caseId, false )
		);
	}

	/**
	 * Adds the given user and signal to all the SI cases provided.
	 *
	 * @param SuggestedInvestigationsCaseUser $user
	 * @param SuggestedInvestigationsSignalMatchResult $signal
	 * @param SuggestedInvestigationsCase[] $cases
	 */
	private function updateCases(
		SuggestedInvestigationsCaseUser $user,
		SuggestedInvestigationsSignalMatchResult $signal,
		array $cases
	): void {
		foreach ( $cases as $case ) {
			$this->caseManager->updateCase( $case->getId(), [ $user ], [ $signal ] );
		}
	}

	private function bumpCaseTimestampForUserIfFirstEdit(
		string $eventType,
		UserIdentity $userIdentity,
		array $extraData
	): void {
		$revId = $extraData['revId'] ?? null;

		if (
			$eventType !== self::EVENT_SUCCESSFUL_EDIT
			|| $revId === null
			|| !$this->userRevisionLookup->isFirstEditByUser( $userIdentity, $revId )
		) {
			return;
		}

		$openCaseIds = $this->caseLookup->getOpenCaseIdsForUser( $userIdentity->getId() );
		if ( $openCaseIds ) {
			$this->caseManager->updateCasesUpdatedAtTimestamps( $openCaseIds );
		}
	}

}
