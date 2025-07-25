<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\DBReadOnlyError;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger
 */
class ProtectedVarsAccessLoggerTest extends MediaWikiUnitTestCase {

	public function testLogWhenDBErrorPreventsInsert() {
		$name = 'Foo';
		$performer = new UserIdentityValue( 1, $name );
		$expectedTarget = Title::makeTitle( NS_USER, $name );

		$titleFactoryMock = $this->createMock( TitleFactory::class );
		$titleFactoryMock->method( 'makeTitle' )
			->willReturn( $expectedTarget );

		$database = $this->createMock( IDatabase::class );

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getPrimaryDatabase' )
			->willReturn( $database );

		// Create a mock ActorStore that returns that any user provided has no actor ID.
		// This will cause the code to skip checking for an existing log when debouncing.
		$mockActorStore = $this->createMock( ActorStore::class );
		$mockActorStore->method( 'findActorId' )
			->willReturn( null );

		// Expect that the DB error causes a critical log to be created.
		$mockLogger = $this->createMock( LoggerInterface::class );
		$mockLogger->expects( $this->once() )
			->method( 'critical' );

		$mockHookRunner = $this->createMock( AbuseFilterHookRunner::class );
		$mockHookRunner->method( 'onAbuseFilterLogProtectedVariableValueAccess' )
			->willReturn( true );

		$logger = $this->getMockBuilder( ProtectedVarsAccessLogger::class )
			->setConstructorArgs( [
				$mockLogger,
				$dbProvider,
				$mockActorStore,
				$mockHookRunner,
				$titleFactoryMock,
				1234,
			] )
			->onlyMethods( [ 'createManualLogEntry' ] )
			->getMock();

		$logEntry = $this->createMock( ManualLogEntry::class );
		$logEntry->expects( $this->once() )
			->method( 'setPerformer' )
			->with( $performer );

		$logEntry->expects( $this->once() )
			->method( 'setTarget' )
			->with( $expectedTarget );

		$logEntry->expects( $this->once() )
			->method( 'insert' )
			->with( $database )
			->willThrowException( new DBReadOnlyError( $database, 'test' ) );

		$logger->expects( $this->once() )
			->method( 'createManualLogEntry' )
			->with( 'view-protected-var-value' )
			->willReturn( $logEntry );

		$this->expectException( DBReadOnlyError::class );
		$logger->logViewProtectedVariableValue( $performer, $name, [] );
		DeferredUpdates::doUpdates();
	}
}
