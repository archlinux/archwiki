<?php

namespace MediaWiki\Extension\Math\Render;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\WANObjectCache;

class RendererFactoryTest extends MediawikiUnitTestCase {

	private function createRendererFactory( array $parameters ): RendererFactory {
		$mathConfig = $this->createMock( MathConfig::class );
		$mathConfig
			->expects( self::any() )
			->method( 'getValidRenderingModes' )
			->willReturn( $parameters['validModes'] ?? MathConfig::SUPPORTED_MODES );

		$options = $this->createMock( ServiceOptions::class );
		$options
			->expects( self::any() )
			->method( 'get' )
			->with( 'MathEnableExperimentalInputFormats' )
			->willReturnCallback( static function () use ( $parameters ) {
				return $parameters['experimentalFormats'] ?? false;
			} );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup
			->expects( self::any() )
			->method( 'getDefaultOption' )
			->with( 'math' )
			->willReturn( $parameters['defaultMode'] ?? MathConfig::MODE_NATIVE_MML );

		$logger = $this->createStub( LoggerInterface::class );
		$logger
			->method( 'debug' )
			->willReturn( null );

		$cache = $this->createStub( WANObjectCache::class );

		return new RendererFactory(
			$options,
			$mathConfig,
			$userOptionsLookup,
			$logger,
			$cache
		);
	}

	public function determineModeDataProvider(): array {
		return [
			[
				[], [], MathConfig::MODE_SOURCE, MathConfig::MODE_SOURCE
			],
			[
				[], [], MathConfig::MODE_MATHML, MathConfig::MODE_MATHML
			],
			[
				[], [], MathConfig::MODE_NATIVE_MML, MathConfig::MODE_NATIVE_MML
			],
			[
				[],
				[ 'forcemathmode' => MathConfig::MODE_MATHML ],
				MathConfig::MODE_NATIVE_MML,
				MathConfig::MODE_MATHML
			],
			[
				[],
				[ 'forcemathmode' => MathConfig::MODE_NATIVE_MML ],
				MathConfig::MODE_MATHML,
				MathConfig::MODE_NATIVE_MML
			],
			[
				[ 'validModes' =>
					[
						MathConfig::MODE_SOURCE,
						MathConfig::MODE_NATIVE_MML
					]
				],
				[],
				MathConfig::MODE_NATIVE_MML,
				MathConfig::MODE_NATIVE_MML
			],
			[
				[ 'validModes' =>
					[
						MathConfig::MODE_SOURCE,
						MathConfig::MODE_NATIVE_MML
					]
				],
				[],
				MathConfig::MODE_MATHML,
				MathConfig::MODE_NATIVE_MML
			],
			[
				[ 'validModes' =>
					[
						MathConfig::MODE_SOURCE,
						MathConfig::MODE_NATIVE_MML
					]
				],
				[ 'forcemathmode' => MathConfig::MODE_MATHML ],
				MathConfig::MODE_SOURCE,
				MathConfig::MODE_NATIVE_MML
			],
			[
				[ 'validModes' =>
					[
						MathConfig::MODE_SOURCE,
						MathConfig::MODE_MATHML
					],
					'defaultMode' => MathConfig::MODE_MATHML
				],
				[ 'forcemathmode' => MathConfig::MODE_NATIVE_MML ],
				MathConfig::MODE_SOURCE,
				MathConfig::MODE_MATHML
			],
			[
				[],
				[
					'chem' => true,
				],
				MathConfig::MODE_NATIVE_MML,
				MathConfig::MODE_NATIVE_MML,
				[
					'chem' => true,
					'type' => 'chem'
				]
			],
			[
				[],
				[
					'chem' => true,
				],
				MathConfig::MODE_SOURCE,
				MathConfig::MODE_MATHML,
				[
					'chem' => true,
					'type' => 'chem'
				]
			],
			[
				[],
				[
					'chem' => true,
					'forcemathmode' => MathConfig::MODE_NATIVE_MML
				],
				MathConfig::MODE_SOURCE,
				MathConfig::MODE_NATIVE_MML,
				[
					'chem' => true,
					'forcemathmode' => MathConfig::MODE_NATIVE_MML,
					'type' => 'chem'
				]
			],
			[
				[],
				[
					'chem' => true,
					'forcemathmode' => MathConfig::MODE_SOURCE
				],
				MathConfig::MODE_NATIVE_MML,
				MathConfig::MODE_MATHML,
				[
					'chem' => true,
					'forcemathmode' => MathConfig::MODE_SOURCE,
					'type' => 'chem'
				]
			],
			[
				[
					'experimentalFormats' => true
				],
				[
					'type' => 'broken_type',
				],
				MathConfig::MODE_MATHML,
				MathConfig::MODE_MATHML,
				[]
			],
			[
				[
					'experimentalFormats' => true
				],
				[
					'type' => 'broken_type',
				],
				MathConfig::MODE_NATIVE_MML,
				MathConfig::MODE_NATIVE_MML
			],
			[
				[
					'experimentalFormats' => true
				],
				[
					'type' => 'pmml',
				],
				MathConfig::MODE_MATHML,
				MathConfig::MODE_MATHML
			],
		];
	}

	/**
	 * @param array $factoryParameters
	 * @param array $tagParameters
	 * @param string $givenMode
	 * @param string $expectedMode
	 * @param array|null $expectedArgs
	 *
	 * @return void
	 * @dataProvider determineModeDataProvider
	 * @covers \MediaWiki\Extension\Math\Render\RendererFactory::determineMode
	 */
	public function testDetermineMode(
		array $factoryParameters,
		array $tagParameters,
		string $givenMode,
		string $expectedMode,
		?array $expectedArgs = null
	): void {
		$factory = $this->createRendererFactory( $factoryParameters );
		[ $mode, $receivedParams ] = $factory->determineMode( $givenMode, $tagParameters );
		$this->assertEquals(
			$expectedMode,
			$mode,
			'Determine mode returns unexpected mode'
		);
		$this->assertEquals(
			$expectedArgs ?? $tagParameters,
			$receivedParams,
			'Determine mode modifies parameters in unexpected behavior'
		);
	}
}
