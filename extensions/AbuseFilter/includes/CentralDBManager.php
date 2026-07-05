<?php

namespace MediaWiki\Extension\AbuseFilter;

use Wikimedia\Rdbms\DBError;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;

class CentralDBManager {
	public const SERVICE_NAME = ServiceNames::CentralDBManager;

	/** @var string|false */
	private $dbName;

	/**
	 * @param LBFactory $loadBalancerFactory
	 * @param string|false|null $dbName
	 * @param bool $filterIsCentral
	 */
	public function __construct(
		private readonly LBFactory $loadBalancerFactory,
		$dbName,
		private readonly bool $filterIsCentral
	) {
		// Use false to agree with LoadBalancer
		$this->dbName = $dbName ?: false;
	}

	/**
	 * @param int $index DB_PRIMARY/DB_REPLICA
	 * @return IDatabase
	 * @throws DBError
	 * @throws CentralDBNotAvailableException
	 */
	public function getConnection( int $index ): IDatabase {
		if ( !is_string( $this->dbName ) ) {
			throw new CentralDBNotAvailableException( '$wgAbuseFilterCentralDB is not configured' );
		}

		return $this->loadBalancerFactory
			->getMainLB( $this->dbName )
			->getConnection( $index, [], $this->dbName );
	}

	/**
	 * @return string
	 * @throws CentralDBNotAvailableException
	 */
	public function getCentralDBName(): string {
		if ( !is_string( $this->dbName ) ) {
			throw new CentralDBNotAvailableException( '$wgAbuseFilterCentralDB is not configured' );
		}
		return $this->dbName;
	}

	/**
	 * Whether this database is the central one.
	 * @todo Deprecate the config in favour of just checking whether the current DB is the same
	 *  as $wgAbuseFilterCentralDB.
	 * @return bool
	 */
	public function filterIsCentral(): bool {
		return $this->filterIsCentral;
	}
}
