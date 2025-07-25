<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountIPHandler;
use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountIPHandler
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\AbstractTemporaryAccountHandler
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\AbstractTemporaryAccountIPHandler
 */
class TemporaryAccountIPHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;
	use MockAuthorityTrait;
	use MockServiceDependenciesTrait;
	use CheckUserTempUserTestTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->enableAutoCreateTempUser();
	}

	/**
	 * By default, services are mocked for a successful Response.
	 * They can be overridden via $options.
	 *
	 * @param array $options
	 * @return TemporaryAccountIPHandler
	 */
	private function getTemporaryAccountIPHandler( array $options = [] ): TemporaryAccountIPHandler {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );
		$this->setService( 'PermissionManager', $permissionManager );

		$users = [
			'~check-user-test-2024-1' => [ 'isHidden' => false ],
			'~check-user-test-2024-2' => [ 'isHidden' => false ],
			'~check-user-test-2024-20' => [ 'isHidden' => false ],
			'~check-user-test-2024-3' => [ 'isHidden' => false ],
			'~check-user-test-2024-30' => [ 'isHidden' => true ],
		];
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromName' )
			->willReturnMap( [
				[
					'~check-user-test-2024-1',
					UserFactory::RIGOR_VALID,
					$this->createActor( $users[ '~check-user-test-2024-1' ] )
				],
				[
					'~check-user-test-2024-2',
					UserFactory::RIGOR_VALID,
					$this->createActor( $users[ '~check-user-test-2024-2' ] )
				],
				[
					'~check-user-test-2024-20',
					UserFactory::RIGOR_VALID,
					$this->createActor( $users[ '~check-user-test-2024-20' ] )
				],
				[
					'~check-user-test-2024-3',
					UserFactory::RIGOR_VALID,
					$this->createActor( $users[ '~check-user-test-2024-3' ] )
				],
				[
					'~check-user-test-2024-30',
					UserFactory::RIGOR_VALID,
					$this->createActor( $users[ '~check-user-test-2024-30' ] )
				],
			] );
		$this->setService( 'UserFactory', $userFactory );

		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );

		$services = $this->getServiceContainer();
		return new TemporaryAccountIPHandler( ...array_values( array_merge(
			[
				'config' => $services->getMainConfig(),
				'jobQueueGroup' => $this->createMock( JobQueueGroup::class ),
				'permissionManager' => $permissionManager,
				'userNameUtils' => $services->getUserNameUtils(),
				'dbProvider' => $services->getDBLoadBalancerFactory(),
				'actorStore' => $services->getActorStore(),
				'blockManager' => $services->getBlockManager(),
				'tempUserConfig' => $services->getTempUserConfig(),
				'checkUserTemporaryAccountsByIPLookup' => $services->get(
					'CheckUserTemporaryAccountsByIPLookup'
				),
				'checkUserPermissionManager' => $checkUserPermissionManager,
				'readOnlyMode' => $services->getReadOnlyMode(),
			],
			$options
		) ) );
	}

	/**
	 * @param array $options
	 *
	 * @return Authority
	 */
	private function createActor( array $options = [] ): User {
		$user = $this->createMock( User::class );
		$user->method( 'isHidden' )->willReturn( $options[ 'isHidden' ] );
		return $user;
	}

	/**
	 * @param bool $canViewHidden
	 *
	 * @return Authority
	 */
	private function getAuthority( bool $canViewHidden = true ): Authority {
		if ( $canViewHidden ) {
			return $this->mockRegisteredUltimateAuthority();
		} else {
			return $this->mockRegisteredNullAuthority();
		}
	}

	private function getRequestData( array $options = [] ): RequestData {
		return new RequestData( [
			'pathParams' => [
				'ip' => $options['ip'] ?? '1.2.3.1',
			],
		] );
	}

	/**
	 * @dataProvider provideExecute
	 */
	public function testExecute( $expected, $options ) {
		$data = $this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountIPHandler(),
			$this->getRequestData( $options ),
			[],
			[],
			[],
			[],
			$this->getAuthority( $options[ 'hideuser' ] ?? true )
		);
		$this->assertArrayEquals(
			$expected,
			$data,
			true
		);
	}

	public static function provideExecute() {
		return [
			'No results' => [
				[],
				[
					'ip' => '1.2.3.4',
				],
			],
			'One temporary account' => [
				[
					'~check-user-test-2024-1',
				],
				[
					'ip' => '1.2.3.1',
				],
			],
			'Two temporary accounts' => [
				[
					'~check-user-test-2024-20',
					'~check-user-test-2024-2',
				],
				[
					'ip' => '1.2.3.2',
				],
			],
			'Hidden temporary account with view permission' => [
				[
					'~check-user-test-2024-30',
					'~check-user-test-2024-3',
				],
				[
					'ip' => '1.2.3.3',
				]
			],
			'Hidden temporary account without view permission' => [
				[
					'~check-user-test-2024-3',
				],
				[
					'ip' => '1.2.3.3',
					'hideuser' => false,
				]
			]
		];
	}

	public function testInvalidIP() {
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					'rest-invalid-ip',
				),
				404
			)
		);

		$this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountIPHandler(),
			$this->getRequestData( [
				'ip' => 'foo'
			] ),
			[],
			[],
			[],
			[],
			$this->getAuthority()
		);
	}

	public function testWhenTemporaryAccountsNotKnown() {
		$this->disableAutoCreateTempUser( [ 'known' => false ] );
		$this->expectExceptionObject( new LocalizedHttpException( new MessageValue( 'rest-no-match' ), 404 ) );

		$this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountIPHandler(),
			$this->getRequestData( [ 'ip' => '1.2.3.4' ] ),
			[],
			[],
			[],
			[],
			$this->getAuthority()
		);
	}

	public function addDBData() {
		$CUTestData = [
			[
				'cuc_actor'      => 1,
				'cuc_ip'         => '1.2.3.1',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.1' ),
				'cuc_this_oldid' => 1,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20200101000000' ),
			],
			[
				'cuc_actor'      => 2,
				'cuc_ip'         => '1.2.3.2',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.2' ),
				'cuc_this_oldid' => 10,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20200101000001' ),
			],
			[
				'cuc_actor'      => 20,
				'cuc_ip'         => '1.2.3.2',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.2' ),
				'cuc_this_oldid' => 100,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20200101000002' ),
			],
			[
				'cuc_actor'      => 3,
				'cuc_ip'         => '1.2.3.3',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.3' ),
				'cuc_this_oldid' => 1000,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20210101000003' ),
			],
			[
				'cuc_actor'      => 30,
				'cuc_ip'         => '1.2.3.3',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.3' ),
				'cuc_this_oldid' => 10000,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20220101000004' ),
			],
			[
				'cuc_actor'      => 20,
				'cuc_ip'         => '1.2.3.2',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.2' ),
				'cuc_this_oldid' => 100000,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20200101000005' ),
			],
		];
		$CUCommonData = [
			'cuc_type' => RC_EDIT,
			'cuc_agent' => 'foo user agent',
			'cuc_namespace' => NS_MAIN,
			'cuc_title' => 'Foo_Page',
			'cuc_minor' => 0,
			'cuc_page_id' => 1,
			'cuc_xff' => 0,
			'cuc_xff_hex' => null,
			'cuc_comment_id' => 0,
			'cuc_last_oldid' => 0,
		];
		$queryBuilder = $this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_changes' )
			->caller( __METHOD__ );
		foreach ( $CUTestData as $row ) {
			$queryBuilder->row( $row + $CUCommonData );
		}
		$queryBuilder->execute();

		$actorTestData = [
			[
				'actor_id' => 1,
				'actor_user' => 1,
				'actor_name' => '~check-user-test-2024-1'
			],
			[
				'actor_id' => 2,
				'actor_user' => 2,
				'actor_name' => '~check-user-test-2024-2'
			],
			[
				'actor_id' => 20,
				'actor_user' => 20,
				'actor_name' => '~check-user-test-2024-20'
			],
			[
				'actor_id' => 3,
				'actor_user' => 3,
				'actor_name' => '~check-user-test-2024-3'
			],
			[
				'actor_id' => 30,
				'actor_user' => 30,
				'actor_name' => '~check-user-test-2024-30'
			]
		];
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'actor' )
			->rows( $actorTestData )
			->caller( __METHOD__ )
			->execute();
	}
}
