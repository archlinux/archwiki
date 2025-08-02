<?php
namespace MediaWiki\CheckUser\Tests\Unit\Services;

use MediaWiki\Block\Block;
use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserRigorOptions;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\CheckUser\Services\CheckUserPermissionManager
 */
class CheckUserPermissionManagerTest extends MediaWikiUnitTestCase {
	private UserOptionsLookup $userOptionsLookup;

	private SpecialPageFactory $specialPageFactory;

	private CentralIdLookup $centralIdLookup;

	private UserFactory $userFactory;

	private Authority $authority;

	private CheckUserPermissionManager $checkUserPermissionsManager;

	protected function setUp(): void {
		parent::setUp();

		$this->userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$this->specialPageFactory = $this->createMock( SpecialPageFactory::class );
		$this->centralIdLookup = $this->createMock( CentralIdLookup::class );
		$this->userFactory = $this->createMock( UserFactory::class );
		$this->authority = $this->createMock( Authority::class );

		$this->checkUserPermissionsManager = new CheckUserPermissionManager(
			$this->userOptionsLookup,
			$this->specialPageFactory,
			$this->centralIdLookup,
			$this->userFactory
		);
	}

	/**
	 * @dataProvider provideCanAccessTemporaryAccountIPAddresses
	 */
	public function testCanAccessTemporaryAccountIPAddresses(
		Authority $authority,
		bool $acceptedAgreement,
		CheckUserPermissionStatus $expectedStatus
	): void {
		$this->userOptionsLookup->method( 'getOption' )
			->with( $authority->getUser(), 'checkuser-temporary-account-enable' )
			->willReturn( $acceptedAgreement ? '1' : '0' );

		$permStatus = $this->checkUserPermissionsManager->canAccessTemporaryAccountIPAddresses( $authority );

		$this->assertEquals( $expectedStatus, $permStatus );
	}

	public static function provideCanAccessTemporaryAccountIPAddresses(): iterable {
		$actor = new UserIdentityValue( 1, 'TestUser' );

		yield 'missing permissions' => [
			new SimpleAuthority( $actor, [] ),
			true,
			CheckUserPermissionStatus::newPermissionError( 'checkuser-temporary-account' )
		];

		yield 'authorized but agreement not accepted' => [
			new SimpleAuthority( $actor, [ 'checkuser-temporary-account' ] ),
			false,
			CheckUserPermissionStatus::newFatal( 'checkuser-tempaccount-reveal-ip-permission-error-description' )
		];

		yield 'authorized to view data without accepting agreement' => [
			new SimpleAuthority( $actor, [ 'checkuser-temporary-account-no-preference' ] ),
			false,
			CheckUserPermissionStatus::newGood()
		];

		yield 'authorized and agreement accepted' => [
			new SimpleAuthority( $actor, [ 'checkuser-temporary-account' ] ),
			true,
			CheckUserPermissionStatus::newGood()
		];
	}

	/**
	 * @dataProvider provideCanAutoRevealIPAddresses
	 */
	public function testCanAutoRevealIPAddresses(
		array $rights,
		CheckUserPermissionStatus $expectedStatus
	): void {
		$actor = new UserIdentityValue( 1, 'TestUser' );
		$authority = new SimpleAuthority( $actor, $rights );
		$autoRevealStatus = $this->checkUserPermissionsManager->canAutoRevealIPAddresses( $authority );

		$this->assertEquals( $expectedStatus, $autoRevealStatus );
	}

