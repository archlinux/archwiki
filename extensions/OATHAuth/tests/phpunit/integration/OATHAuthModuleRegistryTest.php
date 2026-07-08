<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration;

use InvalidArgumentException;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;
use Wikimedia\ObjectFactory\ObjectFactory;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry
 * @group Database
 */
class OATHAuthModuleRegistryTest extends MediaWikiIntegrationTestCase {
	private function makeTestRegistry(): OATHAuthModuleRegistry {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'oathauth_types' )
			->row( [ 'oat_name' => 'first' ] )
			->caller( __METHOD__ )
			->execute();

		$database = $this->createMock( IConnectionProvider::class );
		$database->method( 'getPrimaryDatabase' )->with( 'virtual-oathauth' )->willReturn( $this->getDb() );
		$database->method( 'getReplicaDatabase' )->with( 'virtual-oathauth' )->willReturn( $this->getDb() );

		return new OATHAuthModuleRegistry(
			$database,
			$this->createNoOpMock( ObjectFactory::class ),
			[
				'first'  => 'does not matter',
				'second' => 'does not matter',
				'third'  => 'does not matter',
			]
		);
	}

	public function testModuleExists() {
		$registry = $this->makeTestRegistry();
		$this->assertTrue( $registry->moduleExists( 'first' ) );
		$this->assertFalse( $registry->moduleExists( 'nonexistent' ) );
	}

	public function testGetModuleIds() {
		$registry = $this->makeTestRegistry();

		$this->assertEquals(
			[ 'first', 'second', 'third' ],
			array_keys( $registry->getModuleIds() )
		);
	}

	public function testGetModuleId() {
		$registry = $this->makeTestRegistry();
		$this->assertSame( 1, $registry->getModuleId( 'first' ) );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Module nonexistent does not seem to exist' );
		$registry->getModuleId( 'nonexistent' );
	}

	public function testGetModuleByKey() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'No such two-factor module nonexistent' );

		$registry = $this->makeTestRegistry();
		$registry->getModuleByKey( 'nonexistent' );
	}

	public function testGetAllModules() {
		$services = $this->getServiceContainer();
		$registry = new OATHAuthModuleRegistry(
			$services->getDBLoadBalancerFactory(),
			$services->getObjectFactory(),
			ExtensionRegistry::getInstance()->getAttribute( 'OATHAuthModules' ),
		);

		$this->assertCount( 3, $registry->getAllModules() );
	}
}
