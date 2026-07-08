<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Jobs;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\CompositeIndefiniteBlockChecker;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\Job;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Language\MessageLocalizer;
use Psr\Log\LoggerInterface;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class SuggestedInvestigationsAutoCloseForCaseJob extends Job {

	public const TYPE = 'checkuserSuggestedInvestigationsAutoCloseForCase';

	private const DELAY_SECONDS = 3600;

	public function __construct(
		array $params,
		private readonly SuggestedInvestigationsCaseManagerService $caseManager,
		private readonly SuggestedInvestigationsCaseLookupService $caseLookup,
		private readonly CompositeIndefiniteBlockChecker $blockChecker,
		private readonly LoggerInterface $logger,
		private readonly MessageLocalizer $messageLocalizer,
	) {
		parent::__construct( self::TYPE, $params );
	}

	/**
	 * @param array $params ['caseId', 'jobReleaseTimeStamp'] int The ID of the case to potentially auto-close
	 * @param SuggestedInvestigationsCaseManagerService $caseManager
	 * @param SuggestedInvestigationsCaseLookupService $caseLookup
	 * @param CompositeIndefiniteBlockChecker $blockChecker
	 * @param LoggerInterface $logger
	 * @return self
	 */
	public static function newFromGlobalState(
		array $params,
		SuggestedInvestigationsCaseManagerService $caseManager,
		SuggestedInvestigationsCaseLookupService $caseLookup,
		CompositeIndefiniteBlockChecker $blockChecker,
		LoggerInterface $logger,
	): self {
		return new self(
			$params,
			$caseManager,
			$caseLookup,
			$blockChecker,
			$logger,
			RequestContext::getMain()
		);
	}

	/**
	 * @param int $caseId
	 * @param bool $delayedJobsEnabled Whether to delay the job
	 */
	public static function newSpec( int $caseId, bool $delayedJobsEnabled ): IJobSpecification {
		$params = [ 'caseId' => $caseId ];
		if ( $delayedJobsEnabled ) {
			$params['jobReleaseTimestamp'] = ConvertibleTimestamp::time() + self::DELAY_SECONDS;
		}
		return new JobSpecification( self::TYPE, $params );
	}

	/** @inheritDoc */
	public function run(): bool {
		$caseId = (int)$this->params['caseId'];
		if ( $this->caseLookup->getCaseStatus( $caseId ) !== CaseStatus::Open ) {
			return true;
		}

		$localUserIds = $this->caseLookup->getUserIdsInCase( $caseId );
		if ( $localUserIds === [] ) {
			return true;
		}

		$unblockedUserIds = $this->blockChecker->getUserIdsNotIndefinitelyBlocked( $localUserIds );
		if ( $unblockedUserIds !== [] ) {
			$this->logger->info(
				'Users {userIds} are not indefinitely blocked, skipping auto-close for case {caseId}',
				[ 'userIds' => implode( ', ', $unblockedUserIds ), 'caseId' => $caseId ]
			);

			return true;
		}

		$reason = $this->messageLocalizer
			->msg( 'checkuser-suggestedinvestigations-autoclose-reason' )
			->text();

		$this->caseManager->setCaseStatus( $caseId, CaseStatus::Resolved, $reason, 0 );
		$this->logger->info(
			'Auto resolved case {caseId} as all associated users are indefinitely blocked',
			[ 'caseId' => $caseId ]
		);

		return true;
	}
}
