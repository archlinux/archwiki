<?php

namespace MediaWiki\CheckUser\Jobs;

use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\Job;
use MediaWiki\JobQueue\JobSpecification;

/**
 * Stores Client Hints data for a given reference ID and type.
 *
 * Performed in a job to prevent writes on GET requests.
 */
class StoreClientHintsDataJob extends Job {
	/**
	 * The type of this job, as registered in wgJobTypeConf.
	 */
	public const TYPE = 'checkuserStoreClientHintsDataJob';

	private UserAgentClientHintsManager $userAgentClientHintsManager;

	/** @inheritDoc */
	public function __construct( array $params, UserAgentClientHintsManager $userAgentClientHintsManager ) {
		parent::__construct( self::TYPE, $params );
		$this->userAgentClientHintsManager = $userAgentClientHintsManager;
	}

	/**
	 * Create a new job specification for logging access to temporary account data.
	 *
	 * @param ClientHintsData $clientHintsData The ClientHintsData object containing the Client Hints data for this
	 *     event.
	 * @param int $referenceId The ID of the event which we are storing Client Hints data for in this job
	 * @param string $referenceType The reference type for the ID of the event. A valid value in
	 *    {@link UserAgentClientHintsManager::SUPPORTED_TYPES} (e.g. "privatelog" or "log").
	 * @return IJobSpecification
	 */
	public static function newSpec(
		ClientHintsData $clientHintsData,
		int $referenceId,
		string $referenceType
	): IJobSpecification {
		return new JobSpecification(
			self::TYPE,
			[
				'clientHintsData' => $clientHintsData->jsonSerialize(),
				'referenceId' => $referenceId,
				'referenceType' => $referenceType,
			],
			[],
			null
		);
	}

	/** @return bool */
	public function run(): bool {
		$clientHintsData = ClientHintsData::newFromSerialisedJsonArray( $this->params['clientHintsData'] );
		$this->userAgentClientHintsManager->insertClientHintValues(
			$clientHintsData, $this->params['referenceId'], $this->params['referenceType']
		);

		return true;
	}
}
