<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api;

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryTokens;
use MediaWiki\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\CheckUser\Api\CheckUser\ApiQueryCheckUserAbstractResponse;
use MediaWiki\CheckUser\Services\ApiQueryCheckUserResponseFactory;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Session\SessionManager;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use TestUser;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group API
 * @group medium
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\Api\ApiQueryCheckUser
 * @covers \MediaWiki\CheckUser\Api\CheckUser\ApiQueryCheckUserAbstractResponse
 * @covers \MediaWiki\CheckUser\Api\CheckUser\ApiQueryCheckUserActionsResponse
 * @covers \MediaWiki\CheckUser\Api\CheckUser\ApiQueryCheckUserIpUsersResponse
 * @covers \MediaWiki\CheckUser\Api\CheckUser\ApiQueryCheckUserUserIpsResponse
 * @covers \MediaWiki\CheckUser\Services\ApiQueryCheckUserResponseFactory
 */
class ApiQueryCheckUserTest extends ApiTestCase {

	use CheckUserTempUserTestTrait;

	private const INITIAL_API_PARAMS = [
		'action' => 'query',
		'list' => 'checkuser',
	];

	protected function setUp(): void {
		parent::setUp();
		// Set a fake time to avoid the tests breaking due to 'cutimecond' being a relative time.
		ConvertibleTimestamp::setFakeTime( '20230406060708' );
	}

	/**
	 * Modified version of doApiRequestWithToken
	 * that appends 'cutoken' instead of 'token'
	 * as the token type. Does not accept the
	 * auto token type. Should not need to use
	 * any token type than the csrf token type
	 * for this purpose, but does accept any
	 * named token type that doApiRequestWith
	 * Token would take.
	 *
	 * @inheritDoc
	 */
	public function doApiRequestWithToken(
		array $params, ?array $session = null,
		?Authority $performer = null, $tokenType = 'csrf', $paramPrefix = null
	) {
		// From ApiTestCase::doApiRequest() but modified
		$session = RequestContext::getMain()->getRequest()->getSessionArray();
		$sessionObj = SessionManager::singleton()->getEmptySession();

		if ( $session !== null ) {
			foreach ( $session as $key => $value ) {
				$sessionObj->set( $key, $value );
			}
		}

		// set up global environment
		if ( $performer ) {
			$legacyUser = $this->getServiceContainer()->getUserFactory()->newFromAuthority( $performer );
			$contextUser = $legacyUser;
		} else {
			$contextUser = $this->getTestUser( 'checkuser' )->getUser();
			$performer = $contextUser;
		}

		$sessionObj->setUser( $contextUser );

		$params['cutoken'] = ApiQueryTokens::getToken(
			$contextUser,
			$sessionObj,
			ApiQueryTokens::getTokenTypeSalts()[$tokenType]
		)->toString();
		return parent::doApiRequestWithToken( $params, $session, $performer, null, $paramPrefix );
	}

	public function doCheckUserApiRequest( array $params = [], ?array $session = null, ?Authority $performer = null ) {
		return $this->doApiRequestWithToken( self::INITIAL_API_PARAMS + $params, $session, $performer );
	}

	/**
	 * @param string $moduleName
	 * @return TestingAccessWrapper
	 */
	public function setUpObject( string $moduleName = '' ) {
		$services = $this->getServiceContainer();
		$main = new ApiMain( $this->apiContext, true );
		/** @var ApiQuery $query */
		$query = $main->getModuleManager()->getModule( 'query' );
		return TestingAccessWrapper::newFromObject( new ApiQueryCheckUser(
			$query, $moduleName, $services->get( 'ApiQueryCheckUserResponseFactory' )
		) );
	}

	/**
	 * @dataProvider provideTestInvalidTimeCond
	 */
	public function testInvalidTimeCond( $timeCond ) {
		$this->setExpectedApiException( 'apierror-checkuser-timelimit', 'invalidtime' );
		$this->doCheckUserApiRequest(
			[
				'curequest' => 'actions',
				'cutarget' => $this->getTestUser()->getUserIdentity()->getName(),
				'cutimecond' => $timeCond,
			]
		);
	}

