<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\Block\BlockManager;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\Config;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\PreferencesFactory;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Message\DataMessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\ReadOnlyMode;

class TemporaryAccountRevisionHandler extends AbstractTemporaryAccountNameHandler {

	use TemporaryAccountRevisionTrait;

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
			$preferencesFactory,
			$userNameUtils,
			$dbProvider,
			$actorStore,
			$blockManager,
			$checkUserPermissionsManager,
			$readOnlyMode
		);
		$this->revisionStore = $revisionStore;
	}

	/**
	 * @inheritDoc
	 */
	protected function getData( $actorId, IReadableDatabase $dbr ): array {
		$ids = $this->getValidatedParams()['ids'];
		if ( !count( $ids ) ) {
			throw new LocalizedHttpException(
				DataMessageValue::new( 'paramvalidator-missingparam', [], 'missingparam' )
					->plaintextParams( "ids" ),
				400,
				[
					'error' => 'parameter-validation-failed',
					'name' => 'ids',
					'value' => '',
					'failureCode' => "missingparam",
					'failureData' => null,
				]
			);
		}

		return [ 'ips' => $this->getRevisionsIps( $actorId, $ids, $dbr ) ];
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		$settings = parent::getParamSettings();
		$settings['ids'] = [
			self::PARAM_SOURCE => 'path',
			ParamValidator::PARAM_TYPE => 'integer',
			ParamValidator::PARAM_REQUIRED => true,
			ParamValidator::PARAM_ISMULTI => true,
		];
		return $settings;
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
	protected function getConnectionProvider(): IConnectionProvider {
		return $this->dbProvider;
	}

	/**
	 * @inheritDoc
	 */
	protected function getRevisionStore(): RevisionStore {
		return $this->revisionStore;
	}
}