	public static function provideCanAutoRevealIPAddresses(): iterable {
		yield 'Has auto-reveal right but not IP reveal right' => [
			'rights' => [
				'checkuser-temporary-account-auto-reveal',
			],
			'expected' => CheckUserPermissionStatus::newPermissionError(
				'checkuser-temporary-account'
			),
		];

		yield 'Has IP reveal right but not auto-reveal right' => [
			'rights' => [
				'checkuser-temporary-account-no-preference',
			],
			'expected' => CheckUserPermissionStatus::newPermissionError(
				'checkuser-temporary-account-auto-reveal'
			),
		];

		yield 'Has IP reveal right and auto-reveal right' => [
			'rights' => [
				'checkuser-temporary-account-no-preference',
				'checkuser-temporary-account-auto-reveal',
			],
			'expected' => CheckUserPermissionStatus::newGood(),
		];
	}

	/**
	 * @dataProvider provideCanAccessTemporaryAccountIPAddressesWhenBlocked
	 */
	public function testCanAccessTemporaryAccountIPAddressesWhenBlocked(
		bool $isSitewideBlock
	): void {
		$block = $this->createMock( Block::class );
		$block->method( 'isSitewide' )
			->willReturn( $isSitewideBlock );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )
			->willReturn( new UserIdentityValue( 1, 'TestUser' ) );
		$authority->method( 'getBlock' )
			->willReturn( $block );
		$authority->method( 'isAllowed' )
			->willReturn( true );

		$this->userOptionsLookup->method( 'getOption' )
			->with( $authority->getUser(), 'checkuser-temporary-account-enable' )
			->willReturn( '1' );

		$permStatus = $this->checkUserPermissionsManager->canAccessTemporaryAccountIPAddresses( $authority );

