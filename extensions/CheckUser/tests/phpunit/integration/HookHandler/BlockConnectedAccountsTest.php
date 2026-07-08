<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Api\ApiBlock;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\CheckUserPermissionStatus;
use MediaWiki\Extension\CheckUser\HookHandler\BlockConnectedAccounts;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\BlockConnectedAccounts
 * @group CheckUser
 * @group Database
 */
class BlockConnectedAccountsTest extends ApiTestCase {
	use TempUserTestTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->enableAutoCreateTempUser();
		$this->overrideConfigValue( 'EnableMultiBlocks', true );

		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );
		$this->setService( 'CheckUserPermissionManager', $checkUserPermissionManager );
	}

	private function getHookHandler(): BlockConnectedAccounts {
		$services = $this->getServiceContainer();
		return new BlockConnectedAccounts(
			$services->getTempUserConfig(),
			$services->getBlockTargetFactory(),
			$services->getUserIdentityLookup(),
			$services->get( 'CheckUserPermissionManager' )
		);
	}

	public function testExecuteOnAPIGetAllowedParams() {
		$params = [];
		$this->getHookHandler()->onAPIGetAllowedParams(
			$this->createMock( ApiBlock::class ),
			$params,
			0
		);
		$this->assertArrayEquals( [ 'blockConnectedTempAccounts' => false ], $params );
	}

	public function testExecuteOnApiBlockSucceeded() {
		$this->setTemporaryHook( 'APIGetAllowedParams', static function ( $module, &$params, $flags ) {
			$params['blockConnectedTempAccounts'] = false;
		} );
		$params = [
			'action' => 'block',
			'user' => '~check-user-test-01',
			'reason' => '',
			'newblock' => 1,
			'blockConnectedTempAccounts' => true,
		];
		$ret = $this->doApiRequestWithToken( $params, null, $this->getTestSysOp()->getAuthority() );

		$block = $this->getServiceContainer()->getDatabaseBlockStore()->newFromId( $ret[0]['block']['id'] );
		$this->assertInstanceOf( DatabaseBlock::class, $block, 'Main block succeeded' );
		$this->assertArrayEquals(
			$ret[0]['block']['additionalBlocksStatuses'],
			[ '~check-user-test-02' => [] ],
			'Additional block succeeded'
		);
	}

	/** @dataProvider provideTestExecuteOnApiBlockSucceededNoOp */
	public function testExecuteOnApiBlockSucceededNoOp(
		bool $multiBlocksEnabled,
		bool $hasPermission,
		string $targetName
	) {
		$this->overrideConfigValue( 'EnableMultiBlocks', $multiBlocksEnabled );

		$this->setTemporaryHook( 'APIGetAllowedParams', static function ( $module, &$params, $flags ) {
			$params['blockConnectedTempAccounts'] = true;
		} );

		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn(
				$hasPermission ?
					CheckUserPermissionStatus::newGood() :
					CheckUserPermissionStatus::newPermissionError( 'Foo' )
			);
		$this->setService( 'CheckUserPermissionManager', $checkUserPermissionManager );

		$params = [
			'action' => 'block',
			'user' => $targetName,
			'reason' => '',
			'newblock' => 1,
			'blockConnectedTempAccounts' => true,
		];
		$ret = $this->doApiRequestWithToken( $params, null, $this->getTestSysOp()->getAuthority() );

		$block = $this->getServiceContainer()->getDatabaseBlockStore()->newFromId( $ret[0]['block']['id'] );
		$this->assertInstanceOf( DatabaseBlock::class, $block, 'Main block succeeded' );
		$this->assertArrayEquals(
			$ret[0]['block']['additionalBlocksStatuses'],
			[],
			'No additional blocks made'
		);
	}

	public static function provideTestExecuteOnApiBlockSucceededNoOp() {
		return [
			'multiblocks disabled' => [
				'multiBlocksEnabled' => false,
				'hasPermission' => true,
				'targetName' => '~check-user-test-01',
			],
			'no permission' => [
				'multiBlocksEnabled' => true,
				'hasPermission' => false,
				'targetName' => '~check-user-test-01',
			],
			'invalid target' => [
				'multiBlocksEnabled' => true,
				'hasPermission' => true,
				'targetName' => '1.2.3.4',
			],
		];
	}

	public function addDBDataOnce() {
		// Create some temp accounts and edits on different IPs.
		$this->enableAutoCreateTempUser();

		// This temp account edits from 2 IPv4 IPs
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
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
		$this->editPage(
			'Test page',
			'Test Content 1B',
			'test',
			NS_MAIN,
			$tempUser1
		);
		// Add another action at the same timestamp, from a different IP, to
		// test ordering by two fields
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.3' );
		$this->editPage(
			'Test page',
			'Test Content 1C',
			'test',
			NS_MAIN,
			$tempUser1
		);

		// This temp account is created from $tempUser1's second edit IP and edits
		// from there and also from an IPv6 IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
		ConvertibleTimestamp::setFakeTime( '20230405060708' );
		$tempUser2 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-02', $this->getFauxRequest( '127.0.0.2' ) )
			->getUser();
		$this->editPage(
			'Test page',
			'Test Content 2A',
			'test',
			NS_MAIN,
			$tempUser2
		);
		ConvertibleTimestamp::setFakeTime( '20230405060709' );
		RequestContext::getMain()->getRequest()->setIP( '1:1:1:1:1:1:1:1' );
		$this->editPage(
			'Test page',
			'Test Content 2B',
			'test',
			NS_MAIN,
			$tempUser2
		);

		// This temp account edits from a different IPv6 IP
		// but in the same 64 range as the second temp user as well and
		// repeatedly from an IPv6 IP on a different range
		ConvertibleTimestamp::setFakeTime( '20230405060710' );
		RequestContext::getMain()->getRequest()->setIP( '1:1:1:1:1:1:1:2' );
		$tempUser3 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-03', $this->getFauxRequest( '1:1:1:1:1:1:1:2' ) )->getUser();
		$this->editPage(
			'Test page',
			'Test Content 3A',
			'test',
			NS_MAIN,
			$tempUser3
		);
		RequestContext::getMain()->getRequest()->setIP( '2:2:2:2:2:2:2:2' );
		$this->editPage(
			'Test page',
			'Test Content 3B',
			'test',
			NS_MAIN,
			$tempUser3
		);
		$this->editPage(
			'Test page',
			'Test Content 3C',
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
