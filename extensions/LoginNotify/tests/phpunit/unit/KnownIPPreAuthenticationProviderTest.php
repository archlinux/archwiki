<?php
namespace LoginNotify\Tests\Unit;

use LoginNotify\KnownIPPreAuthenticationProvider;
use LoginNotify\LoginNotify;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\MutableConfig;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNameUtils;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;

/**
 * @covers \LoginNotify\KnownIPPreAuthenticationProvider
 */
class KnownIPPreAuthenticationProviderTest extends MediaWikiUnitTestCase {
	private DerivativeRequest $request;
	private LoginNotify $loginNotify;
	private UserFactory $userFactory;
	private MutableConfig $config;

	private KnownIPPreAuthenticationProvider $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->request = new DerivativeRequest( new FauxRequest(), [] );
		$this->request->setIP( '127.0.0.1' );
		$this->loginNotify = $this->createMock( LoginNotify::class );
		$this->userFactory = $this->createMock( UserFactory::class );
		$this->config = new HashConfig( [ 'LoginNotifyDenyUnknownIPs' => false ] );

		$authManager = $this->createMock( AuthManager::class );
		$authManager->method( 'getRequest' )
			->willReturn( $this->request );

		$this->provider = new KnownIPPreAuthenticationProvider(
			$this->loginNotify,
			$this->userFactory
		);
		$this->provider->init(
			new NullLogger(),
			$authManager,
			$this->createMock( HookContainer::class ),
			$this->config,
			$this->createMock( UserNameUtils::class )
		);
	}

	public function testShouldDoNothingWhenDisabledViaConfig(): void {
		$this->loginNotify->expects( $this->never() )
			->method( $this->anything() );

		$result = $this->provider->testForAuthentication( [ self::makeLoginRequest() ] );

		$this->assertStatusGood( $result );
	}

	public function testShouldDoNothingIfUserNameNotValid(): void {
		$this->config->set( 'LoginNotifyDenyUnknownIPs', true );

		$this->loginNotify->expects( $this->never() )
			->method( $this->anything() );

		$req = self::makeLoginRequest();

		$this->userFactory->method( 'newFromName' )
			->with( $req->username )
			->willReturn( null );

		$result = $this->provider->testForAuthentication( [ $req ] );

		$this->assertStatusGood( $result );
	}

	public function testShouldAcceptRequestFromKnownIP(): void {
		$this->config->set( 'LoginNotifyDenyUnknownIPs', true );

		$user = $this->createMock( User::class );

		$req = self::makeLoginRequest();

		$this->userFactory->method( 'newFromName' )
			->with( $req->username )
			->willReturn( $user );

		$this->loginNotify->method( 'isKnownSystemFast' )
			->with( $user, $this->request )
			->willReturn( LoginNotify::USER_KNOWN );

		$result = $this->provider->testForAuthentication( [ $req ] );

		$this->assertStatusGood( $result );
	}

	public function testShouldRejectRequestFromUnknownIP(): void {
		$this->config->set( 'LoginNotifyDenyUnknownIPs', true );

		$user = $this->createMock( User::class );

		$req = self::makeLoginRequest();

		$this->userFactory->method( 'newFromName' )
			->with( $req->username )
			->willReturn( $user );

		$this->loginNotify->method( 'isKnownSystemFast' )
			->with( $user, $this->request )
			->willReturn( LoginNotify::USER_NOT_KNOWN );

		$result = $this->provider->testForAuthentication( [ $req ] );

		$this->assertStatusError( 'loginnotify-unknown-ip', $result );
	}

	/**
	 * Convenience function to create a PasswordAuthenticationRequest used for login.
	 * @return PasswordAuthenticationRequest
	 */
	private static function makeLoginRequest(): PasswordAuthenticationRequest {
		$req = new PasswordAuthenticationRequest();
		$req->action = AuthManager::ACTION_LOGIN;
		$req->username = 'TestUser';
		$req->password = 'TestPassword';
		return $req;
	}
}
