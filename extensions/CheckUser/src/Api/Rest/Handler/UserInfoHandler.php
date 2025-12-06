<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\CheckUser\Services\CheckUserUserInfoCardService;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * REST endpoint for /checkuser/v0/userinfo to return data for the
 * UserInfoCard feature (T384725)
 */
class UserInfoHandler extends SimpleHandler {

	use TokenAwareHandlerTrait;

	private const USERNAME_PARAM_NAME = 'username';

	private CheckUserUserInfoCardService $userInfoCardService;
	private UserFactory $userFactory;

	public function __construct(
		CheckUserUserInfoCardService $userInfoCardService,
		UserFactory $userFactory
	) {
		$this->userInfoCardService = $userInfoCardService;
		$this->userFactory = $userFactory;
	}

	/**
	 * @throws LocalizedHttpException
	 * @throws HttpException
	 */
	public function run(): Response {
		$this->assertHasAccess();

		$body = $this->getValidatedBody() ?? [];
		$username = $body[ self::USERNAME_PARAM_NAME ];
		$user = $this->userFactory->newFromName( $username );

		if ( $user instanceof UserIdentity ) {
			$user->load();
		}

		// If the user exists, is hidden, and the authority doesn't have the `hideuser`
		// right, then pretend that the user doesn't exist
		if ( $user && $user->isHidden() && !$this->getAuthority()->isAllowed( 'hideuser' ) ) {
			$user = null;
		}

		if ( $user === null || !$user->getId() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-userinfo-user-not-found' ),
				404
			);
		}

		$userInfo = $this->userInfoCardService->getUserInfo(
			$this->getAuthority(),
			$user
		);

		return $this->getResponseFactory()->createJson( $userInfo );
	}

	public function getBodyParamSettings(): array {
		return $this->getTokenParamDefinition() + [
			self::USERNAME_PARAM_NAME => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * @throws HttpException
	 * @throws LocalizedHttpException
	 */
	private function assertHasAccess(): void {
		$this->validateToken();

		$authority = $this->getAuthority();
		if ( !$authority->isNamed() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-access-denied' ),
				401
			);
		}

		$performingUser = $this->userFactory->newFromUserIdentity(
			$authority->getUser()
		);

		if ( $performingUser->pingLimiter( 'checkuser-userinfo' ) ) {
			throw new HttpException( 'Too many requests to user info data', 429 );
		}
	}
}
