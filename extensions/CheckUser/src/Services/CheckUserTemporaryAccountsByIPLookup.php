<?php

namespace MediaWiki\CheckUser\Services;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserFactory;
use StatusValue;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Given an IP, return every known temporary account that has edited from it.
 *
 * Note that in WMF production, using this service outside of Extension:CheckUser
 * requires consultation with Trust & Safety Product.
 */
class CheckUserTemporaryAccountsByIPLookup implements CheckUserQueryInterface {

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserMaximumRowCount'
	];
	private JobQueueGroup $jobQueueGroup;
	private IConnectionProvider $connectionProvider;
	private ServiceOptions $serviceOptions;
	private TempUserConfig $tempUserConfig;
	private UserFactory $userFactory;
	private UserOptionsLookup $userOptionsLookup;
	private PermissionManager $permissionManager;

	public function __construct(
		ServiceOptions $serviceOptions,
		IConnectionProvider $connectionProvider,
		JobQueueGroup $jobQueueGroup,
		TempUserConfig $tempUserConfig,
		UserFactory $userFactory,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup
	) {
		$serviceOptions->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->serviceOptions = $serviceOptions;
		$this->connectionProvider = $connectionProvider;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->tempUserConfig = $tempUserConfig;
		$this->userFactory = $userFactory;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @param string $ip The IP address to use in the lookup
	 * @param Authority $authority The authority making the request
	 * @param bool $shouldLog Should a log entry be created to show that this data was accessed? By default,
	 *   create a log entry. Classes that extend AbstractTemporaryAccountHandler don't need to set this to true,
	 *   because AbstractTemporaryAccountHandler creates a log entry.
	 * @param int|null $limit The maximum number of rows to fetch.
	 * @return StatusValue A good status will have a list of account names or empty list if none were found;
	 *  a bad status will have the relevant permission error encountered
	 */
	public function get( string $ip, Authority $authority, bool $shouldLog = true, ?int $limit = null ): StatusValue {
		// TODO: Use a trait for permissions, to avoid duplication with
		// AbstractTemporaryAccountHandler::checkPermissions
		$status = $this->checkPermissions( $authority );

		if ( !$status->isGood() ) {
			return $status;
		}

		if ( $shouldLog ) {
			$this->jobQueueGroup->push(
				LogTemporaryAccountAccessJob::newSpec(
					$authority->getUser(),
					$ip,
					TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP
				)
			);
		}
		// Normalize the IP into the same format that cuc_ip uses
		$ip = IPUtils::sanitizeIP( $ip );

		// If no limit is supplied, set the default to CheckUserMaximumRowCount.
		if ( !$limit ) {
			$limit = $this->serviceOptions->get( 'CheckUserMaximumRowCount' );
		} else {
			// The limit is the smaller of the user-provided limit parameter and the maximum row count.
			$limit = min( $limit, $this->serviceOptions->get( 'CheckUserMaximumRowCount' ) );
		}

		// T327906: 'cuc_timestamp' is selected to satisfy a Postgres requirement
		// where all ORDER BY fields must be present in SELECT list.

		$dbr = $this->connectionProvider->getReplicaDatabase();
		$rows = $dbr->newSelectQueryBuilder()
			->fields( [ 'actor_name', 'timestamp' => 'MAX(cuc_timestamp)' ] )
			->table( self::CHANGES_TABLE )
			->join( 'actor', null, 'actor_id=cuc_actor' )
			->where( [ 'cuc_ip' => $ip ] )
			->where( $this->tempUserConfig->getMatchCondition( $dbr, 'actor_name', IExpression::LIKE ) )
			->groupBy( 'actor_name' )
			->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchResultSet();

		$accounts = [];
		$canSeeHidden = $authority->isAllowed( 'hideuser' );
		foreach ( $rows as $row ) {
			$account = $row->actor_name;

			// Don't return hidden accounts to authorities who cannot view them
			if ( $canSeeHidden || !$this->userFactory->newFromName( $account )->isHidden() ) {
				$accounts[] = $account;
			}
		}
		return StatusValue::newGood( $accounts );
	}

	private function checkPermissions( Authority $authority ): StatusValue {
		if ( !$authority->isNamed() ) {
			// n.b. Here and for checkuser-rest-access-denied-blocked-user, the message
			// key specifies "REST", but the message is generic enough to reuse in this context.
			return StatusValue::newFatal( 'checkuser-rest-access-denied' );
		}
		if (
			!$this->permissionManager->userHasRight(
				$authority->getUser(),
				'checkuser-temporary-account-no-preference'
			) &&
			(
				!$this->permissionManager->userHasRight(
					$authority->getUser(),
					'checkuser-temporary-account'
				) ||
				!$this->userOptionsLookup->getOption(
					$authority->getUser(),
					'checkuser-temporary-account-enable'
				)
			)
		) {
			return StatusValue::newFatal( 'checkuser-rest-access-denied' );
		}

		if ( $authority->getBlock() ) {
			return StatusValue::newFatal( 'checkuser-rest-access-denied-blocked-user' );
		}
		return StatusValue::newGood();
	}

}
