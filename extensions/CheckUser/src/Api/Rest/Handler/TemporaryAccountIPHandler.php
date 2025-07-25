<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\Block\BlockManager;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\Config\Config;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\ActorStore;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * Given an IP, return every known temporary account that has edited from it
 */
class TemporaryAccountIPHandler extends AbstractTemporaryAccountIPHandler {

	private CheckUserTemporaryAccountsByIPLookup $checkUserTemporaryAccountsByIPLookup;

	public function __construct(
		Config $config,
		JobQueueGroup $jobQueueGroup,
		PermissionManager $permissionManager,
		UserNameUtils $userNameUtils,
		IConnectionProvider $dbProvider,
		ActorStore $actorStore,
		BlockManager $blockManager,
		TempUserConfig $tempUserConfig,
		CheckUserTemporaryAccountsByIPLookup $checkUserTemporaryAccountsByIPLookup,
		CheckUserPermissionManager $checkUserPermissionsManager,
		ReadOnlyMode $readOnlyMode
	) {
		parent::__construct(
			$config, $jobQueueGroup, $permissionManager, $userNameUtils, $dbProvider, $actorStore,
			$blockManager, $tempUserConfig, $checkUserPermissionsManager, $readOnlyMode
		);
		$this->checkUserTemporaryAccountsByIPLookup = $checkUserTemporaryAccountsByIPLookup;
	}

	/**
	 * @inheritDoc
	 */
	protected function getData( $ip, IReadableDatabase $dbr ): array {
		$status = $this->checkUserTemporaryAccountsByIPLookup->get(
			$ip,
			$this->getAuthority(),
			false,
			$this->getValidatedParams()['limit']
		);
		if ( $status->isGood() ) {
			return $status->getValue();
		}
		return [];
	}
}
