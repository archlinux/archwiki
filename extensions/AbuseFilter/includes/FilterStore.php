<?php

namespace MediaWiki\Extension\AbuseFilter;

use ManualLogEntry;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagsManager;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesRegistry;
use MediaWiki\Extension\AbuseFilter\Filter\Filter;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseFilter;
use Status;
use stdClass;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @internal
 */
class FilterStore {
	public const SERVICE_NAME = 'AbuseFilterFilterStore';

	/** @var ConsequencesRegistry */
	private $consequencesRegistry;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var FilterProfiler */
	private $filterProfiler;

	/** @var FilterLookup */
	private $filterLookup;

	/** @var ChangeTagsManager */
	private $tagsManager;

	/** @var FilterValidator */
	private $filterValidator;

	/** @var FilterCompare */
	private $filterCompare;

	/** @var EmergencyCache */
	private $emergencyCache;

	/**
	 * @param ConsequencesRegistry $consequencesRegistry
	 * @param ILoadBalancer $loadBalancer
	 * @param FilterProfiler $filterProfiler
	 * @param FilterLookup $filterLookup
	 * @param ChangeTagsManager $tagsManager
	 * @param FilterValidator $filterValidator
	 * @param FilterCompare $filterCompare
	 * @param EmergencyCache $emergencyCache
	 */
	public function __construct(
		ConsequencesRegistry $consequencesRegistry,
		ILoadBalancer $loadBalancer,
		FilterProfiler $filterProfiler,
		FilterLookup $filterLookup,
		ChangeTagsManager $tagsManager,
		FilterValidator $filterValidator,
		FilterCompare $filterCompare,
		EmergencyCache $emergencyCache
	) {
		$this->consequencesRegistry = $consequencesRegistry;
		$this->loadBalancer = $loadBalancer;
		$this->filterProfiler = $filterProfiler;
		$this->filterLookup = $filterLookup;
		$this->tagsManager = $tagsManager;
		$this->filterValidator = $filterValidator;
		$this->filterCompare = $filterCompare;
		$this->emergencyCache = $emergencyCache;
	}

	/**
	 * Checks whether user input for the filter editing form is valid and if so saves the filter.
	 * Returns a Status object which can be:
	 *  - Good with [ new_filter_id, history_id ] as value if the filter was successfully saved
	 *  - Good with value = false if everything went fine but the filter is unchanged
	 *  - OK with errors if a validation error occurred
	 *  - Fatal in case of a permission-related error
	 *
	 * @param User $user
	 * @param int|null $filter
	 * @param Filter $newFilter
	 * @param Filter $originalFilter
	 * @return Status
	 */
	public function saveFilter(
		User $user,
		?int $filter,
		Filter $newFilter,
		Filter $originalFilter
	): Status {
		$validationStatus = $this->filterValidator->checkAll( $newFilter, $originalFilter, $user );
		if ( !$validationStatus->isGood() ) {
			return $validationStatus;
		}

		// Check for non-changes
		$differences = $this->filterCompare->compareVersions( $newFilter, $originalFilter );
		if ( !count( $differences ) ) {
			return Status::newGood( false );
		}

		// Everything went fine, so let's save the filter
		$wasGlobal = $originalFilter->isGlobal();
		list( $newID, $historyID ) = $this->doSaveFilter( $user, $newFilter, $differences, $filter, $wasGlobal );
		return Status::newGood( [ $newID, $historyID ] );
	}

