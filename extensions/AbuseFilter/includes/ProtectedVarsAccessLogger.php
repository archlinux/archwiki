<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;
use Profiler;
use Psr\Log\LoggerInterface;
use Wikimedia\Assert\Assert;
use Wikimedia\Rdbms\DBError;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Defines the API for the component responsible for logging when a user views the value of protected variables.
 */
class ProtectedVarsAccessLogger {
	/**
	 * Represents a user viewing the value of a protected variable
	 *
	 * @var string
	 */
	public const ACTION_VIEW_PROTECTED_VARIABLE_VALUE = 'view-protected-var-value';

	/**
	 * @var string
	 */
	public const LOG_TYPE = 'abusefilter-protected-vars';

	private LoggerInterface $logger;
	private IConnectionProvider $lbFactory;
	private ActorStore $actorStore;
	private AbuseFilterHookRunner $hookRunner;
	private TitleFactory $titleFactory;
	private int $delay;

	/**
	 * @param LoggerInterface $logger
	 * @param IConnectionProvider $lbFactory
	 * @param ActorStore $actorStore
	 * @param AbuseFilterHookRunner $hookRunner
	 * @param TitleFactory $titleFactory
	 * @param int $delay The number of seconds after which a duplicate log entry can be
	 *  created for a debounced log
	 * @internal Use {@link AbuseLoggerFactory::getProtectedVarsAccessLogger} instead
	 */
	public function __construct(
		LoggerInterface $logger,
		IConnectionProvider $lbFactory,
		ActorStore $actorStore,
		AbuseFilterHookRunner $hookRunner,
		TitleFactory $titleFactory,
		int $delay
	) {
		Assert::parameter( $delay > 0, 'delay', 'delay must be positive' );

		$this->logger = $logger;
		$this->lbFactory = $lbFactory;
		$this->actorStore = $actorStore;
		$this->hookRunner = $hookRunner;
		$this->titleFactory = $titleFactory;
		$this->delay = $delay;
	}

	/**
	 * Log when the user views the values of protected variables
	 *
	 * @param UserIdentity $performer
	 * @param string $target
	 * @param string[] $viewedVariables The variables associated with the values the user saw.
	 * @param int|null $timestamp
	 */
	public function logViewProtectedVariableValue(
		UserIdentity $performer,
		string $target,
		array $viewedVariables,
		?int $timestamp = null
	): void {
		if ( !$timestamp ) {
			$timestamp = (int)wfTimestamp();
		}
		// Create the log on POSTSEND, as this can be called in a context of a GET request through the
		// QueryAbuseLog API (T379083).
		DeferredUpdates::addCallableUpdate( function () use ( $performer, $target, $timestamp, $viewedVariables ) {
			// We need to create a log entry and PostSend-GET expects no writes are performed, so we need to
			// silence the warnings created by this.
			$trxProfiler = Profiler::instance()->getTransactionProfiler();
			$scope = $trxProfiler->silenceForScope( $trxProfiler::EXPECTATION_REPLICAS_ONLY );
			$this->createProtectedVariableValueAccessLog(
				$performer,
				$target,
				$timestamp,
				[ 'variables' => $viewedVariables ]
			);
		} );
	}

	/**
	 * Actually creates the log for when a user views the value of protected variables
	 *
	 * @param UserIdentity $performer
	 * @param string $target
	 * @param int $timestamp
	 * @param array $params
	 */
	private function createProtectedVariableValueAccessLog(
		UserIdentity $performer,
		string $target,
		int $timestamp,
		array $params
	): void {
		// Allow external extensions to hook into this logger and pass along all known
		// values. External extensions can abort this hook to stop additional logging
		if ( !$this->hookRunner->onAbuseFilterLogProtectedVariableValueAccess(
			$performer,
			$target,
			self::ACTION_VIEW_PROTECTED_VARIABLE_VALUE,
			true,
			$timestamp,
			$params
		) ) {
			// Don't continue if the hook returns false
			return;
		}

		$dbw = $this->lbFactory->getPrimaryDatabase();
		$shouldLog = false;

		// Don't log more than one protected variable access log if the same log was created
		// within the delay period.
		$timestampMinusDelay = $timestamp - $this->delay;
		$actorId = $this->actorStore->findActorId( $performer, $dbw );
		$targetAsTitle = $this->titleFactory->makeTitle( NS_USER, $target );
		if ( !$actorId ) {
			$shouldLog = true;
		} else {
			$logline = $dbw->newSelectQueryBuilder()
				->select( '*' )
				->from( 'logging' )
				->where( [
					'log_type' => self::LOG_TYPE,
					'log_action' => self::ACTION_VIEW_PROTECTED_VARIABLE_VALUE,
					'log_actor' => $actorId,
					'log_namespace' => $targetAsTitle->getNamespace(),
					'log_title' => $targetAsTitle->getDBkey(),
					$dbw->expr( 'log_timestamp', '>', $dbw->timestamp( $timestampMinusDelay ) ),
				] )
				->caller( __METHOD__ )
				->fetchRow();

			if ( !$logline ) {
				$shouldLog = true;
			}
		}

		// Actually write to logging table
		if ( $shouldLog ) {
			$logEntry = $this->createManualLogEntry( self::ACTION_VIEW_PROTECTED_VARIABLE_VALUE );
			$logEntry->setPerformer( $performer );
			$logEntry->setTarget( $targetAsTitle );
			$logEntry->setParameters( $params );
			$logEntry->setTimestamp( wfTimestamp( TS_MW, $timestamp ) );

			try {
				$logEntry->insert( $dbw );
			} catch ( DBError $e ) {
				$this->logger->critical(
					'AbuseFilter protected variable log entry was not recorded. ' .
					'This means access to private data can occur without this being auditable. ' .
					'Immediate fix required.'
				);

				throw $e;
			}
		}
	}

	/**
	 * @internal
	 *
	 * @param string $subtype
	 * @return ManualLogEntry
	 */
	protected function createManualLogEntry( string $subtype ): ManualLogEntry {
		return new ManualLogEntry( self::LOG_TYPE, $subtype );
	}
}
