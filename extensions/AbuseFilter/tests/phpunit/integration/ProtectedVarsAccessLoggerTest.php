<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger
 * @group Database
 */
class ProtectedVarsAccessLoggerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Stop external extensions (CU) from affecting the behaviour of the logger, such as changing where the
		// logs are sent.
		$this->clearHook( 'AbuseFilterLogProtectedVariableValueAccess' );
	}

	public function testDebouncedLogs_NoHookModifications() {
		// Stop external extensions (CU) from affecting the logger by overriding any modifications
		$this->clearHook( 'AbuseFilterLogProtectedVariableValueAccess' );

		// Run the same action twice
		$performer = $this->getTestSysop();
		AbuseFilterServices::getAbuseLoggerFactory()
			->getProtectedVarsAccessLogger()
			->logViewProtectedVariableValue( $performer->getUserIdentity(), '~2024-01', [] );
		AbuseFilterServices::getAbuseLoggerFactory()
			->getProtectedVarsAccessLogger()
			->logViewProtectedVariableValue( $performer->getUserIdentity(), '~2024-01', [] );
		DeferredUpdates::doUpdates();

		// Assert that the log is only inserted once into abusefilter's protected vars logging table
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->assertFieldValue( 1 );
	}

	public function testDebouncedLogs_HandlesSpacesInTargetUsername() {
		// Attempt to create two protected var access logs where the target is a username with spaces.
		$performer = $this->getTestSysop();
		$protectedVarsAccessLogger = AbuseFilterServices::getAbuseLoggerFactory()->getProtectedVarsAccessLogger();
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), 'Username with spaces', []
		);
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), 'Username with spaces', []
		);
		DeferredUpdates::doUpdates();

		// Assert that only one log is created, as it should have been debounced (was not before T389854)
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->assertFieldValue( 1 );
	}

	public function testProtectedVarsAccessLogger_HookModification() {
		$this->setTemporaryHook( 'AbuseFilterLogProtectedVariableValueAccess', static function (
			UserIdentity $performer,
			string $target,
			string $action,
			bool $shouldDebounce,
			int $timestamp,
			array $params
		) {
			return false;
		} );

		// Run a loggable action
		$performer = $this->getTestSysop();
		AbuseFilterServices::getAbuseLoggerFactory()
			->getProtectedVarsAccessLogger()
			->logViewProtectedVariableValue( $performer->getUserIdentity(), '~2024-01', [] );

		// Assert that the hook abort also aborted AbuseFilter's logging
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->assertFieldValue( 0 );
	}

	public function testProtectedVarsAccessLogger_ActorStoreHasNoActorId() {
		// Create a mock ActorStore that returns that any user provided has no actor ID.
		// This will cause the code to skip checking for an existing log when debouncing.
		$mockActorStore = $this->createMock( ActorStore::class );
		$mockActorStore->method( 'findActorId' )
			->willReturn( null );

		$protectedVarsAccessLogger = new ProtectedVarsAccessLogger(
			$this->createMock( LoggerInterface::class ),
			$this->getServiceContainer()->getConnectionProvider(),
			$mockActorStore,
			$this->getServiceContainer()->get( AbuseFilterHookRunner::SERVICE_NAME ),
			$this->getServiceContainer()->getTitleFactory(),
			1234
		);
		$performer = $this->getTestSysop();
		$protectedVarsAccessLogger->logViewProtectedVariableValue( $performer->getUserIdentity(), '~2024-01', [] );

		// Assert that a log was created for the access
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
				'log_actor' => $this->getServiceContainer()->getActorStore()
					->findActorId( $performer->getUserIdentity(), $this->getDb() )
			] )
			->assertFieldValue( 1 );
	}
}
