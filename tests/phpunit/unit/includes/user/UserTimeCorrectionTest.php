<?php

namespace MediaWiki\Tests\User;

use DateTime;
use MediaWiki\User\UserTimeCorrection;
use MediaWikiUnitTestCase;

class UserTimeCorrectionTest extends MediaWikiUnitTestCase {

	/**
	 * @covers \MediaWiki\User\UserTimeCorrection::__construct
	 * @covers \MediaWiki\User\UserTimeCorrection::__toString
	 * @covers \MediaWiki\User\UserTimeCorrection::isValid
	 * @dataProvider provideTimeCorrectionExamples
	 *
	 * @param string $input
	 * @param string $expected
	 * @param bool $isValid
	 */
	public function testParser( $input, $expected, $isValid ) {
		$value = new UserTimeCorrection( $input );
		self::assertEquals( $expected, (string)$value );
		self::assertEquals( $isValid, $value->isValid() );
	}

	public function provideTimeCorrectionExamples() {
		return [
			[ '', 'System|0', false ],
			[ 'bogus', 'System|0', false ],
			[ 'ZoneInfo', 'Offset|0', false ],
			[ 'ZoneInfo|bogus', 'Offset|0', false ],
			[ 'ZoneInfo|120|Africa/Johannesburg|bogus', 'Offset|120', false ],
			[ 'ZoneInfo|120|Unknown/Unknown', 'Offset|120', false ],
			// Africa/Johannesburg has not DST
			[ 'ZoneInfo|0|Africa/Johannesburg', 'ZoneInfo|120|Africa/Johannesburg', true ],
			[ 'ZoneInfo|120|Africa/Johannesburg', 'ZoneInfo|120|Africa/Johannesburg', true ],
			// Deprecated timezone name
			[ 'ZoneInfo|330|Asia/Calcutta', 'ZoneInfo|330|Asia/Calcutta', true ],
			// Timezone identifier with space in name
			[ 'ZoneInfo|-420|America/Dawson_Creek', 'ZoneInfo|-420|America/Dawson_Creek', true ],
			[ 'System', 'System|0', true ],
			[ 'System|0', 'System|0', true ],
			[ 'System|120', 'System|0', true ],
			[ '2:30', 'Offset|150', true ],
			[ '02:30', 'Offset|150', true ],
			[ '+02:30', 'Offset|150', true ],
			[ '0230', 'Offset|840', false ],
			[ '2', 'Offset|120', true ],
			[ '14:00', 'Offset|840', true ],
			[ '-12:00', 'Offset|-720', true ],
			[ '15:00', 'Offset|840', false ],
			[ '-13:00', 'Offset|-720', false ],
			[ '2:30:40', 'Offset|150', true ],
			[ '2:30bogus', 'Offset|150', true ],
			[ '2.50', 'System|0', false ],
			[ 'UTC-8', 'System|0', false ],
		];
	}

	/**
	 * @covers       \MediaWiki\User\UserTimeCorrection::__construct
	 * @covers       \MediaWiki\User\UserTimeCorrection::__toString
	 * @covers       \MediaWiki\User\UserTimeCorrection::isValid
	 * @dataProvider provideServerTZoffsetExamples
	 *
	 * @param int $serverOffset
	 * @param string $input
	 * @param string $expected
	 * @param bool $isValid
	 */
	public function testServerOffset( int $serverOffset, string $input, string $expected, bool $isValid ) {
		$value = new UserTimeCorrection( $input, null, $serverOffset );
		self::assertEquals( $expected, (string)$value );
		self::assertEquals( $isValid, $value->isValid() );
	}

	public function provideServerTZoffsetExamples() {
		return [
			[ 120, '', 'System|120', false ],
			[ 120, 'bogus', 'System|120', false ],
			[ 120, 'System', 'System|120', true ],
			[ 120, 'System|120', 'System|120', true ],
			[ -120, 'System|-120', 'System|-120', true ],
			[ 840, 'System|840', 'System|840', true ],
		];
	}

	/**
	 * @covers \MediaWiki\User\UserTimeCorrection::__construct
	 * @covers \MediaWiki\User\UserTimeCorrection::__toString
	 * @covers \MediaWiki\User\UserTimeCorrection::isValid
	 * @dataProvider provideDSTVariations
	 *
	 * @param DateTime $date Date/time to which the correction would apply
	 * @param string $input
	 * @param string $expected
	 * @param bool $isValid
	 */
	public function testDSTVariantions( DateTime $date, $input, $expected, $isValid ) {
		$value = new UserTimeCorrection( $input, $date );
		self::assertEquals( $expected, (string)$value );
		self::assertEquals( $isValid, $value->isValid() );
	}

	public function provideDSTVariations() {
		// Amsterdam observes DST. Johannesburg does not
		return [
			[ new DateTime( '2020-12-01' ), 'ZoneInfo|60|Europe/Amsterdam', 'ZoneInfo|60|Europe/Amsterdam', true ],
			[ new DateTime( '2020-12-01' ), 'ZoneInfo|120|Europe/Amsterdam', 'ZoneInfo|60|Europe/Amsterdam', true ],
			[ new DateTime( '2020-12-01' ), 'ZoneInfo|120|Africa/Johannesburg', 'ZoneInfo|120|Africa/Johannesburg', true ],
			[ new DateTime( '2020-06-01' ), 'ZoneInfo|60|Europe/Amsterdam', 'ZoneInfo|120|Europe/Amsterdam', true ],
			[ new DateTime( '2020-06-01' ), 'ZoneInfo|120|Europe/Amsterdam', 'ZoneInfo|120|Europe/Amsterdam', true ],
			[ new DateTime( '2020-06-01' ), 'ZoneInfo|120|Africa/Johannesburg', 'ZoneInfo|120|Africa/Johannesburg', true ],
			// phpcs:enable
		];
	}

	/**
	 * @covers \MediaWiki\User\UserTimeCorrection::getTimeOffset
	 * @covers \MediaWiki\User\UserTimeCorrection::getTimeOffsetInterval
	 * @covers \MediaWiki\User\UserTimeCorrection::getTimeZone
	 */
	public function testAccessors(): void {
		$value = new UserTimeCorrection( 'ZoneInfo|120|Africa/Johannesburg' );
		self::assertEquals( 120, $value->getTimeOffset() );
		self::assertEquals( 120, (int)$value->getTimeOffsetInterval()->format( '%i' ) );
		self::assertEquals( 'Africa/Johannesburg', $value->getTimeZone()->getName() );
	}
}
