<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\CheckUser\Api\Rest\Handler\UserInfoHandler;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Rest\Handler\SessionHelperTestTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\Message\MessageValue;

/**
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\UserInfoHandler
 * @group Database
 */
class UserInfoHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;
	use SessionHelperTestTrait;

	private static Authority $loggedInPerformer;
	private static Authority $loggedOutPerformer;
	private static array $postRequestParams = [
		'method' => 'POST',
		'headers' => [
			'Content-Type' => 'application/json',
		],
	];

	protected function setUp(): void {
		parent::setUp();

		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
		// The GlobalContributionsPager used in CheckuserUserInfoCardService requires CentralAuth
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
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
			new RequestData( self::$postRequestParams + [
				'bodyContents' => json_encode( [
					'username' => $this->getTestUser()->getUser()->getName(),
				] ),
			] )
		);
	}

	public function testAccessInvalidUserId() {
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'checkuser-rest-userinfo-user-not-found' ), 404 )
		);
		$this->executeHandler(
			$this->getObjectUnderTest(),
			new RequestData( self::$postRequestParams + [
				'bodyContents' => json_encode( [
					'username' => 'non-existing user',
				] ),
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
			new RequestData( self::$postRequestParams + [
				'bodyContents' => json_encode( [
					'username' => $user->getName(),
				] ),
			] ),
			[],
			[],
			[],
			[],
			$user
		);

		$payload = json_decode( $response->getBody()->getContents(), true );
		$this->assertEquals( $editCount, $payload['totalEditCount'] );
	}

	public function testRateLimiting() {
		$this->mergeMwGlobalArrayValue( 'wgRateLimits', [ 'checkuser-userinfo' => [
			'user' => [ 1, 86400 ],
		] ] );

		$session = $this->getSession( true );
		$user = $this->getTestUser()->getUser();
		$requestParams = self::$postRequestParams + [
			'bodyContents' => json_encode( [
				'username' => $user->getName(),
			] ),
		];

		$this->editPage( 'Test', 'Test', 'Test', NS_MAIN, $user );
		$editCount = $user->getEditCount();
		$response = $this->executeHandler(
			$this->getObjectUnderTest(),
			new RequestData( $requestParams ),
			[],
			[],
			[],
			[],
			$user,
			$session
		);

		$payload = json_decode( $response->getBody()->getContents(), true );
		$this->assertEquals( $editCount, $payload[ 'totalEditCount' ] );

		$this->expectExceptionObject(
			new HttpException( 'Too many requests to user info data', 429 )
		);
		$this->executeHandler(
			$this->getObjectUnderTest(),
			new RequestData( $requestParams ),
			[],
			[],
			[],
			[],
			$user,
			$session
		);
	}
}
