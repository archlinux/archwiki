<?php

namespace MediaWiki\CheckUser\Tests\Unit\Api\Rest\Handler;

use MediaWiki\CheckUser\Api\Rest\Handler\UserAgentClientHintsHandler;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWiki\Config\HashConfig;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use StatusValue;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\UserAgentClientHintsHandler
 */
class UserAgentClientHintsHandlerTest extends MediaWikiUnitTestCase {
	use HandlerTestTrait;
	use CheckUserClientHintsCommonTraitTest;
	use MockServiceDependenciesTrait;

	private function getObjectUnderTest( array $overrides ): UserAgentClientHintsHandler {
		return $this->newServiceInstance( UserAgentClientHintsHandler::class, $overrides );
	}

	public function testRunWithClientHintsDisabled() {
		$config = new HashConfig( [
			'CheckUserClientHintsEnabled' => false,
			'CheckUserClientHintsRestApiMaxTimeLag' => 1800,
		] );
		$handler = $this->getObjectUnderTest( [ 'config' => $config ] );
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'rest-no-match' ), 404 )
		);
		$this->executeHandler( $handler, new RequestData(), [], [], [ 'type' => 'revision', 'id' => 1 ] );
	}

	public function testBodyFailedValidationButStillRan() {
		$config = new HashConfig( [
			'CheckUserClientHintsEnabled' => true,
			'CheckUserClientHintsRestApiMaxTimeLag' => 1800,
		] );
		$handler = $this->getMockBuilder( UserAgentClientHintsHandler::class )
			->setConstructorArgs( [
				$config,
				$this->createMock( RevisionStore::class ),
				$this->createMock( UserAgentClientHintsManager::class ),
				$this->createMock( IConnectionProvider::class ),
				$this->createMock( ActorStore::class )
			] )
			->onlyMethods( [ 'getValidatedBody' ] )
			->getMock();
		$handler->method( 'getValidatedBody' )
			->willReturn( null );
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'rest-bad-json-body' ), 400 )
		);
		$this->executeHandler(
			$handler, new RequestData( [ 'headers' => [ 'Content-Type' => 'application/json' ] ] ), [], [],
			[ 'type' => 'revision', 'id' => 1 ]
		);
	}

	public function testBodyFailsValidationOnFormDataSubmitted() {
		$config = new HashConfig( [
			'CheckUserClientHintsEnabled' => true,
			'CheckUserClientHintsRestApiMaxTimeLag' => 1800,
		] );
		$handler = $this->getMockBuilder( UserAgentClientHintsHandler::class )
			->setConstructorArgs( [
				$config,
				$this->createMock( RevisionStore::class ),
				$this->createMock( UserAgentClientHintsManager::class ),
				$this->createMock( IConnectionProvider::class ),
				$this->createMock( ActorStore::class )
			] )
			->onlyMethods( [ 'getValidatedBody' ] )
			->getMock();
		$handler->method( 'getValidatedBody' )
			->willReturn( null );
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'rest-unsupported-content-type' ), 415 )
		);
		$this->executeHandler(
			$handler, new RequestData( [ 'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ] ] ),
			[], [], [ 'type' => 'revision', 'id' => 1 ]
		);
	}

	public function testBodyFailedValidationBecauseOfIncorrectType() {
		$config = new HashConfig( [
			'CheckUserClientHintsEnabled' => true,
			'CheckUserClientHintsRestApiMaxTimeLag' => 1800,
		] );
		$handler = $this->getMockBuilder( UserAgentClientHintsHandler::class )
			->setConstructorArgs( [
				$config,
				$this->createMock( RevisionStore::class ),
				$this->createMock( UserAgentClientHintsManager::class ),
				$this->createMock( IConnectionProvider::class ),
				$this->createMock( ActorStore::class )
			] )
			->onlyMethods( [ 'getValidatedBody' ] )
			->getMock();
		$handler->method( 'getValidatedBody' )
			->willReturn( [ 'platformVersion' => [ [ 'test' => 'test' ], [ 'testing' => 1234 ] ] ] );
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'rest-bad-json-body' ), 400 )
		);
		$this->executeHandler(
			$handler, new RequestData( [ 'headers' => [ 'Content-Type' => 'application/json' ] ] ), [], [],
			[ 'type' => 'revision', 'id' => 1 ]
		);
	}

	public function testUnsupportedType() {
		$config = new HashConfig( [
			'CheckUserClientHintsEnabled' => true,
			'CheckUserClientHintsRestApiMaxTimeLag' => 1800,
		] );
		$handler = $this->getObjectUnderTest( [ 'config' => $config ] );
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'rest-no-match' ), 404 )
		);
		$validatedBody = [ 'brands' => [ 'foo', 'bar' ], 'mobile' => true ];
		$this->executeHandler(
			$handler, new RequestData(), [], [], [ 'type' => 'unsupported', 'id' => 1 ], $validatedBody
		);
	}

	public function testMissingRevision() {
		$config = new HashConfig( [
			'CheckUserClientHintsEnabled' => true,
			'CheckUserClientHintsRestApiMaxTimeLag' => 1800,
		] );
		$revisionStore = $this->createMock( RevisionStore::class );
		$revisionStore->method( 'getRevisionById' )->willReturn( null );
		$handler = $this->getObjectUnderTest( [ 'config' => $config, 'revisionStore' => $revisionStore ] );
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'rest-nonexistent-revision' ), 404 )
		);
		$validatedBody = [ 'brands' => [ 'foo', 'bar' ], 'mobile' => true ];
		$this->executeHandler(
			$handler, new RequestData(), [], [], [ 'type' => 'revision', 'id' => 1 ], $validatedBody
		);
	}

	public function testRevisionTooOldToStoreClientHintsData() {
		$config = new HashConfig( [
			'CheckUserClientHintsEnabled' => true,
			'CheckUserClientHintsRestApiMaxTimeLag' => 5,
		] );
		$revisionStore = $this->createMock( RevisionStore::class );
		$revision = $this->createMock( RevisionRecord::class );
		$user = new UserIdentityValue( 123, 'Foo' );
		$revision->method( 'getUser' )->willReturn( $user );
		$revision->method( 'getTimestamp' )->willReturn(
			ConvertibleTimestamp::convert( TS_MW, ConvertibleTimestamp::time() - 10 )
		);
		$revisionStore->method( 'getRevisionById' )->willReturn( $revision );
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'checkuser-api-useragent-clienthints-called-too-late' ), 403
			)
		);
		$handler = $this->getObjectUnderTest( [ 'config' => $config, 'revisionStore' => $revisionStore ] );
		$validatedBody = [ 'brands' => [ 'foo', 'bar' ], 'mobile' => true ];
		$this->executeHandler(
			$handler, new RequestData(), [], [], [ 'type' => 'revision', 'id' => 1 ], $validatedBody
		);
	}

	public function testRevisionWithNullTimestamp() {
		$config = new HashConfig( [
			'CheckUserClientHintsEnabled' => true,
			'CheckUserClientHintsRestApiMaxTimeLag' => 5,
		] );
		$revisionStore = $this->createMock( RevisionStore::class );
		$revision = $this->createMock( RevisionRecord::class );
		$user = new UserIdentityValue( 123, 'Foo' );
		$revision->method( 'getUser' )->willReturn( $user );
		$revision->method( 'getTimestamp' )->willReturn( null );
		$revisionStore->method( 'getRevisionById' )->willReturn( $revision );
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'checkuser-api-useragent-clienthints-called-too-late' ), 403
			)
		);
		$handler = $this->getObjectUnderTest( [ 'config' => $config, 'revisionStore' => $revisionStore ] );
		$validatedBody = [ 'brands' => [ 'foo', 'bar' ], 'mobile' => true ];
		$this->executeHandler(
			$handler, new RequestData(), [], [], [ 'type' => 'revision', 'id' => 1 ], $validatedBody
		);
	}

	public function testMissingUser() {
		$config = new HashConfig( [
			'CheckUserClientHintsEnabled' => true,
			'CheckUserClientHintsRestApiMaxTimeLag' => 1800,
		] );
		$revisionStore = $this->createMock( RevisionStore::class );
		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getUser' )->willReturn( null );
		$revision->method( 'getTimestamp' )->willReturn( ConvertibleTimestamp::now() );
		$revisionStore->method( 'getRevisionById' )->willReturn( $revision );
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'checkuser-api-useragent-clienthints-revision-user-mismatch' ),
				401
			) );
		$handler = $this->getObjectUnderTest( [ 'config' => $config, 'revisionStore' => $revisionStore ] );
		$validatedBody = [ 'brands' => [ 'foo', 'bar' ], 'mobile' => true ];
		$this->executeHandler(
			$handler, new RequestData(), [], [], [ 'type' => 'revision', 'id' => 1 ], $validatedBody
		);
	}

	public function testUserDoesntMatchRevisionOwner() {
		$config = new HashConfig( [
			'CheckUserClientHintsEnabled' => true,
			'CheckUserClientHintsRestApiMaxTimeLag' => 1800,
		] );
		$revisionStore = $this->createMock( RevisionStore::class );
		$revision = $this->createMock( RevisionRecord::class );
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )->willReturn( 123 );
		$revision->method( 'getUser' )->willReturn( $user );
		$revision->method( 'getTimestamp' )->willReturn( ConvertibleTimestamp::now() );
		$revisionStore->method( 'getRevisionById' )->willReturn( $revision );
		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )
			->willReturn( new UserIdentityValue( 456, 'Foo' ) );
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'checkuser-api-useragent-clienthints-revision-user-mismatch' ), 401
			) );
		$handler = $this->getObjectUnderTest( [ 'config' => $config, 'revisionStore' => $revisionStore ] );
		$validatedBody = [ 'brands' => [ 'foo', 'bar' ], 'mobile' => true ];
		$this->executeHandler(
			$handler, new RequestData(), [], [], [ 'type' => 'revision', 'id' => 1 ], $validatedBody, $authority
		);
	}

	public function testUserMatchesRevisionOwner() {
		$config = new HashConfig( [
			'CheckUserClientHintsEnabled' => true,
			'CheckUserClientHintsRestApiMaxTimeLag' => 1800,
		] );
		$revisionStore = $this->createMock( RevisionStore::class );
		$revision = $this->createMock( RevisionRecord::class );
		$user = new UserIdentityValue( 123, 'Foo' );
		$revision->method( 'getUser' )->willReturn( $user );
		$revision->method( 'getTimestamp' )->willReturn(
			ConvertibleTimestamp::convert( TS_MW, ConvertibleTimestamp::time() - 2 )
		);
		$revisionStore->method( 'getRevisionById' )->willReturn( $revision );
		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )
			->willReturn( $user );
		$userAgentClientHintsManager = $this->createMock( UserAgentClientHintsManager::class );
		$userAgentClientHintsManager->method( 'insertClientHintValues' )
			->willReturn( StatusValue::newGood() );
		$handler = $this->getObjectUnderTest( [
			'config' => $config, 'revisionStore' => $revisionStore,
			'userAgentClientHintsManager' => $userAgentClientHintsManager,
		] );
		$response = $this->executeHandler(
			$handler, new RequestData(), [], [], [ 'type' => 'revision', 'id' => 1 ], [ 'test' => 1 ], $authority
		);
		$this->assertSame(
			json_encode( [
				"value" => $handler->getResponseFactory()->formatMessage(
					new MessageValue( 'checkuser-api-useragent-clienthints-explanation' )
				)
			], JSON_UNESCAPED_SLASHES ),
			$response->getBody()->getContents()
		);
	}

	public function testDataAlreadyExists() {
		$config = new HashConfig( [
			'CheckUserClientHintsEnabled' => true,
			'CheckUserClientHintsRestApiMaxTimeLag' => 1800,
		] );
		$revisionStore = $this->createMock( RevisionStore::class );
		$revision = $this->createMock( RevisionRecord::class );
		$user = new UserIdentityValue( 123, 'Foo' );
		$revision->method( 'getUser' )->willReturn( $user );
		$revision->method( 'getTimestamp' )->willReturn(
			ConvertibleTimestamp::convert( TS_MW, ConvertibleTimestamp::time() - 2 )
		);
		$revisionStore->method( 'getRevisionById' )->willReturn( $revision );
		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )
			->willReturn( $user );
		$userAgentClientHintsManager = $this->createMock( UserAgentClientHintsManager::class );
		$userAgentClientHintsManager->method( 'insertClientHintValues' )
			->willReturn( StatusValue::newFatal( 'error', [ 1 ] ) );
		$handler = $this->getObjectUnderTest( [
			'config' => $config, 'revisionStore' => $revisionStore,
			'userAgentClientHintsManager' => $userAgentClientHintsManager,
		] );
		$this->expectException( LocalizedHttpException::class );
		$this->expectExceptionMessage( 'error' );
		$this->executeHandler(
			$handler, new RequestData(), [], [], [ 'type' => 'revision', 'id' => 1 ], [ 'test' => 1 ], $authority
		);
	}

	public function testNeedsWrite() {
		$config = new HashConfig( [
			'CheckUserClientHintsEnabled' => true,
			'CheckUserClientHintsRestApiMaxTimeLag' => 1800,
		] );
		$handler = $this->getObjectUnderTest( [ 'config' => $config ] );
		$this->assertTrue(
			$handler->needsWriteAccess(),
			'Handler writes to the DB, so needsWriteAccess must return true.'
		);
	}

	public function testMissingToken() {
		$config = new HashConfig( [
			'CheckUserClientHintsEnabled' => true,
			'CheckUserClientHintsRestApiMaxTimeLag' => 1800,
		] );
		$handler = $this->getObjectUnderTest( [ 'config' => $config ] );
		$this->expectException( LocalizedHttpException::class );
		$request = new RequestData();
		$config = [
			'path' => '/foo'
		];
		$this->initHandler( $handler, $request, $config, [], null, $this->getSession( false ) );
		// Invoking the method to be tested
		$this->validateHandler( $handler );
	}
}
