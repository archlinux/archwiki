<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\Block\BlockManager;
use MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\Config;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\ParamValidator\TypeDef\ArrayDef;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\PreferencesFactory;
use MediaWiki\Rest\Response;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserNameUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\ReadOnlyMode;

class BatchTemporaryAccountHandler extends AbstractTemporaryAccountHandler {

	use TemporaryAccountNameTrait;
	use TemporaryAccountRevisionTrait;
	use TemporaryAccountLogTrait;
	use TemporaryAccountAutoRevealTrait;

	private PreferencesFactory $preferencesFactory;
	private RevisionStore $revisionStore;

	public function __construct(
		Config $config,
		JobQueueGroup $jobQueueGroup,
		PermissionManager $permissionManager,
		PreferencesFactory $preferencesFactory,
		UserNameUtils $userNameUtils,
		IConnectionProvider $dbProvider,
		ActorStore $actorStore,
		BlockManager $blockManager,
		RevisionStore $revisionStore,
		CheckUserPermissionManager $checkUserPermissionsManager,
		ReadOnlyMode $readOnlyMode
	) {
		parent::__construct(
			$config,
			$jobQueueGroup,
			$permissionManager,
			$userNameUtils,
			$dbProvider,
			$actorStore,
			$blockManager,
			$checkUserPermissionsManager,
			$readOnlyMode
		);
		$this->preferencesFactory = $preferencesFactory;
		$this->revisionStore = $revisionStore;
	}

	/**
	 * @inheritDoc
	 */
	public function run( $identifier = null ): Response {
		return parent::run( $identifier );
	}

	/**
	 * @inheritDoc
	 */
	public function makeLog( $identifier ) {
		$body = $this->getValidatedBody();

		foreach ( $body['users'] ?? [] as $username => $params ) {
			$this->jobQueueGroup->push(
				LogTemporaryAccountAccessJob::newSpec(
					$this->getAuthority()->getUser(),
					$username,
					$this->getLogType()
				)
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getResults( $identifier ): array {
		$results = [];

		$dbr = $this->getConnectionProvider()->getReplicaDatabase();
		$body = $this->getValidatedBody();

		foreach ( $body['users'] ?? [] as $username => $params ) {
			$results[$username] = $this->getData( [
				$this->getTemporaryAccountActorId( $username ),
				$params['revIds'] ?? [],
				$params['logIds'] ?? [],
				$params['lastUsedIp'] ?? false,
			], $dbr );
		}

		$this->addAutoRevealStatusToResults( $results );

		return $results;
	}

	/**
	 * @inheritDoc
	 */
	protected function getData( $identifier, IReadableDatabase $dbr ): array {
		[ $actorId, $revIds, $logIds, $lastUsedIp ] = $identifier;

		return [
			'revIps' => count( $revIds ) > 0
				? $this->getRevisionsIps( $actorId, $revIds, $dbr )
				: null,
			'logIps' => count( $logIds ) > 0
				? $this->getLogIps( $actorId, $logIds, $dbr )
				: null,
			'lastUsedIp' => $lastUsedIp
				? ( $this->getActorIps( $actorId, 1, $dbr )[0] ?? null )
				: null,
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyParamSettings(): array {
		return [
			'users' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				ArrayDef::PARAM_SCHEMA => ArrayDef::makeMapSchema(
					ArrayDef::makeObjectSchema( [
						'revIds' => ArrayDef::makeListSchema( 'string' ),
						'logIds' => ArrayDef::makeListSchema( 'string' ),
						'lastUsedIp' => 'boolean',
					] )
				),
			]
		] + parent::getBodyParamSettings();
	}

	/**
	 * @inheritDoc
	 */
	protected function getLogType(): string {
		return TemporaryAccountLogger::ACTION_VIEW_IPS;
	}

	/**
	 * @inheritDoc
	 */
	protected function getActorStore(): ActorStore {
		return $this->actorStore;
	}

	/**
	 * @inheritDoc
	 */
	protected function getBlockManager(): BlockManager {
		return $this->blockManager;
	}

	/**
	 * @inheritDoc
	 */
	protected function getConnectionProvider(): IConnectionProvider {
		return $this->dbProvider;
	}

	/**
	 * @inheritDoc
	 */
	protected function getPermissionManager(): PermissionManager {
		return $this->permissionManager;
	}

	/**
	 * @inheritDoc
	 */
	protected function getRevisionStore(): RevisionStore {
		return $this->revisionStore;
	}

	/**
	 * @inheritDoc
	 */
	protected function getPreferencesFactory(): PreferencesFactory {
		return $this->preferencesFactory;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUserNameUtils(): UserNameUtils {
		return $this->userNameUtils;
	}
}
