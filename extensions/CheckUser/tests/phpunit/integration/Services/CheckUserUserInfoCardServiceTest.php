<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use CentralAuthTestUser;
use GrowthExperiments\UserImpact\ComputedUserImpactLookup;
use GrowthExperiments\UserImpact\UserImpact;
use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\CheckUser\Services\CheckUserUserInfoCardService;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\GlobalBlocking\GlobalBlockingServices;
use MediaWiki\Logging\LogEntryBase;
use MediaWiki\Logging\LogPage;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Services\CheckUserUserInfoCardService
 */
class CheckUserUserInfoCardServiceTest extends MediaWikiIntegrationTestCase {

	use CheckUserTempUserTestTrait;
	use MockAuthorityTrait;

	private static User $tempUser1;
	private static User $tempUser2;

	private static User $testUser;
	private static User $testGlobalUser;

	public function setUp(): void {
		parent::setUp();
		// The CheckUserGlobalContributionsLookup used in CheckuserUserInfoCardService requires CentralAuth
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );

		$this->enableAutoCreateTempUser( [
			[ 'genPattern' => '~check-user-test-$1' ],
		] );
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	public function addDBDataOnce() {
		// This method is called even if the tests are marked as skipped in setUp (due to absence of CentralAuth).
		// But there's no point in adding data for tests that won't run, so we check for CentralAuth again here.
		$extensionRegistry = $this->getServiceContainer()->getExtensionRegistry();
		if ( !$extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			return;
		}

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cuci_wiki_map' )
			->rows( [ [ 'ciwm_wiki' => 'enwiki' ], [ 'ciwm_wiki' => 'dewiki' ] ] )
			->caller( __METHOD__ )
			->execute();
		self::$testUser = $this->getTestSysop()->getUser();

		$enwikiMapId = $this->newSelectQueryBuilder()
			->select( 'ciwm_id' )
			->from( 'cuci_wiki_map' )
			->where( [ 'ciwm_wiki' => 'enwiki' ] )
			->caller( __METHOD__ )
			->fetchField();
		$dewikiMapId = $this->newSelectQueryBuilder()
			->select( 'ciwm_id' )
			->from( 'cuci_wiki_map' )
			->where( [ 'ciwm_wiki' => 'dewiki' ] )
			->caller( __METHOD__ )
			->fetchField();

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cuci_user' )
			->rows( [
				[
					'ciu_central_id' => self::$testUser->getId(), 'ciu_ciwm_id' => $enwikiMapId,
					'ciu_timestamp' => $this->getDb()->timestamp( '20240505060708' ),
				],
				[
					'ciu_central_id' => self::$testUser->getId(), 'ciu_ciwm_id' => $dewikiMapId,
					'ciu_timestamp' => $this->getDb()->timestamp( '20240506060708' ),
				],
			] )
			->caller( __METHOD__ )
			->execute();

		$tempUserCreator = $this->getServiceContainer()->getTempUserCreator();
		$result1 = $tempUserCreator->create( '~check-user-test-1', new FauxRequest() );
		$result2 = $tempUserCreator->create( '~check-user-test-2', new FauxRequest() );
		$this->assertTrue( $result1->isGood() && $result2->isGood() );

		self::$tempUser1 = $result1->getUser();
		self::$tempUser2 = $result2->getUser();

		$testGlobalUser = $this->getTestUser();

		$centralAuthUser = new CentralAuthTestUser(
			$testGlobalUser->getUser()->getName(),
			$testGlobalUser->getPassword()
		);
		$centralAuthUser->save( $this->getDb() );
		self::$testGlobalUser = $testGlobalUser->getUser();

		$this->populateLogTable();
	}

