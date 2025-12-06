<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\Block\BlockManager;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup;
use MediaWiki\Config\Config;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserNameUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * Handle temporary account name validation for endpoints that take an account name as a parameter
 */
abstract class AbstractTemporaryAccountNameHandler extends AbstractTemporaryAccountHandler {

	use TemporaryAccountNameTrait;

	protected CheckUserTemporaryAccountAutoRevealLookup $autoRevealLookup;
	protected TemporaryAccountLoggerFactory $loggerFactory;

	public function __construct(
		Config $config,
		JobQueueGroup $jobQueueGroup,
		PermissionManager $permissionManager,
		UserNameUtils $userNameUtils,
		IConnectionProvider $dbProvider,
		ActorStore $actorStore,
		BlockManager $blockManager,
		CheckUserPermissionManager $checkUserPermissionsManager,
		CheckUserTemporaryAccountAutoRevealLookup $autoRevealLookup,
		TemporaryAccountLoggerFactory $loggerFactory,
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
		$this->autoRevealLookup = $autoRevealLookup;
		$this->loggerFactory = $loggerFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function getResults( $identifier ): array {
		$dbr = $this->getConnectionProvider()->getReplicaDatabase();
		$actorId = $this->getTemporaryAccountActorId( $identifier );

		$results = $this->getData( $actorId, $dbr );
		if ( $this->autoRevealLookup->isAutoRevealAvailable() ) {
			$results['autoReveal'] = $this->autoRevealLookup->isAutoRevealOn(
				$this->getAuthority()
			);
		}

		return $results;
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
	public function makeLog( $identifier ) {
		if ( $this->autoRevealLookup->isAutoRevealOn( $this->getAuthority() ) ) {
			$logger = $this->loggerFactory->getLogger();
			$performerName = $this->getAuthority()->getUser()->getName();

			$logger->logViewIPsWithAutoReveal(
				$performerName,
				$this->urlEncodeTitle( $identifier )
			);
			return;
		}

		parent::makeLog( $identifier );
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		return [
			'name' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getUserNameUtils(): UserNameUtils {
		return $this->userNameUtils;
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
	protected function getPermissionManager(): PermissionManager {
		return $this->permissionManager;
	}
}