	public static function provideTestInvalidTimeCond() {
		return [
			[ '-2000000000 years' ],
			[ '1 week' ],
			[ '2000000000 weeks' ],
			[ '-45 weeks ago' ],
		];
	}

	public function testMissingReasonWhenReasonRequired() {
		// enable required reason
		$this->overrideConfigValue( 'CheckUserForceSummary', true );
		$this->expectApiErrorCode( 'missingparam' );
		$this->doCheckUserApiRequest(
			[
				'curequest' => 'actions',
				'cutarget' => $this->getTestUser()->getUserIdentity()->getName()
			]
		);
	}

	public function testEmptyReasonWhenReasonRequired() {
		// enable required reason
		$this->overrideConfigValue( 'CheckUserForceSummary', true );
		$this->expectApiErrorCode( 'missingdata' );
		$this->doCheckUserApiRequest(
			[
				'curequest' => 'actions',
				'cutarget' => $this->getTestUser()->getUserIdentity()->getName(),
				'cureason' => "\n",
			]
		);
	}

	/**
	 * @dataProvider provideRequiredGroupAccess
	 */
	public function testRequiredRightsByGroup( $groups, $allowed ) {
		$testUser = $this->getTestUser( $groups );
		if ( !$allowed ) {
			$this->setExpectedApiException( [ 'apierror-permissiondenied', wfMessage( 'action-checkuser' )->text() ] );
		}
		$result = $this->doCheckUserApiRequest(
			[
				'curequest' => 'actions',
				'cutarget' => $this->getTestUser()->getUserIdentity()->getName(),
			],
			null,
			$testUser->getUser()
		);
		$this->assertNotNull( $result );
	}

	public static function provideRequiredGroupAccess() {
		return [
			'No user groups' => [ '', false ],
			'Checkuser only' => [ 'checkuser', true ],
			'Checkuser and sysop' => [ [ 'checkuser', 'sysop' ], true ],
		];
	}

	/**
	 * @dataProvider provideRequiredRights
	 */
	public function testRequiredRights( $groups, $allowed ) {
		if ( $groups === "checkuser-right" ) {
			$this->setGroupPermissions(
				[ 'checkuser-right' => [ 'checkuser' => true, 'read' => true ] ]
			);
		}
		$this->testRequiredRightsByGroup( $groups, $allowed );
	}

	public static function provideRequiredRights() {
		return [
			'No user groups' => [ '', false ],
			'checkuser right only' => [ 'checkuser-right', true ],
		];
	}

	/** @dataProvider provideExpectedApiResponses */
	public function testResponseFromApi(
		$requestType, $expectedRequestTypeInResponse, $target, $timeCond, $xff, $expectedData
	) {
		ConvertibleTimestamp::setFakeTime( '20230406060708' );
		$result = $this->doCheckUserApiRequest(
			[ 'curequest' => $requestType, 'cutarget' => $target, 'cutimecond' => $timeCond, 'cuxff' => $xff ],
			null,
			$this->getTestUser( [ 'checkuser' ] )->getAuthority()
		);
		$this->assertArrayHasKey( 'query', $result[0] );
		$this->assertArrayHasKey( 'checkuser', $result[0]['query'] );
		$this->assertArrayHasKey( $expectedRequestTypeInResponse, $result[0]['query']['checkuser'] );
		$this->assertArrayEquals(
			$expectedData,
			$result[0]['query']['checkuser'][$expectedRequestTypeInResponse],
			false,
			true,
			"The result of the $requestType checkuser query is not as expected."
		);
	}

