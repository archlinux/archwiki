<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Auth;

use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\OATHAuth\Auth\SecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\Auth\TwoFactorModuleSelectAuthenticationRequest;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\Session;
use MediaWiki\User\User;
use MediaWiki\User\UserNameUtils;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Auth\SecondaryAuthenticationProvider
 */
class SecondaryAuthenticationProviderTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideAuthentication
	 * @param string[] $enabledModules
	 * @param callable[] $steps Each step is a callback that receives the TestCase and the
	 *   response from the class under test (first from beginAuthentication then from
	 *   continueAuthentication) and should return the set of AuthenticationRequests for the
	 *   next call, or nothing if there should be no next call.
	 *   I.e. these steps roughly correspond to user input from submitting the login form.
	 */
	public function testAuthentication( array $enabledModules, array $steps ) {
		$session = $this->createNoOpMock( Session::class, [ 'set' ] );
		$request = $this->createNoOpMock( WebRequest::class, [ 'getSession' ] );
		$request->method( 'getSession' )->willReturn( $session );
		$user = $this->createNoOpMock( User::class, [ 'getName', 'getRequest' ] );
		$user->method( 'getName' )->willReturn( 'TestUser' );
		$user->method( 'getRequest' )->willReturn( $request );

		$keys = array_map( function ( $moduleName ) {
			$key = $this->createNoOpAbstractMock( IAuthKey::class, [ 'getModule' ] );
			$key->method( 'getModule' )->willReturn( $moduleName );
			return $key;
		}, $enabledModules );
		$oathUser = $this->createNoOpMock( OATHUser::class, [ 'getKeys', 'isTwoFactorAuthEnabled' ] );
		$oathUser->method( 'getKeys' )->willReturn( $keys );
		$oathUser->method( 'isTwoFactorAuthEnabled' )->willReturn( (bool)$enabledModules );
		$oathUserRepository = $this->createNoOpMock( OATHUserRepository::class, [ 'findByUser' ] );
		$oathUserRepository->expects( $this->atLeastOnce() )->method( 'findByUser' )
			->willReturnCallback( function () use ( $user, $oathUser ) {
				$this->assertSame( 'TestUser', $user->getName() );
				return $oathUser;
			} );
		$this->setService( 'OATHUserRepository', $oathUserRepository );

		$moduleRegistry = $this->createNoOpMock( OATHAuthModuleRegistry::class, [ 'getModuleByKey' ] );
		$moduleRegistry->method( 'getModuleByKey' )->willReturnCallback( function ( $moduleName ) {
			return $this->getFakeModule( $moduleName );
		} );
		$this->setService( 'OATHAuthModuleRegistry', $moduleRegistry );

		$provider = new SecondaryAuthenticationProvider();
		$provider->init(
			new NullLogger(),
			$this->createNoOpMock( AuthManager::class ),
			$this->createNoOpMock( HookContainer::class ),
			new HashConfig( [ 'OATHPrioritizedModules' => [] ] ),
			$this->createNoOpMock( UserNameUtils::class )
		);
		$response = $provider->beginSecondaryAuthentication( $user, [] );
		foreach ( $steps as $step ) {
			$requests = $step( $this, $response );
			if ( $requests ) {
				$response = $provider->continueSecondaryAuthentication( $user, $requests );
			}
		}
	}

	public static function provideAuthentication() {
		return [
			'no modules' => [
				'enabledModules' => [],
				'steps' => [
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertEquals( AuthenticationResponse::newAbstain(), $response );
					},
				],
			],
			'one module, success' => [
				'enabledModules' => [ 'totp' ],
				'steps' => [
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertUiResponse( $response, $message = '2fa-started', $moduleName = 'totp',
							$hasSwitchRequest = false );
						return [ new FakeModuleAuthenticationRequest( 'totp', true ) ];
					},
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertEquals( AuthenticationResponse::newPass(), $response );
					},
				],
			],
			'one module, recovery codes, success' => [
				'enabledModules' => [ 'recoverycodes' ],
				'steps' => [
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertUiResponse( $response, $message = '2fa-started', $moduleName = 'recoverycodes',
							$hasSwitchRequest = false );
						return [ new FakeModuleAuthenticationRequest( 'recoverycodes', true ) ];
					},
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertEquals( AuthenticationResponse::newPass(), $response );
					},
				]
			],
			'one module, failure then success' => [
				'enabledModules' => [ 'totp' ],
				'steps' => [
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertUiResponse( $response, $message = '2fa-started', $moduleName = 'totp',
							$hasSwitchRequest = false );
						return [ new FakeModuleAuthenticationRequest( 'totp', false ) ];
					},
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertUiResponse( $response, $message = '2fa-failed', $moduleName = 'totp',
							$hasSwitchRequest = false );
						return [ new FakeModuleAuthenticationRequest( 'totp', true ) ];
					},
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertEquals( AuthenticationResponse::newPass(), $response );
					},
				],
			],
			'two modules, no switch' => [
				'enabledModules' => [ 'totp', 'webauthn' ],
				'steps' => [
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertUiResponse( $response, $message = '2fa-started', $moduleName = 'totp',
							$hasSwitchRequest = true );
						$switchReq = $response->neededRequests[1];
						$test->assertSame( 'totp', $switchReq->currentModule );
						$test->assertSame( [ 'totp', 'webauthn' ], array_keys( $switchReq->allowedModules ) );
						return [ new FakeModuleAuthenticationRequest( 'totp', true ) ];
					},
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertEquals( AuthenticationResponse::newPass(), $response );
					},
				],
			],
			'two modules, no switch (but include empty request)' => [
				'enabledModules' => [ 'totp', 'webauthn' ],
				'steps' => [
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertUiResponse( $response, $message = '2fa-started', $moduleName = 'totp',
							$hasSwitchRequest = true );
						return [
							new FakeModuleAuthenticationRequest( 'totp', true ),
							new TwoFactorModuleSelectAuthenticationRequest( 'totp', [
								'totp' => $test->getMockMessage( 'mock-name-totp' ),
								'webauthn' => $test->getMockMessage( 'mock-name-webauthn' ),
							] ),
						];
					},
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertEquals( AuthenticationResponse::newPass(), $response );
					},
				],
			],
			'two modules, no switch (but include empty request, #2)' => [
				'enabledModules' => [ 'totp', 'webauthn' ],
				'steps' => [
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertUiResponse( $response, $message = '2fa-started', $moduleName = 'totp',
							$hasSwitchRequest = true );
						$switchReq = new TwoFactorModuleSelectAuthenticationRequest( 'totp', [
							'totp' => $test->getMockMessage( 'mock-name-totp' ),
							'webauthn' => $test->getMockMessage( 'mock-name-webauthn' ),
						] );
						$switchReq->newModule = '';
						return [
							new FakeModuleAuthenticationRequest( 'totp', true ),
							$switchReq,
						];
					},
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertEquals( AuthenticationResponse::newPass(), $response );
					},
				],
			],
			'two modules, switch' => [
				'enabledModules' => [ 'totp', 'webauthn' ],
				'steps' => [
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertUiResponse( $response, $message = '2fa-started', $moduleName = 'totp',
							$hasSwitchRequest = true );
						$switchReq = new TwoFactorModuleSelectAuthenticationRequest( 'totp', [
							'totp' => $test->getMockMessage( 'mock-name-totp' ),
							'webauthn' => $test->getMockMessage( 'mock-name-webauthn' ),
						] );
						$switchReq->newModule = 'webauthn';
						return [
							$switchReq,
						];
					},
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertUiResponse( $response, $message = '2fa-started', $moduleName = 'webauthn',
							$hasSwitchRequest = true );
						$switchReq = $response->neededRequests[1];
						$test->assertSame( 'webauthn', $switchReq->currentModule );
						$test->assertSame( [ 'totp', 'webauthn' ], array_keys( $switchReq->allowedModules ) );
						return [
							new FakeModuleAuthenticationRequest( 'webauthn', true ),
							$switchReq
						];
					},
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertEquals( AuthenticationResponse::newPass(), $response );
					},
				],
			],
			'two modules, switch (with extra request)' => [
				// When the switch request has newModule set, the normal request must be ignored
				'enabledModules' => [ 'totp', 'webauthn' ],
				'steps' => [
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertUiResponse( $response, $message = '2fa-started', $moduleName = 'totp',
							$hasSwitchRequest = true );
						$switchReq = new TwoFactorModuleSelectAuthenticationRequest( 'totp', [
							'totp' => $test->getMockMessage( 'mock-name-totp' ),
							'webauthn' => $test->getMockMessage( 'mock-name-webauthn' ),
						] );
						$switchReq->newModule = 'webauthn';
						return [
							new FakeModuleAuthenticationRequest( 'totp', true ),
							$switchReq,
						];
					},
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertUiResponse( $response, $message = '2fa-started', $moduleName = 'webauthn',
							$hasSwitchRequest = true );
						$switchReq = $response->neededRequests[1];
						$test->assertSame( 'webauthn', $switchReq->currentModule );
						$test->assertSame( [ 'totp', 'webauthn' ], array_keys( $switchReq->allowedModules ) );
						return [
							new FakeModuleAuthenticationRequest( 'webauthn', true ),
							$switchReq
						];
					},
					static function ( self $test, AuthenticationResponse $response ) {
						$test->assertEquals( AuthenticationResponse::newPass(), $response );
					},
				],
			],
			'three modules, no switch' => [
				'enabledModules' => [ 'recoverycodes', 'totp', 'webauthn' ],
					'steps' => [
						static function ( self $test, AuthenticationResponse $response ) {
							$test->assertUiResponse( $response, $message = '2fa-started', $moduleName = 'recoverycodes',
								$hasSwitchRequest = true );
							$switchReq = $response->neededRequests[1];
							$test->assertSame( 'recoverycodes', $switchReq->currentModule );
							$test->assertSame(
								[ 'recoverycodes', 'totp', 'webauthn' ], array_keys( $switchReq->allowedModules )
							);
							return [ new FakeModuleAuthenticationRequest( 'recoverycodes', true ) ];
						},
						static function ( self $test, AuthenticationResponse $response ) {
							$test->assertEquals( AuthenticationResponse::newPass(), $response );
						},
					],
				],
			];
	}

	private function getFakeModule( string $name ): IModule&MockObject {
		$provider = $this->getMockBuilder( AbstractSecondaryAuthenticationProvider::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'init', 'beginSecondaryAuthentication', 'continueSecondaryAuthentication' ] )
			->getMockForAbstractClass();
		$provider->method( 'beginSecondaryAuthentication' )->willReturnCallback(
			function ( User $user, array $reqs ) use ( $name ) {
				$this->assertSame( 'TestUser', $user->getName() );
				$this->assertSame( [], $reqs );
				return AuthenticationResponse::newUI(
					[ new FakeModuleAuthenticationRequest( $name ) ],
					$this->getMockMessage( '2fa-started' )
				);
			}
		);
		$provider->method( 'continueSecondaryAuthentication' )->willReturnCallback(
			function ( User $user, array $reqs ) use ( $name ) {
				$this->assertSame( 'TestUser', $user->getName() );
				$req = AuthenticationRequest::getRequestByClass( $reqs, FakeModuleAuthenticationRequest::class );
				/** @var FakeModuleAuthenticationRequest $req */
				$this->assertNotNull( $req );
				$this->assertSame( $name, $req->moduleName );
				if ( $req->pass ) {
					return AuthenticationResponse::newPass();
				} else {
					return AuthenticationResponse::newUI(
						[ new FakeModuleAuthenticationRequest( $name ) ],
						$this->getMockMessage( '2fa-failed' )
					);
				}
			}
		);

		$module = $this->createNoOpAbstractMock( IModule::class,
			[ 'getName', 'getDisplayName', 'getSecondaryAuthProvider' ] );
		$module->method( 'getName' )->willReturn( $name );
		$module->method( 'getDisplayName' )->willReturn( $this->getMockMessage( 'mock-name-' . $name ) );
		$module->method( 'getSecondaryAuthProvider' )->willReturn( $provider );
		return $module;
	}

	private function assertUiResponse(
		AuthenticationResponse $response,
		string $message,
		string $moduleName,
		bool $hasSwitchRequest
	): void {
		$this->assertSame( AuthenticationResponse::UI, $response->status );
		$this->assertSame( $message, $response->message->getKey() );
		$this->assertCount( $hasSwitchRequest ? 2 : 1, $response->neededRequests );
		$this->assertInstanceOf( FakeModuleAuthenticationRequest::class, $response->neededRequests[0] );
		$this->assertSame( $moduleName, $response->neededRequests[0]->moduleName );
		if ( $hasSwitchRequest ) {
			$this->assertInstanceOf( TwoFactorModuleSelectAuthenticationRequest::class, $response->neededRequests[1] );
		}
	}

}
