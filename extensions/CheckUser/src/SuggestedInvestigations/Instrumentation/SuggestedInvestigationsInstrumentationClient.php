<?php

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Instrumentation;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\EventBus\Serializers\MediaWiki\UserEntitySerializer;
use MediaWiki\Extension\EventLogging\MetricsPlatform\MetricsClientFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Wrapper class for emitting server-side interaction events to the Suggested Investigations
 * Metrics Platform instrument.
 */
class SuggestedInvestigationsInstrumentationClient implements ISuggestedInvestigationsInstrumentationClient {

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly UserFactory $userFactory,
		private readonly UserRegistrationLookup $userRegistrationLookup,
		private readonly UserEditTracker $userEditTracker,
		private readonly UserGroupManager $userGroupManager,
		private readonly CentralIdLookup $centralIdLookup,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly MetricsClientFactory $metricsClientFactory,
	) {
	}

	/**
	 * Emit an interaction event to the Suggested Investigations Metrics Platform instrument.
	 *
	 * Some fields are automatically populated by this method by reading the data from
	 * the replica DBs:
	 * * If 'case_id' is specified, then missing case-specific fields are populated
	 *   in the interaction data
	 * * If 'performer_id' is specified, then missing performer-specific fields are populated
	 *   in the interaction data
	 *
	 * @inheritDoc
	 */
	public function submitInteraction(
		IContextSource $context,
		string $action,
		array $interactionData
	): void {
		$client = $this->metricsClientFactory->newMetricsClient( $context );

		// Fill any missing case-related fields if the 'case_id' field is set.
		if ( array_key_exists( 'case_id', $interactionData ) ) {
			$this->expandInteractionDataWithCaseSpecificFields( $interactionData );
		}

		// Fill the performer fields if the 'performer' field is set as an array with an 'id' key.
		if (
			array_key_exists( 'performer', $interactionData ) &&
			array_key_exists( 'id', $interactionData['performer'] )
		) {
			$this->expandInteractionDataWithPerformerSpecificFields( $interactionData['performer'] );
		}

		$client->submitInteraction(
			'mediawiki.product_metrics.suggested_investigations_interaction.v2',
			'/analytics/mediawiki/suggested_investigations/interaction/1.1.4',
			$action,
			$interactionData
		);
	}

	/** @inheritDoc */
	public function getUserFragmentsArray( array $userIdentities ): array {
		// If EventBus is not installed, then we cannot generate the array,
		// so gracefully return (T412722)
		if ( !$this->extensionRegistry->isLoaded( 'EventBus' ) ) {
			return [];
		}

		$userEntitySerializer = new UserEntitySerializer(
			$this->userFactory,
			$this->userGroupManager,
			$this->centralIdLookup
		);

		return array_map( static function ( $userIdentity ) use ( $userEntitySerializer ) {
			return $userEntitySerializer->toArray( $userIdentity );
		}, $userIdentities );
	}

	/**
	 * Expands the provided interaction data with case specific fields using values from a replica DB.
	 * Assumes that a 'case_id' field exists. This method will not override any already set fields.
	 */
	private function expandInteractionDataWithCaseSpecificFields( array &$interactionData ): void {
		$dbr = $this->dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );
		$caseId = $interactionData['case_id'];

		if (
			!array_key_exists( 'case_url_identifier', $interactionData ) ||
			!array_key_exists( 'case_note', $interactionData )
		) {
			$caseDetails = $dbr->newSelectQueryBuilder()
				->select( [ 'sic_status_reason', 'sic_url_identifier' ] )
				->from( 'cusi_case' )
				->where( [ 'sic_id' => $caseId ] )
				->caller( __METHOD__ )
				->fetchRow();

			if ( $caseDetails !== false ) {
				if ( !array_key_exists( 'case_url_identifier', $interactionData ) ) {
					$interactionData['case_url_identifier'] = (int)$caseDetails->sic_url_identifier;
				}

				if ( !array_key_exists( 'case_note', $interactionData ) ) {
					$interactionData['case_note'] = $caseDetails->sic_status_reason;
				}
			}
		}

		if ( !array_key_exists( 'users_in_case', $interactionData ) ) {
			$userIdsInCase = $dbr->newSelectQueryBuilder()
				->select( 'siu_user_id' )
				->from( 'cusi_user' )
				->where( [ 'siu_sic_id' => $caseId ] )
				->caller( __METHOD__ )
				->fetchFieldValues();

			$interactionData['users_in_case'] = $this->getUserFragmentsArray(
				array_filter( array_map(
					$this->userIdentityLookup->getUserIdentityByUserId( ... ),
					$userIdsInCase
				) )
			);
		}

		if ( !array_key_exists( 'signals_in_case', $interactionData ) ) {
			$interactionData['signals_in_case'] = $dbr->newSelectQueryBuilder()
				->select( 'sis_name' )
				->distinct()
				->from( 'cusi_signal' )
				->where( [ 'sis_sic_id' => $caseId ] )
				->caller( __METHOD__ )
				->fetchFieldValues();
		}
	}

	/**
	 * Expands the provided interaction data with performer specific fields.
	 * This assumes that the 'performer_id' field exists.
	 *
	 * We re-implement some of the logic from EventLogging so that we can conditionally set
	 * the data depending on whether the action is performed by the system or not.
	 */
	private function expandInteractionDataWithPerformerSpecificFields( array &$performerArray ): void {
		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $performerArray['id'] );
		if ( !$userIdentity ) {
			return;
		}

		$performerArray = array_merge(
			[
				'name' => $userIdentity->getName(),
				'groups' => $this->userGroupManager->getUserEffectiveGroups( $userIdentity ),
				'edit_count' => $this->userEditTracker->getUserEditCount( $userIdentity ),
			],
			$performerArray
		);

		if ( !array_key_exists( 'registration_dt', $performerArray ) ) {
			$registrationTimestamp = $this->userRegistrationLookup->getRegistration( $userIdentity );
			if ( $registrationTimestamp ) {
				$performerArray['registration_dt'] = ConvertibleTimestamp::convert(
					TS_ISO_8601,
					$registrationTimestamp
				);
			}
		}
	}
}
