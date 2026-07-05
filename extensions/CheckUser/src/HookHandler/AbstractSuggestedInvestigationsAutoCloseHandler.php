<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForCaseJob;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\JobQueue\JobQueueGroup;
use Psr\Log\LoggerInterface;

/**
 * Base class for handlers of hooks which are fired when users are blocked or locked.
 */
abstract class AbstractSuggestedInvestigationsAutoCloseHandler {

	public function __construct(
		protected readonly SuggestedInvestigationsCaseLookupService $caseLookupService,
		protected readonly JobQueueGroup $jobQueueGroup,
		protected readonly LoggerInterface $logger,
	) {
	}

	protected function enqueueAutoCloseJobsForUser( int $localUserId ): void {
		$openCaseIds = $this->caseLookupService->getOpenCaseIdsForUser( $localUserId );

		foreach ( $openCaseIds as $caseId ) {
			$job = SuggestedInvestigationsAutoCloseForCaseJob::newSpec( $caseId, $this->isDelayedJobsEnabled() );
			$this->jobQueueGroup->lazyPush( $job );
		}
	}

	private function isDelayedJobsEnabled(): bool {
		if ( $this->jobQueueGroup->get( SuggestedInvestigationsAutoCloseForCaseJob::TYPE )->delayedJobsEnabled() ) {
			return true;
		}

		$this->logger->warning( SuggestedInvestigationsAutoCloseForCaseJob::class . ' delayed jobs are not supported' );

		return false;
	}

}
