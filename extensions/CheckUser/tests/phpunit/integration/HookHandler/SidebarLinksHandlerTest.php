<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CheckUser\CheckUserPermissionStatus;
use MediaWiki\Extension\CheckUser\HookHandler\SidebarLinksHandler;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Extension\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\WebRequest;
use MediaWiki\Skin\Skin;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\SidebarLinksHandler
 */
class SidebarLinksHandlerTest extends MediaWikiIntegrationTestCase {
	/** @var (Authority&MockObject) */
	private Authority $authority;

	/** @var (Skin&MockObject) */
	private Skin $skin;

	private HashConfig $config;

	/** @var (CheckUserPermissionManager&MockObject) */
	private CheckUserPermissionManager $permissionManager;

	/** @var (CheckUserPermissionStatus&MockObject) */
	private CheckUserPermissionStatus $permissionStatus;

	/** (CheckUserTemporaryAccountAutoRevealLookup&MockObject) */
	private CheckUserTemporaryAccountAutoRevealLookup $autoRevealLookup;

	/** (TempUserConfig&MockObject) */
	private TempUserConfig $tempUserConfig;

	/** @var (UserIdentity&MockObject) */
	private UserIdentity $relevantUser;

	private SidebarLinksHandler $sut;

	public function setUp(): void {
		parent::setUp();

		$this->authority = $this->createMock( Authority::class );
		$this->skin = $this->createMock( Skin::class );

		$this->config = new HashConfig();

		$this->permissionStatus = $this->createMock(
			CheckUserPermissionStatus::class
		);
		$this->permissionManager = $this->createMock(
			CheckUserPermissionManager::class
		);
		$this->autoRevealLookup = $this->createMock(
			CheckUserTemporaryAccountAutoRevealLookup::class
		);
		$this->relevantUser = $this->createMock(
			UserIdentity::class
		);
		$this->tempUserConfig = $this->createMock(
			TempUserConfig::class
		);

		$this->sut = new SidebarLinksHandler(
			$this->config,
			$this->permissionManager,
			$this->autoRevealLookup,
			$this->tempUserConfig,
		);
	}

	private function mockSkinMessages(): void {
		$this->skin
			->method( 'msg' )
			->willReturnCallback( static function ( $key ): Message {
				return new Message( $key );
			} );
	}

	public function testGlobalContributionsLinkIPRangeSupport(): void {
		$this->setUserLang( 'qqx' );

		$this->skin
			->method( 'getRelevantUser' )
			->willReturn( null );
		$this->skin
			->method( 'getAuthority' )
			->willReturn( $this->authority );
		$this->mockSkinMessages();
		$this->skin
			->method( 'getPageTarget' )
			->willReturn( '1.2.3.4/16' );
		$this->config->set( MainConfigNames::RangeContributionsCIDRLimit, [
			'IPv4' => 16,
			'IPv6' => 32,
		] );

		$this->permissionStatus
			->method( 'isGood' )
			->willReturn( true );
		$this->permissionManager
			->expects( $this->once() )
			->method( 'canAccessUserGlobalContributions' )
			->with( $this->authority, '1.2.3.4/16' )
			->willReturn( $this->permissionStatus );

		$sidebar = [
			'navigation' => [],
			'TOOLBOX' => [],
			'LANGUAGES' => [],
		];
		$this->sut->onSidebarBeforeOutput( $this->skin, $sidebar );
		$this->assertEquals( [
			'navigation' => [],
			'TOOLBOX' => [
				'global-contributions' => [
					'id' => 't-global-contributions',
					'text' => '(checkuser-global-contributions-link-sidebar)',
					'href' => '/wiki/Special:GlobalContributions/1.2.3.4/16',
					'tooltip-params' => [ '1.2.3.4/16' ],
				],
			],
			'LANGUAGES' => [],
		], $sidebar );
	}

	public function testGlobalContributionsLinkIPRangeSupportOutOfRange(): void {
		$this->setUserLang( 'qqx' );

		$this->skin
			->method( 'getRelevantUser' )
			->willReturn( null );
		$this->skin
			->method( 'getAuthority' )
			->willReturn( $this->authority );
		$this->mockSkinMessages();
		$this->skin
			->method( 'getPageTarget' )
			->willReturn( '1.2.3.4/16' );
		$this->config->set( MainConfigNames::RangeContributionsCIDRLimit, [
			'IPv4' => 32,
			'IPv6' => 32,
		] );

		$this->permissionStatus
			->method( 'isGood' )
			->willReturn( true );
		$this->permissionManager
			->expects( $this->never() )
			->method( 'canAccessUserGlobalContributions' );

		$sidebar = [
			'navigation' => [],
			'TOOLBOX' => [],
			'LANGUAGES' => [],
		];
		$this->sut->onSidebarBeforeOutput( $this->skin, $sidebar );
		$this->assertEquals( [
			'navigation' => [],
			'TOOLBOX' => [],
			'LANGUAGES' => [],
		], $sidebar );
	}

