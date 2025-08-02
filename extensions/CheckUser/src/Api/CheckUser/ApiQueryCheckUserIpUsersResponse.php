<?php

namespace MediaWiki\CheckUser\Api\CheckUser;

use MediaWiki\Api\ApiResult;
use MediaWiki\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Config\Config;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNameUtils;
use MessageLocalizer;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class ApiQueryCheckUserIpUsersResponse extends ApiQueryCheckUserAbstractResponse {

	private UserFactory $userFactory;
	private MessageLocalizer $messageLocalizer;

	public function __construct(
		ApiQueryCheckUser $module,
		IConnectionProvider $dbProvider,
		Config $config,
		MessageLocalizer $messageLocalizer,
		CheckUserLogService $checkUserLogService,
		UserNameUtils $userNameUtils,
		CheckUserLookupUtils $checkUserLookupUtils,
		UserFactory $userFactory
	) {
		parent::__construct(
			$module, $dbProvider, $config, $messageLocalizer, $checkUserLogService,
			$userNameUtils, $checkUserLookupUtils
		);
		$this->userFactory = $userFactory;
		$this->messageLocalizer = $messageLocalizer;
	}

	/** @inheritDoc */
	public function getRequestType(): string {
		return 'ipusers';
	}

	/** @inheritDoc */
	public function getResponseData(): array {
		$res = $this->performQuery( __METHOD__ );

		$users = [];
		foreach ( $res as $row ) {
			$user = $row->user_text;
			$ip = $row->ip;
			$agent = $row->agent;

			// Use the IP as the $row->user_text if the actor ID is NULL and the IP is not NULL (T353953).
			if ( $row->actor === null && $ip ) {
				$user = $ip;
			}

			if ( !isset( $users[$user] ) ) {
				$users[$user] = [
					'end' => ConvertibleTimestamp::convert( TS_ISO_8601, $row->timestamp ),
					'editcount' => 1,
					'ips' => [ $ip ],
					'agents' => [ $agent ]
				];
			} else {
				$users[$user]['start'] = ConvertibleTimestamp::convert( TS_ISO_8601, $row->timestamp );
				$users[$user]['editcount']++;
				if ( !in_array( $ip, $users[$user]['ips'] ) ) {
					$users[$user]['ips'][] = $ip;
				}
				if ( !in_array( $agent, $users[$user]['agents'] ) ) {
					$users[$user]['agents'][] = $agent;
				}
			}
		}

		$resultUsers = [];
		foreach ( $users as $userName => $userData ) {
			// Hide the user name if it is hidden from the current authority.
			$user = $this->userFactory->newFromName( $userName );
			if ( $user !== null && $user->isHidden() && !$this->module->getUser()->isAllowed( 'hideuser' ) ) {
				// If the username is hidden from the current user, then hide the username in the results using the
				// 'rev-deleted-user' message.
				$userName = $this->messageLocalizer->msg( 'rev-deleted-user' )->text();
			}

			$userData['name'] = $userName;
			ApiResult::setIndexedTagName( $userData['ips'], 'ip' );
			ApiResult::setIndexedTagName( $userData['agents'], 'agent' );

			$resultUsers[] = $userData;
		}

		$logType = 'ipusers';
		if ( $this->xff === true ) {
			$logType .= '-xff';
		}
		$this->checkUserLogService->addLogEntry(
			$this->module->getUser(), $logType, 'ip', $this->target, $this->reason
		);
		return $resultUsers;
	}

	/** @inheritDoc */
	protected function validateTargetAndGenerateTargetConditions( string $table ): IExpression {
		$ipExpr = $this->checkUserLookupUtils->getIPTargetExpr( $this->target, $this->xff ?? false, $table );
		if ( $ipExpr === null ) {
			$this->module->dieWithError( 'apierror-badip', 'invalidip' );
		}
		return $ipExpr;
	}

	/** @inheritDoc */
	protected function getPartialQueryBuilderForCuChanges(): SelectQueryBuilder {
		$queryBuilder = $this->dbr->newSelectQueryBuilder()
			->select( [
				'timestamp' => 'cuc_timestamp',
				'ip' => 'cuc_ip',
				'agent' => 'cuc_agent',
				'user_text' => 'actor_name',
				'actor' => 'cuc_actor',
			] )
			->from( 'cu_changes' )
			->join( 'actor', null, 'actor_id=cuc_actor' )
			->where( $this->dbr->expr( 'cuc_timestamp', '>', $this->timeCutoff ) );
		return $queryBuilder;
	}

	/** @inheritDoc */
	protected function getPartialQueryBuilderForCuLogEvent(): SelectQueryBuilder {
		return $this->dbr->newSelectQueryBuilder()
			->select( [
				'timestamp' => 'cule_timestamp',
				'ip' => 'cule_ip',
				'agent' => 'cule_agent',
				'user_text' => 'actor_name',
				'actor' => 'cule_actor',
			] )
			->from( 'cu_log_event' )
			->join( 'actor', null, 'actor_id=cule_actor' )
			->where( $this->dbr->expr( 'cule_timestamp', '>', $this->timeCutoff ) );
	}

	/** @inheritDoc */
	protected function getPartialQueryBuilderForCuPrivateEvent(): SelectQueryBuilder {
		return $this->dbr->newSelectQueryBuilder()
			->select( [
				'timestamp' => 'cupe_timestamp',
				'ip' => 'cupe_ip',
				'agent' => 'cupe_agent',
				'user_text' => 'actor_name',
				'actor' => 'cupe_actor',
			] )
			->from( 'cu_private_event' )
			->leftJoin( 'actor', null, 'actor_id=cupe_actor' )
			->where( $this->dbr->expr( 'cupe_timestamp', '>', $this->timeCutoff ) );
	}
}
