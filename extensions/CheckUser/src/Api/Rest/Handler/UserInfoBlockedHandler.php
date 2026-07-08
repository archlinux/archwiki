<?php

namespace MediaWiki\Extension\CheckUser\Api\Rest\Handler;

use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserFactory;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handler for GET requests to /checkuser/v0/userinfo/blocked/{name}
 * Intended to be called by the ext.checkUser.userInfoCard/UserCardButton.vue component.
 */
class UserInfoBlockedHandler extends SimpleHandler {
	public function __construct(
		private readonly UserFactory $userFactory,
		private readonly UserInfoCardBlockStatusCache $blockStatusCache,
	) {
	}

	/** @throws LocalizedHttpException */
	public function run( string $username ): array {
		$user = $this->userFactory->newFromName( $username );

		if (
			$user === null ||
			!$user->getId() ||
			( $user->isHidden() && !$this->getAuthority()->isAllowed( 'hideuser' ) )
		) {
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-userinfo-user-not-found' ),
				404
			);
		}

		return [
			'shouldShowBlockedIcon' => $this->blockStatusCache->isIndefinitelyBlockedOrLocked( $user->getName() ),
		];
	}

	/** @inheritDoc */
	public function getParamSettings(): array {
		return [
			'name' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
