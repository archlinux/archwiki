<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences;

use MediaWiki\Block\BlockUser;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Consequence;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\ConsequencesDisablerConsequence;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\HookAborterConsequence;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\GlobalNameUtils;
use MediaWiki\Extension\AbuseFilter\Variables\UnsetVariableException;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use Psr\Log\LoggerInterface;
use Status;
use Title;
use User;

class ConsequencesExecutor {
	public const CONSTRUCTOR_OPTIONS = [
		'AbuseFilterLocallyDisabledGlobalActions',
		'AbuseFilterBlockDuration',
		'AbuseFilterAnonBlockDuration',
		'AbuseFilterBlockAutopromoteDuration',
	];

	/** @var ConsequencesLookup */
	private $consLookup;
	/** @var ConsequencesFactory */
	private $consFactory;
	/** @var ConsequencesRegistry */
	private $consRegistry;
	/** @var FilterLookup */
	private $filterLookup;
	/** @var LoggerInterface */
	private $logger;
	/** @var ServiceOptions */
	private $options;
	/** @var User */
	private $user;
	/** @var Title */
	private $title;
	/** @var VariableHolder */
	private $vars;

	/**
	 * @param ConsequencesLookup $consLookup
	 * @param ConsequencesFactory $consFactory
	 * @param ConsequencesRegistry $consRegistry
	 * @param FilterLookup $filterLookup
	 * @param LoggerInterface $logger
	 * @param ServiceOptions $options
	 * @param User $user
	 * @param Title $title
	 * @param VariableHolder $vars
	 */
	public function __construct(
		ConsequencesLookup $consLookup,
		ConsequencesFactory $consFactory,
		ConsequencesRegistry $consRegistry,
		FilterLookup $filterLookup,
		LoggerInterface $logger,
		ServiceOptions $options,
		User $user,
		Title $title,
		VariableHolder $vars
	) {
		$this->consLookup = $consLookup;
		$this->consFactory = $consFactory;
		$this->consRegistry = $consRegistry;
		$this->filterLookup = $filterLookup;
		$this->logger = $logger;
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->user = $user;
		$this->title = $title;
		$this->vars = $vars;
	}

	/**
	 * Executes a set of actions.
	 *
	 * @param string[] $filters
	 * @return Status returns the operation's status. $status->isOK() will return true if
	 *         there were no actions taken, false otherwise. $status->getValue() will return
	 *         an array listing the actions taken. $status->getErrors() etc. will provide
	 *         the errors and warnings to be shown to the user to explain the actions.
	 */
	public function executeFilterActions( array $filters ): Status {
		$actionsByFilter = $this->consLookup->getConsequencesForFilters( $filters );
		$consequences = $this->replaceArraysWithConsequences( $actionsByFilter );
		$actionsToTake = $this->getFilteredConsequences( $consequences );
		$actionsTaken = array_fill_keys( $filters, [] );

		$messages = [];
		foreach ( $actionsToTake as $filter => $actions ) {
			foreach ( $actions as $action => $info ) {
				[ $executed, $newMsg ] = $this->takeConsequenceAction( $info );

				if ( $newMsg !== null ) {
					$messages[] = $newMsg;
				}
				if ( $executed ) {
					$actionsTaken[$filter][] = $action;
				}
			}
		}

		return $this->buildStatus( $actionsTaken, $messages );
	}

