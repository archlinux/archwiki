<?php

namespace MediaWiki\CheckUser\Tests\Unit\SuggestedInvestigations;

use LogicException;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult
 */
class SuggestedInvestigationsSignalMatchResultTest extends MediaWikiUnitTestCase {
	public function testNewNegativeResult(): void {
		$sut = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'test-abc' );
		$this->assertFalse( $sut->isMatch() );
		$this->assertSame( 'test-abc', $sut->getName() );
	}

	public function testGetValueThrowsForNegativeResult(): void {
		$sut = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'test-abc' );
		$this->expectException( LogicException::class );
		$sut->getValue();
	}

	public function testValueMatchAllowsMergingThrowsForNegativeResult(): void {
		$sut = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'test-abc' );
		$this->expectException( LogicException::class );
		$sut->valueMatchAllowsMerging();
	}

	/** @dataProvider provideNewPositiveResult */
	public function testNewPositiveResult( $valueMatchAllowsMerging ): void {
		$sut = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'name-abc', 'value-abc', $valueMatchAllowsMerging
		);
		$this->assertTrue( $sut->isMatch() );
		$this->assertSame( 'name-abc', $sut->getName() );
		$this->assertSame( 'value-abc', $sut->getValue() );
		$this->assertSame( $valueMatchAllowsMerging, $sut->valueMatchAllowsMerging() );
	}

	public static function provideNewPositiveResult(): array {
		return [
			'Value match allows merging' => [ true ],
			'Value match does not allow merging' => [ false ],
		];
	}
}
