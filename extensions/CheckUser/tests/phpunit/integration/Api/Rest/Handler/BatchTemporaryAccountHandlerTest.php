<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\CheckUser\Api\Rest\Handler\BatchTemporaryAccountHandler;
use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\BatchTemporaryAccountHandler
 */
class BatchTemporaryAccountHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;
	use MockServiceDependenciesTrait;
	use TempUserTestTrait;

	public function testGetData() {
		$this->enableAutoCreateTempUser();

		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );

		$actorStore = $this->createMock( ActorStore::class );
		$actorStore->method( 'findActorIdByName' )
			->willReturn( 12345 );
		$actorStore->method( 'getUserIdentityByName' )
			->willReturn( new UserIdentityValue( 12345, '~12345' ) );

		$services = $this->getServiceContainer();
		$handler = $this->getMockBuilder( BatchTemporaryAccountHandler::class )
			->onlyMethods( [ 'getRevisionsIps', 'getLogIps', 'getActorIps' ] )
			->setConstructorArgs( [
				$services->getMainConfig(),
				$services->getJobQueueGroup(),
				$services->getPermissionManager(),
				$services->getPreferencesFactory(),
				$services->getUserNameUtils(),
				$services->getConnectionProvider(),
				$actorStore,
				$services->getBlockManager(),
				$services->getRevisionStore(),
				$checkUserPermissionManager,
				$services->getReadOnlyMode()
			] )
			->getMock();
		$handler->method( 'getRevisionsIps' )
			->with( 12345, [ 1 ] )
			->willReturn( [ 1 => '1.2.3.4' ] );
		$handler->method( 'getLogIps' )
			->with( 12345, [ 1 ] )
			->willReturn( [ 1 => '5.6.7.8' ] );
		$handler->method( 'getActorIps' )
			->with( 12345, 1 )
			->willReturn( [ '9.8.7.6' ] );

		$data = $this->executeHandlerAndGetBodyData(
			$handler,
			new RequestData(),
			[],
			[],
			[],
			[
				'users' => [
					'~12345' => [
						'revIds' => [ 1 ],
						'logIds' => [ 1 ],
						'lastUsedIp' => true,
					],
				],
			],
			$this->getTestUser()->getAuthority()
		);

		$this->assertSame( [
			'~12345' => [
				'revIps' => [ 1 => '1.2.3.4' ],
				'logIps' => [ 1 => '5.6.7.8' ],
				'lastUsedIp' => '9.8.7.6',
			],
			'autoReveal' => false,
		], $data );
	}
}
