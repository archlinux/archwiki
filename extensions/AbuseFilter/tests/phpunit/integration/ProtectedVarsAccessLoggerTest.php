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
			->logViewProtectedVariableValue( $performer->getUserIdentity(), '~2024-01', [ 'protected_var' ] );
		AbuseFilterServices::getAbuseLoggerFactory()
			->getProtectedVarsAccessLogger()
			->logViewProtectedVariableValue( $performer->getUserIdentity(), '~2024-01', [ 'protected_var' ] );
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
			$performer->getUserIdentity(), 'Username with spaces', [ 'protected_var' ]
		);
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), 'Username with spaces', [ 'protected_var' ]
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

	public function testDebouncedLogs_LogsDifferentVars() {
		// Attempt to create two protected var access logs where the target is a username with spaces.
		$performer = $this->getTestSysop();
		$protectedVarsAccessLogger = AbuseFilterServices::getAbuseLoggerFactory()->getProtectedVarsAccessLogger();
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), 'Username', [ 'protected_var1' ]
		);
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), 'Username', [ 'protected_var2' ]
		);
		DeferredUpdates::doUpdates();

		// Assert that two logs are created, as the variables are different (T399819)
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->assertFieldValue( 2 );
	}

	public function testDebouncedLogs_LogsDifferentVarsSupersetOfPrevious() {
		// Attempt to create two protected var access logs where the target is a username with spaces.
		$performer = $this->getTestSysop();
		$protectedVarsAccessLogger = AbuseFilterServices::getAbuseLoggerFactory()->getProtectedVarsAccessLogger();
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), 'Username', [ 'protected_var1' ]
		);
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), 'Username', [ 'protected_var1', 'protected_var2' ]
		);
		DeferredUpdates::doUpdates();

		// Assert that two logs are created, as the second variable is a superset of the first
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->assertFieldValue( 2 );
	}

	public function testDebouncedLogs_LogsDifferentVarsSubsetOfPrevious() {
		// Attempt to create two protected var access logs where the target is a username with spaces.
		$performer = $this->getTestSysop();
		$protectedVarsAccessLogger = AbuseFilterServices::getAbuseLoggerFactory()->getProtectedVarsAccessLogger();
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), 'Username', [ 'protected_var1', 'protected_var2' ]
		);
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), 'Username', [ 'protected_var1' ]
		);
		DeferredUpdates::doUpdates();

		// Assert that one log is created, as the second variable is in a subset of the first
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->assertFieldValue( 1 );
	}

	public function testDebouncedLogs_LogsVarsCoveredByMultiplePrevious() {
		// Attempt to create two protected var access logs where the target is a username with spaces.
		$performer = $this->getTestSysop();
		$protectedVarsAccessLogger = AbuseFilterServices::getAbuseLoggerFactory()->getProtectedVarsAccessLogger();
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), 'Username', [ 'protected_var1' ]
		);
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), 'Username', [ 'protected_var2' ]
		);
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), 'Username', [ 'protected_var1', 'protected_var2' ]
		);
		DeferredUpdates::doUpdates();

		// Assert that two logs are created, as the third access uses variables that were already logged
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->assertFieldValue( 2 );
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
