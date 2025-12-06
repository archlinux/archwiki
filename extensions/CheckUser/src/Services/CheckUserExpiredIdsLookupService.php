<?php

namespace MediaWiki\CheckUser\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Registration\ExtensionRegistry;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class CheckUserExpiredIdsLookupService {

	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'CUDMaxAge',
	];

	private IConnectionProvider $connectionProvider;
	private ExtensionRegistry $extensionRegistry;
	private int $maxDataAgeSeconds;

	public function __construct(
		ServiceOptions $options,
		IConnectionProvider $connectionProvider,
		ExtensionRegistry $extensionRegistry
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->connectionProvider = $connectionProvider;
		$this->extensionRegistry = $extensionRegistry;
		$this->maxDataAgeSeconds = $options->get( 'CUDMaxAge' );
	}

	/**
	 * Given an array of Revision IDs, determines which of them exist and are
	 * considered expired.
	 *
	 * @param string[] $revisionIds Revision IDs to check.
	 * @return string[] List of Revision IDs that exist and are expired.
	 */
	public function listExpiredRevisionIdsInSet( array $revisionIds ): array {
		return $this->doListExpiredIdsInSet(
			'rev_id',
			'revision',
			'rev_timestamp',
			$revisionIds
		);
	}

	/**
	 * Given an array of Log IDs, determines which of them exist and are
	 * considered expired.
	 *
	 * @param string[] $logIds Log IDs to check.
	 * @return string[] List of Log IDs that exist and are expired.
	 */
	public function listExpiredLogIdsInSet( array $logIds ): array {
		return $this->doListExpiredIdsInSet(
			'log_id',
			'logging',
			'log_timestamp',
			$logIds
		);
	}

	/**
	 * Given an array of AbuseFilter Log IDs, determines which of them exist
	 * and are considered expired.
	 *
	 * @param string[] $afLogIds AbuseFilter Log IDs to check.
	 * @return string[] List of AbuseFilter Log IDs that exist and are expired.
	 */
	public function listExpiredAbuseLogIdsInSet( array $afLogIds ): array {
		if ( !$this->extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
			return [];
		}

		return $this->doListExpiredIdsInSet(
			'afl_id',
			'abuse_filter_log',
			'afl_timestamp',
			$afLogIds
		);
	}

	private function doListExpiredIdsInSet(
		string $columnName,
		string $tableName,
		string $timestampColumnName,
		array $ids
	): array {
		if ( count( $ids ) === 0 || !$this->hasCutoff() ) {
			return [];
		}

		$dbr = $this->connectionProvider->getReplicaDatabase();
		$cutoff = $this->getCutoffTimestamp( $dbr );
		$expired = [];

		foreach ( array_chunk( $ids, 500 ) as $batch ) {
			$values = $dbr->newSelectQueryBuilder()
				->select( $columnName )
				->from( $tableName )
				->where( [
					$columnName => $batch,
					$dbr->expr( $timestampColumnName, '<', $cutoff ),
				] )
				->caller( __METHOD__ )
				->fetchFieldValues();

			array_push( $expired, ...$values );
		}

		return $expired;
	}

	/**
	 * Returns the cutoff timestamp that determines whether a log entry is
	 * considered expired based on the current timestamp.
	 *
	 * @param IReadableDatabase $dbr Used to format the timestamp.
	 */
	private function getCutoffTimestamp( IReadableDatabase $dbr ): string {
		return $dbr->timestamp( ConvertibleTimestamp::time() - $this->maxDataAgeSeconds );
	}

	/**
	 * Determines if the service was instantiated providing a "max age" value,
	 * which allows for determining a "cut off" timestamp for determining if
	 * log entries should be considered expired.
	 */
	private function hasCutoff(): bool {
		return ( $this->maxDataAgeSeconds > 0 );
	}
}
