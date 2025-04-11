<?php

namespace MediaWiki\Tests\OutputTransform\Stages;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\OutputTransform\OutputTransformStage;
use MediaWiki\OutputTransform\Stages\HardenNFC;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Tests\OutputTransform\OutputTransformStageTestBase;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\OutputTransform\Stages\HardenNFC
 */
class HardenNFCTest extends OutputTransformStageTestBase {

	public function createStage(): OutputTransformStage {
		return new HardenNFC(
			new ServiceOptions( [] ),
			new NullLogger()
		);
	}

	public function provideShouldRun(): array {
		return [
			[ new ParserOutput(), null, [] ]
		];
	}

	public function provideShouldNotRun(): array {
		$this->markTestSkipped( 'HydrateHeaderPlaceHolders should always run' );
	}

	public function provideTransform(): array {
		$text = "<h1>\u{0338}</h1>";
		$expectedText = "<h1>&#x338;</h1>";
		return [
			[ new ParserOutput( $text ), null, [], new ParserOutput( $expectedText ) ],
		];
	}
}
