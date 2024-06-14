<?php

namespace MediaWiki\Tests\OutputTransform\Stages;

use MediaWiki\OutputTransform\OutputTransformStage;
use MediaWiki\OutputTransform\Stages\AddRedirectHeader;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Tests\OutputTransform\OutputTransformStageTestBase;

/**
 * @covers \MediaWiki\OutputTransform\Stages\AddRedirectHeader
 * @group Database
 *        ^ Title shenanigans seem to require this
 */
class AddRedirectHeaderTest extends OutputTransformStageTestBase {

	public function createStage(): OutputTransformStage {
		return new AddRedirectHeader();
	}

	public function provideShouldRun(): iterable {
		$po = new ParserOutput();
		$po->setRedirectHeader( 'xyz' );
		yield [ $po, null, [] ];
	}

	public function provideShouldNotRun(): array {
		return [ [ new ParserOutput(), null, [] ] ];
	}

	public function provideTransform(): array {
		$text = "<h1>header</h1>\n<p>hello world</p>";
		$redirect = '<div class="redirectMsg">REDIRECT</div>';
		$expectedText = <<<EOF
<div class="redirectMsg">REDIRECT</div><h1>header</h1>\n<p>hello world</p>
EOF;

		$po = new ParserOutput( $text );
		$po->setRedirectHeader( $redirect );
		$expected = new ParserOutput( $expectedText );
		$expected->setRedirectHeader( $redirect );
		return [ [ $po, null, [], $expected ] ];
	}
}
