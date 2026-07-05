<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Api\Rest\Handler;

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\CheckUser\Api\Rest\Handler\BatchTemporaryAccountHandler;
use MediaWiki\Extension\CheckUser\CheckUserPermissionStatus;
use MediaWiki\Extension\CheckUser\HookHandler\Preferences;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Extension\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup;
use MediaWiki\Extension\CheckUser\Tests\Integration\AbuseFilter\FilterFactoryProxyTrait;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\RequestData;
use MediaWiki\Revision\ArchiveSelectQueryBuilder;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionSelectQueryBuilder;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\ActorStore;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNameUtils;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use StatusValue;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\BatchTemporaryAccountHandler
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\AbstractTemporaryAccountHandler
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\AbstractTemporaryAccountNameHandler
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\TemporaryAccountRevisionTrait
 * @covers \MediaWiki\Extension\CheckUser\Logging\TemporaryAccountLogger
 */
class BatchTemporaryAccountHandlerTest extends MediaWikiIntegrationTestCase {

	use FilterFactoryProxyTrait;
	use HandlerTestTrait;
	use MockServiceDependenciesTrait;
	use TempUserTestTrait;

	private static UserIdentity $tempUser;
	private static int $pageCreationLogId;
	private static int $expiredLogId;
	private static int $standardAFLogId;
	private static int $expiredAFLogId = -1;
	private static int $unavailableAFLogId = -1;
	private static array $logIdsForPerformLogsLookupTest;

	/**
	 * @dataProvider provideExecute
	 */
	public function testExecute(
		int $jobQueueGroupExpects,
		int $loggerExpects,
		bool $autoRevealAvailable,
		bool $autoRevealEnabled,
		bool $abuseFilterLoaded
	) {
		if ( $abuseFilterLoaded ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'Abuse Filter' );
		}

		$this->enableAutoCreateTempUser();

