<?php

namespace MediaWiki\Extension\CheckUser\Api\Rest\Handler;

use MediaWiki\Block\BlockManager;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
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

	public function __construct(
		protected readonly Config $config,
		protected readonly JobQueueGroup $jobQueueGroup,
		protected readonly PermissionManager $permissionManager,
		protected readonly UserNameUtils $userNameUtils,
		protected readonly IConnectionProvider $dbProvider,
		protected readonly ActorStore $actorStore,
		protected readonly BlockManager $blockManager,
		private readonly CheckUserPermissionManager $checkUserPermissionsManager,
		private readonly ReadOnlyMode $readOnlyMode,
	) {
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
