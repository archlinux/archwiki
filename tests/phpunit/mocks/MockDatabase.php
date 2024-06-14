<?php

namespace MediaWiki\Tests;

use HashBagOStuff;
use MediaWiki\Logger\LoggerFactory;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\QueryBuilderFromRawSql;
use Wikimedia\Rdbms\QueryStatus;
use Wikimedia\Rdbms\Replication\ReplicationReporter;
use Wikimedia\Rdbms\TransactionProfiler;

/**
 * A default-constructible Database subclass that doesn't access any services so
 * should be safe to use in unit tests.
 *
 * The best way to validate a database query is by actually running it against
 * a real database. So the acceptable use cases for this class are narrow. It
 * may be useful for testing query-building services, or queries against tables
 * that don't actually exist. It can be used for service injection when there
 * is only a minor dependency on the database, for example for quoting.
 *
 * Query collection and fake result generation could easily be added if there is
 * a need for that.
 *
 * @since 1.42
 */
class MockDatabase extends Database {
	private $nextInsertId = 0;

	public function __construct( $options = [] ) {
		// Use a real logger because tests need logging, maybe more than production
		$logger = $options['logger'] ?? LoggerFactory::getInstance( 'MockDatabase' );

		parent::__construct( $options + [
			'cliMode' => true,
			'agent' => '',
			'profiler' => null,
			'trxProfiler' => new TransactionProfiler,
			'logger' => $logger,
			'errorLogger' => $logger,
			'deprecationLogger' => $logger,
			'dbname' => 'test',
			'schema' => '',
			'tablePrefix' => '',
			'password' => '',
			'flags' => 0,
			'serverName' => '',
		] );
		$this->replicationReporter = new ReplicationReporter(
			$options['topologyRole'] ?? IDatabase::ROLE_STREAMING_MASTER,
			$logger,
			$options['srvCache'] ?? new \WANObjectCache( [
				'cache' => new HashBagOStuff(),
				'logger' => $logger,
			] )
		);
	}

	protected function open( $server, $user, $password, $db, $schema, $tablePrefix ) {
	}

	public function isOpen() {
		return true;
	}

	public function indexInfo( $table, $index, $fname = __METHOD__ ) {
		throw new \RuntimeException( 'Not implemented' );
	}

	public function strencode( $s ) {
		return addslashes( $s );
	}

	protected function closeConnection() {
	}

	protected function doSingleStatementQuery( string $sql ): QueryStatus {
		$query = QueryBuilderFromRawSql::buildQuery( $sql, 0 );
		if ( $query->isWriteQuery() ) {
			return new QueryStatus( true, 0, 0, '' );
		} else {
			return new QueryStatus( new FakeResultWrapper( [] ), 0, 0, '' );
		}
	}

	public function tableExists( $table, $fname = __METHOD__ ) {
		return true;
	}

	protected function lastInsertId() {
		return $this->nextInsertId++;
	}

	public function fieldInfo( $table, $field ) {
		throw new \RuntimeException( 'Not implemented' );
	}

	public function getType() {
		return 'mock';
	}

	public function lastErrno() {
		return 0;
	}

	public function lastError() {
		return '';
	}

	public function getSoftwareLink() {
		return '';
	}

	public function getServerVersion() {
		return '';
	}
}
