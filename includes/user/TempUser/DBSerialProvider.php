<?php

namespace MediaWiki\User\TempUser;

use Wikimedia\Rdbms\IDatabase;

/**
 * Base class for serial acquisition code shared between core and CentralAuth.
 *
 * @since 1.39
 */
abstract class DBSerialProvider implements SerialProvider {
	/** @var int */
	private $numShards;

	/**
	 * @param array $config
	 *   - numShards (int, default 1): A small integer. This can be set to a
	 *     value greater than 1 to avoid acquiring a global lock when
	 *     allocating IDs, at the expense of making the IDs be non-monotonic.
	 */
	public function __construct( $config ) {
		$this->numShards = $config['numShards'] ?? 1;
	}

	public function acquireIndex(): int {
		if ( $this->numShards ) {
			$shard = mt_rand( 0, $this->numShards - 1 );
		} else {
			$shard = 0;
		}

		$dbw = $this->getDB();
		$table = $this->getTableName();
		$dbw->startAtomic( __METHOD__ );
		$dbw->upsert(
			$table,
			[
				'uas_shard' => $shard,
				'uas_value' => 1,
			],
			[ [ 'uas_shard' ] ],
			[ 'uas_value=uas_value+1' ],
			__METHOD__
		);
		$value = $dbw->newSelectQueryBuilder()
			->select( 'uas_value' )
			->from( $table )
			->where( [ 'uas_shard' => $shard ] )
			->caller( __METHOD__ )
			->fetchField();
		$dbw->endAtomic( __METHOD__ );
		return $value * $this->numShards + $shard;
	}

	/**
	 * @return IDatabase
	 */
	abstract protected function getDB();

	/**
	 * @return string
	 */
	abstract protected function getTableName();
}
