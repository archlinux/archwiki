<?php

namespace MediaWiki\CheckUser\Tests\Unit\Logging;

use Generator;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\DBReadOnlyError;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \MediaWiki\CheckUser\Logging\TemporaryAccountLogger
 */
class TemporaryAccountLoggerTest extends MediaWikiUnitTestCase {
	public static function provideLogViewDebounced(): Generator {
		yield [
			'isDebounced' => true,
		];
		yield [
			'isDebounced' => false,
		];
	}

	/**
	 * @dataProvider provideLogViewDebounced
	 */
	public function testLogFromExternal( bool $isDebounced ) {
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

		// Mock that the performer does not have an actor ID to skip needing to
		// mock the DB query response for the debounced log type.
		$mockActorStore = $this->createMock( ActorStore::class );
		$mockActorStore->method( 'findActorId' )
			->willReturn( null );

		$logger = $this->getMockBuilder( TemporaryAccountLogger::class )
			->setConstructorArgs( [
				$mockActorStore,
				new NullLogger(),
				$dbProvider,
				$titleFactoryMock,
				24 * 60 * 60,
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
			->with( $database );

		$logger->expects( $this->once() )
			->method( 'createManualLogEntry' )
			->with( 'test' )
			->willReturn( $logEntry );

		$logger->logFromExternal( $performer, 'Foo', 'test', [], $isDebounced, 0 );
	}

	public function testLogFromExternalWhenDBErrorPreventsInsert() {
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

		// Expect that the DB error causes a critical log to be created.
		$mockLogger = $this->createMock( LoggerInterface::class );
		$mockLogger->expects( $this->once() )
			->method( 'critical' );

		$logger = $this->getMockBuilder( TemporaryAccountLogger::class )
			->setConstructorArgs( [
				$this->createMock( ActorStore::class ),
				$mockLogger,
				$dbProvider,
				$titleFactoryMock,
				24 * 60 * 60,
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
			->with( 'test' )
			->willReturn( $logEntry );

		$logger->logFromExternal( $performer, 'Foo', 'test', [], false, 0 );
	}
}
