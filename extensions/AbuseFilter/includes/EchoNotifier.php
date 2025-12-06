<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesRegistry;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseFilter;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Notification\RecipientSet;
use MediaWiki\Title\Title;

/**
 * Helper service for EmergencyWatcher to notify filter maintainers of throttled filters
 * @todo DI not possible due to Echo
 */
class EchoNotifier {
	public const SERVICE_NAME = 'AbuseFilterEchoNotifier';
	public const EVENT_TYPE = 'throttled-filter';

	/** @var FilterLookup */
	private $filterLookup;
	/** @var ConsequencesRegistry */
	private $consequencesRegistry;
	/** @var bool */
	private $isEchoLoaded;

	/**
	 * @param FilterLookup $filterLookup
	 * @param ConsequencesRegistry $consequencesRegistry
	 * @param bool $isEchoLoaded
	 */
	public function __construct(
		FilterLookup $filterLookup,
		ConsequencesRegistry $consequencesRegistry,
		bool $isEchoLoaded
	) {
		$this->filterLookup = $filterLookup;
		$this->consequencesRegistry = $consequencesRegistry;
		$this->isEchoLoaded = $isEchoLoaded;
	}

	private function getTitleForFilter( int $filter ): Title {
		return SpecialAbuseFilter::getTitleForSubpage( (string)$filter );
	}

	private function getFilterObject( int $filter ): ExistingFilter {
		return $this->filterLookup->getFilter( $filter, false );
	}

	/**
	 * @param ExistingFilter $filterObj
	 * @return array
	 */
	private function getDataForEvent( ExistingFilter $filterObj ): array {
		$throttledActionNames = array_intersect(
			$filterObj->getActionsNames(),
			$this->consequencesRegistry->getDangerousActionNames()
		);
		return [
			'type' => self::EVENT_TYPE,
			'title' => $this->getTitleForFilter( $filterObj->getID() ),
			'extra' => [
				'throttled-actions' => $throttledActionNames,
			],
		];
	}

	/**
	 * Send notification about a filter being throttled
	 *
	 * @param int $filter
	 * @return Event|false
	 */
	public function notifyForFilter( int $filter ) {
		if ( $this->isEchoLoaded ) {
			$filterObj = $this->getFilterObject( $filter );
			return Event::create(
				$this->getDataForEvent( $filterObj ),
				new RecipientSet( $filterObj->getUserIdentity() )
			);
		}
		return false;
	}

}
