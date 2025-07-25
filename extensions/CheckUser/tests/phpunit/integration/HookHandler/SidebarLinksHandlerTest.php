<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\HookHandler\SidebarLinksHandler;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\Config;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\Skin;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\HookHandler\SidebarLinksHandler
 */
class SidebarLinksHandlerTest extends MediaWikiIntegrationTestCase {
	/** @var (Authority&MockObject) */
	private Authority $authority;

	/** @var (Skin&MockObject) */
	private Skin $skin;

	/** @var (Config&MockObject) */
	private Config $config;

	/** @var (CheckUserPermissionManager&MockObject) */
	private CheckUserPermissionManager $permissionManager;

	/** @var (CheckUserPermissionStatus&MockObject) */
	private CheckUserPermissionStatus $permissionStatus;

	/** @var (ExtensionRegistry&MockObject) */
	private ExtensionRegistry $extensionRegistry;

	/** @var (UserIdentity&MockObject) */
	private UserIdentity $relevantUser;

	private SidebarLinksHandler $sut;

	public function setUp(): void {
		parent::setUp();

		$this->authority = $this->createMock( Authority::class );
		$this->skin = $this->createMock( Skin::class );

		$this->config = $this->createMock( Config::class );

		$this->permissionStatus = $this->createMock(
			CheckUserPermissionStatus::class
		);
		$this->permissionManager = $this->createMock(
			CheckUserPermissionManager::class
		);
		$this->extensionRegistry = $this->createMock(
			ExtensionRegistry::class
		);
		$this->relevantUser = $this->createMock(
			UserIdentity::class
		);

		$this->sut = new SidebarLinksHandler(
			$this->config,
			$this->permissionManager,
			$this->extensionRegistry
		);
	}

	private function mockSkinMessages() {
		$this->skin
			->method( 'msg' )
			->willReturnCallback( static function ( $key ): Message {
				return new Message( $key );
			} );
	}