	/**
	 * @dataProvider whenGlobalContributionsLinkShouldNotBeAddedDataProvider
	 */
	public function testWhenTheLinkShouldNotBeAdded(
		array $expected,
		array $sidebar,
		bool $hasPageTarget,
		bool $hasAccess
	): void {
		$this->setUserLang( 'qqx' );

		$this->skin
			->method( 'getAuthority' )
			->willReturn( $this->authority );
		$this->mockSkinMessages();

		if ( $hasPageTarget ) {
			$this->skin
				->method( 'getPageTarget' )
				->willReturn( 'Page target' );
			$this->permissionManager
				->expects( $this->once() )
				->method( 'canAccessUserGlobalContributions' )
				->with( $this->authority, 'Page target' )
				->willReturn( $this->permissionStatus );
		} else {
			$this->skin
				->method( 'getPageTarget' )
				->willReturn( '' );
			$this->permissionManager
				->expects( $this->never() )
				->method( 'canAccessUserGlobalContributions' );
		}

		$this->permissionStatus
			->method( 'isGood' )
			->willReturn( $hasAccess );

		$this->sut->onSidebarBeforeOutput( $this->skin, $sidebar );
		$this->assertEquals( $expected, $sidebar );
	}

	public static function whenGlobalContributionsLinkShouldNotBeAddedDataProvider(): array {
		return [
			// Cases when the link is not added
			//
			'When there is no relevant user' => [
				'expected' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [ 'TOOLBOX array' ],
					'LANGUAGES' => [ 'LANGUAGES array' ],
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [ 'TOOLBOX array' ],
					'LANGUAGES' => [ 'LANGUAGES array' ],
				],
				'hasPageTarget' => false,
				'hasAccess' => false,
			],
			'When the accessing user lacks access' => [
				'expected' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [ 'TOOLBOX array' ],
					'LANGUAGES' => [ 'LANGUAGES array' ],
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [ 'TOOLBOX array' ],
					'LANGUAGES' => [ 'LANGUAGES array' ],
				],
				'hasPageTarget' => true,
				'hasAccess' => false,
			],
			'When access is not granted and the sidebar is empty' => [
				// Tests for errors checking preconditions for an empty sidebar
				// (i.e. "Undefined array key 'TOOLBOX'" errors)
				'expected' => [],
				'sidebar' => [],
				'hasPageTarget' => false,
				'hasAccess' => false,
			],
			// Cases when the link is added
			//
			'When access is granted and the sidebar is empty' => [
				// Tests for errors updating the sidebar when it was previously
				// empty (i.e. "Undefined array key 'TOOLBOX'" errors)
				'expected' => [
					'TOOLBOX' => [
						'global-contributions' => [
							'id' => 't-global-contributions',
							'text' => '(checkuser-global-contributions-link-sidebar)',
							'href' => '/wiki/Special:GlobalContributions/Page_target',
							'tooltip-params' => [ 'Page target' ],
						],
					],
				],
				'sidebar' => [],
				'hasPageTarget' => true,
				'hasAccess' => true,
			],
			'When access is granted and the "contributions" link is the first one' => [
				'expected' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions',
						],
						'global-contributions' => [
							'id' => 't-global-contributions',
							'text' => '(checkuser-global-contributions-link-sidebar)',
							'href' => '/wiki/Special:GlobalContributions/Page_target',
							'tooltip-params' => [ 'Page target' ],
						],
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here',
						],
					],
					'LANGUAGES' => [ 'LANGUAGES array' ],
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions',
						],
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here',
						],
					],
					'LANGUAGES' => [ 'LANGUAGES array' ],
				],
				'hasPageTarget' => true,
				'hasAccess' => true,
			],
			'When preconditions are met and the "contributions" link is between others' => [
				'expected' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here',
						],
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions',
						],
						'global-contributions' => [
							'id' => 't-global-contributions',
							'text' => '(checkuser-global-contributions-link-sidebar)',
							'href' => '/wiki/Special:GlobalContributions/Page_target',
							'tooltip-params' => [ 'Page target' ],
						],
						'something-else' => [
							'id' => 't-something-else',
							'text' => 'something-else',
						],
					],
					'LANGUAGES' => [ 'LANGUAGES array' ],
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here',
						],
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions',
						],
						'something-else' => [
							'id' => 't-something-else',
							'text' => 'something-else',
						],
					],
					'LANGUAGES' => [ 'LANGUAGES array' ],
				],
				'hasPageTarget' => true,
				'hasAccess' => true,
			],
			'When preconditions are met and the "contributions" link is the last one' => [
				'expected' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here',
						],
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions',
						],
						'global-contributions' => [
							'id' => 't-global-contributions',
							'text' => '(checkuser-global-contributions-link-sidebar)',
							'href' => '/wiki/Special:GlobalContributions/Page_target',
							'tooltip-params' => [ 'Page target' ],
						],
					],
					'LANGUAGES' => [ 'LANGUAGES array' ],
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here',
						],
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions',
						],
					],
					'LANGUAGES' => [ 'LANGUAGES array' ],
				],
				'hasPageTarget' => true,
				'hasAccess' => true,
			],
		];
	}

	/** @dataProvider provideIpAutoRevealLink */
	public function testIpAutoRevealLink(
		array $sidebar,
		bool $hasTempUsers,
		bool $canAutoReveal,
		bool $globalPreferencesIsLoaded,
		bool $autoRevealIsOn,
		array $expected
	): void {
		$this->setUserLang( 'qqx' );
		$this->config->set( 'CheckUserAutoRevealMaximumExpiry', 1 );

		$this->permissionStatus
			->method( 'isGood' )
			->willReturn( $canAutoReveal );

		$this->permissionManager
			->method( 'canAutoRevealIPAddresses' )
			->willReturn( $this->permissionStatus );

		$this->autoRevealLookup
			->method( 'isAutoRevealOn' )
			->willReturn( $autoRevealIsOn );

		$this->autoRevealLookup
			->method( 'isAutoRevealAvailable' )
			->willReturn( $globalPreferencesIsLoaded );

		$this->tempUserConfig
			->method( 'isKnown' )
			->willReturn( $hasTempUsers );

		$this->skin
			->method( 'getAuthority' )
			->willReturn( $this->authority );
		$this->skin
			->method( 'getOutput' )
			->willReturn( $this->createMock( OutputPage::class ) );
		$mockRequest = $this->createMock( WebRequest::class );
		$mockRequest->method( 'getText' )->willReturn( 'Foo' );
		$this->skin
			->method( 'getRequest' )
			->willReturn( $mockRequest );
		$this->mockSkinMessages();

		$this->sut->onSidebarBeforeOutput( $this->skin, $sidebar );
		$this->assertEquals( $expected, $sidebar );
	}

	public static function provideIpAutoRevealLink() {
		return [
			'Not added if user cannot auto-reveal' => [
				'sidebar' => [],
				'hasTempUsers' => true,
				'canAutoReveal' => false,
				'globalPreferencesIsLoaded' => true,
				'autoRevealIsOn' => false,
				'expected' => [],
			],
			'Not added if GlobalPreferences is not loaded' => [
				'sidebar' => [],
				'hasTempUsers' => true,
				'canAutoReveal' => true,
				'globalPreferencesIsLoaded' => false,
				'autoRevealIsOn' => false,
				'expected' => [],
			],
			'Not added if temp user is unknown' => [
				'sidebar' => [],
				'hasTempUsers' => false,
				'canAutoReveal' => true,
				'globalPreferencesIsLoaded' => true,
				'autoRevealIsOn' => false,
				'expected' => [],
			],
			'Added to existing sidebar toolbox, auto-reveal is off' => [
				'sidebar' => [
					'TOOLBOX' => [
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions',
						],
					],
				],
				'hasTempUsers' => true,
				'canAutoReveal' => true,
				'globalPreferencesIsLoaded' => true,
				'autoRevealIsOn' => false,
				'expected' => [
					'TOOLBOX' => [
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions',
						],
						'checkuser-ip-auto-reveal' => [
							'id' => 't-checkuser-ip-auto-reveal',
							'text' => '(checkuser-ip-auto-reveal-link-sidebar)',
							'href' => '#',
							'class' => 'checkuser-ip-auto-reveal',
							'icon' => 'userTemporaryLocation',
						],
					],
				],
			],
			'Added to existing sidebar toolbox, auto-reveal is on' => [
				'sidebar' => [
					'TOOLBOX' => [
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions',
						],
					],
				],
				'hasTempUsers' => true,
				'canAutoReveal' => true,
				'globalPreferencesIsLoaded' => true,
				'autoRevealIsOn' => true,
				'expected' => [
					'TOOLBOX' => [
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions',
						],
						'checkuser-ip-auto-reveal' => [
							'id' => 't-checkuser-ip-auto-reveal',
							'text' => '(checkuser-ip-auto-reveal-link-sidebar-on)',
							'href' => '#',
							'class' => 'checkuser-ip-auto-reveal',
							'icon' => 'userTemporaryLocation',
						],
					],
				],
			],
			'Added to sidebar without existing toolbox, auto-reveal is off' => [
				'sidebar' => [],
				'hasTempUsers' => true,
				'canAutoReveal' => true,
				'globalPreferencesIsLoaded' => true,
				'autoRevealIsOn' => false,
				'expected' => [
					'TOOLBOX' => [
						'checkuser-ip-auto-reveal' => [
							'id' => 't-checkuser-ip-auto-reveal',
							'text' => '(checkuser-ip-auto-reveal-link-sidebar)',
							'href' => '#',
							'class' => 'checkuser-ip-auto-reveal',
							'icon' => 'userTemporaryLocation',
						],
					],
				],
			],
		];
	}
}
