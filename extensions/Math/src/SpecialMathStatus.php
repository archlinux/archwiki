<?php

namespace MediaWiki\Extension\Math;

use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\Extension\Math\Widget\MathTestInputForm;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use Psr\Log\LoggerInterface;
use UserNotLoggedIn;

/**
 * MediaWiki math extension
 *
 * @copyright 2002-2015 Tomasz Wegrzanowski, Brion Vibber, Moritz Schubotz,
 * and other MediaWiki contributors
 * @license GPL-2.0-or-later
 * @author Moritz Schubotz
 */
class SpecialMathStatus extends UnlistedSpecialPage {
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
		parent::__construct( 'MathStatus' );

		$this->mathConfig = $mathConfig;
		$this->rendererFactory = $rendererFactory;
		$this->logger = LoggerFactory::getInstance( 'Math' );
	}

	/**
	 * @param null|string $query
	 */
	public function execute( $query ) {
		$this->setHeaders();

		if ( !( $this->getUser()->isNamed() ) ) {
			// This page is primarily of interest to developers.
			// This action is comparable to previewing or parsing a small snippet of wikitext.
			// If using RESTBase instead of native MML, this page makes HTTP requests to it.
			// Optimization: Avoid uncached math parsing for logged-out users.
			throw new UserNotLoggedIn();
		}

		$out = $this->getOutput();
		$enabledMathModes = $this->mathConfig->getValidRenderingModeNames();
		$req = $this->getRequest();
		$tex = $req->getText( 'wptex' );

		if ( $tex === '' ) {
			$out->addWikiMsg( 'math-status-introduction', count( $enabledMathModes ) );

			foreach ( $enabledMathModes as $modeNr => $modeName ) {
				$out->wrapWikiMsg( '=== $1 ===', $modeName );
				switch ( $modeNr ) {
					case MathConfig::MODE_MATHML:
						$this->runMathMLTest( $modeName );
						break;
					case MathConfig::MODE_LATEXML:
						$this->runMathLaTeXMLTest( $modeName );
						break;
					case MathConfig::MODE_NATIVE_MML:
						$this->runNativeTest( $modeName );
				}
			}
		}

		$form = new MathTestInputForm( $this, $enabledMathModes, $this->rendererFactory );
		$form->show();
	}

	private function runNativeTest( string $modeName ) {
		$this->getOutput()->addWikiMsgArray( 'math-test-start', [ $modeName ] );
		$renderer = $this->rendererFactory->getRenderer( "a+b", [], MathConfig::MODE_NATIVE_MML );
		if ( !$this->assertTrue( $renderer->render(), "Rendering of a+b in $modeName" ) ) {
			return;
		}
		$real = str_replace( "\n", '', $renderer->getHtmlOutput() );
		$expected = '<mo stretchy="false">+</mo>';
		$this->assertContains( $expected, $real, "Checking the presence of '+' in the MathML output" );
		$this->getOutput()->addWikiMsgArray( 'math-test-end', [ $modeName ] );
	}

	private function runMathMLTest( string $modeName ) {
		$this->getOutput()->addWikiMsgArray( 'math-test-start', [ $modeName ] );
		$this->testSpecialCaseText();
		$this->testMathMLIntegration();
		$this->testPmmlInput();
		$this->getOutput()->addWikiMsgArray( 'math-test-end', [ $modeName ] );
	}

	private function runMathLaTeXMLTest( string $modeName ) {
		$this->getOutput()->addWikiMsgArray( 'math-test-start', [ $modeName ] );
		$this->testLaTeXMLIntegration();
		$this->testLaTeXMLLinebreak();
		$this->getOutput()->addWikiMsgArray( 'math-test-end', [ $modeName ] );
	}

	public function testSpecialCaseText() {
		$renderer = $this->rendererFactory->getRenderer( 'x^2+\text{a sample Text}', [], MathConfig::MODE_MATHML );
		$expected = 'a sample Text</mtext>';
		if ( !$this->assertTrue( $renderer->render(), 'Rendering the input "x^2+\text{a sample Text}"' ) ) {
			return;
		}
		$this->assertContains(
			$expected, $renderer->getHtmlOutput(), 'Comparing to the reference rendering'
		);
	}

	/**
	 * Checks the basic functionality
	 * i.e. if the span element is generated right.
	 */
	public function testMathMLIntegration() {
		$renderer = $this->rendererFactory->getRenderer( "a+b", [], MathConfig::MODE_MATHML );
		if ( !$this->assertTrue( $renderer->render(), "Rendering of a+b in plain MathML mode" ) ) {
			return;
		}
		$real = str_replace( "\n", '', $renderer->getHtmlOutput() );
		$expected = '<mo>+</mo>';
		$this->assertContains( $expected, $real, "Checking the presence of '+' in the MathML output" );
		$this->assertContains(
			'<svg xmlns:xlink="http://www.w3.org/1999/xlink" ',
			$renderer->getSvg(),
			"Check that the generated SVG image contains the xlink namespace"
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
		$renderer = $this->rendererFactory->getRenderer( $inputSample, $attribs, MathConfig::MODE_MATHML );
		$this->assertEquals( 'pmml', $renderer->getInputType(), 'Checking if MathML input is supported' );
		if ( !$this->assertTrue( $renderer->render(), 'Rendering Presentation MathML sample' ) ) {
			return;
		}
		$real = $renderer->getHtmlOutput();
		$this->assertContains( 'mode=mathml', $real, 'Checking if the link to SVG image is in correct mode' );
	}

	/**
	 * Checks the basic functionality
	 * i.e. if the span element is generated right.
	 */
	public function testLaTeXMLIntegration() {
		$renderer = $this->rendererFactory->getRenderer( "a+b", [], MathConfig::MODE_LATEXML );
		if ( !$this->assertTrue( $renderer->render(), "Rendering of a+b in LaTeXML mode" ) ) {
			return;
		}
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$expected = '<math xmlns="http://www.w3.org/1998/Math/MathML" ';
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
		$mathDefaultLaTeXMLSetting = $this->getConfig()->get( 'MathDefaultLaTeXMLSetting' );
		$tex = '';
		$testMax = ceil( $mathDefaultLaTeXMLSetting[ 'linelength' ] / 2 );
		for ( $i = 0; $i < $testMax; $i++ ) {
			$tex .= "$i+";
		}
		$tex .= $testMax;
		$renderer = new MathLaTeXML( $tex, [ 'display' => 'linebreak' ] );
		$renderer->setPurge();
		if ( !$this->assertTrue( $renderer->render(), "Rendering of linebreak test in LaTeXML mode" ) ) {
			return;
		}
		$expected = 'mtr';
		$real = preg_replace( "/\n\\s*/", '', $renderer->getHtmlOutput() );
		$this->assertContains( $expected, $real, "Checking for linebreak" .
			  $renderer->getLastError() );
	}

	private function assertTrue( bool $expression, string $message = '' ): bool {
		if ( $expression ) {
			$this->getOutput()->addWikiMsgArray( 'math-test-success', [ $message ] );
			return true;
		} else {
			$this->getOutput()->addWikiMsgArray( 'math-test-fail', [ $message ] );
			return false;
		}
	}

	private function assertContains( string $expected, string $real, string $message = '' ) {
		if ( !$this->assertTrue( strpos( $real, $expected ) !== false, $message ) ) {
			$this->printDiff( $expected, $real, 'math-test-contains-diff' );
		}
	}

	/**
	 * @param array|string $expected
	 * @param array|string $real
	 * @param string $message
	 */
	private function assertEquals( $expected, $real, string $message = '' ): bool {
		if ( is_array( $expected ) ) {
			foreach ( $expected as $alternative ) {
				if ( $alternative === $real ) {
					$this->getOutput()->addWikiMsgArray( 'math-test-success', [ $message ] );
					return true;
				}
			}
			// non of the alternatives matched
			$this->getOutput()->addWikiMsgArray( 'math-test-fail', [ $message ] );
			return false;
		}
		if ( !$this->assertTrue( $expected === $real, $message ) ) {
			$this->printDiff( $expected, $real, 'math-test-equals-diff' );
			return false;
		}
		return true;
	}

	private function printDiff( string $expected, string $real, string $message = '' ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( "SyntaxHighlight" ) ) {
			$expected = "<syntaxhighlight lang=\"xml\">$expected</syntaxhighlight>";
			$real = "<syntaxhighlight lang=\"xml\">$real</syntaxhighlight>";
			$this->getOutput()->addWikiMsgArray( $message, [ $real, $expected ] );
		} else {
			$this->logger->warning( 'Can not display expected and real value.' .
				'SyntaxHighlight is not installed.' );
		}
	}

	protected function getGroupName(): string {
		return 'other';
	}
}