	/**
	 * @dataProvider whenGlobalContributionsLinkShouldNotBeAddedDataProvider
	 */
	public function testWhenTheLinkShouldNotBeAdded(
		array $expected,
		array $sidebar,
		bool $hasRelevantUser,
		bool $hasAccess
	): void {
		$this->setUserLang( 'qqx' );

		$this->skin
			->method( 'getRelevantUser' )
			->willReturn( $hasRelevantUser ? $this->relevantUser : null );
		$this->skin
			->method( 'getAuthority' )
			->willReturn( $this->authority );
		$this->mockSkinMessages();

		if ( $hasRelevantUser ) {
			$this->relevantUser
				->method( 'getName' )
				->willReturn( 'Relevant User name' );

			$this->permissionManager
				->expects( $this->once() )
				->method( 'canAccessUserGlobalContributions' )
				->with( $this->authority, 'Relevant User name' )
				->willReturn( $this->permissionStatus );
		} else {
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
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [ 'TOOLBOX array' ],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'hasRelevantUser' => false,
				'hasAccess' => false,
			],
			'When the accessing user lacks access' => [
				'expected' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [ 'TOOLBOX array' ],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [ 'TOOLBOX array' ],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'hasRelevantUser' => true,
				'hasAccess' => false,
			],
			'When access is not granted and the sidebar is empty' => [
				// Tests for errors checking preconditions for an empty sidebar
				// (i.e. "Undefined array key 'TOOLBOX'" errors)
				'expected' => [],
				'sidebar' => [],
				'hasRelevantUser' => false,
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
							'href' => '/wiki/Special:GlobalContributions/Relevant_User_name',
						],
					],
				],
				'sidebar' => [],
				'hasRelevantUser' => true,
				'hasAccess' => true,
			],
			'When access is granted and the "contributions" link is the first one' => [
				'expected' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions'
						],
						'global-contributions' => [
							'id' => 't-global-contributions',
							'text' => '(checkuser-global-contributions-link-sidebar)',
							'href' => '/wiki/Special:GlobalContributions/Relevant_User_name',
						],
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here'
						],
					],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions'
						],
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here'
						],
					],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'hasRelevantUser' => true,
				'hasAccess' => true,
			],
			'When preconditions are met and the "contributions" link is between others' => [
				'expected' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here'
						],
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions'
						],
						'global-contributions' => [
							'id' => 't-global-contributions',
							'text' => '(checkuser-global-contributions-link-sidebar)',
							'href' => '/wiki/Special:GlobalContributions/Relevant_User_name',
						],
						'something-else' => [
							'id' => 't-something-else',
							'text' => 'something-else',
						]
					],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here'
						],
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions'
						],
						'something-else' => [
							'id' => 't-something-else',
							'text' => 'something-else',
						]
					],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'hasRelevantUser' => true,
				'hasAccess' => true,
			],
			'When preconditions are met and the "contributions" link is the last one' => [
				'expected' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here'
						],
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions'
						],
						'global-contributions' => [
							'id' => 't-global-contributions',
							'text' => '(checkuser-global-contributions-link-sidebar)',
							'href' => '/wiki/Special:GlobalContributions/Relevant_User_name',
						]
					],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'sidebar' => [
					'navigation' => [ 'navigation array' ],
					'TOOLBOX' => [
						'whatlinkshere' => [
							'id' => 't-whatlinkshere',
							'text' => 'What links here'
						],
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions'
						],
					],
					'LANGUAGES' => [ 'LANGUAGES array' ]
				],
				'hasRelevantUser' => true,
				'hasAccess' => true,
			]
		];
	}

	/** @dataProvider provideIpAutoRevealLink */
	public function testIpAutoRevealLink(
		array $sidebar,
		bool $canAutoReveal,
		bool $globalPreferencesIsLoaded,
		array $expected
	): void {
		$this->setUserLang( 'qqx' );

		$this->permissionStatus
			->method( 'isGood' )
			->willReturn( $canAutoReveal );

		$this->permissionManager
			->method( 'canAutoRevealIPAddresses' )
			->willReturn( $this->permissionStatus );

		$this->extensionRegistry
			->method( 'isLoaded' )
			->willReturn( $globalPreferencesIsLoaded );

		$this->skin
			->method( 'getAuthority' )
			->willReturn( $this->authority );
		$this->skin
			->method( 'getOutput' )
			->willReturn( $this->createMock( OutputPage::class ) );
		$this->mockSkinMessages();

		$this->sut->onSidebarBeforeOutput( $this->skin, $sidebar );
		$this->assertEquals( $expected, $sidebar );
	}

	public static function provideIpAutoRevealLink() {
		return [
			'Not added if user cannot auto-reveal' => [
				'sidebar' => [],
				'canAutoReveal' => false,
				'globalPreferencesIsLoaded' => true,
				'expected' => [],
			],
			'Not added if GlobalPreferences is not loaded' => [
				'sidebar' => [],
				'canAutoReveal' => true,
				'globalPreferencesIsLoaded' => false,
				'expected' => [],
			],
			'Added to existing sidebar toolbox' => [
				'sidebar' => [
					'TOOLBOX' => [
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions'
						],
					],
				],
				'canAutoReveal' => true,
				'globalPreferencesIsLoaded' => true,
				'expected' => [
					'TOOLBOX' => [
						'contributions' => [
							'id' => 't-contributions',
							'text' => 'User contributions'
						],
						'checkuser-ip-auto-reveal' => [
							'id' => 't-checkuser-ip-auto-reveal',
							'text' => '(checkuser-ip-auto-reveal-link-sidebar)',
							'href' => '#',
							'class' => 'checkuser-ip-auto-reveal',
						],
					],
				],
			],
			'Added to sidebar without existing toolbox' => [
				'sidebar' => [],
				'canAutoReveal' => true,
				'globalPreferencesIsLoaded' => true,
				'expected' => [
					'TOOLBOX' => [
						'checkuser-ip-auto-reveal' => [
							'id' => 't-checkuser-ip-auto-reveal',
							'text' => '(checkuser-ip-auto-reveal-link-sidebar)',
							'href' => '#',
							'class' => 'checkuser-ip-auto-reveal',
						],
					],
				],
			],
		];
	}
}
