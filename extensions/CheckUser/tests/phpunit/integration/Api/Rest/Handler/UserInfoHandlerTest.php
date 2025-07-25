<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\CheckUser\Api\Rest\Handler\UserInfoHandler;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\Message\MessageValue;

/**
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\UserInfoHandler
 * @group Database
 */
class UserInfoHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	private static Authority $loggedInPerformer;
	private static Authority $loggedOutPerformer;

	protected function setUp(): void {
		parent::setUp();

		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
	}

	private function getObjectUnderTest(): UserInfoHandler {
		$services = $this->getServiceContainer();
		return new UserInfoHandler(
			$services->get( 'CheckUserUserInfoCardService' ),
			$services->getUserFactory()
		);
	}

	public function testLoggedOutAccessIsDenied() {
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'checkuser-rest-access-denied' ), 401 )
		);
		$this->executeHandler(
			$this->getObjectUnderTest(),
			new RequestData( [
				'pathParams' => [ 'id' => 1 ],
			] )
		);
	}

	public function testAccessInvalidUserId() {
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'checkuser-rest-userinfo-user-not-found' ), 404 )
		);
		$this->executeHandler(
			$this->getObjectUnderTest(),
			new RequestData( [
				'pathParams' => [ 'id' => 10000000 ],
			] ),
			[],
			[],
			[],
			[],
			$this->getTestUser()->getUser()
		);
	}

	public function testGetDataForUser() {
		$user = $this->getTestUser()->getUser();
		$this->editPage( 'Test', 'Test', 'Test', NS_MAIN, $user );
		$editCount = $user->getEditCount();
		$response = $this->executeHandler(
			$this->getObjectUnderTest(),
			new RequestData( [
				'pathParams' => [ 'id' => $user->getId() ],
			] ),
			[],
			[],
			[],
			[],
			$user
		);
		$this->assertEquals( json_decode( $response->getBody()->getContents(), true )['totalEditCount'], $editCount );
	}

	public function testRateLimiting() {
		$this->mergeMwGlobalArrayValue( 'wgRateLimits', [ 'checkuser-userinfo' => [
			'user' => [ 1, 86400 ],
		] ] );
		$user = $this->getTestUser()->getUser();
		$this->editPage( 'Test', 'Test', 'Test', NS_MAIN, $user );
		$editCount = $user->getEditCount();
		$response = $this->executeHandler(
			$this->getObjectUnderTest(),
			new RequestData( [
				'pathParams' => [ 'id' => $user->getId() ],
			] ),
			[],
			[],
			[],
			[],
			$user
		);
		$this->assertEquals( json_decode( $response->getBody()->getContents(), true )['totalEditCount'], $editCount );
		$this->expectExceptionObject( new HttpException( 'Too many requests to user info data', 429 ) );
		$this->executeHandler(
			$this->getObjectUnderTest(),
			new RequestData( [
				'pathParams' => [ 'id' => $user->getId() ],
			] ),
			[],
			[],
			[],
			[],
			$user
		);
	}

}
