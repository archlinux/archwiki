<?php

namespace MediaWiki\Extension\VisualEditor\Tests;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\VisualEditor\DirectParsoidClient;
use MediaWiki\Extension\VisualEditor\DualParsoidClient;
use MediaWiki\Extension\VisualEditor\VisualEditorParsoidClientFactory;
use MediaWiki\Extension\VisualEditor\VRSParsoidClient;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\Handler\Helper\PageRestHelperFactory;
use MediaWikiIntegrationTestCase;
use MultiHttpClient;
use ParsoidVirtualRESTService;
use Psr\Log\NullLogger;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \MediaWiki\Extension\VisualEditor\VisualEditorParsoidClientFactory
 */
class VisualEditorParsoidClientFactoryTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testGetVisualEditorParsoidClientFactory() {
		$veParsoidClientFactory = $this->getServiceContainer()
			->get( VisualEditorParsoidClientFactory::SERVICE_NAME );

		$this->assertInstanceOf( VisualEditorParsoidClientFactory::class, $veParsoidClientFactory );
	}

	private function newClientFactory( array $optionValues ) {
		$options = new ServiceOptions( VisualEditorParsoidClientFactory::CONSTRUCTOR_OPTIONS, $optionValues );

		$httpRequestFactory = $this->createNoOpMock( HttpRequestFactory::class, [ 'createMultiClient' ] );
		$httpRequestFactory->method( 'createMultiClient' )->willReturn(
			$this->createNoOpMock( MultiHttpClient::class )
		);

		return new VisualEditorParsoidClientFactory(
			$options,
			$httpRequestFactory,
			new NullLogger(),
			$this->createNoOpMock( PageRestHelperFactory::class )
		);
	}

	public function provideGetClient() {
		yield 'Empty VRS modules array, DefaultParsoidClient=vrs, no hints' => [
			[
				MainConfigNames::ParsoidSettings => [],
				MainConfigNames::VirtualRestConfig => [
					'modules' => []
				],
				VisualEditorParsoidClientFactory::ENABLE_COOKIE_FORWARDING => false,
				VisualEditorParsoidClientFactory::DEFAULT_PARSOID_CLIENT_SETTING => 'vrs',
			],
			[],
			DirectParsoidClient::class
		];

		yield 'No VRS modules array, DefaultParsoidClient=vrs, no hints' => [
			[
				MainConfigNames::ParsoidSettings => [],
				MainConfigNames::VirtualRestConfig => [],
				VisualEditorParsoidClientFactory::ENABLE_COOKIE_FORWARDING => false,
				VisualEditorParsoidClientFactory::DEFAULT_PARSOID_CLIENT_SETTING => 'vrs',
			],
			[],
			DirectParsoidClient::class
		];

		yield 'restbase module defined, DefaultParsoidClient=vrs, no hints' => [
			[
				MainConfigNames::ParsoidSettings => [],
				MainConfigNames::VirtualRestConfig => [
					'modules' => [ 'restbase' => [] ]
				],
				VisualEditorParsoidClientFactory::ENABLE_COOKIE_FORWARDING => false,
				VisualEditorParsoidClientFactory::DEFAULT_PARSOID_CLIENT_SETTING => 'vrs',
			],
			[],
			VRSParsoidClient::class
		];

		yield 'parsoid module defined, DefaultParsoidClient=vrs, no hints' => [
			[
				MainConfigNames::ParsoidSettings => [],
				MainConfigNames::VirtualRestConfig => [
					'modules' => [ 'parsoid' => [] ]
				],
				VisualEditorParsoidClientFactory::ENABLE_COOKIE_FORWARDING => false,
				VisualEditorParsoidClientFactory::DEFAULT_PARSOID_CLIENT_SETTING => 'vrs',
			],
			[],
			VRSParsoidClient::class
		];

		yield 'parsoid module defined, DefaultParsoidClient=direct, no hints' => [
			[
				MainConfigNames::ParsoidSettings => [],
				MainConfigNames::VirtualRestConfig => [
					'modules' => [ 'parsoid' => [] ]
				],
				VisualEditorParsoidClientFactory::ENABLE_COOKIE_FORWARDING => false,
				VisualEditorParsoidClientFactory::DEFAULT_PARSOID_CLIENT_SETTING => 'direct',
			],
			[],
			DirectParsoidClient::class
		];

		yield 'parsoid module defined, DefaultParsoidClient=direct, ShouldUseVRS=true' => [
			[
				MainConfigNames::ParsoidSettings => [],
				MainConfigNames::VirtualRestConfig => [
					'modules' => [ 'parsoid' => [] ]
				],
				VisualEditorParsoidClientFactory::ENABLE_COOKIE_FORWARDING => false,
				VisualEditorParsoidClientFactory::DEFAULT_PARSOID_CLIENT_SETTING => 'direct',
			],
			[ 'ShouldUseVRS' => true ],
			VRSParsoidClient::class
		];

		yield 'parsoid module define, ShouldUseVRS = false' => [
			[
				MainConfigNames::ParsoidSettings => [],
				MainConfigNames::VirtualRestConfig => [
					'modules' => [ 'parsoid' => [] ]
				],
				VisualEditorParsoidClientFactory::ENABLE_COOKIE_FORWARDING => false,
				VisualEditorParsoidClientFactory::DEFAULT_PARSOID_CLIENT_SETTING => 'vrs',
			],
			[ 'ShouldUseVRS' => false ],
			DirectParsoidClient::class
		];

		yield 'No VRS modules array, ShouldUseVRS = true' => [
			[
				MainConfigNames::ParsoidSettings => [],
				MainConfigNames::VirtualRestConfig => [],
				VisualEditorParsoidClientFactory::ENABLE_COOKIE_FORWARDING => false,
				VisualEditorParsoidClientFactory::DEFAULT_PARSOID_CLIENT_SETTING => 'vrs',
			],
			[ 'ShouldUseVRS' => true ],
			DirectParsoidClient::class
		];
	}

	/**
	 * @dataProvider provideGetClient
	 * @covers ::createParsoidClientInternal
	 * @covers ::createParsoidClient
	 */
	public function testGetClient( $optionValues, $hints, $expectedType ) {
		$authority = $this->createNoOpMock( Authority::class );

		$factory = $this->newClientFactory( $optionValues );

		$client = $factory->createParsoidClientInternal( false, $authority, $hints );
		$this->assertInstanceOf( $expectedType, $client );

		// This just checks that nothing explodes.
		$client = $factory->createParsoidClient( false, $authority );
		$this->assertInstanceOf( DualParsoidClient::class, $client );
	}

	public function provideCookieToForward() {
		yield 'When no cookie is sent' => [ false, false ];

		yield 'When a cookie is sent as a string' => [ 'cookie', 'cookie' ];

		yield 'When a cookie is sent as an array' => [ [ 'cookie' ], 'cookie' ];
	}

	/**
	 * @dataProvider provideCookieToForward
	 * @covers ::createParsoidClient
	 */
	public function testGetVRSClientForwardedCookies( $cookie, $expectedCookie ) {
		$authority = $this->createNoOpMock( Authority::class );

		$optionValues = [
			MainConfigNames::ParsoidSettings => [],
			MainConfigNames::VirtualRestConfig => [
				'modules' => [
					'parsoid' => [
						'forwardCookies' => true,
						'restbaseCompat' => false,
					]
				]
			],
			VisualEditorParsoidClientFactory::ENABLE_COOKIE_FORWARDING => true,
			VisualEditorParsoidClientFactory::DEFAULT_PARSOID_CLIENT_SETTING => 'vrs',
		];

		$parsoidClient = $this->newClientFactory( $optionValues )->createParsoidClientInternal( $cookie, $authority );
		$vrsClient = TestingAccessWrapper::newFromObject( $parsoidClient )->vrsClient;

		$mountAndService = $vrsClient->getMountAndService( '/restbase/' );

		// Assert that the mount and service are correct
		$this->assertInstanceOf( ParsoidVirtualRESTService::class, $mountAndService[1] );
		$this->assertSame( '/restbase/', $mountAndService[0] );
		$this->assertSame( 'parsoid', $mountAndService[1]->getName() );

		$reqs = [
			[
				'url' => 'local/v1/page/html/Main_Page',
				'domain' => 'local',
				'timeout' => null,
				'forwardCookies' => true,
				'HTTPProxy' => null,
				'restbaseCompat' => true,
			],
		];
		$res = $mountAndService[1]->onRequests( $reqs, static function () {
			return;
		} );

		if ( $cookie && is_string( $cookie ) ) {
			$this->assertTrue( isset( $res[0]['forwardCookies'] ) );
			$this->assertSame( $expectedCookie, $res[0]['headers']['Cookie'] );
		} elseif ( $cookie && is_array( $cookie ) ) {
			$this->assertTrue( $res[0]['forwardCookies'] );
			$this->assertSame( $expectedCookie, $res[0]['headers']['Cookie'][0] );
		} else {
			$this->assertTrue( $res[0]['forwardCookies'] );
			$this->assertArrayNotHasKey( 'Cookie', $res[0]['headers'] );
		}
		$this->assertSame( 'local', $res[0]['domain'] );
		$this->assertTrue( $res[0]['forwardCookies'] );
		$this->assertArrayHasKey( 'headers', $res[0] );
		$this->assertArrayHasKey( 'Host', $res[0]['headers'] );
	}

	/**
	 * @dataProvider provideUseParsoidOverHTTP
	 * @covers ::useParsoidOverHTTP
	 */
	public function testUseParsoidOverHTTP( array $optionValues, bool $expected ) {
		$parsoidClient = $this->newClientFactory( $optionValues );

		$this->assertSame( $expected, $parsoidClient->useParsoidOverHTTP() );
	}

	public function provideUseParsoidOverHTTP() {
		// TODO: test a lot more config!

		yield 'restbaseUrl: No VRS modules, DefaultParsoidClient=vrs' => [
			[
				'VirtualRestConfig' => [ 'modules' => [] ],
				'VisualEditorRestbaseURL' => 'parsoid-url',
				'VisualEditorFullRestbaseURL' => 'full-parsoid-url',
				'VisualEditorDefaultParsoidClient' => 'vrs',
				'EnableCookieForwarding' => true,
			],
			false
		];
		yield 'restbaseUrl: VRS modules available, DefaultParsoidClient=vrs' => [
			[
				'VirtualRestConfig' => [ 'modules' => [
					'parsoid' => true,
				] ],
				'VisualEditorRestbaseURL' => 'parsoid-url',
				'VisualEditorFullRestbaseURL' => 'full-parsoid-url',
				'VisualEditorDefaultParsoidClient' => 'vrs',
				'EnableCookieForwarding' => true,
			],
			true
		];
		yield 'restbaseUrl: VRS modules available, but no direct access URLs. DefaultParsoidClient=vrs' => [
			[
				'VirtualRestConfig' => [ 'modules' => [
					'parsoid' => true,
				] ],
				'VisualEditorRestbaseURL' => 'parsoid-url',
				'VisualEditorFullRestbaseURL' => 'full-parsoid-url',
				'VisualEditorDefaultParsoidClient' => 'vrs',
				'EnableCookieForwarding' => true,
			],
			true
		];

		yield 'restbaseUrl: VRS modules available, but DefaultParsoidClient=direct' => [
			[
				'VirtualRestConfig' => [ 'modules' => [
					'parsoid' => true,
				] ],
				'VisualEditorRestbaseURL' => 'parsoid-url',
				'VisualEditorFullRestbaseURL' => 'full-parsoid-url',
				'VisualEditorDefaultParsoidClient' => 'direct',
				'EnableCookieForwarding' => true,
			],
			false
		];
	}

}