	/**
	 * Remove consequences that we already know won't be executed. This includes:
	 * - Only keep the longest block from all filters
	 * - For global filters, remove locally disabled actions
	 * - For every filter, remove "disallow" if a blocking action will be executed
	 * Then, convert the remaining ones to Consequence objects.
	 *
	 * @param array[] $actionsByFilter
	 * @return Consequence[][]
	 * @internal Temporarily public
	 */
	public function replaceArraysWithConsequences( array $actionsByFilter ): array {
		// Keep track of the longest block
		$maxBlock = [ 'id' => null, 'expiry' => -1, 'blocktalk' => null ];
		$dangerousActions = $this->consRegistry->getDangerousActionNames();

		foreach ( $actionsByFilter as $filter => &$actions ) {
			$isGlobalFilter = GlobalNameUtils::splitGlobalName( $filter )[1];

			if ( $isGlobalFilter ) {
				$actions = array_diff_key(
					$actions,
					array_filter( $this->options->get( 'AbuseFilterLocallyDisabledGlobalActions' ) )
				);
			}

			// Don't show the disallow message if a blocking action is executed
			if ( array_intersect( array_keys( $actions ), $dangerousActions )
				&& isset( $actions['disallow'] )
			) {
				unset( $actions['disallow'] );
			}

			foreach ( $actions as $name => $parameters ) {
				switch ( $name ) {
					case 'throttle':
					case 'warn':
					case 'disallow':
					case 'rangeblock':
					case 'degroup':
					case 'blockautopromote':
					case 'tag':
						$actions[$name] = $this->actionsParamsToConsequence( $name, $parameters, $filter );
						break;
					case 'block':
						// TODO Move to a dedicated method and/or create a generic interface
						if ( count( $parameters ) === 3 ) {
							// New type of filters with custom block
							if ( $this->user->isAnon() ) {
								$expiry = $parameters[1];
							} else {
								$expiry = $parameters[2];
							}
						} else {
							// Old type with fixed expiry
							$anonDuration = $this->options->get( 'AbuseFilterAnonBlockDuration' );
							if ( $anonDuration !== null && $this->user->isAnon() ) {
								// The user isn't logged in and the anon block duration
								// doesn't default to $wgAbuseFilterBlockDuration.
								$expiry = $anonDuration;
							} else {
								$expiry = $this->options->get( 'AbuseFilterBlockDuration' );
							}
						}

						$parsedExpiry = BlockUser::parseExpiryInput( $expiry );
						if (
							$maxBlock['expiry'] === -1 ||
							$parsedExpiry > BlockUser::parseExpiryInput( $maxBlock['expiry'] )
						) {
							// Save the parameters to issue the block with
							$maxBlock = [
								'id' => $filter,
								'expiry' => $expiry,
								'blocktalk' => is_array( $parameters ) && in_array( 'blocktalk', $parameters )
							];
						}
						// We'll re-add it later
						unset( $actions['block'] );
						break;
					default:
						$cons = $this->actionsParamsToConsequence( $name, $parameters, $filter );
						if ( $cons !== null ) {
							$actions[$name] = $cons;
						} else {
							unset( $actions[$name] );
						}
				}
			}
		}
		unset( $actions );

		if ( $maxBlock['id'] !== null ) {
			$id = $maxBlock['id'];
			unset( $maxBlock['id'] );
			$actionsByFilter[$id]['block'] = $this->actionsParamsToConsequence( 'block', $maxBlock, $id );
		}

		return $actionsByFilter;
	}

