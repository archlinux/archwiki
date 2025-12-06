<?php
namespace MediaWiki\Extension\Math\Tests\ParsoidHandlers;

use MediaWiki\Extension\Math\Hooks\MathFormulaPostRenderRevisionHook;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\Tests\HookIntegrationSetupTrait;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Math\HookHandlers\ParserHooksHandler
 * @coversDefaultClass \MediaWiki\Extension\Math\HookHandlers\ParserHooksHandler
 */
class MathLegacyIntegrationTest
	extends MediaWikiIntegrationTestCase
	implements MathFormulaPostRenderRevisionHook
{

	use HookIntegrationSetupTrait;

	public function testParsoidRendering() {
		$this->setupTestEnviroment();

		$wt = '<math>TEST_FORMULA_1</math> <math>TEST_FORMULA_2</math>';

		$title = Title::newFromText( 'TestPage' );
		$parserOptions = ParserOptions::newCanonical( 'canonical' );
		$parserOptions->setOption( 'math', MathConfig::MODE_SOURCE );

		$parser = $this->getServiceContainer()->getParserFactory()->getInstance();
		$output = $parser->parse( $wt, $title, $parserOptions );
		$html = $output->getRawText();
		$this->assertStringContainsString( 'source:TEST_FORMULA_1:modified', $html );
		$this->assertStringContainsString( 'source:TEST_FORMULA_2:modified', $html );
		$this->assertMathHookFiredAll( [
			'<render>source:TEST_FORMULA_1</render>',
			'<render>source:TEST_FORMULA_2</render>'
		] );
	}
}
