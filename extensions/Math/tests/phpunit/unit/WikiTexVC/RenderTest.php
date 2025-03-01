<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC;

use MediaWiki\Extension\Math\WikiTexVC\Parser;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\Parser
 */
class RenderTest extends MediaWikiUnitTestCase {

	public function provideTestCases() {
		return [
			[
				'in' => '',
			],
			[
				'in' => 'a',
			],
			[
				'in' => 'a^2',
				'out' => 'a^{2}'
			],
			[
				'in' => 'a^2+b^{2}',
				'out' => 'a^{2}+b^{2}'
			],
			[
				'in' => 'a^{2}+b^{2}',
			],
			[
				'in' => 'l_a^2+l_b^2=l_c^2',
				'out' => 'l_{a}^{2}+l_{b}^{2}=l_{c}^{2}'
			],
			[
				'in' => '\\sin(x)+{}{}\\cos(x)^2 newcommand',
				'out' => '\\sin(x)+{}{}\\cos(x)^{2}newcommand'
			]
		];
	}

	/**
	 * @dataProvider provideTestCases
	 */
	public function testRendering( string $in, ?string $out = null ) {
		$parser = new Parser();

		$out ??= $in;
		$result = $parser->parse( $in );
		$rendered = $result->render();
		$this->assertSame( $out, $rendered, 'Error rendering input: '
			. $in . ' rendered output is: ' . $rendered . ' should be: ' . $out );
	}
}
