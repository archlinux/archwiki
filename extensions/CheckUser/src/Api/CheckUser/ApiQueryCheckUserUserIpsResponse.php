<?php

namespace MediaWiki\CheckUser\Api\CheckUser;

use MediaWiki\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Config\Config;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use MessageLocalizer;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class ApiQueryCheckUserUserIpsResponse extends ApiQueryCheckUserAbstractResponse {

	private UserIdentityLookup $userIdentityLookup;

	/**
	 * @param ApiQueryCheckUser $module
	 * @param IConnectionProvider $dbProvider
	 * @param Config $config
	 * @param MessageLocalizer $messageLocalizer
	 * @param CheckUserLogService $checkUserLogService
	 * @param UserNameUtils $userNameUtils
	 * @param CheckUserLookupUtils $checkUserLookupUtils
	 * @param UserIdentityLookup $userIdentityLookup
	 *
	 * @internal Use CheckUserApiResponseFactory::newFromRequest() instead
	 */
	public function __construct(
		ApiQueryCheckUser $module,
		IConnectionProvider $dbProvider,
		Config $config,
		MessageLocalizer $messageLocalizer,
		CheckUserLogService $checkUserLogService,
		UserNameUtils $userNameUtils,
		CheckUserLookupUtils $checkUserLookupUtils,
		UserIdentityLookup $userIdentityLookup
	) {
		parent::__construct(
			$module, $dbProvider, $config, $messageLocalizer,
			$checkUserLogService, $userNameUtils, $checkUserLookupUtils
		);
		$this->userIdentityLookup = $userIdentityLookup;
	}

	/** @inheritDoc */
	public function getRequestType(): string {
		return 'userips';
	}

	/** @inheritDoc */
	public function getResponseData(): array {
		$res = $this->performQuery( __METHOD__ );

		$ips = [];
		foreach ( $res as $row ) {
			$timestamp = ConvertibleTimestamp::convert( TS_ISO_8601, $row->timestamp );
			$ip = strval( $row->ip );

			if ( !isset( $ips[$ip] ) ) {
				$ips[$ip] = [ 'end' => $timestamp, 'editcount' => 1 ];
			} else {
				$ips[$ip]['start'] = $timestamp;
				$ips[$ip]['editcount']++;
			}
		}

		$resultIPs = [];
		foreach ( $ips as $ip => $data ) {
			$data['address'] = $ip;
			$resultIPs[] = $data;
		}

		$this->checkUserLogService->addLogEntry(
			$this->module->getUser(), 'userips', 'user', $this->target, $this->reason,
			$this->userIdentityLookup->getUserIdentityByName( $this->target )->getId()
		);
		return $resultIPs;
	}

	/** @inheritDoc */
	protected function validateTargetAndGenerateTargetConditions( string $table ): IExpression {
		$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $this->target );
		if ( $userIdentity && $userIdentity->getId() ) {
			$userId = $userIdentity->getId();
		} else {
			$this->module->dieWithError( [ 'nosuchusershort', wfEscapeWikiText( $this->target ) ], 'nosuchuser' );
		}
		return $this->dbr->expr( 'actor_user', '=', $userId );
	}

	/** @inheritDoc */
	protected function getPartialQueryBuilderForCuChanges(): SelectQueryBuilder {
		$queryBuilder = $this->dbr->newSelectQueryBuilder()
			->select( [ 'timestamp' => 'cuc_timestamp', 'ip' => 'cuc_ip' ] )
			->from( 'cu_changes' )
			->join( 'actor', null, 'actor_id=cuc_actor' )
			->where( $this->dbr->expr( 'cuc_timestamp', '>', $this->timeCutoff ) );
		return $queryBuilder;
	}

	/** @inheritDoc */
	protected function getPartialQueryBuilderForCuLogEvent(): SelectQueryBuilder {
		return $this->dbr->newSelectQueryBuilder()
			->select( [ 'timestamp' => 'cule_timestamp', 'ip' => 'cule_ip' ] )
			->from( 'cu_log_event' )
			->join( 'actor', null, 'actor_id=cule_actor' )
			->where( $this->dbr->expr( 'cule_timestamp', '>', $this->timeCutoff ) );
	}

	/** @inheritDoc */
	protected function getPartialQueryBuilderForCuPrivateEvent(): SelectQueryBuilder {
		return $this->dbr->newSelectQueryBuilder()
			->select( [ 'timestamp' => 'cupe_timestamp', 'ip' => 'cupe_ip' ] )
			->from( 'cu_private_event' )
			->join( 'actor', null, 'actor_id=cupe_actor' )
			->where( $this->dbr->expr( 'cupe_timestamp', '>', $this->timeCutoff ) );
	}
}
