<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations;

use LogicException;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult
 */
class SuggestedInvestigationsSignalMatchResultTest extends MediaWikiUnitTestCase {
	public function testNewNegativeResult(): void {
		$sut = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'test-abc' );
		$this->assertFalse( $sut->isMatch() );
		$this->assertSame( 'test-abc', $sut->getName() );
	}

	/** @dataProvider provideGetMethodsThrowingOnNegativeResult */
	public function testGetMethodsThrowingOnNegativeResult( string $methodName ): void {
		$sut = SuggestedInvestigationsSignalMatchResult::newNegativeResult( 'test-abc' );
		$this->expectException( LogicException::class );
		$sut->$methodName();
	}

	public static function provideGetMethodsThrowingOnNegativeResult(): array {
		return [
			'::getValue' => [ 'getValue' ],
			'::valueMatchAllowsMerging' => [ 'valueMatchAllowsMerging' ],
			'::getEquivalentNamesForMerging' => [ 'getEquivalentNamesForMerging' ],
			'::getTriggerId' => [ 'getTriggerId' ],
			'::getTriggerIdTable' => [ 'getTriggerIdTable' ],
			'::getUserInfoBitFlags' => [ 'getUserInfoBitFlags' ],
		];
	}

	/** @dataProvider provideNewPositiveResult */
	public function testNewPositiveResult(
		bool $valueMatchAllowsMerging,
		array $equivalentNamesForMerging,
		?int $triggerId,
		?string $triggerIdTable,
		int $userInfoBitFlags
	): void {
		// null for $triggerId means to use the default value
		if ( $triggerId === null || $triggerIdTable === null ) {
			$sut = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
				name: 'name-abc',
				value: 'value-abc',
				allowsMerging: $valueMatchAllowsMerging,
				equivalentNamesForMerging: $equivalentNamesForMerging,
				userInfoBitFlags: $userInfoBitFlags
			);
		} else {
			$sut = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
				name: 'name-abc',
				value: 'value-abc',
				allowsMerging: $valueMatchAllowsMerging,
				triggerId: $triggerId,
				triggerIdTable: $triggerIdTable,
				equivalentNamesForMerging: $equivalentNamesForMerging,
				userInfoBitFlags: $userInfoBitFlags
			);
		}

		$this->assertTrue( $sut->isMatch() );
		$this->assertSame( 'name-abc', $sut->getName() );
		$this->assertSame( 'value-abc', $sut->getValue() );
		$this->assertSame( $valueMatchAllowsMerging, $sut->valueMatchAllowsMerging() );
		$this->assertSame( $userInfoBitFlags, $sut->getUserInfoBitFlags() );
		$this->assertArrayEquals( $equivalentNamesForMerging, $sut->getEquivalentNamesForMerging(), false, true );
		if ( $triggerId === null || $triggerIdTable === null ) {
			$this->assertSame( 0, $sut->getTriggerId() );
			$this->assertSame( '', $sut->getTriggerIdTable() );
		} else {
			$this->assertSame( $triggerId, $sut->getTriggerId() );
			$this->assertSame( $triggerIdTable, $sut->getTriggerIdTable() );
		}
	}

	public static function provideNewPositiveResult(): array {
		return [
			'Value match allows merging' => [
				'valueMatchAllowsMerging' => true,
				'equivalentNamesForMerging' => [],
				'triggerId' => null,
				'triggerIdTable' => null,
				'userInfoBitFlags' => 0,
			],
			'Value match allows merging with equivalent signal names specified' => [
				'valueMatchAllowsMerging' => true,
				'equivalentNamesForMerging' => [ 'name-abc-2' ],
				'triggerId' => null,
				'triggerIdTable' => null,
				'userInfoBitFlags' => 1,
			],
			'Value match does not allow merging' => [
				'valueMatchAllowsMerging' => false,
				'equivalentNamesForMerging' => [],
				'triggerId' => null,
				'triggerIdTable' => null,
				'userInfoBitFlags' => 123,
			],
			'Trigger ID is set' => [
				'valueMatchAllowsMerging' => false,
				'equivalentNamesForMerging' => [],
				'triggerId' => 1,
				'triggerIdTable' => 'revision',
				'userInfoBitFlags' => 1234657,
			],
		];
	}
}