	public static function provideExpectedApiResponses() {
		return [
			'userips check on CheckUserAPITestUser1' => [
				// The value provided as curequest
				'userips',
				// The expected key name for the array which is the response (usually the same
				// as the curequest but not for 'actions')
				'userips',
				// The target of the checkuser query
				'CheckUserAPITestUser1',
				// The value used as cutimecond, representing a relative time used as the cutoff for results
				'-3 months',
				// The value used as cuxff, representing whether the provided IP target should be searched for as an
				// XFF header.
				null,
				// The expected result of the checkuser query
				[
					[
						'end' => '2023-04-05T06:07:12Z',
						'editcount' => 2,
						'start' => '2023-04-05T06:07:11Z',
						'address' => '1.2.3.4',
					],
					[
						'end' => '2023-04-05T06:07:09Z',
						'editcount' => 1,
						'address' => '127.0.0.2',
					],
					[
						'end' => '2023-04-05T06:07:07Z',
						'editcount' => 1,
						'address' => '127.0.0.1',
					],
				]
			],
			'ipusers check on 127.0.0.1/24' => [
				'ipusers', 'ipusers', '127.0.0.1/24', '-3 months', null,
				[
					[
						'name' => 'CheckUserAPITestUser2',
						'end' => '2023-04-05T06:07:10Z',
						'editcount' => 2,
						'agents' => [ 'user-agent-for-edits', 'user-agent-for-logs' ],
						'ips' => [ '127.0.0.2', '127.0.0.1' ],
						'start' => '2023-04-05T06:07:08Z'
					],
					[
						'name' => 'CheckUserAPITestUser1',
						'end' => '2023-04-05T06:07:09Z',
						'editcount' => 2,
						'agents' => [ 'user-agent-for-edits', 'user-agent-for-logs' ],
						'ips' => [ '127.0.0.2', '127.0.0.1' ],
						'start' => '2023-04-05T06:07:07Z'
					],
				]
			],
			'ipusers XFF check on 127.2.3.4' => [
				'ipusers', 'ipusers', '127.2.3.4', '-3 months', true,
				[
					[
						'name' => 'CheckUserAPITestUser1',
						'end' => '2023-04-05T06:07:12Z',
						'editcount' => 2,
						'agents' => [ 'user-agent-for-logout', 'user-agent-for-edits' ],
						'ips' => [ '1.2.3.4' ],
						'start' => '2023-04-05T06:07:11Z'
					],
				]
			],
			'actions XFF check on 127.2.3.4' => [
				'actions', 'edits', '127.2.3.4', '-3 months', true,
				[
					[
						'timestamp' => '2023-04-05T06:07:12Z',
						'ns' => 2,
						'title' => 'CheckUserAPITestUser1',
						'user' => 'CheckUserAPITestUser1',
						'ip' => '1.2.3.4',
						'agent' => 'user-agent-for-logout',
						'summary' => wfMessage( 'logentry-checkuser-private-event-user-logout' )->text(),
						'xff' => '127.2.3.4',
					],
					[
						'timestamp' => '2023-04-05T06:07:11Z',
						'ns' => 0,
						'title' => 'CheckUserTestPage',
						'user' => 'CheckUserAPITestUser1',
						'ip' => '1.2.3.4',
						'agent' => 'user-agent-for-edits',
						'summary' => 'Test1233',
						'xff' => '127.2.3.4',
					],
				]
			],
			'actions check on CheckUserAPITestUser2' => [
				'actions', 'edits', 'CheckUserAPITestUser2', '2023-04-05T06:07:09Z', true,
				[
					[
						'timestamp' => '2023-04-05T06:07:10Z',
						'ns' => 0,
						'title' => 'CheckUserTestPage',
						'user' => 'CheckUserAPITestUser2',
						'ip' => '127.0.0.2',
						'agent' => 'user-agent-for-edits',
						'summary' => 'Test1232',
						'minor' => 'm',
					],
				]
			],
			'actions on 1.2.3.5 (IP performer when temporary accounts are enabled)' => [
				'actions',
				'edits',
				'1.2.3.5',
				'-3 months',
				null,
				[
					[
						'timestamp' => '2023-04-05T06:07:13Z',
						'ns' => 2,
						'title' => 'CheckUserAPITestUser1',
						'user' => '1.2.3.5',
						'ip' => '1.2.3.5',
						'agent' => 'user-agent-for-password-reset',
						'summary' => wfMessage(
							'logentry-checkuser-private-event-password-reset-email-sent',
							[ '', '', '', 'CheckUserAPITestUser1' ]
						)->text(),
					],
				]
			],
			'ipusers on 1.2.3.5 (IP performer when temporary accounts are enabled)' => [
				'ipusers',
				'ipusers',
				'1.2.3.5',
				'-3 months',
				null,
				[
					[
						'end' => '2023-04-05T06:07:13Z',
						'editcount' => 1,
						'ips' => [ '1.2.3.5' ],
						'agents' => [ 'user-agent-for-password-reset' ],
						'name' => '1.2.3.5',
					],
				]
			],
		];
	}

