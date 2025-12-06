<?php

namespace MediaWiki\Extension\Scribunto\Tests;

use MediaWiki\Config\ConfigException;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Scribunto\EngineFactory;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Scribunto\EngineFactory
 */
class EngineFactoryTest extends MediaWikiIntegrationTestCase {

	private function newFactory( array $options ) {
		return new EngineFactory(
			new ServiceOptions(
				EngineFactory::CONSTRUCTOR_OPTIONS,
				new HashConfig( $options )
			),
		);
	}

	public function testGetDefaultEngine() {
		$factory = $this->getServiceContainer()->getService( 'Scribunto.EngineFactory' );
		$this->assertNotNull( $factory->getDefaultEngine() );
	}

	/** @dataProvider provideGetDefaultEngineException */
	public function testGetDefaultEngineException( array $options ) {
		$factory = $this->newFactory( $options );

		$this->expectException( ConfigException::class );
		$factory->getDefaultEngine();
	}

	public static function provideGetDefaultEngineException(): iterable {
		return [
			[
				[
					'ScribuntoDefaultEngine' => null,
					'ScribuntoEngineConf' => [],
				],
			],
			[
				[
					'ScribuntoDefaultEngine' => 'not-defined',
					'ScribuntoEngineConf' => [],
				],
			],
		];
	}
}
