<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\Extension\CheckUser\Api\Rest\Handler\UserInfoBlockedHandler;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\Message\MessageValue;

/**
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\UserInfoBlockedHandler
 * @group Database
 */
class UserInfoBlockedHandlerTest extends MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	private UserInfoCardBlockStatusCache $blockStatusCache;
	private UserInfoBlockedHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		$services = $this->getServiceContainer();
		$this->blockStatusCache = $this->createMock( UserInfoCardBlockStatusCache::class );
		$this->handler = new UserInfoBlockedHandler(
			$services->getUserFactory(),
			$this->blockStatusCache
		);
	}

	public function testAccessInvalidUserId() {
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'checkuser-rest-userinfo-user-not-found' ), 404 )
		);

		$this->executeHandler(
			$this->handler,
			new RequestData( [
				'pathParams' => [
					'name' => 'UserThatDefinitelyDoesNotExist',
				],
			] ),
			[],
			[],
			[],
			[],
			$this->getTestUser()->getUser()
		);
	}

	public function testReturnsFalseOnNonBlockedUser() {
		$user = $this->getMutableTestUser();

		$response = $this->executeHandler(
			$this->handler,
			new RequestData( [
				'pathParams' => [
					'name' => $user->getUser()->getName(),
				],
			] ),
			[],
			[],
			[],
			[],
			$user->getAuthority()
		);

		$payload = json_decode( $response->getBody()->getContents(), true );
		$this->assertFalse( $payload['shouldShowBlockedIcon'] );
	}

	public function testReturnsTrueOnNonBlockedUser() {
		$user = $this->getMutableTestUser();
		$this->blockStatusCache->method( 'isIndefinitelyBlockedOrLocked' )->willReturn( true );

		$response = $this->executeHandler(
			$this->handler,
			new RequestData( [
				'pathParams' => [
					'name' => $user->getUser()->getName(),
				],
			] ),
			[],
			[],
			[],
			[],
			$user->getAuthority()
		);

		$payload = json_decode( $response->getBody()->getContents(), true );
		$this->assertTrue( $payload['shouldShowBlockedIcon'] );
	}
}
