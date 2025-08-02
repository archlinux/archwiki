<?php

namespace MediaWiki\CheckUser\Jobs;

use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\Job;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;

/**
 * Log when a user views the IP addresses of a temporary account or the user views the temporary accounts
 * associated with a given IP or IP range.
 */
class LogTemporaryAccountAccessJob extends Job {
	/**
	 * The type of this job, as registered in wgJobTypeConf.
	 */
	public const TYPE = 'checkuserLogTemporaryAccountAccess';

	/**
	 * @inheritDoc
	 */
	public function __construct( $title, $params ) {
		parent::__construct( self::TYPE, $params );
	}

	/**
	 * Create a new job specification for logging access to temporary account data.
	 *
	 * @param UserIdentity $performer The user that accessed the data.
	 * @param string $target Name of the temporary account or IP address that was looked up.
	 * @param string $type The type of data access that was performed
	 * (one of the TemporaryAccountLogger::ACTION_VIEW_* constants).
	 *
	 * @return IJobSpecification
	 */
	public static function newSpec(
		UserIdentity $performer,
		string $target,
		string $type
	): IJobSpecification {
		return new JobSpecification(
			self::TYPE,
			[
				'performer' => $performer->getName(),
				'target' => $target,
				'timestamp' => (int)wfTimestamp(),
				'type' => $type,
			],
			[],
			null
		);
	}

	/**
	 * @return bool
	 */
	public function run() {
		$services = MediaWikiServices::getInstance();

		$performer = $services
			->getUserIdentityLookup()
			->getUserIdentityByName( $this->params['performer'] );
		$target = $this->params['target'];
		$timestamp = $this->params['timestamp'];
		$type = $this->params['type'];

		if ( !$performer ) {
			$this->setLastError( 'Invalid performer' );
			return false;
		}

		/** @var TemporaryAccountLogger $logger */
		$logger = $services
			->get( 'CheckUserTemporaryAccountLoggerFactory' )
			->getLogger();
		if ( $type === TemporaryAccountLogger::ACTION_VIEW_IPS ) {
			$logger->logViewIPs( $performer, $target, $timestamp );
		} elseif ( $type === TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP ) {
			$logger->logViewTemporaryAccountsOnIP( $performer, $target, $timestamp );
		} elseif ( $type === TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP_GLOBAL ) {
			$logger->logViewTemporaryAccountsOnIP( $performer, $target, $timestamp, true );
		} else {
			$this->setLastError( "Invalid type '$type'" );
			return false;
		}

		return true;
	}
}