	/**
	 * @param string $actionName
	 * @param array $rawParams
	 * @param int|string $filter
	 * @return Consequence|null
	 */
	private function actionsParamsToConsequence( string $actionName, array $rawParams, $filter ): ?Consequence {
		[ $filterID, $isGlobalFilter ] = GlobalNameUtils::splitGlobalName( $filter );
		$filterObj = $this->filterLookup->getFilter( $filterID, $isGlobalFilter );

		$baseConsParams = new Parameters(
			$filterObj,
			$isGlobalFilter,
			$this->user,
			$this->title,
			$this->vars->getComputedVariable( 'action' )->toString()
		);

		switch ( $actionName ) {
			case 'throttle':
				$throttleId = array_shift( $rawParams );
				list( $rateCount, $ratePeriod ) = explode( ',', array_shift( $rawParams ) );

				$throttleParams = [
					'id' => $throttleId,
					'count' => (int)$rateCount,
					'period' => (int)$ratePeriod,
					'groups' => $rawParams,
					'global' => $isGlobalFilter
				];
				return $this->consFactory->newThrottle( $baseConsParams, $throttleParams );
			case 'warn':
				return $this->consFactory->newWarn( $baseConsParams, $rawParams[0] ?? 'abusefilter-warning' );
			case 'disallow':
				return $this->consFactory->newDisallow( $baseConsParams, $rawParams[0] ?? 'abusefilter-disallowed' );
			case 'rangeblock':
				return $this->consFactory->newRangeBlock( $baseConsParams, '1 week' );
			case 'degroup':
				return $this->consFactory->newDegroup( $baseConsParams, $this->vars );
			case 'blockautopromote':
				$duration = $this->options->get( 'AbuseFilterBlockAutopromoteDuration' ) * 86400;
				return $this->consFactory->newBlockAutopromote( $baseConsParams, $duration );
			case 'block':
				return $this->consFactory->newBlock( $baseConsParams, $rawParams['expiry'], $rawParams['blocktalk'] );
			case 'tag':
				try {
					// The variable is not lazy-loaded
					$accountName = $this->vars->getComputedVariable( 'accountname' )->toNative();
				} catch ( UnsetVariableException $_ ) {
					$accountName = null;
				}
				return $this->consFactory->newTag( $baseConsParams, $accountName, $rawParams );
			default:
				if ( array_key_exists( $actionName, $this->consRegistry->getCustomActions() ) ) {
					$callback = $this->consRegistry->getCustomActions()[$actionName];
					return $callback( $baseConsParams, $rawParams );
				} else {
					$this->logger->warning( "Unrecognised action $actionName" );
					return null;
				}
		}
	}

	/**
	 * Pre-check any "special" consequence and remove any further actions prevented by them. Specifically:
	 * should be actually executed. Normalizations done here:
	 * - For every filter with "throttle" enabled, remove other actions if the throttle counter hasn't been reached
	 * - For every filter with "warn" enabled, remove other actions if the warning hasn't been shown
	 *
	 * @param Consequence[][] $actionsByFilter
	 * @return Consequence[][]
	 * @internal Temporary method
	 */
	public function getFilteredConsequences( array $actionsByFilter ): array {
		foreach ( $actionsByFilter as $filter => $actions ) {
			/** @var ConsequencesDisablerConsequence[] $consequenceDisablers */
			$consequenceDisablers = array_filter( $actions, static function ( $el ) {
				return $el instanceof ConsequencesDisablerConsequence;
			} );
			'@phan-var ConsequencesDisablerConsequence[] $consequenceDisablers';
			uasort(
				$consequenceDisablers,
				static function ( ConsequencesDisablerConsequence $x, ConsequencesDisablerConsequence $y ) {
					return $x->getSort() - $y->getSort();
				}
			);
			foreach ( $consequenceDisablers as $name => $consequence ) {
				if ( $consequence->shouldDisableOtherConsequences() ) {
					$actionsByFilter[$filter] = [ $name => $consequence ];
					continue 2;
				}
			}
		}

		return $actionsByFilter;
	}

	/**
	 * @param Consequence $consequence
	 * @return array [ Executed (bool), Message (?array) ] The message is given as an array
	 *   containing the message key followed by any message parameters.
	 * @todo Improve return value
	 */
	private function takeConsequenceAction( Consequence $consequence ): array {
		$res = $consequence->execute();
		if ( $res && $consequence instanceof HookAborterConsequence ) {
			$message = $consequence->getMessage();
		}

		return [ $res, $message ?? null ];
	}

	/**
	 * Constructs a Status object as returned by executeFilterActions() from the list of
	 * actions taken and the corresponding list of messages.
	 *
	 * @param array[] $actionsTaken associative array mapping each filter to the list if
	 *                actions taken because of that filter.
	 * @param array[] $messages a list of arrays, where each array contains a message key
	 *                followed by any message parameters.
	 *
	 * @return Status
	 */
	private function buildStatus( array $actionsTaken, array $messages ): Status {
		$status = Status::newGood( $actionsTaken );

		foreach ( $messages as $msg ) {
			$status->fatal( ...$msg );
		}

		return $status;
	}
}
