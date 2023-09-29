<?php

namespace MediaWiki\Tests\User\CentralId;

use CentralIdLookup;
use HashConfig;
use InvalidArgumentException;
use LocalIdLookup;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Unit\DummyServicesTrait;
use MediaWiki\User\CentralId\CentralIdLookupFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @coversDefaultClass \MediaWiki\User\CentralId\CentralIdLookupFactory
 */
class CentralIdLookupFactoryTest extends MediaWikiUnitTestCase {
	use DummyServicesTrait;

	/** @var CentralIdLookup */
	private $centralLookupMock;

	protected function setUp(): void {
		parent::setUp();
		$this->centralLookupMock = $this->getMockForAbstractClass( CentralIdLookup::class );
	}

	private function makeFactory(): CentralIdLookupFactory {
		$services = [
			'DBLoadBalancer' => $this->createMock( ILoadBalancer::class ),
			'MainConfig' => new HashConfig( [
				MainConfigNames::SharedDB => null,
				MainConfigNames::SharedTables => [],
				MainConfigNames::LocalDatabases => [],
			] ),
		];
		$localIdLookupTest = [
			'class' => LocalIdLookup::class,
			'services' => [
				'MainConfig',
				'DBLoadBalancer',
			]
		];
		return new CentralIdLookupFactory(
			new ServiceOptions(
				CentralIdLookupFactory::CONSTRUCTOR_OPTIONS,
				[
					MainConfigNames::CentralIdLookupProviders => [
						'local' => $localIdLookupTest,
						'local2' => $localIdLookupTest,
						'mock' => [ 'factory' => function () {
							return $this->centralLookupMock;
						} ]
					],
					MainConfigNames::CentralIdLookupProvider => 'mock',
				]
			),
			$this->getDummyObjectFactory( $services ),
			$this->createNoOpMock( UserIdentityLookup::class )
		);
	}

	/**
	 * @covers ::__construct
	 * @covers ::getLookup
	 */
	public function testCreation() {
		$factory = $this->makeFactory();

		$this->assertSame( $this->centralLookupMock, $factory->getLookup() );
		$this->assertSame( $this->centralLookupMock, $factory->getLookup( 'mock' ) );
		$this->assertSame( 'mock', $this->centralLookupMock->getProviderId() );

		$local = $factory->getLookup( 'local' );
		$this->assertNotSame( $this->centralLookupMock, $local );
		$this->assertInstanceOf( LocalIdLookup::class, $local );
		$this->assertSame( $local, $factory->getLookup( 'local' ) );
		$this->assertSame( 'local', $local->getProviderId() );

		$local2 = $factory->getLookup( 'local2' );
		$this->assertNotSame( $local, $local2 );
		$this->assertInstanceOf( LocalIdLookup::class, $local2 );
		$this->assertSame( 'local2', $local2->getProviderId() );
	}

	/**
	 * @covers ::getLookup
	 */
	public function testUnconfiguredProvidersThrow() {
		$this->expectException( InvalidArgumentException::class );
		$factory = $this->makeFactory();
		$factory->getLookup( 'unconfigured' );
	}

	/**
	 * @covers ::getNonLocalLookup
	 */
	public function testGetNonLocal() {
		$factory = $this->makeFactory();
		$this->assertInstanceOf( CentralIdLookup::class, $factory->getNonLocalLookup() );
		$this->assertNull( $factory->getNonLocalLookup( 'local' ) );
	}

	/**
	 * @covers ::getProviderIds
	 */
	public function testGetProviderIds() {
		$factory = $this->makeFactory();
		$expected = [ 'local', 'local2', 'mock' ];
		$this->assertSame( $expected, $factory->getProviderIds() );
	}

	/**
	 * @covers ::getDefaultProviderId
	 */
	public function testGetDefaultProviderId() {
		$factory = $this->makeFactory();
		$this->assertSame( 'mock', $factory->getDefaultProviderId() );
	}
}
