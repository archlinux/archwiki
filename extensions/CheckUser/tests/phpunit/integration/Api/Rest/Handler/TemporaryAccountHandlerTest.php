<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Api\Rest\Handler;

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\Block\AbstractBlock;
use MediaWiki\Block\Block;
use MediaWiki\Block\BlockManager;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CheckUser\Api\Rest\Handler\TemporaryAccountHandler;
use MediaWiki\Extension\CheckUser\CheckUserPermissionStatus;
use MediaWiki\Extension\CheckUser\HookHandler\Preferences;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Extension\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNameUtils;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\ReadOnlyMode;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\TemporaryAccountHandler
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\AbstractTemporaryAccountHandler
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\AbstractTemporaryAccountNameHandler
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\TemporaryAccountNameTrait
 */
class TemporaryAccountHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	/**
	 * By default, services are mocked for a successful Response.
	 * They can be overridden via $options.
	 *
	 * @param array $options
	 * @return TemporaryAccountHandler
	 */
	private function getTemporaryAccountHandler( array $options = [] ): TemporaryAccountHandler {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$userNameUtils = $this->createMock( UserNameUtils::class );
		$userNameUtils->method( 'isTemp' )
			->willReturn( true );

		$actorStore = $this->createMock( ActorStore::class );
		$actorStore->method( 'findActorIdByName' )
			->willReturn( 1234 );
		$actorStore->method( 'getUserIdentityByName' )
			->willReturn( new UserIdentityValue( 1234, '*Unregistered 1' ) );

		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );

		$services = $this->getServiceContainer();
		return new TemporaryAccountHandler( ...array_values( array_merge(
			[
				'config' => $services->getMainConfig(),
				'jobQueueGroup' => $this->createMock( JobQueueGroup::class ),
				'permissionManager' => $permissionManager,
				'userNameUtils' => $userNameUtils,
				'dbProvider' => $services->getConnectionProvider(),
				'actorStore' => $actorStore,
				'blockManager' => $services->getBlockManager(),
				'checkUserPermissionManager' => $checkUserPermissionManager,
				'autoRevealLookup' => $services->get(
					'CheckUserTemporaryAccountAutoRevealLookup'
				),
				'LoggerFactory' => $services->get( 'CheckUserTemporaryAccountLoggerFactory' ),
				'readOnlyMode' => $services->getReadOnlyMode(),
			],
			$options
		) ) );
	}

	private function getAuthorityForSuccess(): Authority {
		return $this->getTestUser()->getAuthority();
	}

	private function getRequestData( array $options = [] ): RequestData {
		$pathParams = [
			'name' => $options['name'] ?? '*Unregistered 1',
		];
		$queryParams = [];
		if ( isset( $options['limit'] ) ) {
			$queryParams['limit'] = $options['limit'];
		}

		return new RequestData( [
			'pathParams' => $pathParams,
			'queryParams' => $queryParams,
		] );
	}

	public function testExecute() {
		$this->overrideConfigValue( 'CheckUserMaximumRowCount', 5000 );
		$data = $this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountHandler(),
			$this->getRequestData(),
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
		$this->assertArrayEquals(
			[
				'1.2.3.7',
				'1.2.3.6',
				'1.2.3.5',
				'1.2.3.4',
				'1.2.3.3',
				'1.2.3.2',
				'1.2.3.1',
			],
			$data['ips'],
			true
		);
	}

	public function testExecuteLimit() {
		$this->overrideConfigValue( 'CheckUserMaximumRowCount', 5000 );
		$requestData = $this->getRequestData( [ 'limit' => 3 ] );
		$data = $this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountHandler(),
			$requestData,
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
		$this->assertCount(
			3,
			$data['ips'],
			'Resulting number of IP addresses is not as expected'
		);
		$this->assertArrayEquals(
			[
				'1.2.3.7',
				'1.2.3.6',
				'1.2.3.5',
			],
			$data['ips'],
			true,
			false,
			'Resulting IP addresses are not as expected'
		);
	}

	public function testExecuteLimitConfig() {
		$this->overrideConfigValue( 'CheckUserMaximumRowCount', 1 );
		$data = $this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountHandler(),
			$this->getRequestData(),
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
		$this->assertArrayEquals(
			[ '1.2.3.7' ],
			$data['ips']
		);
	}

	public function testExecuteWhenSiteInReadOnlyMode() {
		// Create a mock ReadOnlyMode service instance that says the site is in read only mode.
		// This is done to avoid other unrelated code failing while read only mode is set.
		$mockReadOnlyMode = $this->createMock( ReadOnlyMode::class );
		$mockReadOnlyMode->method( 'getReason' )
			->willReturn( 'Maintenance' );

		$handler = $this->getTemporaryAccountHandler( [
			'permissionManager' => $this->getServiceContainer()->getPermissionManager(),
			'checkUserPermissionManager' => $this->getServiceContainer()->getService( 'CheckUserPermissionManager' ),
			'readOnlyMode' => $mockReadOnlyMode,
		] );

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'readonlytext', [ 'Maintenance' ] ), 503 )
		);
		$this->executeHandler(
			$handler,
			$this->getRequestData(),
			[],
			[],
			[],
			[],
			$this->mockRegisteredUltimateAuthority()
		);
	}

	/**
	 * @dataProvider provideExecutePermissionErrorsNoRight
	 */
	public function testExecutePermissionErrorsNoRight( bool $named, array $expected ) {
		$handler = $this->getTemporaryAccountHandler( [
			'permissionManager' => $this->getServiceContainer()->getPermissionManager(),
			'checkUserPermissionManager' => $this->getServiceContainer()->getService( 'CheckUserPermissionManager' ),
		] );

		$user = $this->getTestUser()->getUser();

		$authority = $this->createMock( Authority::class );
		$authority->method( 'isNamed' )
			->willReturn( $named );
		$authority->method( 'getUser' )
			->willReturn( $user );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					'checkuser-rest-access-denied',
				),
				$expected['code']
			)
		);

		// Can't use executeHandlerAndGetHttpException, since it doesn't take an Authority
		$this->executeHandler(
			$handler,
			$this->getRequestData(),
			[],
			[],
			[],
			[],
			$authority
		);
	}

	public static function provideExecutePermissionErrorsNoRight() {
		return [
			'Anon or temporary user' => [
				false,
				[
					'code' => 401,
				],
			],
			'Registered (named) user' => [
				true,
				[
					'code' => 403,
				],
			],
		];
	}

	public function testExecutePermissionErrorsNoPreference() {
		$handler = $this->getTemporaryAccountHandler( [
			'userOptionsLookup' => $this->getServiceContainer()->getUserOptionsLookup(),
			'checkUserPermissionManager' => $this->getServiceContainer()->getService( 'CheckUserPermissionManager' ),
		] );

		$user = $this->getTestUser()->getUser();

		$authority = $this->createMock( Authority::class );
		$authority->method( 'isNamed' )
			->willReturn( true );
		$authority->method( 'getUser' )
			->willReturn( $user );
		$authority->method( 'isAllowed' )
			->willReturnCallback( static function ( $right ) {
				// Grant the user any right other than 'checkuser-temporary-account-no-preference',
				// so that the preference check is made (as that right allows the user to skip
				// the preference check).
				return $right !== 'checkuser-temporary-account-no-preference';
			} );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					'checkuser-rest-access-denied',
				),
				403
			)
		);

		// Can't use executeHandlerAndGetHttpException, since it doesn't take an Authority
		$this->executeHandler(
			$handler,
			$this->getRequestData(),
			[],
			[],
			[],
			[],
			$authority
		);
	}

	public function testExecutePermissionErrorsBlocked() {
		$block = $this->createMock( Block::class );
		$block->method( 'isSitewide' )
			->willReturn( true );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'isNamed' )
			->willReturn( true );
		$authority->method( 'getBlock' )
			->willReturn( $block );
		$authority->method( 'isAllowed' )
			->willReturn( true );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					'checkuser-rest-access-denied-blocked-user'
				),
				403
			)
		);

		// Can't use executeHandlerAndGetHttpException, since it doesn't take an Authority
		$handler = $this->getTemporaryAccountHandler( [
			'checkUserPermissionManager' => $this->getServiceContainer()->getService( 'CheckUserPermissionManager' ),
		] );
		$this->executeHandler(
			$handler,
			$this->getRequestData(),
			[],
			[],
			[],
			[],
			$authority
		);
	}

	/**
	 * @dataProvider provideExecutePermissionErrorsBadName
	 */
	public function testExecutePermissionErrorsBadName( $name ) {
		$handler = $this->getTemporaryAccountHandler( [
			'userNameUtils' => $this->getServiceContainer()->getUserNameUtils(),
		] );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'isNamed' )
			->willReturn( true );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					'rest-invalid-user'
				),
				404
			)
		);

		// Can't use executeHandlerAndGetHttpException, since it doesn't take an Authority
		$this->executeHandler(
			$handler,
			$this->getRequestData( [ 'name' => $name ] ),
			[],
			[],
			[],
			[],
			$authority
		);
	}

	public static function provideExecutePermissionErrorsBadName() {
		return [
			'Registered username' => [ 'SomeName' ],
			'IP address' => [ '127.0.0.1' ],
		];
	}

	public function testExecutePermissionErrorsNonexistentName() {
		$actorStore = $this->createMock( ActorStore::class );
		$handler = $this->getTemporaryAccountHandler( [
			'actorStore' => $actorStore,
		] );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'isNamed' )
			->willReturn( true );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					'rest-nonexistent-user'
				),
				404
			)
		);

		// Can't use executeHandlerAndGetHttpException, since it doesn't take an Authority
		$this->executeHandler(
			$handler,
			$this->getRequestData( [ 'name' => '*Unregistered 9999' ] ),
			[],
			[],
			[],
			[],
			$authority
		);
	}

	/** @dataProvider provideExecutePermissionErrorsSuppressedUser */
	public function testExecutePermissionErrorsSuppressedUser(
		$authorityHasHideUserRight,
		$expectedMessageKey,
		$expectedHttpStatusCode
	) {
		$mockBlock = $this->createMock( AbstractBlock::class );
		$mockBlock->method( 'getHideName' )
			->willReturn( true );
		$mockBlockManager = $this->createMock( BlockManager::class );
		$mockBlockManager->method( 'getBlock' )
			->willReturn( $mockBlock );
		$mockPermissionManager = $this->createMock( PermissionManager::class );
		$mockPermissionManager->method( 'userHasRight' )
			->willReturnCallback( static function ( $_, $permission ) use ( $authorityHasHideUserRight ) {
				if ( $permission === 'viewsuppressed' ) {
					return false;
				} elseif ( $permission === 'hideuser' ) {
					return $authorityHasHideUserRight;
				}
				return true;
			} );
		$handler = $this->getTemporaryAccountHandler( [
			'blockManager' => $mockBlockManager,
			'permissionManager' => $mockPermissionManager,
		] );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'isNamed' )
			->willReturn( true );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					$expectedMessageKey
				),
				$expectedHttpStatusCode
			)
		);

		// Can't use executeHandlerAndGetHttpException, since it doesn't take an Authority
		$this->executeHandler(
			$handler,
			$this->getRequestData( [ 'name' => '*Unregistered 1' ] ),
			[],
			[],
			[],
			[],
			$authority
		);
	}

	public static function provideExecutePermissionErrorsSuppressedUser() {
		return [
			'Authority has the "hideuser" right' => [ true, 'checkuser-rest-access-denied', 403 ],
			'Authority does not have the "hideuser" right' => [ false, 'rest-nonexistent-user', 404 ],
		];
	}

	/**
	 * @dataProvider provideExecuteLogs
	 */
	public function testExecuteLogs(
		int $jobQueueGroupExpects,
		int $loggerExpects,
		array $preferences
	): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );

		ConvertibleTimestamp::setFakeTime( '20230406060708' );

		$serviceOptions = new ServiceOptions(
			CheckUserTemporaryAccountAutoRevealLookup::CONSTRUCTOR_OPTIONS,
			$this->getServiceContainer()->getMainConfig()
		);

		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );
		$checkUserPermissionManager->method( 'canAutoRevealIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );

		$preferencesFactory = $this->createMock( GlobalPreferencesFactory::class );
		$preferencesFactory->method( 'getGlobalPreferencesValues' )
			->willReturn( $preferences );

		$autoRevealLookup = new CheckUserTemporaryAccountAutoRevealLookup(
			$serviceOptions,
			$preferencesFactory,
			$checkUserPermissionManager
		);

		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->expects( $this->exactly( $jobQueueGroupExpects ) )
			->method( 'push' );

		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->exactly( $loggerExpects ) )
			->method( 'info' )
			->with(
				'{username} viewed IP addresses for {target}',
				$this->callback( static function ( $context ) {
					return $context['target'] === '*Unregistered_1';
				} )
			);

		$this->setLogger( 'CheckUser', $logger );

		$data = $this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountHandler( [
				'autoRevealLookup' => $autoRevealLookup,
				'jobQueueGroup' => $jobQueueGroup,
			] ),
			$this->getRequestData(),
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
	}

	public static function provideExecuteLogs(): array {
		ConvertibleTimestamp::setFakeTime( '20230406060708' );
		$timeNow = ConvertibleTimestamp::time();
		$validFutureTimestamp = $timeNow + 100;
		$invalidFutureTimestamp = $timeNow + 9999999;
		$pastTimestamp = $timeNow - 100;
		ConvertibleTimestamp::setFakeTime( false );

		return [
			'The correct logger is called when auto-reveal is on with valid expiry' => [
				'jobQueueGroupExpects' => 0,
				'loggerExpects' => 1,
				'preferences' => [ Preferences::ENABLE_IP_AUTO_REVEAL => $validFutureTimestamp ],
			],
			'The correct logger is called when auto-reveal is on with expiry too far in the future' => [
				'jobQueueGroupExpects' => 1,
				'loggerExpects' => 0,
				'preferences' => [ Preferences::ENABLE_IP_AUTO_REVEAL => $invalidFutureTimestamp ],
			],
			'The correct logger is called when auto-reveal is on with expiry in the past' => [
				'jobQueueGroupExpects' => 1,
				'loggerExpects' => 0,
				'preferences' => [ Preferences::ENABLE_IP_AUTO_REVEAL => $pastTimestamp ],
			],
			'The correct logger is called when auto-reveal is off' => [
				'jobQueueGroupExpects' => 1,
				'loggerExpects' => 0,
				'preferences' => [],
			],
		];
	}

	public function addDBDataOnce() {
		// Add test data for cu_changes
		$testData = [
			[
				'cuc_actor'      => 1234,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.1' ),
				'cuc_this_oldid' => 1,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20200101000000' ),
			],
			[
				'cuc_actor'      => 1234,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.2' ),
				'cuc_this_oldid' => 10,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20200102000000' ),
			],
			[
				'cuc_actor'      => 1234,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.3' ),
				'cuc_this_oldid' => 100,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20200103000000' ),
			],
			[
				'cuc_actor'      => 1234,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_this_oldid' => 1000,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20200104000000' ),
			],
			[
				'cuc_actor'      => 1234,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_this_oldid' => 10000,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20210105000000' ),
			],
			[
				'cuc_actor'      => 1234,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_this_oldid' => 100000,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20220101000000' ),
			],
		];

		$commonData = [
			'cuc_type'       => RC_EDIT,
			'cuc_agent_id'   => 0,
			'cuc_namespace'  => NS_MAIN,
			'cuc_title'      => 'Foo_Page',
			'cuc_minor'      => 0,
			'cuc_page_id'    => 1,
			'cuc_xff'        => 0,
			'cuc_xff_hex'    => null,
			'cuc_comment_id' => 0,
			'cuc_last_oldid' => 0,
		];

		$queryBuilder = $this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_changes' )
			->caller( __METHOD__ );
		foreach ( $testData as $row ) {
			$queryBuilder->row( $row + $commonData );
		}
		$queryBuilder->execute();

		// Add test data for cu_log_event
		$testData = [
			[
				'cule_actor'      => 1234,
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cule_log_id'     => 1,
				'cule_timestamp'  => $this->getDb()->timestamp( '20200104000000' ),
			],
			[
				'cule_actor'      => 1234,
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cule_log_id'     => 2,
				'cule_timestamp'  => $this->getDb()->timestamp( '20220101000000' ),
			],
			[
				'cule_actor'      => 1234,
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.6' ),
				'cule_log_id'     => 3,
				'cule_timestamp'  => $this->getDb()->timestamp( '20220109000000' ),
			],
		];

		$commonData = [
			'cule_xff'      => 0,
			'cule_xff_hex'  => null,
			'cule_agent_id' => 0,
		];

		$queryBuilder = $this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_log_event' )
			->caller( __METHOD__ );
		foreach ( $testData as $row ) {
			$queryBuilder->row( $row + $commonData );
		}
		$queryBuilder->execute();

		// Add test data for cu_private_event
		$testData = [
			[
				'cupe_actor'      => 1234,
				'cupe_ip_hex'     => IPUtils::toHex( '1.2.3.7' ),
				'cupe_timestamp'  => $this->getDb()->timestamp( '20220110000000' ),
			],
		];

		$commonData = [
			'cupe_agent_id' => 0,
			'cupe_xff'      => 0,
			'cupe_xff_hex'  => null,
			'cupe_params'   => '',
		];

		$queryBuilder = $this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_private_event' )
			->caller( __METHOD__ );
		foreach ( $testData as $row ) {
			$queryBuilder->row( $row + $commonData );
		}
		$queryBuilder->execute();
	}
}
