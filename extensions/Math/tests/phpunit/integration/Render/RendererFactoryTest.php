<?php

namespace MediaWiki\Extension\Math\Tests\Render;

use InvalidArgumentException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathLaTeXML;
use MediaWiki\Extension\Math\MathMathML;
use MediaWiki\Extension\Math\MathMathMLCli;
use MediaWiki\Extension\Math\MathNativeMML;
use MediaWiki\Extension\Math\MathSource;
use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @covers \MediaWiki\Extension\Math\Render\RendererFactory
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class RendererFactoryTest extends MediaWikiIntegrationTestCase {

	private ServiceOptions|MockObject $options;

	private MockObject|MathConfig $mathConfig;

	private UserOptionsLookup|MockObject $userOptionsLookup;

	private LoggerInterface|MockObject $logger;

	private WANObjectCache|MockObject $cache;

	private RendererFactory $factory;
	private const FAKE_INPUT_HASH = 'global:MediaWiki\Extension\Math\MathRenderer:test_hash_123';
	private const FAKE_DATA = [
		'math_mode' => MathConfig::MODE_SOURCE,
		'tex' => 'x^2'
	];

	protected function setUp(): void {
		parent::setUp();

		$this->options = $this->createMock( ServiceOptions::class );
		$this->mathConfig = $this->createMock( MathConfig::class );
		$this->userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$this->logger = $this->createMock( LoggerInterface::class );
		$fakeWAN = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );

		$fakeWAN->set( self::FAKE_INPUT_HASH, self::FAKE_DATA );
		$this->cache = $fakeWAN;

		$this->options->method( 'assertRequiredOptions' )->willReturn( true );

		$this->mathConfig->method( 'getValidRenderingModes' )->willReturn( [
			MathConfig::MODE_SOURCE,
			MathConfig::MODE_MATHML,
			MathConfig::MODE_LATEXML,
			MathConfig::MODE_NATIVE_MML
		] );

		$this->userOptionsLookup->method( 'getDefaultOption' )
			->with( 'math' )
			->willReturn( MathConfig::MODE_MATHML );

		$this->factory = new RendererFactory(
			$this->options,
			$this->mathConfig,
			$this->userOptionsLookup,
			$this->logger,
			$this->cache
		);
	}

	public function testGetRendererForSourceMode() {
		$tex = 'x^2 + y^2 = z^2';

		$renderer = $this->factory->getRenderer( $tex, [], MathConfig::MODE_SOURCE );

		$this->assertInstanceOf( MathSource::class, $renderer );
		$this->assertEquals( $tex, $renderer->getTex() );
	}

	public function testGetRendererForNativeMMLMode() {
		$tex = 'x^2 + y^2 = z^2';

		$renderer = $this->factory->getRenderer( $tex, [], MathConfig::MODE_NATIVE_MML );

		$this->assertInstanceOf( MathNativeMML::class, $renderer );
	}

	public function testGetRendererForLaTeXMLMode() {
		$tex = 'x^2 + y^2 = z^2';

		$renderer = $this->factory->getRenderer( $tex, [], MathConfig::MODE_LATEXML );

		$this->assertInstanceOf( MathLaTeXML::class, $renderer );
	}

	public function testGetRendererForMathMLMode() {
		$tex = 'x^2 + y^2 = z^2';

		$this->options->method( 'get' )
			->willReturnMap( [
				[ 'MathoidCli', false ],
				[ 'MathEnableExperimentalInputFormats', false ]
			] );

		$renderer = $this->factory->getRenderer( $tex, [], MathConfig::MODE_MATHML );

		$this->assertInstanceOf( MathMathML::class, $renderer );
	}

	public function testGetRendererForMathMLModeWithMathoidCli() {
		$tex = 'x^2 + y^2 = z^2';

		$this->options->method( 'get' )
			->willReturnMap( [
				[ 'MathoidCli', true ],
				[ 'MathEnableExperimentalInputFormats', false ]
			] );

		$renderer = $this->factory->getRenderer( $tex, [], MathConfig::MODE_MATHML );

		$this->assertInstanceOf( MathMathMLCli::class, $renderer );
	}

	public function testGetRendererWithInvalidMode() {
		$tex = 'x^2 + y^2 = z^2';
		$invalidMode = 'invalid_mode';

		$renderer = $this->factory->getRenderer( $tex, [], $invalidMode );

		// Should fall back to default mode (MATHML)
		$this->assertInstanceOf( MathMathML::class, $renderer );
	}

	public function testGetRendererWithForceMathMode() {
		$tex = 'x^2 + y^2 = z^2';
		$params = [ 'forcemathmode' => MathConfig::MODE_SOURCE ];

		$renderer = $this->factory->getRenderer( $tex, $params, MathConfig::MODE_MATHML );

		// Should use the forced mode instead of the provided mode
		$this->assertInstanceOf( MathSource::class, $renderer );
	}

	public function testGetRendererWithChemParam() {
		$tex = 'H_2O';
		$params = [ 'chem' => true ];

		$this->options->method( 'get' )
			->willReturnMap( [
				[ 'MathoidCli', false ],
				[ 'MathEnableExperimentalInputFormats', false ]
			] );

		$renderer = $this->factory->getRenderer( $tex, $params, MathConfig::MODE_SOURCE );

		// Chemistry should override to MATHML mode
		$this->assertInstanceOf( MathMathML::class, $renderer );
		$this->assertEquals( 'chem', $renderer->getInputType() );
	}

	public function testGetRendererWithExperimentalFormat() {
		$tex = '<math><mi>x</mi></math>';
		$params = [ 'type' => 'pmml' ];

		$this->options->method( 'get' )
			->willReturnMap( [
				[ 'MathoidCli', false ],
				[ 'MathEnableExperimentalInputFormats', true ]
			] );

		$renderer = $this->factory->getRenderer( $tex, $params, MathConfig::MODE_MATHML );

		$this->assertEquals( 'pmml', $renderer->getInputType() );
	}

	public function testGetFromHash() {
		// Create a partial mock to avoid actually initializing from cache
		$factory = $this->getMockBuilder( RendererFactory::class )
			->setConstructorArgs( [
				$this->options,
				$this->mathConfig,
				$this->userOptionsLookup,
				$this->logger,
				$this->cache
			] )
			->onlyMethods( [ 'getRenderer' ] )
			->getMock();

		$mockRenderer = $this->createMock( MathSource::class );
		$mockRenderer->expects( $this->once() )
			->method( 'initializeFromCache' )
			->with( self::FAKE_DATA );

		$factory->expects( $this->once() )
			->method( 'getRenderer' )
			->with( '', [], MathConfig::MODE_SOURCE )
			->willReturn( $mockRenderer );

		$result = $factory->getFromHash( 'test_hash_123' );
		$this->assertSame( $mockRenderer, $result );
	}

	public function testGetFromHashWithInvalidCache() {
		$inputHash = 'invalid_hash';

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cache key is invalid' );

		$this->factory->getFromHash( $inputHash );
	}
}
