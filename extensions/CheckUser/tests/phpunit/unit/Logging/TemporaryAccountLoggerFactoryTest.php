<?php

namespace MediaWiki\CheckUser\Tests\Unit\Logging;

use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\ActorStore;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory
 */
class TemporaryAccountLoggerFactoryTest extends MediaWikiUnitTestCase {
	private function getFactory(): TemporaryAccountLoggerFactory {
		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getPrimaryDatabase' )->willReturn( $this->createMock( IDatabase::class ) );
		$dbProvider->method( 'getReplicaDatabase' )->willReturn( $this->createMock( IDatabase::class ) );
		return new TemporaryAccountLoggerFactory(
			$this->createMock( ActorStore::class ),
			new NullLogger(),
			$dbProvider,
			$this->createMock( TitleFactory::class )
		);
	}

	public function testCreateFactory(): void {
		$factory = $this->getFactory();
		$this->assertInstanceOf( TemporaryAccountLoggerFactory::class, $factory );
	}

	public function testGetLogger(): void {
		$delay = 60;
		$factory = $this->getFactory();
		$logger = TestingAccessWrapper::newFromObject(
			$factory->getLogger( $delay )
		);
		$this->assertInstanceOf( TemporaryAccountLogger::class, $logger->object );
		$this->assertSame( $delay, $logger->delay, 'delay' );
	}
}