		$serviceOptions = new ServiceOptions(
			CheckUserTemporaryAccountAutoRevealLookup::CONSTRUCTOR_OPTIONS,
			$this->getServiceContainer()->getMainConfig()
		);

		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );
		$checkUserPermissionManager->method( 'canAutoRevealIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );

		$actorStore = $this->createMock( ActorStore::class );
		$actorStore->method( 'findActorIdByName' )
			->willReturn( 12345 );
		$actorStore->method( 'getUserIdentityByName' )
			->willReturn( new UserIdentityValue( 12345, '~12345' ) );

		if ( $autoRevealAvailable ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
			$preferencesFactory = $this->createMock( GlobalPreferencesFactory::class );
			$preferencesFactory->method( 'getGlobalPreferencesValues' )
				->willReturn(
					$autoRevealEnabled ?
					[ Preferences::ENABLE_IP_AUTO_REVEAL => time() + 10000 ] :
					[]
				);
			$autoRevealLookup = new CheckUserTemporaryAccountAutoRevealLookup(
				$serviceOptions,
				$preferencesFactory,
				$checkUserPermissionManager
			);
		} else {
			$autoRevealLookup = $this->createMock(
				CheckUserTemporaryAccountAutoRevealLookup::class
			);
		}

		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->willReturn( $abuseFilterLoaded );

		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->expects( $this->exactly( $jobQueueGroupExpects ) )
			->method( 'push' );

		// Set a mock logger for the test and then reset the services as we need services to use this mock logger.
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->exactly( $loggerExpects ) )
			->method( 'info' )
			->with(
				'{username} viewed IP addresses for {target}',
				$this->callback( static function ( $context ) {
					return $context['target'] === '~12345';
				} )
			);
		$this->setLogger( 'CheckUser', $logger );
		$this->resetServices();

		$services = $this->getServiceContainer();
		$handler = $this->getMockBuilder( BatchTemporaryAccountHandler::class )
			->onlyMethods( [ 'getRevisionsIps', 'getLogIps', 'getActorIps' ] )
			->setConstructorArgs( [
				$services->getMainConfig(),
				$jobQueueGroup,
				$services->getPermissionManager(),
				$services->getUserNameUtils(),
				$services->getConnectionProvider(),
				$actorStore,
				$services->getBlockManager(),
				$services->getRevisionStore(),
				$checkUserPermissionManager,
				$autoRevealLookup,
				$services->get( 'CheckUserTemporaryAccountLoggerFactory' ),
				$services->getReadOnlyMode(),
				$extensionRegistry,
				$services->get( 'CheckUserExpiredIdsLookupService' ),
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

		$expectedData = [
			'~12345' => [
				'revIps' => [ 1 => '1.2.3.4' ],
				'logIps' => [ 1 => '5.6.7.8' ],
				'lastUsedIp' => '9.8.7.6',
			],
		];
		if ( $abuseFilterLoaded ) {
			$expectedData['~12345']['abuseLogIps'] = null;
		}
		if ( $autoRevealAvailable ) {
			$expectedData['autoReveal'] = $autoRevealEnabled;
		}

		$this->assertSame( $expectedData, $data );
	}

	public static function provideExecute() {
		return [
			'The correct logger is called when auto-reveal is on' => [
				'jobQueueGroupExpects' => 0,
				'loggerExpects' => 1,
				'autoRevealAvailable' => true,
				'autoRevealEnabled' => true,
				'abuseFilterLoaded' => true,
			],
			'The correct logger is called when auto-reveal is off' => [
				'jobQueueGroupExpects' => 1,
				'loggerExpects' => 0,
				'autoRevealAvailable' => true,
				'autoRevealEnabled' => false,
				'abuseFilterLoaded' => true,
			],
			'The correct logger is called when auto-reveal is unavailable' => [
				'jobQueueGroupExpects' => 1,
				'loggerExpects' => 0,
				'autoRevealAvailable' => false,
				'autoRevealEnabled' => true,
				'abuseFilterLoaded' => true,
			],
			'abuseLogIps key is not added if AbuseFilter is not installed' => [
				'jobQueueGroupExpects' => 1,
				'loggerExpects' => 0,
				'autoRevealAvailable' => false,
				'autoRevealEnabled' => true,
				'abuseFilterLoaded' => false,
			],
		];
	}

	public function testExecuteSkipsNonExistentUsers() {
		$this->enableAutoCreateTempUser();

		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );

		// ActorStore returns a valid actor for ~12345 but null for ~99999
		$actorStore = $this->createMock( ActorStore::class );
		$actorStore->method( 'findActorIdByName' )
			->willReturnCallback( static function ( $name ) {
				return $name === '~12345' ? 12345 : null;
			} );
		$actorStore->method( 'getUserIdentityByName' )
			->willReturnCallback( static function ( $name ) {
				return $name === '~12345'
					? new UserIdentityValue( 12345, '~12345' )
					: null;
			} );

		$autoRevealLookup = $this->createMock(
			CheckUserTemporaryAccountAutoRevealLookup::class
		);

		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->willReturn( false );

		$jobQueueGroup = $this->createMock( JobQueueGroup::class );

		$this->setLogger( 'CheckUser', $this->createNoOpMock( LoggerInterface::class ) );
		$this->resetServices();

		$services = $this->getServiceContainer();
		$handler = $this->getMockBuilder( BatchTemporaryAccountHandler::class )
			->onlyMethods( [ 'getRevisionsIps', 'getLogIps', 'getActorIps' ] )
			->setConstructorArgs( [
				$services->getMainConfig(),
				$jobQueueGroup,
				$services->getPermissionManager(),
				$services->getUserNameUtils(),
				$services->getConnectionProvider(),
				$actorStore,
				$services->getBlockManager(),
				$services->getRevisionStore(),
				$checkUserPermissionManager,
				$autoRevealLookup,
				$services->get( 'CheckUserTemporaryAccountLoggerFactory' ),
				$services->getReadOnlyMode(),
				$extensionRegistry,
				$services->get( 'CheckUserExpiredIdsLookupService' ),
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
					'~99999' => [
						'revIds' => [ 2 ],
						'logIds' => [ 2 ],
						'lastUsedIp' => true,
					],
				],
			],
			$this->getTestUser()->getAuthority()
		);

		// ~99999 should be silently skipped, only ~12345 should be in the result
		$this->assertArrayHasKey( '~12345', $data );
		$this->assertArrayNotHasKey( '~99999', $data );
		$this->assertSame( [ 1 => '1.2.3.4' ], $data['~12345']['revIps'] );
		$this->assertSame( [ 1 => '5.6.7.8' ], $data['~12345']['logIps'] );
		$this->assertSame( '9.8.7.6', $data['~12345']['lastUsedIp'] );
	}

	/** @dataProvider provideExecuteForSpecificTypeOfIds */
	public function testExecuteForSpecificTypeOfIds(
		callable $revIdsCallback,
		callable $logIdsCallback,
		callable $abuseLogIdsCallback,
		callable $expectedResponseCallback,
		array $permissions
	) {
		if ( count( $abuseLogIdsCallback() ) ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'Abuse Filter' );
		}

		$handler = $this->mockHandler();
		// Assume that existing log entries are visible to the user, except for log 1000, which is suppressed
		$handler->method( 'performLogsLookup' )
			->willReturnCallback( static function ( $ids ) {
				// Only return log entries for the log IDs that are in the input array and are defined log IDs in
				// the test data.
				return new FakeResultWrapper( array_values( array_map( static function ( $id ) {
					return [
						'log_id' => $id,
						'log_deleted' => $id !== 1000 ? 0 : LogPage::DELETED_RESTRICTED | LogPage::DELETED_USER,
					];
				}, array_intersect( $ids, [ 10, 100, 1000, self::$pageCreationLogId ] ) ) ) );
			} );

		$authority = $this->mockRegisteredAuthorityWithPermissions( $permissions );

		$userName = self::$tempUser->getName();
		$data = $this->executeHandlerAndGetBodyData(
			$handler,
			new RequestData(),
			[],
			[],
			[],
			[
				'users' => [
					$userName => [
						'revIds' => $revIdsCallback(),
						'logIds' => $logIdsCallback(),
						'lastUsedIp' => false,
						'abuseLogIds' => $abuseLogIdsCallback(),
					],
				],
			],
			$authority
		);

		$responseTemplate = [
			'revIps' => null,
			'logIps' => null,
			'lastUsedIp' => null,
		];
		$extensionRegistry = $this->getServiceContainer()->getExtensionRegistry();
		if ( $extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
			$responseTemplate['abuseLogIps'] = null;
		}
		$expectedResponse = array_merge(
			$responseTemplate,
			$expectedResponseCallback()
		);

		$expected = [ $userName => $expectedResponse ];
		// When GlobalPreferences is available
		if ( $handler->getAutoRevealLookup()->isAutoRevealAvailable() ) {
			$expected['autoReveal'] = false;
		}

		$this->assertArrayEquals( $expected, $data, false, true );
	}

	public static function provideExecuteForSpecificTypeOfIds() {
		return [
			'Request with no IDs' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [],
				'permissions' => [],
			],
			'Single log ID' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [ 10 ],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					'logIps' => [ 10 => '1.2.3.4' ],
				],
				'permissions' => [],
			],
			'Two log IDs' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [ 10, 100 ],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					'logIps' => [ 10 => '1.2.3.4', 100 => '1.2.3.5' ],
				],
				'permissions' => [],
			],
			'One visible and one suppressed log (unprivileged user)' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [ 10, 1000 ],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					'logIps' => [
						10 => '1.2.3.4',
						// null means the ID is unavailable (but not expired)
						1000 => null,
					],
				],
				'permissions' => [],
			],
			'One visible and one expired log (privileged user)' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [ 10, self::$expiredLogId ],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					'logIps' => [
						10 => '1.2.3.4',
						// The expired entry is not present in the response
					],
				],
				'permissions' => [ 'viewsuppressed' ],
			],
			'One visible and one suppressed log (privileged user)' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [ 10, 1000 ],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					'logIps' => [ 10 => '1.2.3.4', 1000 => '1.2.3.5' ],
				],
				'permissions' => [ 'viewsuppressed' ],
			],
			'One visible, one expired, one unavailable log (privileged user)' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [
					10,
					self::$expiredLogId,
					9999,
				],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					// Note the expired entry (self::$expiredLogId) is not
					// present in the response
					'logIps' => [
						10 => '1.2.3.4',
						// 9999 is considered unavailable since there is no
						// log entry with such ID.
						9999 => null,
					],
				],
				'permissions' => [ 'viewsuppressed' ],
			],
			'One visible, one expired, one unavailable log (privileged user)' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [
					10,
					self::$expiredLogId,
					9999,
				],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					// Note the expired entry (self::$expiredLogId) is not
					// present in the response
					'logIps' => [
						10 => '1.2.3.4',
						// 9999 is considered unavailable since there is no
						// log entry with such ID.
						9999 => null,
					],
				],
				'permissions' => [ 'viewsuppressed' ],
			],
			'Nonexistent log IDs included' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [ 10, 9999 ],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					'logIps' => [
						10 => '1.2.3.4',
						// null means the ID is unavailable (but not expired)
						9999 => null,
					],
				],
				'permissions' => [],
			],
			'Creation log' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [ self::$pageCreationLogId ],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					'logIps' => [ self::$pageCreationLogId => '1.2.3.20' ],
				],
				'permissions' => [],
			],
			'AbuseFilter log (one existing, one expired)' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [],
				'abuseLogIdsCallback' => static fn () => [ 1, 2 ],
				'expectedResponseCallback' => static fn () => [
					'abuseLogIps' => [ 1 => '1.2.3.4' ],
				],
				'permissions' => [
					'abusefilter-log-detail',
					'abusefilter-log-private',
				],
			],
			'AbuseFilter log (existing, expired, unavailable, non-existing IDs)' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [],
				'abuseLogIdsCallback' => static fn () => [
					1,
					self::$expiredAFLogId,
					self::$unavailableAFLogId,
					9999,
				],
				'expectedResponseCallback' => static fn () => [
					'abuseLogIps' => [
						1 => '1.2.3.4',
						// null means the ID is unavailable (but not expired)
						self::$unavailableAFLogId => null,
					],
				],
				'permissions' => [
					'abusefilter-log-detail',
					'abusefilter-log-private',
				],
			],
		];
	}

	public function testPerformLogsLookup() {
		// Tests ::performLogsLookup, which is mocked in other tests to avoid
		// having to create log entries for every test.
		$handler = $this->mockHandler();
		$handler = TestingAccessWrapper::newFromObject( $handler );
		$actualRows = $handler->performLogsLookup( self::$logIdsForPerformLogsLookupTest );
		foreach ( $actualRows as $index => $row ) {
			$this->assertSame(
				(int)$row->log_id,
				self::$logIdsForPerformLogsLookupTest[$index],
				"Log ID for row $index is not as expected"
			);
		}
	}

	/**
	 * This test covers the code from TemporaryAccountRevisionTrait
	 * that is called by the handler under test.
	 *
	 * @dataProvider executeForRevisionsDataProvider
	 */
	public function testExecuteForRevisions(
		callable $expectedCallback,
		callable $validatedBody
	): void {
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

		$mockRevisionStore = $this->getMockRevisionStore();
		$mockRevisionStore->method( 'newRevisionsFromBatch' )
			->willReturnCallback( function ( $rows ) {
				$revisions = [];
				$rows->rewind();
				foreach ( $rows as $row ) {
					$mockRevision = $this->createMock( RevisionRecord::class );
					$mockRevision->method( 'userCan' )
						->willReturn( true );
					$mockRevision->method( 'getId' )
						->willReturn( $row->rev_id );

					$revisions[] = $mockRevision;
				}
				return StatusValue::newGood( $revisions );
			} );

		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );

		$services = $this->getServiceContainer();
		$handler = new BatchTemporaryAccountHandler(
			...array_values( [
				'config' => $services->getMainConfig(),
				'jobQueueGroup' => $this->createMock( JobQueueGroup::class ),
				'permissionManager' => $permissionManager,
				'userNameUtils' => $userNameUtils,
				'dbProvider' => $services->getConnectionProvider(),
				'actorStore' => $actorStore,
				'blockManager' => $services->getBlockManager(),
				'revisionStore' => $mockRevisionStore,
				'checkUserPermissionManager' => $checkUserPermissionManager,
				'autoRevealLookup' => $services->get(
					'CheckUserTemporaryAccountAutoRevealLookup'
				),
				'loggerFactory' => $services->get( 'CheckUserTemporaryAccountLoggerFactory' ),
				'readOnlyMode' => $services->getReadOnlyMode(),
				'extensionRegistry' => $services->getExtensionRegistry(),
				'expiredIdsLookupService' => $services->get( 'CheckUserExpiredIdsLookupService' ),
			] )
		);

		$data = $this->executeHandlerAndGetBodyData(
			$handler,
			new RequestData(),
			[],
			[],
			[],
			$validatedBody(),
			$this->getTestUser()->getAuthority()
		);

		$expected = $expectedCallback();

		// Don't add the 'abuseFilterIps' and 'autoReveal' keys unless the
		// dependencies for them to appear are met (T414008)
		$extensionRegistry = $this->getServiceContainer()->getExtensionRegistry();
		if ( $extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
			$expected[self::$tempUser->getName()]['abuseLogIps'] = null;
		}
		if ( $extensionRegistry->isLoaded( 'GlobalPreferences' ) ) {
			$expected['autoReveal'] = false;
		}

		$this->assertArrayEquals( $expected, $data, true, true );
	}

	public static function executeForRevisionsDataProvider(): array {
		return [
			'No revision IDs' => [
				'expectedCallback' => static fn () => [
					self::$tempUser->getName() => [
						'logIps' => null,
						'revIps' => null,
						'lastUsedIp' => '1.2.3.5',
					],
				],
				'validatedBody' => static fn () => [
					'users' => [
						self::$tempUser->getName() => [
							'revIds' => [],
							'logIds' => [],
							'lastUsedIp' => true,
						],
					],
				],
			],
			'One revision ID' => [
				'expectedCallback' => static fn () => [
					self::$tempUser->getName() => [
						'logIps' => null,
						'revIps' => [
							10 => '1.2.3.4',
						],
						'lastUsedIp' => '1.2.3.5',
					],
				],
				'validatedBody' => static fn () => [
					'users' => [
						self::$tempUser->getName() => [
							'revIds' => [ 10 ],
							'logIds' => [],
							'lastUsedIp' => true,
						],
					],
				],
			],
			'Multiple existing revision IDs' => [
				'expectedCallback' => static fn () => [
					self::$tempUser->getName() => [
						'logIps' => null,
						'revIps' => [
							10 => '1.2.3.4',
							100 => '1.2.3.5',
							1000 => '1.2.3.5',
						],
						'lastUsedIp' => '1.2.3.5',
					],
				],
				'validatedBody' => static fn () => [
					'users' => [
						self::$tempUser->getName() => [
							'revIds' => [ 1000, 10, 100 ],
							'logIds' => [],
							'lastUsedIp' => true,
						],
					],
				],
			],
			'Multiple revision IDs, both existing and nonexistent' => [
				'expectedCallback' => static fn () => [
					self::$tempUser->getName() => [
						'logIps' => null,
						'revIps' => [
							10 => '1.2.3.4',
							9999 => null,
						],
						'lastUsedIp' => '1.2.3.5',
					],
				],
				'validatedBody' => static fn () => [
					'users' => [
						self::$tempUser->getName() => [
							'revIds' => [ 9999, 10 ],
							'logIds' => [],
							'lastUsedIp' => true,
						],
					],
				],
			],
		];
	}

	private function mockHandler(): BatchTemporaryAccountHandler&MockObject {
		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );

		$jobQueueGroup = $this->createMock( JobQueueGroup::class );

		$this->setLogger( 'CheckUser', $this->createNoOpMock( LoggerInterface::class ) );

		$services = $this->getServiceContainer();
		$extensionRegistry = $services->getExtensionRegistry();
		$handler = $this->getMockBuilder( BatchTemporaryAccountHandler::class )
			->onlyMethods( [ 'performLogsLookup' ] )
			->setConstructorArgs( [
				$services->getMainConfig(),
				$jobQueueGroup,
				$services->getPermissionManager(),
				$services->getUserNameUtils(),
				$services->getConnectionProvider(),
				$services->getActorStore(),
				$services->getBlockManager(),
				$services->getRevisionStore(),
				$checkUserPermissionManager,
				$services->get(
					'CheckUserTemporaryAccountAutoRevealLookup'
				),
				$services->get( 'CheckUserTemporaryAccountLoggerFactory' ),
				$services->getReadOnlyMode(),
				$extensionRegistry,
				$services->get( 'CheckUserExpiredIdsLookupService' ),
			] )
			->getMock();

		return $handler;
	}

	public function addDBDataOnce(): void {
		$this->enableAutoCreateTempUser();

		// Create temporary accounts for use in generating test data
		$tempUser1 = $this->getServiceContainer()->getTempUserCreator()
			->create( null, new FauxRequest() )
			->getUser();
		$tempUser2 = $this->getServiceContainer()->getTempUserCreator()
			->create( null, new FauxRequest() )
			->getUser();

		self::$tempUser = $tempUser1;

		$this->addDBDataForAbuseLog( $tempUser1, $tempUser2 );
		$this->addDBDataForLogs( $tempUser1 );

		// Add an expired log entry
		$logEntry = $this->createLogEntry( $tempUser2 );
		$logEntry->setTimestamp( '20150101012345' );
		self::$expiredLogId = $logEntry->insert();

		$this->addDBDataForCuChanges();
	}

	private function addDBDataForLogs( User $tempUser ): void {
		$actorId = $this->getServiceContainer()->getActorStore()->acquireActorId( $tempUser, $this->getDb() );

		// And some additional log entries that associate log ids with IP addresses
		$testData = [
			[
				'cule_actor'      => $actorId,
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cule_log_id'     => 10,
				'cule_timestamp'  => $this->getDb()->timestamp( '20200101000000' ),
				'cule_agent_id'   => 0,
				'cule_xff'        => 0,
				'cule_xff_hex'    => null,
			],
			[
				'cule_actor'      => $actorId,
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cule_log_id'     => 100,
				'cule_timestamp'  => $this->getDb()->timestamp( '20210101000000' ),
				'cule_agent_id'   => 0,
				'cule_xff'        => 0,
				'cule_xff_hex'    => null,
			],
			[
				'cule_actor'      => $actorId,
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cule_log_id'     => 1000,
				'cule_timestamp'  => $this->getDb()->timestamp( '20220101000000' ),
				'cule_agent_id'   => 0,
				'cule_xff'        => 0,
				'cule_xff_hex'    => null,
			],
		];

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_log_event' )
			->rows( $testData )
			->caller( __METHOD__ )
			->execute();

		$this->addDBDataForCreationLog( $tempUser, $actorId );

		self::$logIdsForPerformLogsLookupTest = [
			$this->createLogEntry( $tempUser )->insert(), $this->createLogEntry( $tempUser )->insert(),
		];
	}

	private function addDBDataForCreationLog( User $tempUser, int $actorId ): void {
		// Create a page using the temporary account, so that we can test looking up CU data for log entries
		// which don't have CU data but have an associated revision which does.
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.20' );
		$this->editPage(
			$this->getNonexistingTestPage(),
			'testingabc',
			'test create',
			NS_MAIN,
			$tempUser
		);

		// Assert that no cu_log_event row exists for the page creation (as then we won't be testing
		// that the CU data comes from cu_changes)
		self::$pageCreationLogId = $this->newSelectQueryBuilder()
			->select( 'log_id' )
			->from( 'logging' )
			->where( [ 'log_type' => 'create', 'log_actor' => $actorId ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertNotFalse( self::$pageCreationLogId );
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'log_search' )
			->where( [ 'ls_log_id' => self::$pageCreationLogId ] )
			->caller( __METHOD__ )
			->assertFieldValue( '1' );
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cu_log_event' )
			->where( [ 'cule_log_id' => self::$pageCreationLogId ] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	private function addDBDataForAbuseLog( User $tempUser1, User $tempUser2 ): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Abuse Filter' ) ) {
			return;
		}

		$performer = $this->getTestSysop()->getUser();
		$filterStore = AbuseFilterServices::getFilterStore();

		$status = $filterStore->saveFilter(
			$performer,
			null,
			$this->getFilterFactoryProxy()->getFilter( [
				'id' => '1',
				'name' => 'Test filter',
				'privacy' => Flags::FILTER_HIDDEN,
				'rules' => 'old_wikitext = "abc"',
			] ),
			MutableFilter::newDefault()
		);

		$this->assertStatusGood( $status );

		$filterId = $status->value[ 0 ];

		// Insert two hits on the filter performed by different users but on the same IP
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		$abuseFilterLoggerFactory = AbuseFilterServices::getAbuseLoggerFactory();
		$abuseFilterLoggerFactory->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$tempUser1,
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_name' => $tempUser1->getName(),
				'old_wikitext' => 'abc',
			] )
		)->addLogEntries( [ $filterId => [] ] );

		$abuseFilterLoggerFactory = AbuseFilterServices::getAbuseLoggerFactory();
		$abuseFilterLoggerFactory->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$tempUser2,
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_name' => $tempUser2->getName(),
				'old_wikitext' => 'abc',
			] )
		)->addLogEntries( [ $filterId => [] ] );

		$dbw = $this->getDb();
		$template = [
			'afl_global' => 0,
			'afl_filter_id' => 1,
			'afl_user' => $tempUser1->getId(),
			'afl_user_text' => $tempUser1->getName(),
			'afl_ip_hex' => dechex( ip2long( '1.2.3.4' ) ),
			'afl_action' => 'edit',
			'afl_actions' => 'tag',
			'afl_var_dump' => 'tt:1',
			'afl_namespace' => 0,
			'afl_title' => 'Main_page',
			'afl_deleted' => 0,
			'afl_rev_id' => 1,
		];

		// Full data in a non-expired entry
		$dbw->newInsertQueryBuilder()
			->caller( __METHOD__ )
			->table( 'abuse_filter_log' )
			->row(
				array_merge( $template, [
					'afl_timestamp' => $dbw->timestamp( ConvertibleTimestamp::now() ),
				] )
			)
			->execute();

		$this->assertSame( 1, $dbw->affectedRows() );
		self::$standardAFLogId = $dbw->insertId();
		$this->assertGreaterThan( 0, self::$standardAFLogId );

		// Simulate an expired log by setting an old timestamp as well as
		// removing the IP data (see PurgeOldLogData.php in AbuseFilter).
		$dbw->newInsertQueryBuilder()
			->caller( __METHOD__ )
			->table( 'abuse_filter_log' )
			->row(
				array_merge( $template, [
					'afl_ip_hex' => '',
					'afl_timestamp' => $dbw->timestamp( '20150101012345' ),
				] )
			)
			->execute();

		$this->assertSame( 1, $dbw->affectedRows() );
		self::$expiredAFLogId = $dbw->insertId();
		$this->assertGreaterThan( 0, self::$expiredAFLogId );

		// Not expired, but missing IP
		$dbw->newInsertQueryBuilder()
			->caller( __METHOD__ )
			->table( 'abuse_filter_log' )
			->row(
				array_merge( $template, [
					'afl_ip_hex' => '',
					'afl_timestamp' => $dbw->timestamp( ConvertibleTimestamp::now() ),
				] )
			)
			->execute();

		$this->assertSame( 1, $dbw->affectedRows() );
		self::$unavailableAFLogId = $dbw->insertId();
		$this->assertGreaterThan( 0, self::$unavailableAFLogId );
	}

	private function addDBDataForCuChanges(): void {
		$testData = [
			[
				'cuc_actor'      => 1234,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_this_oldid' => 10,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20200101000000' ),
			],
			[
				'cuc_actor'      => 1234,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_this_oldid' => 100,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20210101000000' ),
			],
			[
				'cuc_actor'      => 1234,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_this_oldid' => 1000,
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
	}

	private function createLogEntry( UserIdentity $performer ): ManualLogEntry {
		$logEntry = new ManualLogEntry( 'move', 'move' );
		$logEntry->setPerformer( $performer );
		$logEntry->setDeleted( LogPage::DELETED_USER | LogPage::DELETED_RESTRICTED );
		$logEntry->setTarget( $this->getExistingTestPage() );
		$logEntry->setParameters( [
			'4::target' => wfRandomString(),
			'5::noredir' => '0',
		] );
		return $logEntry;
	}

	private function getMockRevisionStore(): RevisionStore&MockObject {
		// Mock the RevisionStore to say that all revisions can be viewed by the authority (we need to do this as
		// the revisions are not inserted to the DB).
		$mockRevision = $this->createMock( RevisionRecord::class );
		$mockRevision->method( 'userCan' )
			->willReturn( true );
		$mockRevision->method( 'getId' )
			->willReturn( 1000 );
		// Create a mock RevisionStore to return the mock select query builder and also
		// mock ::newRevisionsFromBatch.
		$mockRevisionStore = $this->createMock( RevisionStore::class );
		$mockRevisionStore->method( 'newSelectQueryBuilder' )
			->willReturn( $this->getMockRevisionOrArchiveQueryBuilder(
				RevisionSelectQueryBuilder::class,
				'rev_id'
			) );
		$mockRevisionStore->method( 'newArchiveSelectQueryBuilder' )
			->willReturn( $this->getMockRevisionOrArchiveQueryBuilder(
				ArchiveSelectQueryBuilder::class,
				'ar_rev_id'
			) );
		return $mockRevisionStore;
	}

	/**
	 * Creates a mock ArchiveSelectQueryBuilder or RevisionSelectQueryBuilder that
	 * returns mock revision rows from ::fetchResultSet. These mock rows are controlled
	 * by the IDs that are requested in the query.
	 *
	 * @param class-string<RevisionSelectQueryBuilder|ArchiveSelectQueryBuilder> $className
	 * @param string $revColumnName
	 * @return ArchiveSelectQueryBuilder|RevisionSelectQueryBuilder|MockObject
	 */
	private function getMockRevisionOrArchiveQueryBuilder( string $className, string $revColumnName ) {
		/** @var MockObject|RevisionSelectQueryBuilder|ArchiveSelectQueryBuilder $mockSelectQueryBuilder */
		$mockSelectQueryBuilder = $this->getMockBuilder( $className )
			->onlyMethods( [ 'fetchResultSet' ] )
			->setConstructorArgs( [ $this->createMock( IReadableDatabase::class ) ] )
			->getMock();
		$mockSelectQueryBuilder->method( 'fetchResultSet' )
			->willReturnCallback( static function () use ( $mockSelectQueryBuilder, $revColumnName ) {
				return new FakeResultWrapper( array_map(
					static fn ( $revId ) => [ $revColumnName => $revId ],
					array_values( array_intersect(
						$mockSelectQueryBuilder->getQueryInfo()['conds'][$revColumnName],
						[ 10, 100, 1000 ]
					) )
				) );
			} );
		return $mockSelectQueryBuilder;
	}
}
