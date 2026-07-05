<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\Api\Rest\Handler\ConnectedTemporaryAccountsHandler;
use MediaWiki\Extension\CheckUser\CheckUserPermissionStatus;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\ConnectedTemporaryAccountsHandler
 */
class ConnectedTemporaryAccountsHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;
	use TempUserTestTrait;

	public function setUp(): void {
		parent::setUp();
		$this->enableAutoCreateTempUser();
	}

	/**
	 * @param array $options
	 * @return ConnectedTemporaryAccountsHandler
	 */
	private function getConnectedTemporaryAccountsHandler( array $options = [] ): ConnectedTemporaryAccountsHandler {
		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );
		$this->setService( 'CheckUserPermissionManager', $checkUserPermissionManager );

		$services = $this->getServiceContainer();
		return new ConnectedTemporaryAccountsHandler( ...array_values( array_merge(
			[
				'config' => $services->getMainConfig(),
				'jobQueueGroup' => $this->createMock( JobQueueGroup::class ),
				'permissionManager' => $services->getPermissionManager(),
				'userNameUtils' => $services->getUserNameUtils(),
				'dbProvider' => $services->getConnectionProvider(),
				'actorStore' => $services->getActorStore(),
				'blockManager' => $services->getBlockManager(),
				'checkUserPermissionManager' => $services->get( 'CheckUserPermissionManager' ),
				'autoRevealLookup' => $services->get(
					'CheckUserTemporaryAccountAutoRevealLookup'
				),
				'LoggerFactory' => $services->get( 'CheckUserTemporaryAccountLoggerFactory' ),
				'readOnlyMode' => $services->getReadOnlyMode(),
				'checkUserTemporaryAccountsByIPLookup' => $services->get( 'CheckUserTemporaryAccountsByIPLookup' ),
			],
			$options
		) ) );
	}

	/**
	 * @dataProvider provideTestExecute
	 */
	public function testExecute( $name, $expectedAccounts, $expectedIpsUsedCount ) {
		$data = $this->executeHandlerAndGetBodyData(
			$this->getConnectedTemporaryAccountsHandler(),
			new RequestData( [
				'pathParams' => [
					'name' => $name,
				],
				'queryParams' => [],
			] ),
			[],
			[],
			[],
			[],
			$this->getTestSysop()->getAuthority()
		);

		$this->assertEquals( $expectedIpsUsedCount, $data['ipsUsedCount'] );
		$this->assertEquals( $expectedAccounts, $data['connectedAccounts'] );
	}

	public static function provideTestExecute() {
		return [
			'Connected accounts from primary using multiple IPs' => [
				'name' => '~check-user-test-01',
				'expectedAccounts' => [ '~check-user-test-02', '~check-user-test-01' ],
				'expectedIpsUsedCount' => 2,
			],
			'Connected accounts from primary using a single IP' => [
				'name' => '~check-user-test-02',
				'expectedAccounts' => [ '~check-user-test-02', '~check-user-test-01' ],
				'expectedIpsUsedCount' => 1,
			],
			'No connected accounts' => [
				'name' => '~check-user-test-03',
				'expectedAccounts' => [ '~check-user-test-03' ],
				'expectedIpsUsedCount' => 1,
			],
		];
	}

	public function addDBDataOnce() {
		// Create some temp accounts and edits on different IPs.
		$this->enableAutoCreateTempUser( [
			[ 'genPattern' => '~check-user-test-$1' ],
		] );

		// This temp account edits from an IPv4 and an IPv6 IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.1' );
		ConvertibleTimestamp::setFakeTime( '20230405060706' );
		$tempUser1 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-01', $this->getFauxRequest( '127.0.0.1' ) )->getUser();
		$this->editPage(
			'Test page',
			'Test Content 1A',
			'test',
			NS_MAIN,
			$tempUser1
		);
		ConvertibleTimestamp::setFakeTime( '20230405060707' );
		RequestContext::getMain()->getRequest()->setIP( '1:1:1:1:1:1:1:1' );
		$this->editPage(
			'Test page',
			'Test Content 1B',
			'test',
			NS_MAIN,
			$tempUser1
		);

		// This temp account is created from $tempUser1's second edit IP and edits
		// from there
		RequestContext::getMain()->getRequest()->setIP( '1:1:1:1:1:1:1:1' );
		ConvertibleTimestamp::setFakeTime( '20230405060708' );
		$tempUser2 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-02', $this->getFauxRequest( '1:1:1:1:1:1:1:1' ) )
			->getUser();
		$this->editPage(
			'Test page',
			'Test Content 2A',
			'test',
			NS_MAIN,
			$tempUser2
		);

		// This temp account doesn't share an IP with any other account
		ConvertibleTimestamp::setFakeTime( '20230405060711' );
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		$tempUser3 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-03', $this->getFauxRequest( '1.2.3.4' ) )->getUser();
		$this->editPage(
			'Test page',
			'Test Content 3A',
			'test',
			NS_MAIN,
			$tempUser3
		);

		ConvertibleTimestamp::setFakeTime( false );
	}

	private function getFauxRequest( string $ip ): FauxRequest {
		$request = new FauxRequest();
		$request->setIP( $ip );
		return $request;
	}
}