	public function testActionsForHiddenUser() {
		// Block CheckUserAPITestUser1 with 'hideuser' enabled.
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$this->getServiceContainer()->getUserIdentityLookup()->getUserIdentityByName( 'CheckUserAPITestUser1' ),
				$this->getTestUser( [ 'sysop', 'suppress' ] )->getUser(),
				'infinity',
				'block to hide the test user',
				[ 'isHideUser' => true ]
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );
		// Perform an 'actions' request and verify that the hidden user is not shown in the response.
		$this->testResponseFromApi(
			'actions', 'edits', '127.2.3.4', '-3 months', true,
			[
				[
					'timestamp' => '2023-04-05T06:07:12Z',
					'ns' => 2,
					'title' => wfMessage( 'rev-deleted-user' )->text(),
					'user' => wfMessage( 'rev-deleted-user' )->text(),
					'ip' => '1.2.3.4',
					'agent' => 'user-agent-for-logout',
					'summary' => wfMessage( 'logentry-checkuser-private-event-user-logout' )->text(),
					'xff' => '127.2.3.4',
				],
				[
					'timestamp' => '2023-04-05T06:07:11Z',
					'ns' => 0,
					'title' => 'CheckUserTestPage',
					'user' => wfMessage( 'rev-deleted-user' )->text(),
					'ip' => '1.2.3.4',
					'agent' => 'user-agent-for-edits',
					'summary' => 'Test1233',
					'xff' => '127.2.3.4',
				],
			]
		);
	}

	public function testIpUsersForHiddenUser() {
		// Block CheckUserAPITestUser1 with 'hideuser' enabled.
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$this->getServiceContainer()->getUserIdentityLookup()->getUserIdentityByName( 'CheckUserAPITestUser1' ),
				$this->getTestUser( [ 'sysop', 'suppress' ] )->getUser(),
				'infinity',
				'block to hide the test user',
				[ 'isHideUser' => true ]
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );
		// Perform an 'ipusers' request and verify that the hidden user is not shown in the response.
		$this->testResponseFromApi(
			'ipusers', 'ipusers', '127.2.3.4', '-3 months', true,
			[
				[
					'name' => wfMessage( 'rev-deleted-user' )->text(),
					'end' => '2023-04-05T06:07:12Z',
					'editcount' => 2,
					'agents' => [ 'user-agent-for-logout', 'user-agent-for-edits' ],
					'ips' => [ '1.2.3.4' ],
					'start' => '2023-04-05T06:07:11Z'
				],
			]
		);
	}

	/** @dataProvider provideCuRequestTypesThatAcceptAUsernameTarget */
	public function testApiForNonExistentUserAsTarget( $requestType ) {
		$this->expectApiErrorCode( 'nosuchuser' );
		$this->doCheckUserApiRequest(
			[ 'curequest' => $requestType, 'cutarget' => 'NonExistentUser', 'cutimecond' => '-3 months' ]
		);
	}

	public static function provideCuRequestTypesThatAcceptAUsernameTarget() {
		return [
			'userips' => [ 'userips' ],
			'actions' => [ 'actions' ],
		];
	}

	/** @dataProvider provideCuRequestTypesThatAcceptAUsernameTarget */
	public function testInvalidUsername( $requestType ) {
		$this->expectApiErrorCode( 'baduser' );
		$this->doCheckUserApiRequest(
			[ 'curequest' => $requestType, 'cutarget' => '#', 'cutimecond' => '-3 months' ]
		);
	}

	public function testActionsForOverRangeIPTarget() {
		$this->expectApiErrorCode( 'invalidip' );
		$this->doCheckUserApiRequest(
			[ 'curequest' => 'actions', 'cutarget' => '1.2.3.4/2', 'cutimecond' => '-3 months' ]
		);
	}

	public function testIpUsersForInvalidIP() {
		$this->expectApiErrorCode( 'invalidip' );
		$this->doCheckUserApiRequest(
			[ 'curequest' => 'ipusers', 'cutarget' => 'Username', 'cutimecond' => '-3 months' ]
		);
	}

	public function testEditsCausesDeprecationWarning() {
		$response = $this->doCheckUserApiRequest(
			[ 'curequest' => 'edits', 'cutarget' => '127.0.0.1', 'cutimecond' => '-3 months' ]
		);
		$this->assertArrayHasKey( 'warnings', $response[0] );
		$this->assertArrayHasKey( 'checkuser', $response[0]['warnings'] );
		$this->assertArrayHasKey( 'warnings', $response[0]['warnings']['checkuser'] );
		$this->assertStringContainsString( 'curequest=actions', $response[0]['warnings']['checkuser']['warnings'] );
	}

	public function testIsWriteMode() {
		$this->assertTrue(
			$this->setUpObject()->isWriteMode(),
			'The checkuser API writes to the cu_log table so write mode is needed.'
		);
	}

	public function testMustBePosted() {
		$this->assertTrue(
			$this->setUpObject()->mustBePosted(),
			'The checkuser API, like Special:CheckUser, must be posted.'
		);
	}

	public function testNeedsToken() {
		$this->assertSame(
			'csrf',
			$this->setUpObject()->needsToken(),
			'The checkuser API requires the csrf token.'
		);
	}

	/**
	 * Tests that the function returns valid URLs.
	 * Does not test that the URL is correct as if
	 * the URL is changed in a proposed commit the
	 * reviewer should check the URL points to the
	 * right place.
	 */
	public function testGetHelpUrls() {
		$helpUrls = $this->setUpObject()->getHelpUrls();
		if ( !is_string( $helpUrls ) && !is_array( $helpUrls ) ) {
			$this->fail( 'getHelpUrls should return an array of URLs or a URL' );
		}
		if ( is_string( $helpUrls ) ) {
			$helpUrls = [ $helpUrls ];
		}
		foreach ( $helpUrls as $helpUrl ) {
			$this->assertIsArray( parse_url( $helpUrl ) );
		}
	}

	public function testGetExamplesMessages() {
		// Test that all the items in ::getExamplesMessages have keys which is a string and values which are valid
		// message keys.
		$examplesMessages = $this->setUpObject()->getExamplesMessages();
		foreach ( $examplesMessages as $query => $messageKey ) {
			$this->assertIsString(
				$query,
				'The URL query string was not as expected.'
			);
			$this->assertTrue(
				wfMessage( $messageKey )->exists(),
				"The message key $messageKey does not exist."
			);
		}
	}

	public function testExecuteOnUnrecognisedRequestTypeFromRequestFactory() {
		$this->expectApiErrorCode( 'invalidmode' );
		// Create a mock response object to return when the request factory is called
		// which returns an unrecognised request type from ::getRequestType.
		$mockResponse = $this->getMockBuilder( ApiQueryCheckUserAbstractResponse::class )
			->disableOriginalConstructor()
			->getMockForAbstractClass();
		$mockResponse->method( 'getRequestType' )
			->willReturn( 'unrecognised' );
		// Mock the ApiQueryCheckUserResponseFactory to return $mockResponse from ::newFromRequest
		$mockResponseFactory = $this->getMockBuilder( ApiQueryCheckUserResponseFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$mockResponseFactory->method( 'newFromRequest' )
			->willReturn( $mockResponse );
		// Install this mock ApiQueryCheckUserResponseFactory into the service container
		$this->setService( 'ApiQueryCheckUserResponseFactory', $mockResponseFactory );
		// Execute the API request
		$this->doCheckUserApiRequest(
			[ 'curequest' => 'actions', 'cutarget' => 'Test', 'cutimecond' => '-3 months', 'limit' => '50' ]
		);
	}

	private function createLogEntry( UserIdentity $performer, Title $page ) {
		$logEntry = new ManualLogEntry( 'phpunit', 'test' );
		$logEntry->setPerformer( $performer );
		$logEntry->setTarget( $page );
		$logEntry->setComment( 'A very good reason' );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId );
	}

	public function addDBDataOnce() {
		$this->overrideConfigValue( 'CheckUserLogLogins', true );
		// Add some testing entries to the CheckUser result tables to test the API
		// Get two testing users with pre-defined usernames and a test page with a pre-defined name
		// so that we can use them in the tests without having to store the name.
		$firstTestUser = ( new TestUser( 'CheckUserAPITestUser1' ) )->getUser();
		$secondTestUser = ( new TestUser( 'CheckUserAPITestUser2' ) )->getUser();
		$testPage = $this->getExistingTestPage( 'CheckUserTestPage' )->getTitle();
		// Clear the cu_changes and cu_log_event tables to avoid log entries created by the test users being created
		// or the page being created affecting the tests.
		$this->truncateTables( [ 'cu_changes', 'cu_log_event' ] );

		// Insert two testing log entries with each performed where one is performed by each test user
		$request = RequestContext::getMain()->getRequest();
		$request->setIP( '127.0.0.1' );
		$request->setHeader( 'User-Agent', 'user-agent-for-logs' );
		ConvertibleTimestamp::setFakeTime( '20230405060707' );
		$this->createLogEntry( $firstTestUser, $testPage );
		ConvertibleTimestamp::setFakeTime( '20230405060708' );
		$this->createLogEntry( $secondTestUser, $testPage );

		// Insert two testing edits to cu_changes with a IP as 127.0.0.2 and have one performed by each test user
		ConvertibleTimestamp::setFakeTime( '20230405060709' );
		$request->setHeader( 'User-Agent', 'user-agent-for-edits' );
		$request->setIP( '127.0.0.2' );
		$this->editPage(
			Title::newFromDBkey( 'CheckUserTestPage' ),
			'Testing1231',
			'Test1231',
			NS_MAIN,
			$firstTestUser
		);
		ConvertibleTimestamp::setFakeTime( '20230405060710' );
		// Make the second edit a minor edit to test that the API can handle this.
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromDBkey( 'CheckUserTestPage' ) );
		$updater = $page->newPageUpdater( $secondTestUser )
			->setContent( SlotRecord::MAIN, new WikitextContent( 'Testing1232' ) )
			->setFlags( EDIT_MINOR );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'Test1232' ) );
		$this->assertTrue( $updater->wasRevisionCreated() );

		// Insert one edit with a different IP and a defined XFF header
		$request->setIP( '1.2.3.4' );
		$request->setHeader( 'X-Forwarded-For', '127.2.3.4' );
		ConvertibleTimestamp::setFakeTime( '20230405060711' );
		$this->editPage(
			Title::newFromDBkey( 'CheckUserTestPage' ),
			'Testing1233',
			'Test1233',
			NS_MAIN,
			$firstTestUser
		);

		// Simulate a logout event for the first user
		$hookRunner = new HookRunner( $this->getServiceContainer()->getHookContainer() );
		ConvertibleTimestamp::setFakeTime( '20230405060712' );
		$request->setHeader( 'User-Agent', 'user-agent-for-logout' );
		$injectHtml = '';
		$hookRunner->onUserLogoutComplete(
			$this->getServiceContainer()->getUserFactory()
				->newFromUserIdentity( UserIdentityValue::newAnonymous( '1.2.3.4' ) ),
			$injectHtml,
			$firstTestUser->getName()
		);

		// Simulate a password reset request for the first user on a new IP with no XFF when temporary accounts
		// are enabled (to set setting cupe_actor to NULL).
		ConvertibleTimestamp::setFakeTime( '20230405060713' );
		$request->setIP( '1.2.3.5' );
		$request->setHeader( 'X-Forwarded-For', '' );
		$request->setHeader( 'User-Agent', 'user-agent-for-password-reset' );
		$this->enableAutoCreateTempUser();
		$hookRunner->onUser__mailPasswordInternal(
			$this->getServiceContainer()->getUserFactory()
				->newFromUserIdentity( UserIdentityValue::newAnonymous( '1.2.3.5' ) ),
			'1.2.3.5',
			$firstTestUser
		);

		// Reset the fake time to avoid any issues with other test classes. A fake time will be set before each
		// test in ::setUp.
		ConvertibleTimestamp::setFakeTime( false );
	}
}