	/**
	 * Saves new filter's info to DB
	 *
	 * @param User $user
	 * @param Filter $newFilter
	 * @param array $differences
	 * @param int|null $filter
	 * @param bool $wasGlobal
	 * @return int[] first element is new ID, second is history ID
	 */
	private function doSaveFilter(
		User $user,
		Filter $newFilter,
		array $differences,
		?int $filter,
		bool $wasGlobal
	): array {
		$dbw = $this->loadBalancer->getConnectionRef( DB_PRIMARY );
		$newRow = get_object_vars( $this->filterToDatabaseRow( $newFilter ) );

		// Set last modifier.
		$newRow['af_timestamp'] = $dbw->timestamp();
		$newRow['af_user'] = $user->getId();
		$newRow['af_user_text'] = $user->getName();

		$isNew = $filter === null;
		$newID = $filter;

		// Preserve the old throttled status (if any) only if disabling the filter.
		// TODO: It might make more sense to check what was actually changed
		$newRow['af_throttled'] = ( $newRow['af_throttled'] ?? false ) && !$newRow['af_enabled'];
		// This is null when creating a new filter, but the DB field is NOT NULL
		$newRow['af_hit_count'] = $newRow['af_hit_count'] ?? 0;
		$newRow['af_id'] = $newID;

		$dbw->startAtomic( __METHOD__ );
		$dbw->replace( 'abuse_filter', 'af_id', $newRow, __METHOD__ );

		if ( $isNew ) {
			$newID = $dbw->insertId();
		}
		'@phan-var int $newID';

		$actions = $newFilter->getActions();
		$actionsRows = [];
		foreach ( $this->consequencesRegistry->getAllEnabledActionNames() as $action ) {
			// Check if it's set
			$enabled = isset( $actions[$action] );

			if ( $enabled ) {
				$parameters = $actions[$action];
				if ( $action === 'throttle' && $parameters[0] === null ) {
					// FIXME: Do we really need to keep the filter ID inside throttle parameters?
					// We'd save space, keep things simpler and avoid this hack. Note: if removing
					// it, a maintenance script will be necessary to clean up the table.
					$parameters[0] = $newID;
				}

				$thisRow = [
					'afa_filter' => $newID,
					'afa_consequence' => $action,
					'afa_parameters' => implode( "\n", $parameters )
				];
				$actionsRows[] = $thisRow;
			}
		}

		// Create a history row
		$afhRow = [];

		foreach ( AbuseFilter::HISTORY_MAPPINGS as $afCol => $afhCol ) {
			$afhRow[$afhCol] = $newRow[$afCol];
		}

		$afhRow['afh_actions'] = serialize( $actions );

		$afhRow['afh_changed_fields'] = implode( ',', $differences );

		$flags = [];
		if ( $newRow['af_hidden'] ) {
			$flags[] = 'hidden';
		}
		if ( $newRow['af_enabled'] ) {
			$flags[] = 'enabled';
		}
		if ( $newRow['af_deleted'] ) {
			$flags[] = 'deleted';
		}
		if ( $newRow['af_global'] ) {
			$flags[] = 'global';
		}

		$afhRow['afh_flags'] = implode( ',', $flags );

		$afhRow['afh_filter'] = $newID;

		// Do the update
		$dbw->insert( 'abuse_filter_history', $afhRow, __METHOD__ );
		$historyID = $dbw->insertId();
		if ( !$isNew ) {
			$dbw->delete(
				'abuse_filter_action',
				[ 'afa_filter' => $filter ],
				__METHOD__
			);
		}
		$dbw->insert( 'abuse_filter_action', $actionsRows, __METHOD__ );

		$dbw->endAtomic( __METHOD__ );

		// Invalidate cache if this was a global rule
		if ( $wasGlobal || $newRow['af_global'] ) {
			$this->filterLookup->purgeGroupWANCache( $newRow['af_group'] );
		}

		// Logging
		$subtype = $isNew ? 'create' : 'modify';
		$logEntry = new ManualLogEntry( 'abusefilter', $subtype );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( SpecialAbuseFilter::getTitleForSubpage( (string)$newID ) );
		$logEntry->setParameters( [
			'historyId' => $historyID,
			'newId' => $newID
		] );
		$logid = $logEntry->insert( $dbw );
		$logEntry->publish( $logid );

		// Purge the tag list cache so the fetchAllTags hook applies tag changes
		if ( isset( $actions['tag'] ) ) {
			$this->tagsManager->purgeTagCache();
		}

		$this->filterProfiler->resetFilterProfile( $newID );
		if ( $newRow['af_enabled'] ) {
			$this->emergencyCache->setNewForFilter( $newID, $newRow['af_group'] );
		}
		return [ $newID, $historyID ];
	}

	/**
	 * @todo Perhaps add validation to ensure no null values remained.
	 * @param Filter $filter
	 * @return stdClass
	 */
	private function filterToDatabaseRow( Filter $filter ): stdClass {
		// T67807: integer 1's & 0's might be better understood than booleans
		return (object)[
			'af_id' => $filter->getID(),
			'af_pattern' => $filter->getRules(),
			'af_public_comments' => $filter->getName(),
			'af_comments' => $filter->getComments(),
			'af_group' => $filter->getGroup(),
			'af_actions' => implode( ',', $filter->getActionsNames() ),
			'af_enabled' => (int)$filter->isEnabled(),
			'af_deleted' => (int)$filter->isDeleted(),
			'af_hidden' => (int)$filter->isHidden(),
			'af_global' => (int)$filter->isGlobal(),
			'af_user' => $filter->getUserID(),
			'af_user_text' => $filter->getUserName(),
			'af_timestamp' => $filter->getTimestamp(),
			'af_hit_count' => $filter->getHitCount(),
			'af_throttled' => (int)$filter->isThrottled(),
		];
	}
}
