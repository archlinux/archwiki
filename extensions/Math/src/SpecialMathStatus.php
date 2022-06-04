<?php

namespace MediaWiki\Extension\Math;

use ExtensionRegistry;
use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\Logger\LoggerFactory;
use MWException;
use PermissionsError;
use Psr\Log\LoggerInterface;
use SpecialPage;

/**
 * MediaWiki math extension
 *
 * @copyright 2002-2015 Tomasz Wegrzanowski, Brion Vibber, Moritz Schubotz,
 * and other MediaWiki contributors
 * @license GPL-2.0-or-later
 * @author Moritz Schubotz
 */
class SpecialMathStatus extends SpecialPage {
	/** @var LoggerInterface */
	private $logger;

	/** @var MathConfig */
	private $mathConfig;

	/** @var RendererFactory */
	private $rendererFactory;

	public function __construct(
		MathConfig $mathConfig,
		RendererFactory $rendererFactory
	) {
		parent::__construct( 'MathStatus', 'purge' );

		$this->mathConfig = $mathConfig;
		$this->rendererFactory = $rendererFactory;
		$this->logger = LoggerFactory::getInstance( 'Math' );
	}

	/**
	 * @param null|string $query
	 *
	 * @throws MWException
	 * @throws PermissionsError
	 */
	public function execute( $query ) {
		$this->setHeaders();

		$out = $this->getOutput();
		$enabledMathModes = $this->mathConfig->getValidRenderingModeNames();
		$out->addWikiMsg( 'math-status-introduction', count( $enabledMathModes ) );

		foreach ( $enabledMathModes as $modeNr => $modeName ) {
			$out->wrapWikiMsg( '=== $1 ===', $modeName );
			switch ( $modeNr ) {
				case MathConfig::MODE_MATHML:
					$this->runMathMLTest( $modeName );
					break;
				case MathConfig::MODE_LATEXML:
					$this->runMathLaTeXMLTest( $modeName );
			}
		}
	}

	private function runMathMLTest( $modeName ) {
		$this->getOutput()->addWikiMsgArray( 'math-test-start', [ $modeName ] );
		$this->testSpecialCaseText();
		$this->testMathMLIntegration();
		$this->testPmmlInput();
		$this->getOutput()->addWikiMsgArray( 'math-test-end', [ $modeName ] );
	}

	private function runMathLaTeXMLTest( $modeName ) {
		$this->getOutput()->addWikiMsgArray( 'math-test-start', [ $modeName ] );
		$this->testLaTeXMLIntegration();
		$this->testLaTeXMLLinebreak();
		$this->getOutput()->addWikiMsgArray( 'math-test-end', [ $modeName ] );
	}

	public function testSpecialCaseText() {
		$renderer = $this->rendererFactory->getRenderer( 'x^2+\text{a sample Text}', [], MathConfig::MODE_MATHML );
		$expected = 'a sample Text</mtext>';
		$this->assertTrue( $renderer->render(), 'Rendering the input "x^2+\text{a sample Text}"' );
		$this->assertContains(
			$expected, $renderer->getHtmlOutput(), 'Comparing to the reference rendering'
		);
	}

	/**
	 * Checks the basic functionality
	 * i.e. if the span element is generated right.
	 */
	public function testMathMLIntegration() {
		$svgRef = file_get_contents( __DIR__ . '/../images/reference.svg' );
		$svgRefNoSpeech = file_get_contents( __DIR__ . '/../images/reference-nospeech.svg' );
		$renderer = $this->rendererFactory->getRenderer( "a+b", [], MathConfig::MODE_MATHML );
		$this->assertTrue( $renderer->render(), "Rendering of a+b in plain MathML mode" );
		$real = str_replace( "\n", '', $renderer->getHtmlOutput() );
		$expected = '<mo>+</mo>';
		$this->assertContains( $expected, $real, "Checking the presence of '+' in the MathML output" );
		$this->assertEquals( [ $svgRef, $svgRefNoSpeech ], $renderer->getSvg(),
			"Comparing the generated SVG with the reference"
		);
	}

	/**
	 * Checks the experimental option to 'render' MathML input
	 */
	public function testPmmlInput() {
		// sample from 'Navajo Coal Combustion and Respiratory Health Near Shiprock,
		// New Mexico' in ''Journal of Environmental and Public Health'' , vol. 2010p.
		// authors  Joseph E. Bunnell;  Linda V. Garcia;  Jill M. Furst;
		// Harry Lerch;  Ricardo A. Olea;  Stephen E. Suitt;  Allan Kolker
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$inputSample = '<msub>  <mrow>  <mi> P</mi> </mrow>  <mrow>  <mi> i</mi>  <mi> j</mi> </mrow> </msub>  <mo> =</mo>  <mfrac>  <mrow>  <mn> 100</mn>  <msub>  <mrow>  <mi> d</mi> </mrow>  <mrow>  <mi> i</mi>  <mi> j</mi> </mrow> </msub> </mrow>  <mrow>  <mn> 6.75</mn>  <msub>  <mrow>  <mi> r</mi> </mrow>  <mrow>  <mi> j</mi> </mrow> </msub> </mrow> </mfrac>  <mo> ,</mo> </math>';
		$attribs = [ 'type' => 'pmml' ];
		$renderer = new MathMathML( $inputSample, $attribs );
		$this->assertEquals( 'pmml', $renderer->getInputType(), 'Checking if MathML input is supported' );
		$this->assertTrue( $renderer->render(), 'Rendering Presentation MathML sample' );
		$real = MathRenderer::renderMath( $inputSample, $attribs, MathConfig::MODE_MATHML );
		$expected = 'hash=5628b8248b79267ecac656102334d5e3&amp;mode=mathml';
		$this->assertContains( $expected, $real, 'Checking if the link to SVG image is correct' );
	}