	private function getObjectUnderTest(
		array $overrides = []
	): CheckUserUserInfoCardService {
		$services = $this->getServiceContainer();
		return new CheckUserUserInfoCardService(
			$services->getService( 'GrowthExperimentsUserImpactLookup' ),
			$services->getExtensionRegistry(),
			$services->getUserRegistrationLookup(),
			$services->getUserGroupManager(),
			$overrides[ 'CheckUserGlobalContributionsLookup' ] ??
				$services->get( 'CheckUserGlobalContributionsLookup' ),
			$services->getConnectionProvider(),
			$services->getStatsFactory(),
			$overrides[ 'CheckUserPermissionManager' ] ??
				$services->get( 'CheckUserPermissionManager' ),
			$services->getUserFactory(),
			$services->getUserEditTracker(),
			$services->get( 'CheckUserTemporaryAccountsByIPLookup' ),
			RequestContext::getMain(),
			$services->getTitleFactory(),
			$services->getGenderCache(),
			$overrides[ 'TempUserConfig' ] ??
				$services->getTempUserConfig(),
			new ServiceOptions(
				CheckUserUserInfoCardService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getCentralIdLookup()
		);
	}

	public function testExecute() {
		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
		ConvertibleTimestamp::setFakeTime( '20240606060708' );
		$page = $this->getNonexistingTestPage();
		$user = self::$testUser->getUser();
		$this->assertStatusGood(
			$this->editPage( $page, 'test', '', NS_MAIN, $user )
		);
		// Run deferred updates, to ensure that globalEditCount gets populated in CentralAuth.
		$this->runDeferredUpdates();

		$this->setService( 'CheckUserGlobalContributionsLookup', function () use ( $user ) {
			$gcLookupMock = $this->createMock( CheckUserGlobalContributionsLookup::class );
			$gcLookupMock
				->method( 'getActiveWikisVisibleToUser' )
				->willReturn( [ 'enwiki', 'dewiki', 'mkwiki', 'nonexistentwiki' ] );

			return $gcLookupMock;
		} );

		$this->setWikiFarm();

		$userInfo = $this->getObjectUnderTest()->getUserInfo(
			$this->getTestUser()->getAuthority(),
			$user
		);
		$this->assertSame( 1, $userInfo[ 'totalEditCount' ] );
		// TODO: Fix this test so that we assert that the globalEditCount is 1.
		$this->assertArrayHasKey( 'globalEditCount', $userInfo );
		$this->assertSame( 0, $userInfo[ 'thanksGiven' ] );
		$this->assertSame( 0, $userInfo[ 'thanksReceived' ] );
		$this->assertSame( 1, current( $userInfo[ 'editCountByDay' ] ), 'Edit count for the current day is 1' );
		$this->assertSame( 0, $userInfo['revertedEditCount'] );
		$this->assertSame( $user->getName(), $userInfo['name'] );
		$this->assertSame( $this->getServiceContainer()->getGenderCache()->getGenderOf( $user ), $userInfo['gender'] );
		$this->assertArrayHasKey( 'localRegistration', $userInfo );
		$this->assertArrayHasKey( 'firstRegistration', $userInfo );
		$this->assertSame( '<strong>Groups</strong>: Bureaucrats, Administrators', $userInfo['groups'] );
		$this->assertSame(
			[
				'dewiki' => 'https://de.wikipedia.org/wiki/Special:Contributions/' . $user->getName(),
				'enwiki' => 'https://en.wikipedia.org/wiki/Special:Contributions/' . $user->getName(),
				'mkwiki' => 'https://mk.wikipedia.org/wiki/Special:Contributions/' . $user->getName(),
			],
			$userInfo['activeWikis']
		);

		$this->setService( 'CheckUserGlobalContributionsLookup', static function () use ( $user ) {
			return null;
		} );
		$userInfoWithoutGlobalContributionsLookup = $this->getObjectUnderTest()->getUserInfo(
			$this->getTestUser()->getAuthority(),
			$user
		);
		$this->assertSame( [], $userInfoWithoutGlobalContributionsLookup['activeWikis'] );
		unset( $userInfo['activeWikis'] );
		unset( $userInfoWithoutGlobalContributionsLookup['activeWikis'] );
		$this->assertArrayEquals( $userInfo, $userInfoWithoutGlobalContributionsLookup );
	}

	public function testUserImpactIsEmpty() {
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
		$this->overrideMwServices(
			null,
			[ 'GrowthExperimentsUserImpactLookup' => function () {
				$mock = $this->createMock( ComputedUserImpactLookup::class );
				$mock->method( 'getUserImpact' )->willReturn( null );
				return $mock;
			} ]
		);
		$userInfo = $this->getObjectUnderTest()->getUserInfo(
			$this->getTestUser()->getAuthority(),
			$this->getTestUser()->getUser()
		);
		$this->assertArrayNotHasKey( 'thanksGiven', $userInfo );
		$this->assertArrayHasKey( 'name', $userInfo );
	}

	/**
	 * @dataProvider userImpactDataPointsAreIncludedDataProvider
	 */
	public function testUserImpactDataPointsAreIncluded(
		array $expectedKeys,
		array $expectedMissingKeys,
		array $expectedData,
		bool $hasUserImpact,
		int $totalEditsCount,
		int $givenThanksCount,
		int $receivedThanksCount,
		int $revertedEditCount,
		int $totalArticlesCreatedCount,
		?int $lastEditTimestamp,
		array $editCountByDay
	): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );

		$performer = $this->getTestUser()->getAuthority();
		$target = $this->getTestUser()->getUserIdentity();

		$userImpactMock = $this->createMock( UserImpact::class );
		$userImpactLookup = $this->createMock( ComputedUserImpactLookup::class );
		$userImpactLookup
			->method( 'getUserImpact' )
			->with( $target )
			->willReturn( $hasUserImpact ? $userImpactMock : null );

		$impactValues = [
			'getTotalEditsCount' => $totalEditsCount,
			'getGivenThanksCount' => $givenThanksCount,
			'getReceivedThanksCount' => $receivedThanksCount,
			'getEditCountByDay' => $editCountByDay,
			'getRevertedEditCount' => $revertedEditCount,
			'getTotalArticlesCreatedCount' => $totalArticlesCreatedCount,
			'getLastEditTimestamp' => $lastEditTimestamp,
		];

		foreach ( $impactValues as $method => $value ) {
			$userImpactMock
				->method( $method )
				->willReturn( $value );
		}

		$this->setService( 'GrowthExperimentsUserImpactLookup', $userImpactLookup );

		$userInfo = $this->getObjectUnderTest()->getUserInfo( $performer, $target );

		$this->assertArrayContains(
			$expectedData + [ 'name' => $target->getName() ],
			$userInfo
		);