		if ( $isSitewideBlock ) {
			$this->assertStatusNotGood( $permStatus );
			$this->assertSame( $block, $permStatus->getBlock() );
		} else {
			$this->assertStatusGood( $permStatus );
			$this->assertNull( $permStatus->getBlock() );
		}
	}

	public static function provideCanAccessTemporaryAccountIPAddressesWhenBlocked() {
		return [
			'user is sitewide blocked' => [ true ],
			'user is not sitewide blocked' => [ false ],
		];
	}

	/**
	 * @dataProvider canAccessGlobalContributionsForIPsDataProvider
	 */
	public function testCanAccessGlobalContributionsForIPAddress(
		string $error,
		bool $hasGlobalContributionsPage,
		string $targetLiteral,
		bool $authorityIsRegistered
	): void {
		$this->specialPageFactory
			->method( 'exists' )
			->with( 'GlobalContributions' )
			->willReturn( $hasGlobalContributionsPage );

		$this->authority
			->method( 'isRegistered' )
			->willReturn( $authorityIsRegistered );

		// Identity and CentralAuth checks are not performed for IPs
		$this->userFactory
			->expects( $this->never() )
			->method( 'newFromName' );
		$this->centralIdLookup
			->expects( $this->never() )
			->method( 'centralIdFromLocalUser' );

		$status = $this->checkUserPermissionsManager->canAccessUserGlobalContributions(
			$this->authority,
			$targetLiteral
		);

		if ( $error === '' ) {
			$this->assertTrue( $status->isGood() );
			$this->assertNull( $status->getValue() );
		} else {
			$this->assertFalse( $status->isGood() );
			$this->assertEquals(
				$error,
				$status->getMessages()[0]->getKey()
			);
		}
	}

	public static function canAccessGlobalContributionsForIPsDataProvider(): iterable {
		return [
			'When the GlobalContributions page does not exist' => [
				'error' => 'nospecialpagetext',
				'hasGlobalContributionsPage' => false,
				'targetLiteral' => '1.2.3.4',
				'authorityIsRegistered' => true,
			],
			'When the accessing authority is not registered' => [
				'error' => 'exception-nologin-text',
				'hasGlobalContributionsPage' => true,
				'targetLiteral' => '1.2.3.4',
				'authorityIsRegistered' => false,
			],
			'When all conditions are satisfied for an IPv4 address' => [
				'error' => '',
				'hasGlobalContributionsPage' => true,
				'targetLiteral' => '1.2.3.4',
				'authorityIsRegistered' => true,
			],
			'When all conditions are satisfied for an IPv6 address' => [
				'error' => '',
				'hasGlobalContributionsPage' => true,
				'targetLiteral' => '2001:0000:130F:0000:0000:09C0:876A:130B',
				'authorityIsRegistered' => true,
			],
			'When all conditions are satisfied for an IPv4 range' => [
				'error' => '',
				'hasGlobalContributionsPage' => true,
				'targetLiteral' => '1.2.3.4/24',
				'authorityIsRegistered' => true,
			],
			'When all conditions are satisfied for an IPv6 range' => [
				'error' => '',
				'hasGlobalContributionsPage' => true,
				'targetLiteral' => '2001:db8:1234::/48',
				'authorityIsRegistered' => true,
			],
		];
	}

	/**
	 * @dataProvider canAccessGlobalContributionsDataProvider
	 */
	public function testCanAccessGlobalContributions(
		string $errorMessage,
		bool $authorityIsRegistered,
		bool $hasTargetIdentity,
		bool $targetIdentityIsRegistered,
		bool $centralAuthUserExists
	): void {
		$this->specialPageFactory
			->method( 'exists' )
			->with( 'GlobalContributions' )
			->willReturn( true );

		$this->authority
			->method( 'isRegistered' )
			->willReturn( $authorityIsRegistered );

		if ( $hasTargetIdentity ) {
			$targetIdentity = $this->createMock(
				User::class
			);

			$targetIdentity
				->method( 'isRegistered' )
				->willReturn( $targetIdentityIsRegistered );

			$this->userFactory
				->method( 'newFromName' )
				->with( 'Username', UserRigorOptions::RIGOR_NONE )
				->willReturn( $targetIdentity );

			$this->centralIdLookup
				->method( 'centralIdFromLocalUser' )
				->with( $targetIdentity, $this->authority )
				->willReturn( $centralAuthUserExists ? 1 : 0 );
		} else {
			$this->userFactory
				->expects( $this->once() )
				->method( 'newFromName' )
				->willReturn( null );

			$this->centralIdLookup
				->expects( $this->never() )
				->method( 'centralIdFromLocalUser' );
		}

		$status = $this->checkUserPermissionsManager->canAccessUserGlobalContributions(
			$this->authority,
			'Username'
		);

		if ( $errorMessage === '' ) {
			$this->assertTrue( $status->isGood() );
			$this->assertNull( $status->getValue() );
		} else {
			$this->assertFalse( $status->isGood() );
			$this->assertEquals(
				$errorMessage,
				$status->getMessages()[0]->getKey()
			);
		}
	}

	public static function canAccessGlobalContributionsDataProvider(): iterable {
		return [
			'When all conditions are satisfied for a regular user' => [
				'errorMessage' => '',
				'authorityIsRegistered' => true,
				'hasTargetIdentity' => true,
				'targetIdentityIsRegistered' => true,
				'centralAuthUserExists' => true
			],
			'When the target identity can\'t be retrieved' => [
				// This happens, for example, when providing an invalid username
				// to UserFactory::newFromName()
				'errorMessage' => 'checkuser-target-nonexistent',
				'authorityIsRegistered' => true,
				'hasTargetIdentity' => false,
				'targetIdentityIsRegistered' => false,
				'centralAuthUserExists' => true
			],
			'When the user identity GC is requested for is not registered' => [
				'errorMessage' => 'checkuser-global-contributions-no-results-no-central-user',
				'authorityIsRegistered' => true,
				'hasTargetIdentity' => true,
				'targetIdentityIsRegistered' => false,
				'centralAuthUserExists' => true
			],
			'When the CentralAuthUser does not exist' => [
				'errorMessage' => 'checkuser-global-contributions-no-results-no-central-user',
				'authorityIsRegistered' => true,
				'hasTargetIdentity' => true,
				'targetIdentityIsRegistered' => true,
				'centralAuthUserExists' => false
			],
		];
	}
}
