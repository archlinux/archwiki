<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Jobs;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\Job;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\User\UserIdentityLookup;
use Psr\Log\LoggerInterface;

/**
 * Resolves a username to a local user identity and enqueues
 * {@see SuggestedInvestigationsAutoCloseForCaseJob} for each of the user's open cases.
 *
 * This is the second level of the two-level dispatch used when a user is globally
 * blocked or locked: {@see SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher} pushes one instance of
 * this job to each remote wiki where the user has an account, and this job then
 * handles the wiki-local auto-close logic without any delay.
 */
class SuggestedInvestigationsAutoCloseForUserJob extends Job {

	public const TYPE = 'checkuserSuggestedInvestigationsAutoCloseForUser';

	public function __construct(
		array $params,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly SuggestedInvestigationsCaseLookupService $caseLookup,
		private readonly JobQueueGroup $jobQueueGroup,
		private readonly LoggerInterface $logger
	) {
		parent::__construct( self::TYPE, $params );
	}

	public static function newSpec( string $username ): IJobSpecification {
		return new JobSpecification( self::TYPE, [ 'username' => $username ] );
	}

	/** @inheritDoc */
	public function run(): bool {
		if ( !$this->caseLookup->areSuggestedInvestigationsEnabled() ) {
			return true;
		}

		$username = $this->params['username'];
		$localUser = $this->userIdentityLookup->getUserIdentityByName( $username );
		if ( $localUser === null || !$localUser->isRegistered() ) {
			$this->logger->info(
				'User {username}: CentralAuth reported local account but no user found. Skipping cross-wiki auto-close',
				[ 'username' => $username ]
			);

			return true;
		}

		$openCaseIds = $this->caseLookup->getOpenCaseIdsForUser( $localUser->getId() );
		foreach ( $openCaseIds as $caseId ) {
			$this->jobQueueGroup->lazyPush(
				SuggestedInvestigationsAutoCloseForCaseJob::newSpec( $caseId, false )
			);
		}

		return true;
	}

}
