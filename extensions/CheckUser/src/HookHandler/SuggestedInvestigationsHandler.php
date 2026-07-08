<?php

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsMatchSignalsAgainstUserJob;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\Hook\UserSetEmailAuthenticationTimestampHook;
use MediaWiki\User\Hook\UserSetEmailHook;
use MediaWiki\User\UserIdentity;

/**
 * Listens for events that trigger suggested investigation signals to be matched against a user.
 */
class SuggestedInvestigationsHandler implements
	LocalUserCreatedHook,
	PageSaveCompleteHook,
	UserSetEmailHook,
	UserSetEmailAuthenticationTimestampHook
{

	public function __construct(
		private readonly JobQueueGroup $jobQueueGroup,
	) {
	}

	/** @inheritDoc */
	public function onLocalUserCreated( $user, $autocreated ): void {
		$this->matchSignalsAgainstUserInJob(
			$user,
			$autocreated ?
				SuggestedInvestigationsSignalMatchService::EVENT_AUTOCREATE_ACCOUNT :
				SuggestedInvestigationsSignalMatchService::EVENT_CREATE_ACCOUNT
		);
	}

	/** @inheritDoc */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		// Don't process null edits, as the revision ID will not be performed by the user
		// who just attempted to edit
		if ( $editResult->isNullEdit() ) {
			return;
		}

		$this->matchSignalsAgainstUserInJob(
			$user,
			SuggestedInvestigationsSignalMatchService::EVENT_SUCCESSFUL_EDIT,
			[ 'revId' => $revisionRecord->getId() ]
		);
	}

	/** @inheritDoc */
	public function onUserSetEmail( $user, &$email ): void {
		$this->matchSignalsAgainstUserInJob(
			$user,
			SuggestedInvestigationsSignalMatchService::EVENT_SET_EMAIL
		);
	}

	/** @inheritDoc */
	public function onUserSetEmailAuthenticationTimestamp( $user, &$timestamp ): void {
		$this->matchSignalsAgainstUserInJob(
			$user,
			SuggestedInvestigationsSignalMatchService::EVENT_CONFIRM_EMAIL
		);
	}

	/**
	 * Matches signals against the provided event in a job
	 *
	 * @param UserIdentity $userIdentity
	 * @param string $eventType One of the `SuggestedInvestigationsSignalMatchService::EVENT_*` constants
	 * @param array $extraData
	 */
	private function matchSignalsAgainstUserInJob(
		UserIdentity $userIdentity,
		string $eventType,
		array $extraData = []
	): void {
		$this->jobQueueGroup->lazyPush(
			SuggestedInvestigationsMatchSignalsAgainstUserJob::newSpec( $userIdentity, $eventType, $extraData )
		);
	}
}