	/**
	 * Checks the basic functionality
	 * i.e. if the span element is generated right.
	 */
	public function testLaTeXMLIntegration() {
		$renderer = $this->rendererFactory->getRenderer( "a+b", [], MathConfig::MODE_LATEXML );
		$this->assertTrue( $renderer->render(), "Rendering of a+b in LaTeXML mode" );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$expected = '<math xmlns="http://www.w3.org/1998/Math/MathML" id="p1.m1" class="ltx_Math" alttext="{\displaystyle a+b}" ><semantics><mrow id="p1.m1.4" xref="p1.m1.4.cmml"><mi id="p1.m1.1" xref="p1.m1.1.cmml">a</mi><mo id="p1.m1.2" xref="p1.m1.2.cmml">+</mo><mi id="p1.m1.3" xref="p1.m1.3.cmml">b</mi></mrow><annotation-xml encoding="MathML-Content"><apply id="p1.m1.4.cmml" xref="p1.m1.4"><plus id="p1.m1.2.cmml" xref="p1.m1.2"/><ci id="p1.m1.1.cmml" xref="p1.m1.1">a</ci><ci id="p1.m1.3.cmml" xref="p1.m1.3">b</ci></apply></annotation-xml><annotation encoding="application/x-tex">{\displaystyle a+b}</annotation></semantics></math>';
		$real = preg_replace( "/\n\\s*/", '', $renderer->getHtmlOutput() );
		$this->assertContains( $expected, $real,
			"Comparing the output to the MathML reference rendering" .
			  $renderer->getLastError() );
	}

	/**
	 * Checks LaTeXML line break functionality
	 * i.e. if a long line contains a mtr element.
	 * http://www.w3.org/TR/REC-MathML/chap3_5.html#sec3.5.2
	 */
	public function testLaTeXMLLinebreak() {
		global $wgMathDefaultLaTeXMLSetting;
		$tex = '';
		$testMax = ceil( $wgMathDefaultLaTeXMLSetting[ 'linelength' ] / 2 );
		for ( $i = 0; $i < $testMax; $i++ ) {
			$tex .= "$i+";
		}
		$tex .= $testMax;
		$renderer = new MathLaTeXML( $tex, [ 'display' => 'linebreak' ] );
		$this->assertTrue( $renderer->render( true ), "Rendering of linebreak test in LaTeXML mode" );
		$expected = 'mtr';
		$real = preg_replace( "/\n\\s*/", '', $renderer->getHtmlOutput() );
		$this->assertContains( $expected, $real, "Checking for linebreak" .
			  $renderer->getLastError() );
	}

	private function assertTrue( $expression, $message = '' ) {
		if ( $expression ) {
			$this->getOutput()->addWikiMsgArray( 'math-test-success', $message );
			return true;
		} else {
			$this->getOutput()->addWikiMsgArray( 'math-test-fail', $message );
			return false;
		}
	}

	private function assertContains( $expected, $real, $message = '' ) {
		if ( !$this->assertTrue( strpos( $real, $expected ) !== false, $message ) ) {
			$this->printDiff( $expected, $real, 'math-test-contains-diff' );
		}
	}

	private function assertEquals( $expected, $real, $message = '' ) {
		if ( is_array( $expected ) ) {
			foreach ( $expected as $alternative ) {
				if ( $alternative === $real ) {
					$this->getOutput()->addWikiMsgArray( 'math-test-success', $message );
					return true;
				}
			}
			// non of the alternatives matched
			$this->getOutput()->addWikiMsgArray( 'math-test-fail', $message );
			return false;
		}
		if ( !$this->assertTrue( $expected === $real, $message ) ) {
			$this->printDiff( $expected, $real, 'math-test-equals-diff' );
			return false;
		}
		return true;
	}

	private function printDiff( $expected, $real, $message = '' ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( "SyntaxHighlight" ) ) {
			$expected = "<syntaxhighlight lang=\"xml\">$expected</syntaxhighlight>";
			$real = "<syntaxhighlight lang=\"xml\">$real</syntaxhighlight>";
			$this->getOutput()->addWikiMsgArray( $message, [ $real, $expected ] );
		} else {
			$this->logger->warning( 'Can not display expected and real value.' .
				'SyntaxHighlight is not installed.' );
		}
	}

	protected function getGroupName() {
		return 'other';
	}
}
