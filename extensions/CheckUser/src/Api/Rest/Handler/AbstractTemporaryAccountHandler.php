<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\Block\BlockManager;
use MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\Config;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\ReadOnlyMode;

abstract class AbstractTemporaryAccountHandler extends SimpleHandler {

	use TokenAwareHandlerTrait;

	protected Config $config;
	protected JobQueueGroup $jobQueueGroup;
	protected PermissionManager $permissionManager;
	protected UserNameUtils $userNameUtils;
	protected IConnectionProvider $dbProvider;
	protected ActorStore $actorStore;
	protected BlockManager $blockManager;
	private CheckUserPermissionManager $checkUserPermissionsManager;
	private ReadOnlyMode $readOnlyMode;

	public function __construct(
		Config $config,
		JobQueueGroup $jobQueueGroup,
		PermissionManager $permissionManager,
		UserNameUtils $userNameUtils,
		IConnectionProvider $dbProvider,
		ActorStore $actorStore,
		BlockManager $blockManager,
		CheckUserPermissionManager $checkUserPermissionsManager,
		ReadOnlyMode $readOnlyMode
	) {
		$this->config = $config;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->permissionManager = $permissionManager;
		$this->userNameUtils = $userNameUtils;
		$this->dbProvider = $dbProvider;
		$this->actorStore = $actorStore;
		$this->blockManager = $blockManager;
		$this->checkUserPermissionsManager = $checkUserPermissionsManager;
		$this->readOnlyMode = $readOnlyMode;
	}

	/**
	 * Check if the performer has the right to use this API, and throw if not.
	 */
	protected function checkPermissions() {
		if ( !$this->getAuthority()->isNamed() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-access-denied' ),
				401
			);
		}

		$permStatus = $this->checkUserPermissionsManager->canAccessTemporaryAccountIPAddresses(
			$this->getAuthority()
		);
		if ( !$permStatus->isGood() ) {
			if ( $permStatus->getBlock() ) {
				throw new LocalizedHttpException(
					new MessageValue( 'checkuser-rest-access-denied-blocked-user' ),
					403
				);
			}

			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-access-denied' ),
				403
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function run( $identifier ): Response {
		$this->checkPermissions();

		$readOnlyReason = $this->readOnlyMode->getReason();
		if ( $readOnlyReason ) {
			throw new LocalizedHttpException(
				new MessageValue( 'readonlytext', [ $readOnlyReason ] ),
				503
			);
		}

		$results = $this->getResults( $identifier );

		$this->makeLog( $identifier );

		$maxAge = $this->config->get( 'CheckUserTemporaryAccountMaxAge' );
		$response = $this->getResponseFactory()->createJson( $results );
		$response->setHeader( 'Cache-Control', "private, max-age=$maxAge" );
		return $response;
	}

	/**
	 * Enqueue a job to log the reveal that was performed.
	 *
	 * @param int|string $identifier
	 */
	public function makeLog( $identifier ) {
		$this->jobQueueGroup->push(
			LogTemporaryAccountAccessJob::newSpec(
				$this->getAuthority()->getUser(),
				$identifier,
				$this->getLogType()
			)
		);
	}

	/**
	 * @param string $identifier
	 * @return array associated IP addresses or temporary accounts
	 */
	abstract protected function getResults( $identifier ): array;

	/**
	 * @param int|string|array $identifier
	 * @param IReadableDatabase $dbr
	 * @return array associated IP addresses or temporary accounts
	 */
	abstract protected function getData( $identifier, IReadableDatabase $dbr ): array;

	/**
	 * @return string log type to record
	 */
	abstract protected function getLogType(): string;

	public function getBodyParamSettings(): array {
		return $this->getTokenParamDefinition();
	}

	/** @inheritDoc */
	public function validate( Validator $restValidator ) {
		parent::validate( $restValidator );
		$this->validateToken();
	}
}
