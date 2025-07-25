<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MailAddress;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\MainConfigNames;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler
 * @group CheckUser
 */
class CheckUserPrivateEventsHandlerTest extends MediaWikiUnitTestCase {
	public function getObjectUnderTestForNoCheckUserInsertCalls( $overrides = [] ): CheckUserPrivateEventsHandler {
		$noOpMockCheckUserInsert = $this->createNoOpMock( CheckUserInsert::class );
		return new CheckUserPrivateEventsHandler(
			$noOpMockCheckUserInsert,
			$overrides['config'] ?? $this->createMock( Config::class ),
			$overrides['userIdentityLookup'] ?? $this->createMock( UserIdentityLookup::class ),
			$overrides['userFactory'] ?? $this->createMock( UserFactory::class ),
			$overrides['readOnlyMode'] ?? $this->createMock( ReadOnlyMode::class ),
			$overrides['userAgentClientHintsManager'] ?? $this->createMock( UserAgentClientHintsManager::class ),
			$overrides['jobQueueGroup'] ?? $this->createMock( JobQueueGroup::class ),
			$overrides['dbProvider'] ?? $this->createMock( IConnectionProvider::class ),
		);
	}

	public function testUserLogoutCompleteWhenLogLoginsConfigSetToFalse() {
		$handler = $this->getObjectUnderTestForNoCheckUserInsertCalls( [
			'config' => new HashConfig( [ 'CheckUserLogLogins' => false ] ),
		] );
		$html = '';
		$handler->onUserLogoutComplete( $this->createMock( User::class ), $html, 'OldName' );
	}

	public function testOnAuthManagerLoginAuthenticateAuditWhenLogLoginsConfigSetToFalse() {
		$handler = $this->getObjectUnderTestForNoCheckUserInsertCalls( [
			'config' => new HashConfig( [ 'CheckUserLogLogins' => false ] ),
		] );
		$handler->onAuthManagerLoginAuthenticateAudit(
			AuthenticationResponse::newPass( 'test' ),
			$this->createMock( User::class ),
			'test',
			[]
		);
	}

	public function testOnEmailUserNoSaveForSelfEmail() {
		// Call the method under test, with $to and $from having the same email and name.
		$to = new MailAddress( 'test@test.com', 'Test' );
		$from = new MailAddress( 'test@test.com', 'Test' );
		$subject = 'Test';
		$text = 'Test';
		$error = false;
		$this->getObjectUnderTestForNoCheckUserInsertCalls()
			->onEmailUser( $to, $from, $subject, $text, $error );
		// Run DeferredUpdates as the private event is created in a DeferredUpdate.
		DeferredUpdates::doUpdates();
	}

	public function testOnEmailUserForNoSecretKey() {
		// Mock that wgSecretKey is set to null.
		$handler = $this->getObjectUnderTestForNoCheckUserInsertCalls( [
			'config' => new HashConfig( [ 'SecretKey' => null ] ),
		] );
		// Call the method under test
		$to = new MailAddress( 'test@test.com', 'Test' );
		$from = new MailAddress( 'testing@test.com', 'Testing' );
		$subject = 'Test';
		$text = 'Test';
		$error = false;
		$handler->onEmailUser( $to, $from, $subject, $text, $error );
		// Run DeferredUpdates as the private event is created in a DeferredUpdate.
		DeferredUpdates::doUpdates();
	}

	public function testOnEmailUserReadOnlyMode() {
		// Mock that the wgSecretKey is defined but the site is in read only mode.
		$mockReadOnlyMode = $this->createMock( ReadOnlyMode::class );
		$mockReadOnlyMode->method( 'isReadOnly' )->willReturn( true );
		$handler = $this->getObjectUnderTestForNoCheckUserInsertCalls( [
			'readOnlyMode' => $mockReadOnlyMode,
			'config' => new HashConfig( [ 'SecretKey' => 'test' ] ),
		] );
		// Call the method under test
		$to = new MailAddress( 'test@test.com', 'Test' );
		$from = new MailAddress( 'testing@test.com', 'Testing' );
		$subject = 'Test';
		$text = 'Test';
		$error = false;
		$handler->onEmailUser( $to, $from, $subject, $text, $error );
		// Run DeferredUpdates as the private event is created in a DeferredUpdate.
		DeferredUpdates::doUpdates();
	}

	/** @dataProvider provideOnLocalUserCreatedWhenNotSavedToPreventDuplicateEvent */
	public function testOnLocalUserCreatedWhenNotSavedToPreventDuplicateEvent( $logRestrictionsConfig ) {
		$handler = $this->getObjectUnderTestForNoCheckUserInsertCalls( [
			'config' => new HashConfig( [
				MainConfigNames::NewUserLog => true,
				MainConfigNames::LogRestrictions => $logRestrictionsConfig,
			] ),
		] );
		$handler->onLocalUserCreated( $this->createMock( User::class ), false );
	}

	public static function provideOnLocalUserCreatedWhenNotSavedToPreventDuplicateEvent() {
		return [
			'wgLogRestrictions is empty' => [ [] ],
			'wgLogRestrictions contains a "newusers" key with value of "*"' => [ [ 'newusers' => '*' ] ],
		];
	}
}