		foreach ( $expectedKeys as $key ) {
			$this->assertArrayHasKey( $key, $userInfo );
		}
		foreach ( $expectedMissingKeys as $key ) {
			$this->assertArrayNotHasKey( $key, $userInfo );
		}
		// T401466
		if ( $userInfo['totalEditCount'] >= 1_000 ) {
			$this->assertNull( $userInfo['revertedEditCount'] );
		}
	}

	public static function userImpactDataPointsAreIncludedDataProvider(): array {
		$defaultExpectedKeys = [
			'activeLocalBlocksAllWikis',
			'activeWikis',
			'canAccessTemporaryAccountIpAddresses',
			'firstRegistration',
			'gender',
			'groups',
			'localRegistration',
			'pastBlocksOnLocalWiki',
			'userPageIsKnown',
		];
		$editCountByDay1 = [
			'2025-01-01' => 1,
			'2025-01-02' => 2,
			'2025-01-03' => 3,
			'2025-01-04' => 4,
		];
		$editCountByDay2 = [
			'2025-02-01' => 10,
			'2025-03-02' => 9,
			'2025-04-03' => 8,
			'2025-05-04' => 7,
		];

		return [
			'Without user impact data' => [
				'expectedKeys' => $defaultExpectedKeys,
				'expectedMissingKeys' => [
					'editCountByDay',
					'lastEditTimestamp',
				],
				'expectedData' => [
					'totalEditCount' => 0,
				],
				'hasUserImpact' => false,
				'totalEditsCount' => 10,
				'givenThanksCount' => 50,
				'receivedThanksCount' => 100,
				'revertedEditCount' => 2,
				'totalArticlesCreatedCount' => 3,
				'lastEditTimestamp' => null,
				'editCountByDay' => [],
			],
			'With user impact data and last edit timestamp available' => [
				'expectedKeys' => $defaultExpectedKeys + [
					'editCountByDay',
					'lastEditTimestamp',
				],
				'expectedMissingKeys' => [],
				'expectedData' => [
					'totalEditCount' => 1000,
					'thanksGiven' => 60,
					'thanksReceived' => 200,
					'revertedEditCount' => null,
					'newArticlesCount' => null,
					'lastEditTimestamp' => '20250101100000',
					'editCountByDay' => $editCountByDay1,
				],
				'hasUserImpact' => true,
				'totalEditsCount' => 1000,
				'givenThanksCount' => 60,
				'receivedThanksCount' => 200,
				'revertedEditCount' => 20,
				'totalArticlesCreatedCount' => 30,
				'lastEditTimestamp' => strtotime( '2025-01-01T12:00:00+0200' ),
				'editCountByDay' => $editCountByDay1,
			],
			'With user impact data but last edit timestamp unavailable' => [
				'expectedKeys' => $defaultExpectedKeys + [
					'editCountByDay',
					'lastEditTimestamp',
				],
				'expectedMissingKeys' => [],
				'expectedData' => [
					'totalEditCount' => 10,
					'thanksGiven' => 50,
					'thanksReceived' => 100,
					'revertedEditCount' => 2,
					'newArticlesCount' => 3,
					'lastEditTimestamp' => null,
					'editCountByDay' => $editCountByDay2,
				],
				'hasUserImpact' => true,
				'totalEditsCount' => 10,
				'givenThanksCount' => 50,
				'receivedThanksCount' => 100,
				'revertedEditCount' => 2,
				'totalArticlesCreatedCount' => 3,
				'lastEditTimestamp' => null,
				'editCountByDay' => $editCountByDay2,
			],
		];
	}

	public function testExecuteInvalidUser() {
		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
		// User impacts can only be retrieved for registered users
		$anonUser = $this->getServiceContainer()
			->getUserFactory()
			->newAnonymous( '1.2.3.4' );
		$userImpact = $this->getObjectUnderTest()->getUserInfo(
			$this->getTestUser()->getAuthority(),
			$anonUser
		);
		$this->assertSame( [], $userImpact );
	}

	public function testLoadingWithoutGrowthExperiments() {
		$services = $this->getServiceContainer();
		$infoCardService = new CheckUserUserInfoCardService(
			null,
			$services->getExtensionRegistry(),
			$services->getUserRegistrationLookup(),
			$services->getUserGroupManager(),
			$services->get( 'CheckUserGlobalContributionsLookup' ),
			$services->getConnectionProvider(),
			$services->getStatsFactory(),
			$services->get( 'CheckUserPermissionManager' ),
			$services->getUserFactory(),
			$services->getUserEditTracker(),
			$services->get( 'CheckUserTemporaryAccountsByIPLookup' ),
			RequestContext::getMain(),
			$services->getTitleFactory(),
			$services->getGenderCache(),
			$services->getTempUserConfig(),
			new ServiceOptions(
				CheckUserUserInfoCardService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getCentralIdLookup()
		);
		$targetUser = $this->getTestUser()->getUser();
		$userInfo = $infoCardService->getUserInfo(
			$this->getTestUser()->getAuthority(), $targetUser
		);
		$this->assertArrayContains( [
			'name' => $targetUser->getName(),
			'groups' => '',
			'totalEditCount' => 0,
			'activeWikis' => [],
			'pastBlocksOnLocalWiki' => 0,
		], $userInfo );
		$this->assertArrayContains( [ 'activeLocalBlocksAllWikis' => 0 ], $userInfo );
		$this->assertArrayNotHasKey( 'thanksGiven', $userInfo );
	}

	public function testCheckUserChecksDataPoint() {
		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
		$cuUserAuthority = $this->getTestUser( [ 'checkuser' ] )->getAuthority();
		$user = $this->getTestUser()->getUser();
		$this->assertSame(
			0,
			$this->getObjectUnderTest()->getUserInfo(
				$cuUserAuthority, $user
			)['checkUserChecks']
		);
		$timestamp = (int)wfTimestamp( TS_UNIX, '20250611000000' );
		$olderTimestamp = (int)wfTimestamp( TS_UNIX, '20250411000000' );
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_log' )
			->rows( [
				[
					'cul_target_text' => $user->getName(),
					'cul_target_id' => $user->getId(),
					'cul_timestamp' => $this->getDb()->timestamp( $timestamp ),
					'cul_type' => 'userips',
					'cul_reason_id' => 1,
					'cul_reason_plaintext_id' => 2,
					'cul_actor' => $user->getActorId(),
				],
				[
					'cul_target_text' => $user->getName(),
					'cul_target_id' => $user->getId(),
					'cul_timestamp' => $this->getDb()->timestamp( $olderTimestamp ),
					'cul_type' => 'userips',
					'cul_reason_id' => 1,
					'cul_reason_plaintext_id' => 2,
					'cul_actor' => $user->getActorId(),
				],
			] )
			->caller( __METHOD__ )
			->execute();

		$result = $this->getObjectUnderTest()->getUserInfo(
			$cuUserAuthority, $user
		);
		$this->assertSame(
			2,
			$result['checkUserChecks']
		);
		$this->assertSame(
			$timestamp,
			(int)wfTimestamp(
				TS_UNIX,
				$result['checkUserLastCheck']
			)
		);
		// User without checkuser-log permission should not see any checkUser related output.
		$result = $this->getObjectUnderTest()->getUserInfo(
			$user, $user
		);
		$this->assertArrayNotHasKey(
			'checkuserChecks',
			$result
		);
		$this->assertArrayNotHasKey(
			'checkUserLastCheck',
			$result
		);
	}

	/** @dataProvider provideBlockLogDelete */
	public function testGetPastBlocksOnLocalWiki( int $logDeleted, array $userRights, bool $canSee ) {
		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
		$user = $this->getTestUser()->getUser();
		$this->overrideUserPermissions( $user, $userRights );
		$this->assertSame(
			0,
			$this->getObjectUnderTest()->getUserInfo( $user, $user )['pastBlocksOnLocalWiki']
		);
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'logging' )
			->rows( [
				[
					'log_actor' => $user->getActorId(),
					'log_comment_id' => 1,
					'log_params' => '',
					'log_type' => 'block',
					'log_action' => 'block',
					'log_namespace' => NS_USER,
					'log_title' => str_replace( ' ', '_', $user->getName() ),
					'log_deleted' => $logDeleted,
				],
			] )
			->caller( __METHOD__ )
			->execute();

		$this->assertSame( $canSee ? 1 : 0,
			$this->getObjectUnderTest()->getUserInfo( $user, $user )['pastBlocksOnLocalWiki']
		);
	}

	public static function provideBlockLogDelete() {
		return [
			'Log not deleted' => [
				'logDeleted' => 0,
				'userRights' => [],
				'canSee' => true,
			],
			'Log action deleted, unprivileged user' => [
				'logDeleted' => LogPage::DELETED_ACTION,
				'userRights' => [],
				'canSee' => false,
			],
			'Log action suppressed, unprivileged user' => [
				'logDeleted' => LogPage::SUPPRESSED_ACTION,
				'userRights' => [],
				'canSee' => false,
			],
			'Log action deleted, has (deletedhistory)' => [
				'logDeleted' => LogPage::DELETED_ACTION,
				'userRights' => [ 'deletedhistory' ],
				'canSee' => true,
			],
			'Log action suppressed, has (deletedhistory)' => [
				'logDeleted' => LogPage::SUPPRESSED_ACTION,
				'userRights' => [ 'deletedhistory' ],
				'canSee' => false,
			],
			'Log action suppressed, has (deletedhistory) and (viewsuppressed)' => [
				'logDeleted' => LogPage::SUPPRESSED_ACTION,
				'userRights' => [ 'deletedhistory', 'viewsuppressed' ],
				'canSee' => true,
			],
		];
	}

	/** @dataProvider provideBlockLogDeleteWithSuppression */
	public function testGetBlocksOnLocalWikiWithSuppression( int $logDeleted, array $userRights, bool $canSee ) {
		$user = $this->getTestUser()->getUser();
		$this->overrideUserPermissions( $user, $userRights );
		$this->assertSame(
			0,
			$this->getObjectUnderTest()->getUserInfo(
				$user, $user
			)['pastBlocksOnLocalWiki']
		);
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'logging' )
			->row( [
				'log_actor' => $user->getActorId(),
				'log_comment_id' => 1,
				'log_params' => '',
				'log_type' => 'suppress',
				'log_action' => 'block',
				'log_namespace' => NS_USER,
				'log_title' => str_replace( ' ', '_', $user->getName() ),
				'log_deleted' => $logDeleted,
			] )
			->caller( __METHOD__ )
			->execute();

		$this->assertSame(
			$canSee ? 1 : 0,
			$this->getObjectUnderTest()->getUserInfo(
				$user, $user
			)['pastBlocksOnLocalWiki'],
			'Testing block in suppress log'
		);

		// Add a public block log entry; other visibility levels are tested in testGetPastBlocksOnLocalWiki
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'logging' )
			->row( [
				'log_actor' => $user->getActorId(),
				'log_comment_id' => 1,
				'log_params' => '',
				'log_type' => 'block',
				'log_action' => 'block',
				'log_namespace' => NS_USER,
				'log_title' => str_replace( ' ', '_', $user->getName() ),
			] )
			->caller( __METHOD__ )
			->execute();

		$this->assertSame(
			$canSee ? 2 : 1,
			$this->getObjectUnderTest()->getUserInfo(
				$user, $user
			)['pastBlocksOnLocalWiki'],
			'Testing block in both logs'
		);
	}

	public static function provideBlockLogDeleteWithSuppression() {
		return [
			'Unprivileged user, log is not deleted' => [
				'logDeleted' => 0,
				'userRights' => [],
				'canSee' => false,
			],
			'User has (suppressionlog), log is not deleted' => [
				'logDeleted' => 0,
				'userRights' => [ 'suppressionlog' ],
				'canSee' => true,
			],
			'User has (suppressionlog), log is deleted' => [
				'logDeleted' => LogPage::SUPPRESSED_ACTION,
				'userRights' => [ 'suppressionlog' ],
				'canSee' => false,
			],
			'User has (suppressionlog), (viewsuppressed) and (deletedhistory), log is deleted' => [
				'logDeleted' => LogPage::SUPPRESSED_ACTION,
				'userRights' => [ 'suppressionlog', 'viewsuppressed', 'deletedhistory' ],
				'canSee' => true,
			],
		];
	}

	public function testCanAccessTemporaryAccountIPAddresses() {
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$this->setGroupPermissions( 'sysop', 'checkuser-temporary-account', true );
		$authority = $this->getTestUser( [ 'checkuser' ] )->getAuthority();
		$userOptionsManager->setOption(
			$authority->getUser(),
			'checkuser-temporary-account-enable',
			'1'
		);
		$userOptionsManager->saveOptions( $authority->getUser() );

		$user = $this->getTestSysop()->getUser();
		$userOptionsManager->setOption(
			$user,
			'checkuser-temporary-account-enable',
		'1'
		);
		$userOptionsManager->saveOptions( $user );
		$result = $this->getObjectUnderTest( [
			'CheckUserGlobalContributionsLookup' => $this->mockContributionsLookup(),
		] )->getUserInfo(
			$authority, $user
		);
		$this->assertSame( true, $result['canAccessTemporaryAccountIpAddresses'] );

		$newUser = $this->getTestUser( [ 'noaccess' ] )->getUser();
		$result = $this->getObjectUnderTest( [
			'CheckUserGlobalContributionsLookup' => $this->mockContributionsLookup(),
		] )->getUserInfo(
			$newUser, $user
		);
		$this->assertSame( false, $result['canAccessTemporaryAccountIpAddresses'] );
	}

	/**
	 * @dataProvider provideUserPageIsKnown
	 */
	public function testUserPageIsKnown(
		bool $PageIsKnown,
		bool $knownViaHook,
		bool $expected
	) {
		// T399252
		$this->clearHook( 'TitleIsAlwaysKnown' );

		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
		$user = $this->getTestUser()->getUser();

		// Simulate the case where a page does not exist but has meaningful content due to an extension
		// (T396304).
		if ( $knownViaHook ) {
			$this->setTemporaryHook(
				'TitleIsAlwaysKnown',
				static fn ( Title $title, ?bool &$isKnown ) => $isKnown = $title->equals( $user->getUserPage() ),
			);
		}

		if ( $PageIsKnown ) {
			$this->getExistingTestPage( $user->getUserPage() );
		} else {
			$this->getNonexistingTestPage( $user->getUserPage() );
		}

		$userInfo = $this->getObjectUnderTest()->getUserInfo(
			$this->getTestUser()->getAuthority(),
			$user
		);

		$this->assertArrayHasKey( 'userPageIsKnown', $userInfo );
		$this->assertSame( $expected, $userInfo['userPageIsKnown'] );
	}

	public static function provideUserPageIsKnown(): iterable {
		yield 'nonexistent page' => [ false, false, false ];
		yield 'existing page' => [ true, false, true ];
		yield 'page known via hook' => [ false, true, true ];
	}

	/** @dataProvider provideExecuteWhenSpecialCentralAuthUrlDefined */
	public function testExecuteWhenSpecialCentralAuthUrlDefined( $centralWikiId, $expectedUrlWithoutUsername ) {
		$this->overrideConfigValue( 'CheckUserUserInfoCardCentralWikiId', $centralWikiId );
		$this->setWikiFarm();

		$targetUser = $this->getTestUser()->getUser();

		$userInfo = $this->getObjectUnderTest()->getUserInfo( $this->mockRegisteredNullAuthority(), $targetUser );

		$this->assertArrayHasKey( 'specialCentralAuthUrl', $userInfo );
		$this->assertSame(
			$expectedUrlWithoutUsername . '/' . str_replace( ' ', '_', $targetUser->getName() ),
			$userInfo['specialCentralAuthUrl']
		);
	}

	public static function provideExecuteWhenSpecialCentralAuthUrlDefined(): array {
		return [
			'Central wiki is defined' => [ 'dewiki', 'https://de.wikipedia.org/wiki/Special:CentralAuth' ],
		];
	}

	/** @dataProvider provideExecuteWhenSpecialCentralAuthUrlDefinedAsLocalWiki */
	public function testExecuteWhenSpecialCentralAuthUrlDefinedAsLocalWiki( $centralWikiId ) {
		$expectedUrlWithoutUsername = SpecialPage::getTitleFor( 'CentralAuth' )->getLocalURL();
		$this->testExecuteWhenSpecialCentralAuthUrlDefined( $centralWikiId, $expectedUrlWithoutUsername );
	}

	public static function provideExecuteWhenSpecialCentralAuthUrlDefinedAsLocalWiki(): array {
		return [
			'Central wiki is unrecognised' => [ 'dewikiabc' ],
			'Central wiki is false' => [ false ],
		];
	}

	public function testProvidesIPRevealData(): void {
		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );

		$sut = $this->getObjectUnderTest( [
			'CheckUserGlobalContributionsLookup' => $this->mockContributionsLookup(),
		] );

		// A user with access to the IP reveal log (checkuser-temporary-account-log
		// permission) but that can't see deleted history.
		$performer = $this->mockRegisteredAuthorityWithPermissions( [
			'checkuser-temporary-account-log',
			'checkuser-temporary-account-no-preference',
		] );

		$userInfo1 = $sut->getUserInfo( $performer, self::$tempUser1 );
		$this->assertArrayContains( [
				'name' => self::$tempUser1->getUser()->getName(),
				'groups' => '',
				'numberOfIpReveals' => 2,
				'ipRevealLastCheck' => '20250102030408',
			],
			$userInfo1
		);

		$userInfo2 = $sut->getUserInfo( $performer, self::$tempUser2 );
		$this->assertArrayContains( [
				'name' => self::$tempUser2->getUser()->getName(),
				'groups' => '',
				'numberOfIpReveals' => 3,
				'ipRevealLastCheck' => '20250102030513',
			],
			$userInfo2
		);
	}

	/**
	 * @dataProvider skipsIPRevealDataWhenUnavailableDataProvider
	 */
	public function testSkipsIPRevealDataWhenUnavailable(
		bool $expectsToSkipIPReveal,
		bool $canAccessTemporaryAccountIPAddresses,
		bool $canAccessIPRevealLog,
		bool $tempUsersEnabled,
		bool $targetIsATemporaryAccount
	): void {
		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );

		$permissions = [];

		if ( $canAccessTemporaryAccountIPAddresses ) {
			// Grant the permissions making CheckUserPermissionManager to return
			// true for canAccessTemporaryAccountIPAddresses().
			$permissions = [
				'checkuser-temporary-account-no-preference',
				'checkuser-temporary-account-log',
			];
		}

		if ( $canAccessIPRevealLog ) {
			$permissions[] = 'checkuser-temporary-account-log';
		}

		if ( !$tempUsersEnabled ) {
			$this->disableAutoCreateTempUser();
		} else {
			$this->enableAutoCreateTempUser();
		}

		$this->assertEquals(
			$tempUsersEnabled,
			$this->getServiceContainer()->getTempUserConfig()->isEnabled()
		);

		$target = $targetIsATemporaryAccount ?
			self::$tempUser1->getUser() :
			self::$testUser;

		$performer = $this->mockRegisteredAuthorityWithPermissions( $permissions );

		$this->assertNotEquals(
			$target->getName(),
			$performer->getUser()->getName(),
			'Ensure the performer is not checking its own data'
		);

		$sut = $this->getObjectUnderTest( [
			'CheckUserGlobalContributionsLookup' => $this->mockContributionsLookup(),
		] );

		$result = $sut->getUserInfo( $performer, $target );

		if ( $expectsToSkipIPReveal ) {
			$this->assertArrayNotHasKey( 'numberOfIpReveals', $result );
			$this->assertArrayNotHasKey( 'ipRevealLastCheck', $result );
		} else {
			$this->assertArrayHasKey( 'numberOfIpReveals', $result );
			$this->assertArrayHasKey( 'ipRevealLastCheck', $result );
		}
	}

	public static function skipsIPRevealDataWhenUnavailableDataProvider(): array {
		return [
			'Missing all permissions' => [
				'expectsToSkipIPReveal' => true,
				'canAccessTemporaryAccountIPAddresses' => false,
				'canAccessIPRevealLog' => false,
				'tempUsersEnabled' => true,
				'targetIsATemporaryAccount' => true,
			],
			// checkuser-temporary-account-log would still grant access
			'Missing Temp Account IP Addresses permissions' => [
				'expectsToSkipIPReveal' => false,
				'canAccessTemporaryAccountIPAddresses' => false,
				'canAccessIPRevealLog' => true,
				'tempUsersEnabled' => true,
				'targetIsATemporaryAccount' => true,
			],
			// canAccessTemporaryAccountIPAddresses would still grant access
			'Missing permission: checkuser-temporary-account-log' => [
				'expectsToSkipIPReveal' => false,
				'canAccessTemporaryAccountIPAddresses' => true,
				'canAccessIPRevealLog' => false,
				'tempUsersEnabled' => true,
				'targetIsATemporaryAccount' => true,
			],
			'Temp users disabled' => [
				'expectsToSkipIPReveal' => true,
				'canAccessTemporaryAccountIPAddresses' => true,
				'canAccessIPRevealLog' => true,
				'tempUsersEnabled' => false,
				'targetIsATemporaryAccount' => true,
			],
			'Target is a non-temp user' => [
				'expectsToSkipIPReveal' => true,
				'canAccessTemporaryAccountIPAddresses' => true,
				'canAccessIPRevealLog' => true,
				'tempUsersEnabled' => true,
				'targetIsATemporaryAccount' => false,
			],
			'Has permissions, temp users enabled, target is a temp user' => [
				'expectsToSkipIPReveal' => false,
				'canAccessTemporaryAccountIPAddresses' => true,
				'canAccessIPRevealLog' => true,
				'tempUsersEnabled' => true,
				'targetIsATemporaryAccount' => true,
			],
		];
	}

	/**
	 * @dataProvider doesntCountHiddenActionsDataProvider
	 */
	public function testDoesntCountHiddenActions(
		int $expectedNumberOfIpReveals,
		bool $deletedHistoryAllowed,
		bool $suppressedRevisionAllowed
	): void {
		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );

		$sut = $this->getObjectUnderTest( [
			'CheckUserGlobalContributionsLookup' => $this->mockContributionsLookup(),
		] );

		$performerPermissions = [
			'checkuser-log',
			'checkuser-temporary-account-log',
			'checkuser-temporary-account-no-preference',
		];

		if ( $suppressedRevisionAllowed ) {
			$performerPermissions = array_merge(
				[ 'suppressrevision', 'viewsuppressed' ],
				$performerPermissions
			);
		}

		if ( $deletedHistoryAllowed ) {
			$performerPermissions[] = 'deletedhistory';
		}

		$performer = $this->mockRegisteredAuthorityWithPermissions(
			$performerPermissions
		);

		$result = $sut->getUserInfo( $performer, self::$tempUser1->getUser() );

		$this->assertArrayHasKey( 'numberOfIpReveals', $result );
		$this->assertEquals(
			$expectedNumberOfIpReveals,
			$result[ 'numberOfIpReveals' ]
		);
	}

	public static function doesntCountHiddenActionsDataProvider(): iterable {
		return [
			'Has deletedHistory and suppressedRevision access' => [
				'expectedNumberOfIpReveals' => 4,
				'deletedHistoryAllowed' => true,
				'suppressedRevisionAllowed' => true,
			],
			'Has deletedHistory access' => [
				'expectedNumberOfIpReveals' => 3,
				'deletedHistoryAllowed' => true,
				'suppressedRevisionAllowed' => false,
			],
			'Has suppressRevision access' => [
				'expectedNumberOfIpReveals' => 2,
				'deletedHistoryAllowed' => false,
				'suppressedRevisionAllowed' => true,
			],
			'Does not have access to deleted logs' => [
				'expectedNumberOfIpReveals' => 2,
				'deletedHistoryAllowed' => false,
				'suppressedRevisionAllowed' => false,
			],
		];
	}

	/** @dataProvider provideGlobalRestrictions */
	public function testGlobalRestrictions(
		bool $lockUser,
		bool $globallyBlockUser,
		bool $locallyDisableGlobalBlock,
		?string $expectedResult,
		?string $expectedTimestamp
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );

		$user = self::$testGlobalUser;
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
		try {
			ConvertibleTimestamp::setFakeTime( '20250102030405' );
			$this->setUserLocked( $user, $lockUser );
			ConvertibleTimestamp::setFakeTime( '20250203040506' );
			$this->setUserGloballyBlocked( $user, $globallyBlockUser, $locallyDisableGlobalBlock );

			$userInfo = $this->getObjectUnderTest()->getUserInfo(
				$this->mockRegisteredNullAuthority(),
				$user
			);
			$this->assertSame( $expectedResult, $userInfo['globalRestrictions'] );
			$this->assertSame( $expectedTimestamp, $userInfo['globalRestrictionsTimestamp'] );
		} finally {
			// Clean up the state for other tests, just in case
			$this->setUserLocked( $user, false );
			$this->setUserGloballyBlocked( $user, false, false );
		}
	}

	public static function provideGlobalRestrictions(): array {
		return [
			'Non-restricted user' => [
				'lockUser' => false,
				'globallyBlockUser' => false,
				'locallyDisableGlobalBlock' => false,
				'expectedResult' => null,
				'expectedTimestamp' => null,
			],
			'Locked user' => [
				'lockUser' => true,
				'globallyBlockUser' => false,
				'locallyDisableGlobalBlock' => false,
				'expectedResult' => 'locked',
				'expectedTimestamp' => '20250102030405',
			],
			'Globally blocked user' => [
				'lockUser' => false,
				'globallyBlockUser' => true,
				'locallyDisableGlobalBlock' => false,
				'expectedResult' => 'blocked',
				'expectedTimestamp' => '20250203040506',
			],
			'Globally blocked user with locally disabled block' => [
				'lockUser' => false,
				'globallyBlockUser' => true,
				'locallyDisableGlobalBlock' => true,
				'expectedResult' => 'blocked-disabled',
				'expectedTimestamp' => '20250203040506',
			],
			'Globally blocked and locked user' => [
				'lockUser' => true,
				'globallyBlockUser' => true,
				'locallyDisableGlobalBlock' => false,
				'expectedResult' => 'locked',
				'expectedTimestamp' => '20250102030405',
			],
		];
	}

	/** @dataProvider provideGlobalRestrictionsBlockedAndHidden */
	public function testGlobalRestrictionsBlockedAndHidden( array $permissions, bool $shouldSee ) {
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );

		$user = self::$testGlobalUser;
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
		try {
			ConvertibleTimestamp::setFakeTime( '20250102030405' );
			$this->setUserLocked( $user, false, CentralAuthUser::HIDDEN_LEVEL_LISTS );
			ConvertibleTimestamp::setFakeTime( '20250203040506' );
			$this->setUserGloballyBlocked( $user, true, false );

			$userInfo = $this->getObjectUnderTest()->getUserInfo(
				$this->mockAnonAuthorityWithPermissions( $permissions ),
				$user
			);
			$this->assertSame( $shouldSee ? 'blocked' : null, $userInfo['globalRestrictions'] );
			$this->assertSame( $shouldSee ? '20250203040506' : null, $userInfo['globalRestrictionsTimestamp'] );
		} finally {
			// Clean up the state for other tests, just in case
			$this->setUserLocked( $user, false );
			$this->setUserGloballyBlocked( $user, false, false );
		}
	}

	public static function provideGlobalRestrictionsBlockedAndHidden(): array {
		return [
			'Viewer has centralauth-suppress right' => [
				'permissions' => [ 'centralauth-suppress' ],
				'shouldSee' => true,
			],
			'Viewer does not have centralauth-suppress right' => [
				'permissions' => [],
				'shouldSee' => false,
			],
		];
	}

	public function testAccountsOnIPsCountNotShownForRegisteredUsers() {
		$checkUserTemporaryAccountsByIPLookup = $this->createMock( CheckUserTemporaryAccountsByIPLookup::class );
		$checkUserTemporaryAccountsByIPLookup
			->expects( $this->never() )
			->method( 'getBucketedCount' );
		$userInfoCardService = $this->getObjectUnderTest( [
			'CheckUserTemporaryAccountsByIPLookup' => $checkUserTemporaryAccountsByIPLookup,
		] );
		$userInfoCardService->getUserInfo(
			$this->getTestSysop()->getAuthority(),
			$this->getTestUser()->getUserIdentity()
		);
	}

	private function setUserLocked(
		User $user,
		bool $isLocked,
		int $hiddenLevel = CentralAuthUser::HIDDEN_LEVEL_NONE
	): void {
		$context = RequestContext::getMain();
		$context->setUser( $this->getTestSysop()->getUser() );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );

		$centralAuthUser = CentralAuthUser::getInstance( $user );
		$this->assertStatusGood(
			$centralAuthUser->adminLockHide( $isLocked, $hiddenLevel, 'Test', $context )
		);
		$this->assertSame( $isLocked, $centralAuthUser->isLocked() );
	}

	private function setUserGloballyBlocked( User $user, bool $isBlocked, bool $isLocallyDisabled ): void {
		$testBlocker = $this->getTestUser( [ 'steward', 'sysop' ] )->getUser();

		$globalBlockingServices = GlobalBlockingServices::wrap( $this->getServiceContainer() );
		$globalBlockManager = $globalBlockingServices->getGlobalBlockManager();
		$globalBlockLookup = $globalBlockingServices->getGlobalBlockLookup();

		if ( $isBlocked ) {
			$globalBlockManager->block( $user->getName(), 'Test', 'infinite', $testBlocker );
		} else {
			$globalBlockManager->unblock( $user->getName(), 'Test', $testBlocker );
		}

		$id = $globalBlockLookup->getGlobalBlockId( $user->getName() );
		$this->assertSame( $isBlocked, $id !== 0 );

		// Disabling the block makes sense only if the block is present
		if ( $isBlocked ) {
			$localStatusManager = $globalBlockingServices->getGlobalBlockLocalStatusManager();
			$localStatusLookup = $globalBlockingServices->getGlobalBlockLocalStatusLookup();

			if ( $isLocallyDisabled ) {
				$localStatusManager->locallyDisableBlock( $user->getName(), 'Test', $testBlocker );
			} else {
				$localStatusManager->locallyEnableBlock( $user->getName(), 'Test', $testBlocker );
			}

			$status = $localStatusLookup->getLocalStatusInfo( $id );
			$this->assertSame( $isLocallyDisabled, $status !== false );
		}
	}

	private function populateLogTable(): void {
		$db = $this->getDb();
		$sysOpUserId = $this->getTestSysop()->getUser()->getId();
		$comment = $this->getServiceContainer()->getCommentStore()
			->createComment( $db, 'test' );

		$prototype = [
			'log_type' => TemporaryAccountLogger::LOG_TYPE,
			'log_action' => TemporaryAccountLogger::ACTION_VIEW_IPS,
			'log_actor' => $sysOpUserId,
			'log_namespace' => NS_USER,
			'log_deleted' => 0x0,
			'log_comment_id' => $comment->id,
			'log_params' => LogEntryBase::makeParamBlob( [] ),
		];
		$prototypeUser1 = array_merge(
			$prototype,
			[ 'log_title' => self::$tempUser1->getName() ]
		);
		$prototypeUser2 = array_merge(
			$prototype,
			[ 'log_title' => self::$tempUser2->getName() ]
		);

		$rows = [
			// Wrong type
			array_merge( $prototypeUser1, [
				'log_timestamp' => $db->timestamp( '20250102030404' ),
				'log_type' => 'something-else',
			] ),
			// Wrong action
			array_merge( $prototypeUser1, [
				'log_action' => 'something-else',
				'log_timestamp' => $db->timestamp( '20250102030405' ),
			] ),
			// Deleted log action
			array_merge( $prototypeUser1, [
				'log_deleted' => LogPage::DELETED_ACTION,
				'log_timestamp' => $db->timestamp( '20250102030406' ),
			] ),
			// Restricted log action
			array_merge( $prototypeUser1, [
				'log_deleted' => LogPage::SUPPRESSED_ACTION,
				'log_timestamp' => $db->timestamp( '20250102030406' ),
			] ),
			// Valid entry
			array_merge( $prototypeUser1, [
				'log_timestamp' => $db->timestamp( '20250102030407' ),
			] ),
			// Valid entry
			array_merge( $prototypeUser1, [
				'log_timestamp' => $db->timestamp( '20250102030408' ),
			] ),
			// Wrong namespace
			array_merge( $prototypeUser1, [
				'log_namespace' => NS_USER_TALK,
				'log_timestamp' => $db->timestamp( '20250102030409' ),
			] ),
			// Wrong type
			array_merge( $prototypeUser1, [
				'log_timestamp' => $db->timestamp( '20250102030410' ),
				'log_type' => 'something-else',
			] ),
			// Valid entry
			array_merge( $prototypeUser2, [
				'log_timestamp' => $db->timestamp( '20250102030510' ),
			] ),
			// Valid entry
			array_merge( $prototypeUser2, [
				'log_timestamp' => $db->timestamp( '20250102030511' ),
			] ),
			// Wrong action
			array_merge( $prototypeUser2, [
				'log_action' => 'another-action',
				'log_timestamp' => $db->timestamp( '20250102030512' ),
			] ),
			// Valid entry
			array_merge( $prototypeUser2, [
				'log_timestamp' => $db->timestamp( '20250102030513' ),
			] ),
			// Non-temp user
			array_merge( $prototype, [
				'log_timestamp' => $db->timestamp( '20250102030412' ),
				'log_title' => 'Wrong User',
			] ),
		];

		// insertInto() requires columns to be in the same order for each row,
		// but array_merge() doesn't guarantee to preserve the key order of
		// neither array
		foreach ( $rows as &$row ) {
			ksort( $row );
		}

		$db->newInsertQueryBuilder()
			->insertInto( 'logging' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	private function setWikiFarm(): void {
		$conf = new SiteConfiguration();
		$conf->settings = [
			'wgServer' => [
				'enwiki' => 'https://en.wikipedia.org',
				'dewiki' => 'https://de.wikipedia.org',
				'mkwiki' => 'https://mk.wikipedia.org',
			],
			'wgArticlePath' => [
				'enwiki' => '/wiki/$1',
				'dewiki' => '/wiki/$1',
				'mkwiki' => '/wiki/$1',
			],
		];
		$conf->suffixes = [ 'wiki' ];
		$this->setMwGlobals( 'wgConf', $conf );
	}

	/**
	 * @return (CheckUserGlobalContributionsLookup&MockObject)
	 */
	private function mockContributionsLookup(): CheckUserGlobalContributionsLookup {
		$gcLookupMock = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$gcLookupMock
			->method( 'getActiveWikisVisibleToUser' )
			->willReturn( [ 'enwiki' ] );

		return $gcLookupMock;
	}
}
