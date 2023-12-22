<?php

namespace MediaWiki\Extension\Math\Tests\TexVC;

use MediaWiki\Extension\Math\TexVC\MMLmappings\TexConstants\Tag;
use MediaWiki\Extension\Math\TexVC\MMLmappings\Util\MMLTestUtil;
use MediaWiki\Extension\Math\TexVC\TexVC;
use MediaWikiUnitTestCase;

/**
 * These are some specific testcases by MML-Rendering by TexVC.
 * They are explicitly described here instead of in JSON files because
 * Mathoid or LaTeXML do not generate suitable results for reference.
 * @covers \MediaWiki\Extension\Math\TexVC\TexVC
 */
class MMLRenderTest extends MediaWikiUnitTestCase {

	public function testAtop() {
		$input = "{ a \atop b }";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mfrac linethickness=\"0\">", $mathMLtexVC );
	}

	public function testChoose() {
		$input = "{ a \choose b }";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mfrac linethickness=\"0\">", $mathMLtexVC );
		$this->assertStringContainsString( ")", $mathMLtexVC );
		$this->assertStringContainsString( "(", $mathMLtexVC );
	}

	public function testOver() {
		$input = "{a \over b }";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mfrac>", $mathMLtexVC );
	}

	public function testBigcup() {
		$input = "\bigcup_{i=_1}^n E_i";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "munderover", $mathMLtexVC );
	}

	public function testBigcap() {
		$input = "\bigcap_{i=_1}^n E_i";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "munderover", $mathMLtexVC );
	}

	public function testScriptAlignment() {
		$input = "\log_{10} f";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringNotContainsString( "<mo></mo>", $mathMLtexVC );
		$this->assertStringContainsString( "<msub>", $mathMLtexVC );
	}

	public function testNotOperatorname() {
		$input = "\\not\operatorname{R}";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mpadded width=\"0\">", $mathMLtexVC );
		$this->assertStringContainsString( "<mtext>&#x29F8;</mtext>", $mathMLtexVC );
	}

	public function testUnder() {
		$input = "\\underbrace{ a+b+\\cdots+z }_{26}";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<munder>",  $mathMLtexVC );
		$this->assertStringNotContainsString( "<msub>",  $mathMLtexVC );
	}

	public function testSumDisplaystyle() {
		$input = "\sum_{k=1}^N k^2";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mstyle displaystyle=\"true\" scriptlevel=\"0\">", $mathMLtexVC );
	}

	public function testSumTextstyle() {
		$input = "\\textstyle \sum_{k=1}^N k^2";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mstyle displaystyle=\"false\" scriptlevel=\"0\">", $mathMLtexVC );
	}

	public function testPilcrow() {
		$input = "\P";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mo>&#xB6;</mo>\n</math>",  $mathMLtexVC );
	}

	public function testLesserThan() {
		$input = "<, \\nless,";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mo>&lt;</mo>", $mathMLtexVC );
	}

	public function testSidesetError() {
		$input = "\\sideset{_1^2}{_3^4}";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "merror", $mathMLtexVC );
	}

	public function testSidesetSum() {
		$input = "\\sideset{_1^2}{_3^4}\\sum";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringNotContainsString( "merror", $mathMLtexVC );
		$this->assertStringContainsString( "mmultiscripts", $mathMLtexVC );
		$this->assertStringContainsString( "<mprescripts/>", $mathMLtexVC );
		$this->assertStringContainsString( ">&#x2211;", $mathMLtexVC );
	}

	public function testSidesetProd() {
		$input = "\\sideset{_1^2}{_3^4}\\prod";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringNotContainsString( "merror", $mathMLtexVC );
		$this->assertStringContainsString( "mmultiscripts", $mathMLtexVC );
		$this->assertStringContainsString( "<mprescripts/>", $mathMLtexVC );
		$this->assertStringContainsString( ">&#x220F;", $mathMLtexVC );
	}

	public function testSidesetFQ() {
		$input = "\\sideset{_1^2}{_3^4}\prod_a^b";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringNotContainsString( "merror", $mathMLtexVC );
		$this->assertStringContainsString( "mmultiscripts", $mathMLtexVC );
		$this->assertStringContainsString( "<mprescripts/>", $mathMLtexVC );
		$this->assertStringContainsString( ">&#x220F;", $mathMLtexVC );
		$this->assertStringContainsString( "<mi>a</mi>", $mathMLtexVC );
		$this->assertStringContainsString( "<mi>b</mi>", $mathMLtexVC );
		$this->assertStringContainsString( "movablelimits", $mathMLtexVC );
	}

	public function testLimitsProd() {
		$input = "\\prod\\limits_{j=1}^k A_{\\alpha_j}";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "munderover", $mathMLtexVC );
		$this->assertStringContainsString( "&#x220F;", $mathMLtexVC, );
		$this->assertStringContainsString( "msub", $mathMLtexVC, );
	}

	public function testLimitsSum() {
		$input = "\\sum\\limits_{j=1}^k A_{\\alpha_j}";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "munderover", $mathMLtexVC );
		$this->assertStringContainsString( "&#x2211;", $mathMLtexVC, );
		$this->assertStringContainsString( "msub", $mathMLtexVC, );
	}

	public function testLimitsLim() {
		$input = "\\lim_{x \\to 2}";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "lim", $mathMLtexVC );
		$this->assertStringContainsString( "munder", $mathMLtexVC, );
	}

	public function testRenderSpaceSemicolon() {
		$input = "{\\;}";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "mstyle", $mathMLtexVC );
		$this->assertStringContainsString( "mspace", $mathMLtexVC, );
	}

	public function testSpacesAndCommas() {
		$input = "{a}{b , c}\\,";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mo>,", $mathMLtexVC );
		$this->assertStringContainsString( "mspace", $mathMLtexVC, );
	}

	public function testPrecedingSubscriptsFQ() {
		$input = "{}_1^2\\!\\Omega_3^4";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mrow " . Tag::CLASSTAG . "=\"ORD\"/>", $mathMLtexVC );
		$this->assertStringContainsString( "msubsup", $mathMLtexVC, );
	}

	public function testPrecedingSubscriptsDQ() {
		$input = "{}_pF_q";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mrow " . Tag::CLASSTAG . "=\"ORD\"/>", $mathMLtexVC );
		$this->assertStringContainsString( "msub", $mathMLtexVC, );
	}

	public function testPilcrowAndSectionSign() {
		$input = "\\P P \\S S";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mi>P", $mathMLtexVC );
		$this->assertStringContainsString( "<mi>S", $mathMLtexVC, );
		$this->assertStringContainsString( "&#xB6;", $mathMLtexVC, );
		$this->assertStringContainsString( "&#xA7;", $mathMLtexVC, );
	}

	public function testDerivatives1() {
		$input = "b_{f''}";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mo>&#x2033;", $mathMLtexVC );
		$this->assertStringContainsString( "msup", $mathMLtexVC, );
	}

	public function testDerivatives2() {
		$input = "f''''(x)";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mo>&#x2057;", $mathMLtexVC );
		$this->assertStringContainsString( "msup", $mathMLtexVC, );
	}

	public function testLimitsTextstyle() {
		$input = "\\textstyle \\lim_{n \\to \\infty}x_n";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "lim", $mathMLtexVC );
		$this->assertStringContainsString( "munder", $mathMLtexVC, );
		$this->assertStringContainsString( "movablelimits=\"true\"", $mathMLtexVC );
	}

	public function testColorGeneration1() {
		$input = "\\color{Dandelion}{Dandelion}";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "#FDBC42", $mathMLtexVC );
	}

	public function testColorGeneration2() {
		$input = "\\color{ForestGreen}{ForestGreen}";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "#009B55", $mathMLtexVC );
	}

	public function testColorGeneration3() {
		$input = "\\color{Rhodamine}{Rhodamine}";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "#EF559F", $mathMLtexVC );
	}

	public function testStyle1() {
		$input = "\\displaystyle \{U(\omega )\cdot \sigma _{H}(\omega )\} z";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mstyle displaystyle=\"true\" scriptlevel=\"0\">", $mathMLtexVC );
		$this->assertStringContainsString( "<mi>z</mi>", $mathMLtexVC );
		$this->assertStringContainsString( "</mstyle>", $mathMLtexVC, );
	}

	public function testStyle2() {
		$input = "\\displaystyle abc \\textstyle def";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mstyle displaystyle=\"false\" scriptlevel=\"0\">", $mathMLtexVC );
		$this->assertStringContainsString( "</mstyle>", $mathMLtexVC, );
	}

	public function testStyle3() {
		$input = "\\scriptstyle{abc} def \\textstyle ghi";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mstyle displaystyle=\"false\" scriptlevel=\"1\">", $mathMLtexVC );
		$this->assertStringContainsString( "</mstyle>", $mathMLtexVC, );
	}

	public function testStyle4() {
		$input = "\\textstyle b > \\textstyle \\delta";
		$mathMLtexVC = $this->generateMML( $input );
		$count = 0;
		str_replace( "<mstyle displaystyle", "", $mathMLtexVC, $count );
		$this->assertEquals( 2, $count );
	}

	public function testSpaceText() {
		$input = "\\text{if}~n\ \\text{is even} ";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mspace", $mathMLtexVC );
		$this->assertStringNotContainsString( "&#xA0;", $mathMLtexVC );
	}

	public function testSpaceOther() {
		// It is expected to render CR as whitespace
		$input = "\,e_{x}=\sum _{t=1}^{\infty }\ _{t}p_{x}";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mspace", $mathMLtexVC );
	}

	public function testGenfracDQ() {
		$input = "\\binom{m}{k - j}_{\!\!q}";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "<mi>q</mi>", $mathMLtexVC );
		$this->assertStringContainsString( "msub", $mathMLtexVC );
	}

	public function testDQZeroArgs() {
		$input = "nF^{_{}}/RT";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "msup", $mathMLtexVC );
	}

	public function testMatrixDQ() {
		$input = "\\begin{pmatrix} S \\\\Se\\\\Te\\end{pmatrix}_2";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "mtable", $mathMLtexVC );
		$this->assertStringContainsString( "msub", $mathMLtexVC );
		$this->assertStringContainsString( "<mn>2</mn>", $mathMLtexVC );
	}

	public function testAttributeDuplicate() {
		// This checks that there is some non-erronous output caused by double class attributes
		$input  = "\\mu_{\\operatorname{\\inf}}";
		$mathMLtexVC = $this->generateMML( $input );
		$this->assertStringContainsString( "&#x3BC;", $mathMLtexVC );
	}

	private function generateMML( $input, $chem = false ) {
		$texVC = new TexVC();
		$resultT = $texVC->check( $input, [
			'debug' => false,
			'usemathrm' => false,
			'oldtexvc' => false,
			'usemhchem' => $chem
		] );

		return MMLTestUtil::getMMLwrapped( $resultT["input"] ) ?? "<math> error texvc </math>";
	}
}
