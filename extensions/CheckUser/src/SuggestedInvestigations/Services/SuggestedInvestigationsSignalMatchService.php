<?php

namespace MediaWiki\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsCase;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Config\ServiceOptions;
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

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly HookRunner $hookRunner,
		private readonly SuggestedInvestigationsCaseLookupService $caseLookup,
		private readonly SuggestedInvestigationsCaseManagerService $caseManager,
		private readonly LoggerInterface $logger,
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
	 */
	public function matchSignalsAgainstUser( UserIdentity $userIdentity, string $eventType ): void {
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
			$userIdentity, $eventType, $signalMatchResults
		);

		foreach ( $signalMatchResults as $signalMatchResult ) {
			if ( !$signalMatchResult->isMatch() ) {
				continue;
			}

			if ( $signalMatchResult->valueMatchAllowsMerging() ) {
				$this->processMergeableSignal( $userIdentity, $signalMatchResult );
			} else {
				$this->createNewCase( $userIdentity, $signalMatchResult );
			}
		}
	}

	/**
	 * Checks if there are any existing open SI cases with the same signal.
	 * * If there's at least one invalid case, does nothing.
	 * * If there are only open ones, attaches the user to all of them.
	 * * If there aren't, creates a new SI case for the user and signal.
	 */
	private function processMergeableSignal(
		UserIdentity $user,
		SuggestedInvestigationsSignalMatchResult $signal
	): void {
		$mergeableCases = $this->caseLookup->getCasesForSignal( $signal, [ CaseStatus::Open, CaseStatus::Invalid ] );

		$hasInvalidCase = array_any(
			$mergeableCases,
			static fn ( $case ) => $case->getStatus() === CaseStatus::Invalid
		);

		if ( $hasInvalidCase ) {
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

		if ( count( $mergeableCases ) === 0 ) {
			$this->createNewCase( $user, $signal );
		} else {
			$this->addUserToCases( $user, $mergeableCases );
		}
	}

	/**
	 * Creates a new SI case for the user and signal.
	 */
	private function createNewCase(
		UserIdentity $user,
		SuggestedInvestigationsSignalMatchResult $signal
	): void {
		$signals = [ $signal ];
		$users = [ $user ];
		$this->hookRunner->onCheckUserSuggestedInvestigationsBeforeCaseCreated(
			$signals, $users
		);
		$this->caseManager->createCase( $users, $signals );
	}

	/**
	 * Adds the given user to all the SI cases provided.
	 * @param UserIdentity $user
	 * @param SuggestedInvestigationsCase[] $cases
	 */
	private function addUserToCases( UserIdentity $user, array $cases ): void {
		$users = [ $user ];
		foreach ( $cases as $case ) {
			$this->caseManager->addUsersToCase( $case->getId(), $users );
		}
	}
}
