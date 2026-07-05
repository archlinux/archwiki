<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Jobs;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\Job;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;

/**
 * Matches Suggested Investigations signals against a user.
 *
 * Done in a job to avoid potentially expensive computations on the main request
 */
class SuggestedInvestigationsMatchSignalsAgainstUserJob extends Job {
	/**
	 * The type of this job, as registered in wgJobTypeConf.
	 */
	public const TYPE = 'checkuserSuggestedInvestigationsMatchSignalsAgainstUserJob';

	/** @inheritDoc */
	public function __construct(
		array $params,
		private readonly SuggestedInvestigationsSignalMatchService $suggestedInvestigationsSignalMatchService,
	) {
		parent::__construct( self::TYPE, $params );
	}

	/**
	 * Creates a new job specification for a job which when run calls
	 * {@link SuggestedInvestigationsSignalMatchService::matchSignalsAgainstUser}.
	 * The arguments to this method are passed through as arguments to that method.
	 */
	public static function newSpec(
		UserIdentity $userIdentity,
		string $eventType,
		array $extraData
	): IJobSpecification {
		return new JobSpecification(
			self::TYPE,
			[
				'userIdentityId' => $userIdentity->getId(),
				'userIdentityName' => $userIdentity->getName(),
				'eventType' => $eventType,
				'extraData' => $extraData,
			]
		);
	}

	/** @inheritDoc */
	public function run(): bool {
		$this->suggestedInvestigationsSignalMatchService->matchSignalsAgainstUser(
			new UserIdentityValue( $this->params['userIdentityId'], $this->params['userIdentityName'] ),
			$this->params['eventType'],
			$this->params['extraData']
		);

		return true;
	}
}
