<?php

use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\DatabaseFactory;
use Wikimedia\Rdbms\DatabaseMysqli;
use Wikimedia\Rdbms\DatabasePostgres;
use Wikimedia\Rdbms\DatabaseSqlite;

/**
 * @covers Wikimedia\Rdbms\DatabaseFactory
 */
class DatabaseFactoryTest extends PHPUnit\Framework\TestCase {

	use MediaWikiCoversValidator;

	public function testFactory() {
		$factory = new DatabaseFactory();
		$m = Database::NEW_UNCONNECTED; // no-connect mode
		$p = [
			'host' => 'localhost',
			'serverName' => 'localdb',
			'user' => 'me',
			'password' => 'myself',
			'dbname' => 'i'
		];

		$this->assertInstanceOf( DatabaseMysqli::class, $factory->create( 'mysqli', $p, $m ) );
		$this->assertInstanceOf( DatabaseMysqli::class, $factory->create( 'MySqli', $p, $m ) );
		$this->assertInstanceOf( DatabaseMysqli::class, $factory->create( 'MySQLi', $p, $m ) );
		$this->assertInstanceOf( DatabasePostgres::class, $factory->create( 'postgres', $p, $m ) );
		$this->assertInstanceOf( DatabasePostgres::class, $factory->create( 'Postgres', $p, $m ) );

		$x = $p + [ 'dbFilePath' => 'some/file.sqlite' ];
		$this->assertInstanceOf( DatabaseSqlite::class, $factory->create( 'sqlite', $x, $m ) );
		$x = $p + [ 'dbDirectory' => 'some/file' ];
		$this->assertInstanceOf( DatabaseSqlite::class, $factory->create( 'sqlite', $x, $m ) );

		$conn = $factory->create( 'sqlite', $p, $m );
		$this->assertEquals( 'localhost', $conn->getServer() );
		$this->assertEquals( 'localdb', $conn->getServerName() );
	}
}
