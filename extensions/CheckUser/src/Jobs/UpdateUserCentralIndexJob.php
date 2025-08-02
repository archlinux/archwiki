<?php

namespace MediaWiki\CheckUser\Jobs;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\JobQueue\Job;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Updates or inserts a row to the cuci_user table to record that an action occured.
 *
 * Performed in a job to prevent potentially expensive writes being performed on the
 * main request / post-send.
 */
class UpdateUserCentralIndexJob extends Job implements CheckUserQueryInterface {
	/**
	 * The type of this job, as registered in wgJobTypeConf.
	 */
	public const TYPE = 'checkuserUpdateUserCentralIndexJob';

	private IConnectionProvider $dbProvider;

	/** @inheritDoc */
	public function __construct( ?Title $title, array $params, IConnectionProvider $dbProvider ) {
		parent::__construct( self::TYPE, $params );
		$this->dbProvider = $dbProvider;
	}

	/** @return bool */
	public function run(): bool {
		$dbw = $this->dbProvider->getPrimaryDatabase( self::VIRTUAL_GLOBAL_DB_DOMAIN );

		// Get an exclusive lock to update the cuci_user central index for this central ID and wiki map
		// ID to avoid deadlocks. We cannot use ::forUpdate due to T374244
		$key = "cuci-insert:" . $this->params['wikiMapID'] . ":" . $this->params['centralID'];
		$scopedLock = $dbw->getScopedLockAndFlush( $key, __METHOD__, 5 );
		if ( !$scopedLock ) {
			return true;
		}

		// Return if the timestamp in the DB is after or equal to the timestamp specified in the job. This can
		// occur if another update job ran before this one did.
		$lastTimestamp = $dbw->newSelectQueryBuilder()
			->select( 'ciu_timestamp' )
			->from( 'cuci_user' )
			->where( [ 'ciu_ciwm_id' => $this->params['wikiMapID'], 'ciu_central_id' => $this->params['centralID'] ] )
			->caller( __METHOD__ )
			->fetchField();

		if (
			$lastTimestamp &&
			$this->params['timestamp'] <= ConvertibleTimestamp::convert( TS_MW, $lastTimestamp )
		) {
			return true;
		}

		// Either insert the cuci_user row or update it if one already exists, setting the timestamp provided.
		$dbw->newInsertQueryBuilder()
			->insertInto( 'cuci_user' )
			->row( [
				'ciu_timestamp' => $dbw->timestamp( $this->params['timestamp'] ),
				'ciu_ciwm_id' => $this->params['wikiMapID'],
				'ciu_central_id' => $this->params['centralID'],
			] )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'ciu_ciwm_id', 'ciu_central_id' ] )
			->set( [ 'ciu_timestamp' => $dbw->timestamp( $this->params['timestamp'] ) ] )
			->caller( __METHOD__ )
			->execute();

		return true;
	}
}
