<?php

namespace MediaWiki\Extension\CheckUser\Api\CheckUser;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\Extension\CheckUser\Services\CheckUserLogService;
use MediaWiki\Extension\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class ApiQueryCheckUserUserIpsResponse extends ApiQueryCheckUserAbstractResponse {

	/**
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
		private readonly UserIdentityLookup $userIdentityLookup,
	) {
		parent::__construct(
			$module,
			$dbProvider,
			$config,
			$messageLocalizer,
			$checkUserLogService,
			$userNameUtils,
			$checkUserLookupUtils
		);
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
			$ip = IPUtils::formatHex( $row->ip_hex );

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
			$this->module->getUser(),
			'userips',
			'user',
			$this->target,
			$this->reason,
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
			->select( [ 'timestamp' => 'cuc_timestamp', 'ip_hex' => 'cuc_ip_hex' ] )
			->from( 'cu_changes' )
			->join( 'actor', null, 'actor_id=cuc_actor' )
			->where( $this->dbr->expr( 'cuc_ip_hex', '!=', null ) )
			->where( $this->dbr->expr( 'cuc_timestamp', '>', $this->timeCutoff ) );
		return $queryBuilder;
	}

	/** @inheritDoc */
	protected function getPartialQueryBuilderForCuLogEvent(): SelectQueryBuilder {
		return $this->dbr->newSelectQueryBuilder()
			->select( [ 'timestamp' => 'cule_timestamp', 'ip_hex' => 'cule_ip_hex' ] )
			->from( 'cu_log_event' )
			->join( 'actor', null, 'actor_id=cule_actor' )
			->where( $this->dbr->expr( 'cule_ip_hex', '!=', null ) )
			->where( $this->dbr->expr( 'cule_timestamp', '>', $this->timeCutoff ) );
	}

	/** @inheritDoc */
	protected function getPartialQueryBuilderForCuPrivateEvent(): SelectQueryBuilder {
		return $this->dbr->newSelectQueryBuilder()
			->select( [ 'timestamp' => 'cupe_timestamp', 'ip_hex' => 'cupe_ip_hex' ] )
			->from( 'cu_private_event' )
			->join( 'actor', null, 'actor_id=cupe_actor' )
			->where( $this->dbr->expr( 'cupe_ip_hex', '!=', null ) )
			->where( $this->dbr->expr( 'cupe_timestamp', '>', $this->timeCutoff ) );
	}
}
