<?php

namespace MediaWiki\Extension\Math\Tests;

use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\Page\PageReferenceValue;
use MediaWikiIntegrationTestCase;
use ParserOptions;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Math\HookHandlers\ParserHooksHandler
 * @coversDefaultClass \MediaWiki\Extension\Math\HookHandlers\ParserHooksHandler
 */
class ParserIntegrationTests extends MediaWikiIntegrationTestCase {

	private function getDummyRenderer( $mode, $tex, $params ): MathRenderer {
		return new class( $mode, $tex, $params ) extends MathRenderer {
			public function __construct( $mode, $tex = '', $params = [] ) {
				parent::__construct( $tex, $params );
				$this->mode = $mode;
			}

			public function render( $forceReRendering = false ) {
				return true;
			}

			public function checkTeX() {
				return true;
			}

			public function getHtmlOutput() {
				return "<render>$this->mode:$this->tex</render>";
			}

			protected function getMathTableName() {
				return 'whatever';
			}
		};
	}

	private function setupDummyRendering() {
		$this->setMwGlobals( 'wgMathValidModes', [ MathConfig::MODE_SOURCE, MathConfig::MODE_PNG ] );
		$this->mergeMwGlobalArrayValue( 'wgDefaultUserOptions', [ 'math' => MathConfig::MODE_SOURCE ] );
		$dummyRendererFactory = $this->createMock( RendererFactory::class );
		$dummyRendererFactory->method( 'getRenderer' )
			->willReturnCallback( function ( $tex, $params, $mode ) {
				return $this->getDummyRenderer( $mode, $tex, $params );
			} );
		$this->setService( 'Math.RendererFactory', $dummyRendererFactory );
	}

	/**
	 * @covers ::onParserOptionsRegister
	 */
	public function testMathParserOption() {
		$user = $this->getTestUser()->getUserIdentity();
		$defaultMode = $this->getServiceContainer()->getUserOptionsLookup()->getOption( $user, 'math' );
		$this->assertSame( $defaultMode, ParserOptions::newFromUser( $user )->getOption( 'math' ) );
		// 3 corresponds to 'source', see Hooks::mathModeToString
		$this->getServiceContainer()->getUserOptionsManager()->setOption( $user, 'math', 3 );
		$this->assertSame( MathConfig::MODE_SOURCE, ParserOptions::newFromUser( $user )->getOption( 'math' ) );
	}

	public function testParserCacheIntegration() {
		$this->setupDummyRendering();

		$page = $this->getExistingTestPage( __METHOD__ );
		$this->assertTrue(
			$this->editPage( $page, '<math>TEST_FORMULA</math>' )->isGood(),
			'Sanity: edited page'
		);

		$parserOutputAccess = $this->getServiceContainer()->getParserOutputAccess();

		// source was set as a default, so the rendering will be shared with
		// canonical rendering produced by page edit
		$parserOptions1 = ParserOptions::newFromAnon();
		$parserOptions1->setOption( 'math', MathConfig::MODE_SOURCE );
		$render = $parserOutputAccess->getCachedParserOutput( $page, $parserOptions1 );
		$this->assertNotNull( $render );
		$this->assertStringContainsString( "<render>source:TEST_FORMULA</render>", $render->getText() );

		// Now render with 'png' and make sure we didn't get the cached output
		$parserOptions2 = ParserOptions::newFromAnon();
		$parserOptions2->setOption( 'math', MathConfig::MODE_PNG );
		$this->assertNull( $parserOutputAccess->getCachedParserOutput( $page, $parserOptions2 ) );
		$renderStatus = $parserOutputAccess->getParserOutput( $page, $parserOptions2 );
		$this->assertTrue( $renderStatus->isGood() );
		$this->assertStringContainsString(
			"<render>png:TEST_FORMULA</render>",
			$renderStatus->getValue()->getText()
		);

		// Fetch from cache with source
		$cachedWithDummy1 = $parserOutputAccess->getCachedParserOutput( $page, $parserOptions1 );
		$this->assertNotNull( $cachedWithDummy1 );
		$this->assertStringContainsString(
			"<render>source:TEST_FORMULA</render>",
			$cachedWithDummy1->getText()
		);

		// Fetch from cache with png
		$cachedWithDummy2 = $parserOutputAccess->getCachedParserOutput( $page, $parserOptions2 );
		$this->assertNotNull( $cachedWithDummy2 );
		$this->assertStringContainsString(
			"<render>png:TEST_FORMULA</render>",
			$cachedWithDummy2->getText()
		);
	}

	public function testMathInLink() {
		$this->setupDummyRendering();
		$po = ParserOptions::newFromAnon();
		$po->setOption( 'math', 'png' );
		$res = $this->getServiceContainer()
			->getParser()
			->parse(
				'[[test|<math>formula</math>]]',
				PageReferenceValue::localReference( NS_MAIN, __METHOD__ ),
				$po
			)
			->getText();
		$this->assertStringMatchesFormat( '%A<a%S><render>png:formula</render></a>%A', $res );
	}
}
