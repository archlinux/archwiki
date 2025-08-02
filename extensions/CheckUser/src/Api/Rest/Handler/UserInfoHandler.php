<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\CheckUser\Services\CheckUserUserInfoCardService;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\User\UserFactory;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * REST endpoint for /checkuser/v0/userinfo/{id} to return data for the UserInfoCard feature (T384725)
 */
class UserInfoHandler extends SimpleHandler {

	use TokenAwareHandlerTrait;

	private CheckUserUserInfoCardService $userInfoCardService;
	private UserFactory $userFactory;

	public function __construct(
		CheckUserUserInfoCardService $userInfoCardService,
		UserFactory $userFactory
	) {
		$this->userInfoCardService = $userInfoCardService;
		$this->userFactory = $userFactory;
	}

	public function run( int $id ): Response {
		$this->validateToken();
		if ( !$this->getAuthority()->isNamed() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-access-denied' ),
				401
			);
		}
		$performingUser = $this->userFactory->newFromUserIdentity( $this->getAuthority()->getUser() );
		if ( $performingUser->pingLimiter( 'checkuser-userinfo' ) ) {
			throw new HttpException( 'Too many requests to user info data', 429 );
		}
		$user = $this->userFactory->newFromId( $id );
		$user->load();
		if ( !$user->getId() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-userinfo-user-not-found' ),
				404
			);
		}
		$userInfo = $this->userInfoCardService->getUserInfo( $user );

		return $this->getResponseFactory()->createJson( $userInfo );
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

	public function getBodyParamSettings(): array {
		return $this->getTokenParamDefinition();
	}
}
