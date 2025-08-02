<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountLogHandler;
use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountLogHandler
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountLogTrait
 */
class TemporaryAccountLogHandlerTest extends MediaWikiIntegrationTestCase {

	use CheckUserTempUserTestTrait;
	use MockAuthorityTrait;
	use HandlerTestTrait;

	private static array $logIdsForPerformLogsLookupTest;
	private static UserIdentity $tempUser;

	protected function setUp(): void {
		parent::setUp();

		$this->enableAutoCreateTempUser();
	}

	/**
	 * @param array $options Overrides for the services
	 * @return array
	 */
	private function getTemporaryAccountLogHandlerConstructorArguments( array $options = [] ): array {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );

		$services = $this->getServiceContainer();
		return array_merge(
			[
				'config' => $services->getMainConfig(),
				'jobQueueGroup' => $this->createMock( JobQueueGroup::class ),
				'permissionManager' => $permissionManager,
				'preferencesFactory' => $services->getPreferencesFactory(),
				'userNameUtils' => $services->getUserNameUtils(),
				'dbProvider' => $services->getConnectionProvider(),
				'actorStore' => $services->getActorStore(),
				'blockManager' => $services->getBlockManager(),
				'checkUserPermissionManager' => $checkUserPermissionManager,
				'readOnlyMode' => $services->getReadOnlyMode(),
			],
			$options
		);
	}

	/**
	 * By default, services are mocked for a successful Response.
	 * They can be overridden via $options.
	 *
	 * @param array $options
	 * @return TemporaryAccountLogHandler|MockObject
	 */
	private function getPartiallyMockedTemporaryAccountLogHandler( array $options = [] ) {
		// Mock ::performLogsLookup to avoid DB lookups when these tests do not create entries
		// in the logging table.
		return $this->getMockBuilder( TemporaryAccountLogHandler::class )
			->onlyMethods( [ 'performLogsLookup' ] )
			->setConstructorArgs( array_values(
				$this->getTemporaryAccountLogHandlerConstructorArguments( $options )
			) )
			->getMock();
	}

	/**
	 * @return Authority
	 */
	private function getAuthorityForSuccess(): Authority {
		return $this->getTestUser()->getAuthority();
	}

	private function getRequestData( array $options = [] ): RequestData {
		return new RequestData( [
			'pathParams' => [
				'name' => $options['name'] ?? self::$tempUser->getName(),
				'ids' => $options['ids'] ?? [ 10 ],
			],
		] );
	}

	/**
	 * @dataProvider provideExecute
	 */
	public function testExecute( $expected, $optionsCallback ) {
		$temporaryAccountLogHandler = $this->getPartiallyMockedTemporaryAccountLogHandler();
		$temporaryAccountLogHandler->method( 'performLogsLookup' )
			->willReturnCallback( static function ( $ids ) {
				// Only return log entries for the log IDs that are in the input array and are defined log IDs in
				// the test data. These rows also have log_deleted as 0. Other values for log_deleted are tested in
				// other tests.
				return new FakeResultWrapper( array_values( array_map( static function ( $id ) {
					return [ 'log_id' => $id, 'log_deleted' => 0 ];
				}, array_intersect( $ids, [ 10, 100, 1000 ] ) ) ) );
			} );
		$data = $this->executeHandlerAndGetBodyData(
			$temporaryAccountLogHandler,
			$this->getRequestData( $optionsCallback() ),
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
		$this->assertArrayEquals(
			$expected,
			$data['ips'],
			true,
			true
		);
	}

	public static function provideExecute() {
		return [
			'One log entry' => [
				[
					'10' => '1.2.3.4',
				],
				static fn () => [
					'name' => self::$tempUser->getName(),
					'ids' => 10,
				],
			],
			'Multiple log entries' => [
				[
					'10' => '1.2.3.4',
					'100' => '1.2.3.5',
					'1000' => '1.2.3.5',
				],
				static fn () => [
					'name' => self::$tempUser->getName(),
					'ids' => [ 1000, 10, 100 ],
				],
			],
			'Nonexistent log entries included' => [
				[
					'10' => '1.2.3.4',
				],
				static fn () => [
					'name' => self::$tempUser->getName(),
					'ids' => [ 9999, 10 ],
				],
			],
		];
	}

	public function testErrorOnMissingLogIds() {
		$this->expectExceptionCode( 400 );
		$this->expectExceptionMessage( 'paramvalidator-missingparam' );
		$this->executeHandlerAndGetBodyData(
			$this->getPartiallyMockedTemporaryAccountLogHandler(),
			$this->getRequestData( [
				'ids' => []
			] ),
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
	}

	public function testWhenLogPerformerIsSuppressed() {
		// Set up a mock actor store that gets the real actor ID for the test temp user.
		$actorStore = $this->createMock( ActorStore::class );
		$actorStore->method( 'findActorIdByName' )
			->willReturn(
				$this->getServiceContainer()->getActorStore()->findActorId( self::$tempUser, $this->getDb() )
			);
		$actorStore->method( 'getUserIdentityByName' )
			->willReturn( self::$tempUser );
		$temporaryAccountLogHandler = $this->getPartiallyMockedTemporaryAccountLogHandler( [
			'actorStore' => $actorStore,
		] );
		$temporaryAccountLogHandler->method( 'performLogsLookup' )
			->willReturnCallback( static function ( $ids ) {
				// Only return log entries for the log IDs that are in the input array and are defined log IDs in
				// the test data. These rows also have log_deleted as 0. Other values for log_deleted are tested in
				// other tests.
				return new FakeResultWrapper( array_map( static function ( $id ) {
					return [ 'log_id' => $id, 'log_deleted' => LogPage::DELETED_RESTRICTED | LogPage::DELETED_USER ];
				}, array_intersect( $ids, [ 10, 100, 1000 ] ) ) );
			} );
		$data = $this->executeHandlerAndGetBodyData(
			$temporaryAccountLogHandler,
			$this->getRequestData( [
				'name' => self::$tempUser->getName(),
				'ids' => 10,
			] ),
			[],
			[],
			[],
			[],
			$this->mockRegisteredAuthorityWithPermissions( [ 'checkuser-temporary-account' ] )
		);
		$this->assertArrayEquals( [], $data['ips'] );
	}

	public function testPerformLogsLookup() {
		// Tests ::performLogsLookup, which is mocked in other tests to avoid
		// having to create log entries for every test.
		$temporaryAccountLogHandler = new TemporaryAccountLogHandler(
			...array_values( $this->getTemporaryAccountLogHandlerConstructorArguments() )
		);
		$temporaryAccountLogHandler = TestingAccessWrapper::newFromObject( $temporaryAccountLogHandler );
		$actualRows = $temporaryAccountLogHandler->performLogsLookup( self::$logIdsForPerformLogsLookupTest );
		foreach ( $actualRows as $index => $row ) {
			$this->assertSame(
				(int)$row->log_id,
				self::$logIdsForPerformLogsLookupTest[$index],
				"Log ID for row $index is not as expected"
			);
		}
	}

	private function createLogEntry( UserIdentity $performer ): ManualLogEntry {
		$logEntry = new ManualLogEntry( 'move', 'move' );
		$logEntry->setPerformer( $performer );
		$logEntry->setDeleted( LogPage::DELETED_USER | LogPage::DELETED_RESTRICTED );
		$logEntry->setTarget( $this->getExistingTestPage() );
		$logEntry->setParameters( [
			'4::target' => wfRandomString(),
			'5::noredir' => '0'
		] );
		return $logEntry;
	}

	public function testLookupForPageCreationLog() {
		$actorId = $this->getServiceContainer()->getActorStore()->findActorId( self::$tempUser, $this->getDb() );
		$pageCreationLogId = $this->newSelectQueryBuilder()
			->select( 'log_id' )
			->from( 'logging' )
			->where( [ 'log_type' => 'create', 'log_actor' => $actorId ] )
			->caller( __METHOD__ )
			->fetchField();

		$data = $this->executeHandlerAndGetBodyData(
			new TemporaryAccountLogHandler(
				...array_values( $this->getTemporaryAccountLogHandlerConstructorArguments() )
			),
			$this->getRequestData( [
				'name' => self::$tempUser->getName(),
				'ids' => (int)$pageCreationLogId,
			] ),
			[],
			[],
			[],
			[],
			$this->mockRegisteredAuthorityWithPermissions( [ 'checkuser-temporary-account' ] )
		);
		$this->assertArrayEquals( [ $pageCreationLogId => '1.2.3.20' ], $data['ips'], false, true );
	}

	public function addDBDataOnce() {
		// Create a temporary account for use in generating test data
		$this->enableAutoCreateTempUser();
		$tempUser = $this->getServiceContainer()->getTempUserCreator()
			->create( null, new FauxRequest() )
			->getUser();
		$actorId = $this->getServiceContainer()->getActorStore()->acquireActorId( $tempUser, $this->getDb() );

		$testData = [
			[
				'cule_actor'      => $actorId,
				'cule_ip'         => '1.2.3.4',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cule_log_id'     => 10,
				'cule_timestamp'  => $this->getDb()->timestamp( '20200101000000' ),
				'cule_agent'      => 'foo user agent',
				'cule_xff'        => 0,
				'cule_xff_hex'    => null,
			],
			[
				'cule_actor'      => $actorId,
				'cule_ip'         => '1.2.3.5',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cule_log_id'     => 100,
				'cule_timestamp'  => $this->getDb()->timestamp( '20210101000000' ),
				'cule_agent'      => 'foo user agent',
				'cule_xff'        => 0,
				'cule_xff_hex'    => null,
			],
			[
				'cule_actor'      => $actorId,
				'cule_ip'         => '1.2.3.5',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cule_log_id'     => 1000,
				'cule_timestamp'  => $this->getDb()->timestamp( '20220101000000' ),
				'cule_agent'      => 'foo user agent',
				'cule_xff'        => 0,
				'cule_xff_hex'    => null,
			],
		];

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_log_event' )
			->rows( $testData )
			->caller( __METHOD__ )
			->execute();

		self::$logIdsForPerformLogsLookupTest = [
			$this->createLogEntry( $tempUser )->insert(), $this->createLogEntry( $tempUser )->insert()
		];

		// Create a page using the temporary account, so that we can test looking up CU data for log entries
		// which don't have CU data but have an associated revision which does.
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.20' );
		$this->editPage(
			$this->getNonexistingTestPage(), 'testingabc', 'test create', NS_MAIN, $tempUser
		);

		// Assert that no cu_log_event row exists for the page creation (as then we won't be testing
		// that the CU data comes from cu_changes)
		$pageCreationLogId = $this->newSelectQueryBuilder()
			->select( 'log_id' )
			->from( 'logging' )
			->where( [ 'log_type' => 'create', 'log_actor' => $actorId ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertNotFalse( $pageCreationLogId );
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'log_search' )
			->where( [ 'ls_log_id' => $pageCreationLogId ] )
			->caller( __METHOD__ )
			->assertFieldValue( '1' );
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cu_log_event' )
			->where( [ 'cule_log_id' => $pageCreationLogId ] )
			->caller( __METHOD__ )
			->assertEmptyResult();

		self::$tempUser = $tempUser;
	}
}
