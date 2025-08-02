<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MailAddress;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\CheckUser\EncryptedData;
use MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler;
use MediaWiki\CheckUser\Jobs\StoreClientHintsDataJob;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Logging\LogEntryBase;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Profiler;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler
 * @group Database
 * @group CheckUser
 */
class CheckUserPrivateEventsHandlerTest extends MediaWikiIntegrationTestCase {

	use CheckUserCommonTraitTest;
	use CheckUserTempUserTestTrait;

	private function getObjectUnderTest( $entrypoint = null ): CheckUserPrivateEventsHandler {
		return new CheckUserPrivateEventsHandler(
			$this->getServiceContainer()->get( 'CheckUserInsert' ),
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getUserIdentityLookup(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getReadOnlyMode(),
			$this->getServiceContainer()->get( 'UserAgentClientHintsManager' ),
			$this->getServiceContainer()->getJobQueueGroup(),
			$this->getServiceContainer()->getConnectionProvider(),
			$entrypoint
		);
	}

	/**
	 * Re-define the CheckUserInsert service to expect no calls to any of its methods.
	 * This is done to assert that no inserts to the database occur instead of having
	 * to assert a row count of zero.
	 *
	 * @return void
	 */
	private function expectNoCheckUserInsertCalls() {
		$this->setService( 'CheckUserInsert', function () {
			return $this->createNoOpMock( CheckUserInsert::class );
		} );
	}

	public function testUserLogoutComplete() {
		$this->overrideConfigValue( 'CheckUserLogLogins', true );
		$testUser = $this->getTestUser()->getUserIdentity();
		$html = '';
		$this->getObjectUnderTest()->onUserLogoutComplete(
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' ),
			$html,
			$testUser->getName()
		);
		$this->assertRowCount(
			1, 'cu_private_event', 'cupe_id',
			'Should have logged the event to cu_private_event'
		);
	}

	public function testUserLogoutCompleteInvalidUser() {
		$this->expectNoCheckUserInsertCalls();
		$this->overrideConfigValue( 'CheckUserLogLogins', true );
		$html = '';
		$this->getObjectUnderTest()->onUserLogoutComplete(
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' ),
			$html,
			'Nonexisting test user1234567'
		);
	}

	private function doTestOnAuthManagerLoginAuthenticateAudit(
		AuthenticationResponse $authResp, User $userObj,
		string $userName, bool $isAnonPerformer, string $expectedLogAction,
		bool $shouldCollectClientHintsData = true
	): void {
		if ( $isAnonPerformer ) {
			$this->disableAutoCreateTempUser();
		}
		// Set a Client Hints header that we will check is stored if the authentication is a success.
		RequestContext::getMain()->getRequest()->setHeader( 'Sec-CH-UA-Bitness', '"32"' );
		$this->getObjectUnderTest()->onAuthManagerLoginAuthenticateAudit( $authResp, $userObj, $userName, [] );
		$this->runJobs( [ 'minJobs' => 0 ], [ 'type' => StoreClientHintsDataJob::TYPE ] );
		$expectedValues = [ NS_USER, $userName ];
		if ( $isAnonPerformer ) {
			$expectedValues[] = 0;
			$expectedValues[] = RequestContext::getMain()->getRequest()->getIP();
		} else {
			$expectedValues[] = $userObj->getId();
			$expectedValues[] = $userName;
		}
		$expectedValues[] = LogEntryBase::makeParamBlob( [ '4::target' => $userName ] );
		$expectedValues[] = $expectedLogAction;
		$this->newSelectQueryBuilder()
			->select( [ 'cupe_namespace', 'cupe_title', 'actor_user', 'actor_name', 'cupe_params', 'cupe_log_action' ] )
			->from( 'cu_private_event' )
			->join( 'actor', null, 'actor_id=cupe_actor' )
			->caller( __METHOD__ )
			->assertRowValue( $expectedValues );
		$actualEventId = (int)$this->newSelectQueryBuilder()
			->select( 'cupe_id' )
			->from( 'cu_private_event' )
			->caller( __METHOD__ )
			->fetchField();
		$jsConfigVars = RequestContext::getMain()->getOutput()->getJsConfigVars();
		if ( $authResp->status === AuthenticationResponse::FAIL ) {
			// If the response was that authentication failed, then expect that wgCheckUserClientHintsPrivateEventId is
			// added as a JS variable
			if ( $shouldCollectClientHintsData ) {
				$this->assertArrayHasKey( 'wgCheckUserClientHintsPrivateEventId', $jsConfigVars );
				$this->assertSame(
					$actualEventId,
					$jsConfigVars['wgCheckUserClientHintsPrivateEventId']
				);
			} else {
				$this->assertArrayNotHasKey( 'wgCheckUserClientHintsPrivateEventId', $jsConfigVars );
			}
			// Check that the method using headers was not used for failed login attempts, as we are using the API.
			$this->newSelectQueryBuilder()
				->select( '1' )
				->from( 'cu_useragent_clienthints_map' )
				->caller( __METHOD__ )
				->assertEmptyResult();
		} else {
			// If the authentication succeeded, then expect that Client Hints data is read from the HTTP headers.
			$this->assertArrayNotHasKey( 'wgCheckUserClientHintsPrivateEventId', $jsConfigVars );
			if ( $shouldCollectClientHintsData ) {
				$this->newSelectQueryBuilder()
					->select( [ 'uachm_reference_id', 'uachm_reference_type', 'uach_name', 'uach_value' ] )
					->from( 'cu_useragent_clienthints_map' )
					->join( 'cu_useragent_clienthints', null, [ 'uachm_uach_id = uach_id' ] )
					->caller( __METHOD__ )
					->assertRowValue( [
						$actualEventId, UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT, 'bitness', '32',
					] );
			} else {
				$this->newSelectQueryBuilder()
					->select( '1' )
					->from( 'cu_useragent_clienthints_map' )
					->caller( __METHOD__ )
					->assertEmptyResult();
			}
		}
	}

	/** @dataProvider provideOnAuthManagerLoginAuthenticateAudit */
	public function testOnAuthManagerLoginAuthenticateAudit(
		string $authStatus, string $expectedLogAction, bool $isAnonPerformer, array $userGroups
	) {
		$this->overrideConfigValues( [
			'CheckUserLogLogins' => true,
			'CheckUserLogSuccessfulBotLogins' => true,
		] );
		$userObj = $this->getTestUser( $userGroups )->getUser();
		$userName = $userObj->getName();
		$authResp = $this->getMockAuthenticationResponseForStatus( $authStatus, $userName );

		$this->doTestOnAuthManagerLoginAuthenticateAudit(
			$authResp, $userObj, $userName, $isAnonPerformer, $expectedLogAction
		);
	}

	public static function provideOnAuthManagerLoginAuthenticateAudit() {
		return [
			'successful login' => [ AuthenticationResponse::PASS, 'login-success', false, [] ],
			'failed login' => [ AuthenticationResponse::FAIL, 'login-failure', true, [] ],
			'successful bot login' => [ AuthenticationResponse::PASS, 'login-success', false, [ 'bot' ] ],
		];
	}

	/** @dataProvider provideOnAuthManagerLoginAuthenticateAuditWithCentralAuthInstalled */
	public function testOnAuthManagerLoginAuthenticateAuditWithCentralAuthInstalled(
		array $authFailReasons, bool $existingUser, string $expectedLogAction, bool $isAnonPerformer
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$authResp = AuthenticationResponse::newFail(
			$this->createMock( Message::class ),
			$authFailReasons
		);

		$this->overrideConfigValues( [
			'CheckUserLogLogins' => true,
			'CheckUserLogSuccessfulBotLogins' => true,
		] );
		$userObj = $existingUser
			? $this->getTestUser()->getUser()
			: $this->getServiceContainer()->getUserFactory()->newFromName( wfRandomString() );
		$userName = $userObj->getName();

		$this->doTestOnAuthManagerLoginAuthenticateAudit(
			$authResp, $userObj, $userName, $isAnonPerformer, $expectedLogAction
		);
	}

	public static function provideOnAuthManagerLoginAuthenticateAuditWithCentralAuthInstalled() {
		return [
			'failed login with correct password' => [
				// This is CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD, but cannot be referenced
				//  directly due to T321864
				[ "good password" ],
				true,
				'login-failure-with-good-password',
				true,
			],
			'failed login with the correct password but locked and no local account' => [
				// This is CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD and CentralAuthUser::AUTHENTICATE_LOCKED,
				//  respectively but cannot be referenced directly due to T321864
				[ "good password", "locked" ],
				false,
				'login-failure-with-good-password',
				true,
			],
			'failed login with correct password but locked' => [
				// This is CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD and CentralAuthUser::AUTHENTICATE_LOCKED,
				//  respectively but cannot be referenced directly due to T321864
				[ "good password", "locked" ],
				true,
				'login-failure-with-good-password',
				false,
			],
		];
	}

	public function testOnAuthManagerLoginAuthenticateAuditForFailedLoginButClientHintsDisabled() {
		$this->overrideConfigValues( [
			'CheckUserLogLogins' => true,
			'CheckUserClientHintsEnabled' => false,
		] );
		$userObj = $this->getTestUser()->getUser();
		$userName = $userObj->getName();
		$authResp = $this->getMockAuthenticationResponseForStatus( AuthenticationResponse::PASS, $userName );

		$this->doTestOnAuthManagerLoginAuthenticateAudit(
			$authResp, $userObj, $userName, false, 'login-success', false
		);
	}

	public function testOnAuthManagerLoginAuthenticateAuditForSuccessfulLoginButInvalidClientHintsHeaders() {
		$this->overrideConfigValues( [
			'CheckUserLogLogins' => true,
		] );
		$userObj = $this->getTestUser()->getUser();
		$userName = $userObj->getName();
		$authResp = $this->getMockAuthenticationResponseForStatus( AuthenticationResponse::PASS, $userName );

		// Add an invalid Client Hints header, and then expect that a warning is created because of this along with
		// no Client Hints data being saved for the event.
		RequestContext::getMain()->getRequest()->setHeader( 'Sec-CH-UA-Full-Version-List', '?0' );
		$mockLogger = $this->createMock( LoggerInterface::class );
		$mockLogger->expects( $this->once() )
			->method( 'warning' )
			->willReturnCallback( function ( $message, $context ) {
				$actualEventId = (int)$this->newSelectQueryBuilder()
					->select( 'cupe_id' )
					->from( 'cu_private_event' )
					->fetchField();
				$this->assertArrayEquals(
					[
						$actualEventId,
						[ 'Sec-CH-UA-Full-Version-List' => '?0', 'Sec-CH-UA-Bitness' => '"32"' ],
						'privatelog'
					],
					$context
				);
			} );
		$this->setLogger( 'CheckUser', $mockLogger );

		$this->doTestOnAuthManagerLoginAuthenticateAudit(
			$authResp, $userObj, $userName, false, 'login-success', false
		);
	}

	private function getMockAuthenticationResponseForStatus( $status, $user = 'test' ) {
		$req = $this->getMockForAbstractClass( AuthenticationRequest::class );
		switch ( $status ) {
			case AuthenticationResponse::PASS:
				return AuthenticationResponse::newPass( $user );
			case AuthenticationResponse::FAIL:
				return AuthenticationResponse::newFail( $this->createMock( Message::class ) );
			case AuthenticationResponse::ABSTAIN:
				return AuthenticationResponse::newAbstain();
			case AuthenticationResponse::REDIRECT:
				return AuthenticationResponse::newRedirect( [ $req ], '' );
			case AuthenticationResponse::RESTART:
				return AuthenticationResponse::newRestart( $this->createMock( Message::class ) );
			case AuthenticationResponse::UI:
				return AuthenticationResponse::newUI( [ $req ], $this->createMock( Message::class ) );
			default:
				$this->fail( 'No AuthenticationResponse mock was defined for the status ' . $status );
		}
	}

	/** @dataProvider provideOnAuthManagerLoginAuthenticateAuditNoSave */
	public function testOnAuthManagerLoginAuthenticateAuditNoSave(
		string $status, bool $validUser, array $userGroups, bool $logLogins, bool $logBots
	) {
		$this->expectNoCheckUserInsertCalls();
		$this->overrideConfigValues( [
			'CheckUserLogLogins' => $logLogins,
			'CheckUserLogSuccessfulBotLogins' => $logBots,
		] );
		if ( $validUser ) {
			$userObj = $this->getTestUser( $userGroups )->getUser();
			$userName = $userObj->getName();
		} else {
			$userObj = null;
			$userName = '';
		}
		$ret = $this->getMockAuthenticationResponseForStatus( $status, $userName );
		$this->getObjectUnderTest()->onAuthManagerLoginAuthenticateAudit( $ret, $userObj, $userName, [] );
	}

	public static function provideOnAuthManagerLoginAuthenticateAuditNoSave() {
		return [
			'invalid user' => [ AuthenticationResponse::PASS, false, [], true, true ],
			'Abstain authentication response' => [ AuthenticationResponse::ABSTAIN, true, [], true, true ],
			'Redirect authentication response' => [ AuthenticationResponse::REDIRECT, true, [], true, true ],
			'UI authentication response' => [ AuthenticationResponse::UI, true, [], true, true ],
			'Restart authentication response' => [ AuthenticationResponse::RESTART, true, [], true, true ],
			'LogLogins set to false' => [ AuthenticationResponse::PASS, true, [], false, true ],
			'Successful authentication for bot account with wgCheckUserLogSuccessfulBotLogins set to false' => [
				AuthenticationResponse::PASS, true, [ 'bot' ], true, false,
			],
		];
	}

	/** @dataProvider provideOnEmailUserInvalidUsernames */
	public function testOnEmailUserForInvalidUsername( $toUsername, $fromUsername ) {
		$this->expectNoCheckUserInsertCalls();
		// Call the method under test
		$to = new MailAddress( 'test@test.com', $toUsername );
		$from = new MailAddress( 'testing@test.com', $fromUsername );
		$subject = 'Test';
		$text = 'Test';
		$error = false;
		$this->getObjectUnderTest()->onEmailUser( $to, $from, $subject, $text, $error );
		// Run DeferredUpdates as the private event is created in a DeferredUpdate.
		DeferredUpdates::doUpdates();
	}

	public static function provideOnEmailUserInvalidUsernames() {
		return [
			'Invalid from username' => [ 'ValidToUsername', 'Template:InvalidFromUsername#test' ],
			'Invalid to username' => [ 'Template:InvalidToUsername#test', 'ValidToUsername' ],
		];
	}

	private function commonOnEmailUser(
		MailAddress $to, MailAddress $from, array $cuPrivateWhere, bool $shouldCollectClientHintsData = true
	) {
		// Call the method under test with the provided arguments and some mock arguments that are unused.
		$subject = 'Test subject';
		$text = 'Test text';
		$error = false;
		$this->getObjectUnderTest()->onEmailUser( $to, $from, $subject, $text, $error );
		// Assert that the row was inserted with the correct data.
		$actualPrivateEventIds = $this->newSelectQueryBuilder()
			->select( 'cupe_id' )
			->from( 'cu_private_event' )
			->where( array_merge( $cuPrivateWhere, [ 'cupe_namespace' => NS_USER ] ) )
			->caller( __METHOD__ )
			->fetchFieldValues();
		$this->assertCount( 1, $actualPrivateEventIds );
		// Check that the inserted cu_private_event ID is added to the JS config variables if Client Hints data is
		// being collected.
		$jsConfigVars = RequestContext::getMain()->getOutput()->getJsConfigVars();
		if ( $shouldCollectClientHintsData ) {
			$this->assertArrayHasKey( 'wgCheckUserClientHintsPrivateEventId', $jsConfigVars );
			$this->assertSame(
				(int)$actualPrivateEventIds[0],
				$jsConfigVars['wgCheckUserClientHintsPrivateEventId']
			);
		} else {
			$this->assertArrayNotHasKey( 'wgCheckUserClientHintsPrivateEventId', $jsConfigVars );
		}
	}

	public function testOnEmailUserFrom() {
		// Verify that the user who sent the email is marked as the performer and their userpage is the title
		// associated with the event.
		$userTo = $this->getTestUser()->getUserIdentity();
		$userFrom = $this->getTestSysop()->getUser();
		$this->commonOnEmailUser(
			new MailAddress( 'test@test.com', $userTo->getName() ),
			new MailAddress( 'testing@test.com', $userFrom->getName() ),
			[ 'cupe_actor' => $userFrom->getActorId(), 'cupe_title' => $userFrom->getName() ]
		);
	}

	/** @covers \MediaWiki\CheckUser\EncryptedData */
	public function testOnEmailWithCUPublicKeyDefined() {
		if ( !in_array( 'rc4', openssl_get_cipher_methods() ) ) {
			$this->markTestSkipped( 'Storing encrypted email data requires the RC4 cipher' );
		}

		// Generate a private/public key-pair to use in the test. This is needed to allow checking that the encrypted
		// data that is stored in the database can be decrypted and the decrypted data is correct.
		$privateKey = openssl_pkey_new( [
			'digest_alg' => 'rc4', 'private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA,
		] );
		$this->overrideConfigValue( 'CUPublicKey', openssl_pkey_get_details( $privateKey )['key'] );
		// Run the method under test.
		$userTo = $this->getTestUser()->getUser();
		$userFrom = $this->getTestSysop()->getUserIdentity();
		$this->commonOnEmailUser(
			new MailAddress( 'test@test.com', $userTo->getName() ),
			new MailAddress( 'testing@test.com', $userFrom->getName() ),
			[]
		);
		// Load the EncryptedData object from the database.
		$encryptedData = unserialize(
			$this->newSelectQueryBuilder()
				->select( 'cupe_private' )
				->from( 'cu_private_event' )
				->caller( __METHOD__ )
				->fetchField()
		);
		$this->assertInstanceOf( EncryptedData::class, $encryptedData );
		// Check that the plaintext data remains the same after an encryption and decryption cycle.
		// This also checks that the plaintext data being encrypted by the method under test is as expected.
		$this->assertSame(
			$userTo->getEmail() . ':' . $userTo->getId(),
			$encryptedData->getPlaintext( $privateKey ),
			'The encrypted data for a user email event could not be decrypted or was incorrect.'
		);
	}

	public function testOnEmailUserLogParams() {
		// Also use this test to check that Client Hints are only attempted to be collected if the feature is enabled.
		$this->overrideConfigValue( 'CheckUserClientHintsEnabled', false );
		// Verify that the log params for the email event contains a hash.
		$userTo = $this->getTestUser()->getUser();
		$userFrom = $this->getTestSysop()->getUserIdentity();
		$this->commonOnEmailUser(
			new MailAddress( 'test@test.com', $userTo->getName() ),
			new MailAddress( 'testing@test.com', $userFrom->getName() ),
			[
				$this->getDb()->expr( 'cupe_params', IExpression::LIKE, new LikeValue(
					$this->getDb()->anyString(),
					'4::hash',
					$this->getDb()->anyString()
				) )
			],
			false
		);
	}

	public function testOnUser__mailPasswordInternal() {
		$performer = $this->getTestUser()->getUser();
		$account = $this->getTestSysop()->getUser();
		$this->getObjectUnderTest()->onUser__mailPasswordInternal( $performer, 'IGNORED', $account );
		$this->assertRowCount(
			1, 'cu_private_event', 'cupe_id',
			'The row was not inserted or was inserted with the wrong data',
			[
				'cupe_actor' => $performer->getActorId(),
				'cupe_namespace' => NS_USER,
				'cupe_title' => $account->getName(),
				$this->getDb()->expr( 'cupe_params', IExpression::LIKE, new LikeValue(
					$this->getDb()->anyString(),
					'"4::receiver"',
					$this->getDb()->anyString(),
					$account->getName(),
					$this->getDb()->anyString()
				) )
			]
		);
		// Add wgCheckUserClientHintsPrivateEventId so that Client Hints data is sent
		$actualEventId = (int)$this->newSelectQueryBuilder()
			->select( 'cupe_id' )
			->from( 'cu_private_event' )
			->caller( __METHOD__ )
			->fetchField();
		$jsConfigVars = RequestContext::getMain()->getOutput()->getJsConfigVars();
		$this->assertArrayHasKey( 'wgCheckUserClientHintsPrivateEventId', $jsConfigVars );
		$this->assertSame(
			$actualEventId,
			$jsConfigVars['wgCheckUserClientHintsPrivateEventId']
		);
	}

	/** @dataProvider provideOnLocalUserCreated */
	public function testOnLocalUserCreated( bool $autocreated ) {
		// Set wgNewUserLog to false to ensure that the private event is added when $autocreated is false.
		// The behaviour when wgNewUserLog is true is tested elsewhere.
		RequestContext::getMain()->getRequest()->setHeader( 'Sec-CH-UA-Bitness', '"32"' );
		$this->overrideConfigValue( MainConfigNames::NewUserLog, false );
		$user = $this->getTestUser()->getUser();
		$this->getObjectUnderTest()->onLocalUserCreated( $user, $autocreated );
		$this->runJobs( [ 'minJobs' => 0 ], [ 'type' => StoreClientHintsDataJob::TYPE ] );
		// Check that the row was inserted along with the Client Hints data.
		$insertedPrivateEventId = $this->newSelectQueryBuilder()
			->select( 'cupe_id' )
			->from( 'cu_private_event' )
			->where( [
				'cupe_actor'  => $user->getActorId(),
				'cupe_namespace' => NS_USER,
				'cupe_title' => $user->getName(),
				'cupe_log_action' => $autocreated ? 'autocreate-account' : 'create-account',
			] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertNotFalse( $insertedPrivateEventId );
		$this->newSelectQueryBuilder()
			->select( 'uachm_reference_id' )
			->from( 'cu_useragent_clienthints_map' )
			->where( [ 'uachm_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT ] )
			->caller( __METHOD__ )
			->assertFieldValue( $insertedPrivateEventId );
	}

	public static function provideOnLocalUserCreated() {
		return [
			'New user was autocreated' => [ true ],
			'New user was not autocreated' => [ false ]
		];
	}

	public function testOnLocalUserCreatedWhenNewUsersLogRestricted() {
		// Set wgNewUserLog to true but restrict the newusers log to users with the 'suppressionlog' right
		$this->overrideConfigValue( MainConfigNames::NewUserLog, true );
		$this->overrideConfigValue( MainConfigNames::LogRestrictions, [ 'newusers' => 'suppressionlog' ] );
		$user = $this->getTestUser()->getUser();
		$this->getObjectUnderTest()->onLocalUserCreated( $user, false );
		$this->assertRowCount(
			1, 'cu_private_event', 'cupe_id',
			'The row was not inserted or was inserted with the wrong data',
			[
				'cupe_actor'  => $user->getActorId(),
				'cupe_namespace' => NS_USER,
				'cupe_title' => $user->getName(),
				'cupe_log_action' => 'create-account'
			]
		);
	}

	public function testOnLocalUserCreatedForAutocreationWhenPublicLogExists() {
		// Set wgNewUserLog to true so that account auto-creations cause a log entry
		$this->overrideConfigValue( MainConfigNames::NewUserLog, true );
		$this->overrideConfigValue( MainConfigNames::LogRestrictions, [] );

		// Create a temporary account which will cause an auto-creation log entry and also call the
		// ::onLocalUserCreated method in CheckUser for us (which we are testing).
		// Wrap this in a transaction to simulate DBO_TRX, as our handler will be run in a pre-commit
		// callback.
		$dbw = $this->getDb();
		$dbw->startAtomic( __METHOD__ );

		$user = $this->getServiceContainer()->getTempUserCreator()->create( null, new FauxRequest() )->getUser();
		$accountCreationLogId = $this->newSelectQueryBuilder()
			->select( 'log_id' )
			->from( 'logging' )
			->join( 'actor', null, 'actor_id=log_actor' )
			->where( [ 'actor_user' => $user->getId(), 'log_type' => 'newusers', 'log_action' => 'autocreate' ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertNotFalse( $accountCreationLogId );

		$dbw->endAtomic( __METHOD__ );

		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cu_log_event' )
			->join( 'actor', null, 'actor_id=cule_actor' )
			->where( [ 'cule_log_id' => $accountCreationLogId, 'actor_user' => $user->getId() ] )
			->caller( __METHOD__ )
			->assertFieldValue( '1' );
	}

	public function testOnLocalUserCreatedForAutocreationWhenPublicLogMissing() {
		// Set wgNewUserLog to true so that account auto-creations cause a log entry
		$this->overrideConfigValue( MainConfigNames::NewUserLog, true );
		$this->overrideConfigValue( MainConfigNames::LogRestrictions, [] );

		// Create a temporary account which will cause an auto-creation log entry and then drop this log entry
		// to simulate it not being created for some reason.
		// Wrap this in a transaction to simulate DBO_TRX, as our handler will be run in a pre-commit
		// callback.
		$dbw = $this->getDb();
		$dbw->startAtomic( __METHOD__ );

		$user = $this->getServiceContainer()->getTempUserCreator()->create( null, new FauxRequest() )->getUser();
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'logging' )
			->where( [ 'log_title' => $user->getName() ] )
			->caller( __METHOD__ )
			->execute();

		// Check that the cu_private_event and cu_log_event tables are empty. This should be the case because
		// the code we are testing is wrapped in a pre-commit callback and we still have a pending
		// transaction.
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cu_log_event' )
			->join( 'actor', null, 'actor_id=cule_actor' )
			->where( [ 'actor_user' => $user->getId() ] )
			->caller( __METHOD__ )
			->assertEmptyResult();
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cu_private_event' )
			->join( 'actor', null, 'actor_id=cupe_actor' )
			->where( [ 'actor_user' => $user->getId() ] )
			->caller( __METHOD__ )
			->assertEmptyResult();

		$dbw->endAtomic( __METHOD__ );

		// The local user created handler should find no log entry in the logging table, and so instead use the
		// cu_private_event table for the account auto-creation event.
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cu_log_event' )
			->join( 'actor', null, 'actor_id=cule_actor' )
			->where( [ 'actor_user' => $user->getId() ] )
			->caller( __METHOD__ )
			->assertEmptyResult();
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cu_private_event' )
			->join( 'actor', null, 'actor_id=cupe_actor' )
			->where( [
				'actor_user' => $user->getId(),
				'cupe_log_action' => 'autocreate-account',
				'cupe_log_type' => 'checkuser-private-event'
			] )
			->caller( __METHOD__ )
			->assertFieldValue( '1' );
	}

	public function testOnLocalUserCreatedForAutocreationDoesNothingOnRollback(): void {
		// Set wgNewUserLog to true so that account auto-creations cause a log entry
		$this->overrideConfigValue( MainConfigNames::NewUserLog, true );
		$this->overrideConfigValue( MainConfigNames::LogRestrictions, [] );

		// Create a temporary account which will cause an auto-creation log entry and also call the
		// ::onLocalUserCreated method in CheckUser for us (which we are testing).
		// Wrap this in a transaction to simulate DBO_TRX, as our handler will be run in a pre-commit
		// callback.
		$dbw = $this->getDb();
		$dbw->startAtomic( __METHOD__, $dbw::ATOMIC_CANCELABLE );

		$user = $this->getServiceContainer()->getTempUserCreator()->create( null, new FauxRequest() )->getUser();
		$accountCreationLogId = $this->newSelectQueryBuilder()
			->select( 'log_id' )
			->from( 'logging' )
			->join( 'actor', null, 'actor_id=log_actor' )
			->where( [ 'actor_user' => $user->getId(), 'log_type' => 'newusers', 'log_action' => 'autocreate' ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertNotFalse( $accountCreationLogId );

		// Roll back the transaction, which should cancel our pending callback
		// that would write the private event.
		$dbw->cancelAtomic( __METHOD__ );

		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cu_log_event' )
			->caller( __METHOD__ )
			->assertEmptyResult();
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cu_private_event' )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	public function testClientHintsDataCollectedOnSpecialUserLogout() {
		RequestContext::getMain()->getRequest()->setHeader( 'Sec-Ch-Ua', ';v=abc' );
		$this->overrideConfigValues( [
			'CheckUserLogLogins' => true,
			'CheckUserClientHintsEnabled' => true,
		] );
		$testUser = $this->getTestUser()->getUser();
		$ipUser = $this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' );
		$this->getObjectUnderTest( 'index' )->onUserLogoutComplete(
			$ipUser,
			$html,
			$testUser->getName()
		);
		$this->runJobs( [ 'minJobs' => 0 ], [ 'type' => StoreClientHintsDataJob::TYPE ] );
		$referenceID = $this->newSelectQueryBuilder()
			->select( 'cupe_id' )
			->from( 'cu_private_event' )
			->where( [
				'cupe_actor' => $this->getServiceContainer()->getActorNormalization()->findActorId(
					$testUser,
					$this->getDb()
				),
				'cupe_log_action' => 'user-logout',
			] )
			->caller( __METHOD__ )
			->fetchField();
		$rowCount = $this->newSelectQueryBuilder()
			->select( 'uachm_reference_id' )
			->from( 'cu_useragent_clienthints_map' )
			->where( [
				'uachm_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
				'uachm_reference_id' => $referenceID,
			] )
			->caller( __METHOD__ )
			->fetchRowCount();
		$this->assertSame( 1, $rowCount );
	}

	public function testClientHintsDataCollectedOnApiUserLogout() {
		RequestContext::getMain()->getRequest()->setVal(
			'checkuserclienthints', json_encode( [ 'architecture' => 'foo' ] )
		);
		$this->overrideConfigValues( [
			'CheckUserLogLogins' => true,
			'CheckUserClientHintsEnabled' => true,
		] );
		$testUser = $this->getTestUser()->getUser();
		$ipUser = $this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' );
		$this->getObjectUnderTest( 'api' )->onUserLogoutComplete(
			$ipUser,
			$html,
			$testUser->getName()
		);
		$referenceID = $this->newSelectQueryBuilder()
			->select( 'cupe_id' )
			->from( 'cu_private_event' )
			->where( [
				'cupe_actor' => $this->getServiceContainer()->getActorNormalization()->findActorId(
					$testUser,
					$this->getDb()
				),
				'cupe_log_action' => 'user-logout',
			] )
			->caller( __METHOD__ )
			->fetchField();
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cu_useragent_clienthints_map' )
			->where( [
				'uachm_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
				'uachm_reference_id' => $referenceID,
			] )
			->caller( __METHOD__ )
			->assertFieldValue( 1 );
	}

	public function testClientHintsDataNotCollectedOnApiUserLogoutIfNotInPostRequest() {
		RequestContext::getMain()->getRequest()->setHeader( 'Sec-Ch-Ua', ';v=abc' );
		$this->overrideConfigValues( [
			'CheckUserLogLogins' => true,
			'CheckUserClientHintsEnabled' => true,
		] );
		$testUser = $this->getTestUser()->getUser();
		$ipUser = $this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' );
		$this->getObjectUnderTest( 'api' )->onUserLogoutComplete(
			$ipUser,
			$html,
			$testUser->getName()
		);
		$this->runJobs( [ 'minJobs' => 0 ], [ 'type' => StoreClientHintsDataJob::TYPE ] );
		$referenceID = $this->newSelectQueryBuilder()
			->select( 'cupe_id' )
			->from( 'cu_private_event' )
			->where( [
				'cupe_actor' => $this->getServiceContainer()->getActorNormalization()->findActorId(
					$testUser,
					$this->getDb()
				),
				'cupe_log_action' => 'user-logout',
			] )
			->caller( __METHOD__ )
			->fetchField();
		$this->newSelectQueryBuilder()
			->select( 'uachm_reference_id' )
			->from( 'cu_useragent_clienthints_map' )
			->where( [
				'uachm_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
				'uachm_reference_id' => $referenceID,
			] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	public function testClientHintsDataNotCollectedOnApiUserLogoutIfPostDataMalformed() {
		RequestContext::getMain()->getRequest()->setVal( 'checkuserclienthints',
			json_encode( [ 'platformVersion' => [ 'bar' ] ] )
		);
		$this->overrideConfigValues( [
			'CheckUserLogLogins' => true,
			'CheckUserClientHintsEnabled' => true,
		] );
		$testUser = $this->getTestUser()->getUser();
		$ipUser = $this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' );
		$this->getObjectUnderTest( 'api' )->onUserLogoutComplete(
			$ipUser,
			$html,
			$testUser->getName()
		);
		$referenceID = $this->newSelectQueryBuilder()
			->select( 'cupe_id' )
			->from( 'cu_private_event' )
			->where( [
				'cupe_actor' => $this->getServiceContainer()->getActorNormalization()->findActorId(
					$testUser,
					$this->getDb()
				),
				'cupe_log_action' => 'user-logout',
			] )
			->caller( __METHOD__ )
			->fetchField();
		$this->newSelectQueryBuilder()
			->select( 'uachm_reference_id' )
			->from( 'cu_useragent_clienthints_map' )
			->where( [
				'uachm_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
				'uachm_reference_id' => $referenceID,
			] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	public function testStoreClientHintsDataFromHeadersForPostRequest() {
		// Get a cu_private_event row ID for use in the test.
		/** @var CheckUserInsert $checkUserInsert */
		$checkUserInsert = $this->getServiceContainer()->get( 'CheckUserInsert' );
		$insertedId = $checkUserInsert->insertIntoCuPrivateEventTable(
			[], __METHOD__, $this->getTestUser()->getUser()
		);
		// Call the private method with a request that is a POST request and has transaction profiler set to be
		// a POST request
		$fauxRequest = new FauxRequest( [], true );
		$fauxRequest->setHeader( 'Sec-CH-UA-Bitness', '"32"' );
		$trxLimits = $this->getServiceContainer()->getMainConfig()->get( MainConfigNames::TrxProfilerLimits );
		Profiler::instance()->getTransactionProfiler()->redefineExpectations( $trxLimits['POST'], __METHOD__ );
		$objectUnderTest = $this->getObjectUnderTest();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$objectUnderTest->storeClientHintsDataFromHeaders(
			$insertedId, 'privatelog', $fauxRequest
		);
		// Expect Client Hints data to exist without having to run jobs for this event
		$this->newSelectQueryBuilder()
			->select( 'uachm_reference_id' )
			->from( 'cu_useragent_clienthints_map' )
			->where( [ 'uachm_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT ] )
			->caller( __METHOD__ )
			->assertFieldValue( $insertedId );
	}
}
