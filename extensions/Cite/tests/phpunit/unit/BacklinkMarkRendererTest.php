<?php

namespace Cite\Tests\Unit;

use Cite\AlphabetsProvider;
use Cite\BacklinkMarkRenderer;
use Cite\ReferenceMessageLocalizer;
use MediaWiki\Config\HashConfig;
use MediaWiki\Message\Message;

/**
 * @covers \Cite\BacklinkMarkRenderer
 * @license GPL-2.0-or-later
 */
class BacklinkMarkRendererTest extends \MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideGetBacklinkMarker
	 */
	public function testGetBacklinkMarker(
		string $expectedLabel, int $reuseIndex, ?string $customAlphabet
	) {
		$mockMessageLocalizer = $this->createNoOpMock( ReferenceMessageLocalizer::class, [ 'msg' ] );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) {
				$msg = $this->createMock( Message::class );
				$msg->method( 'isDisabled' )->willReturn( true );
				return $msg;
			}
		);

		$config = new HashConfig( [
			'CiteDefaultBacklinkAlphabet' => $customAlphabet,
			'CiteUseLegacyBacklinkLabels' => false,
		] );

		// FIXME: also test CLDR alphabet integration
		$mockAlphabetsProvider = $this->createMock( AlphabetsProvider::class );
		$mockAlphabetsProvider->method( 'getIndexCharacters' )->willReturn( [ 'z', 'y', 'x' ] );

		$renderer = new BacklinkMarkRenderer(
			'de',
			$mockMessageLocalizer,
			$mockAlphabetsProvider,
			null,
			$config
		);

		$label = $renderer->getBacklinkMarker( $reuseIndex );
		$this->assertSame( $expectedLabel, $label );
	}

	public static function provideGetBacklinkMarker() {
		return [
			[ 'ab', 5, 'a b c' ],
		];
	}

	/**
	 * @dataProvider provideGetLegacyAlphabeticMarker
	 */
	public function testGetLegacyAlphabeticMarker(
		string $expectedLabel, string $parentLabel, ?string $customAlphabet, int $reuseIndex, int $reuseCount
	) {
		$mockMessageLocalizer = $this->createNoOpMock( ReferenceMessageLocalizer::class,
			[ 'msg', 'localizeSeparators', 'localizeDigits' ] );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) use ( $customAlphabet ) {
				$msg = $this->createMock( Message::class );
				$msg->method( 'isDisabled' )->willReturn( $customAlphabet === null );
				$msg->method( 'plain' )->willReturn( $customAlphabet );
				return $msg;
			}
		);
		$mockMessageLocalizer->method( 'localizeSeparators' )->willReturn( ',' );
		$mockMessageLocalizer->method( 'localizeDigits' )->willReturnArgument( 0 );

		$mockAlphabetsProvider = $this->createMock( AlphabetsProvider::class );
		$mockAlphabetsProvider->method( 'getIndexCharacters' )->willReturn( [ 'z', 'y', 'x' ] );

		$config = new HashConfig( [
			'CiteDefaultBacklinkAlphabet' => null,
			'CiteUseLegacyBacklinkLabels' => true,
		] );

		$renderer = new BacklinkMarkRenderer(
			'de',
			$mockMessageLocalizer,
			$mockAlphabetsProvider,
			null,
			$config
		);

		$label = $renderer->getLegacyAlphabeticMarker( $reuseIndex, $reuseCount, $parentLabel );
		$this->assertSame( $expectedLabel, $label );
	}

	public static function provideGetLegacyAlphabeticMarker() {
		yield [ 'aa', '4', 'aa ab ac', 1, 9 ];
		yield [ 'ab', '4', 'aa ab ac', 2, 9 ];
		yield [ 'å', '4', 'å b c', 1, 1 ];
		yield [ '4,10', '4', 'a b c', 10, 10 ];
	}

	/**
	 * @dataProvider provideGetLegacyNumericMarker
	 */
	public function testGetLegacyNumericMarker(
		string $expectedLabel, string $parentLabel, int $reuseIndex, int $reuseCount
	) {
		$mockMessageLocalizer = $this->createNoOpMock( ReferenceMessageLocalizer::class,
			[ 'msg', 'localizeSeparators', 'localizeDigits' ] );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) {
				$msg = $this->createMock( Message::class );
				$msg->method( 'isDisabled' )->willReturn( true );
				return $msg;
			}
		);
		$mockMessageLocalizer->method( 'localizeSeparators' )->willReturn( ',' );
		$mockMessageLocalizer->method( 'localizeDigits' )->willReturnArgument( 0 );

		$mockAlphabetsProvider = $this->createMock( AlphabetsProvider::class );
		$mockAlphabetsProvider->method( 'getIndexCharacters' )->willReturn( [ 'z', 'y', 'x' ] );

		$config = new HashConfig( [
			'CiteDefaultBacklinkAlphabet' => null,
			'CiteUseLegacyBacklinkLabels' => true,
		] );

		$renderer = new BacklinkMarkRenderer(
			'de',
			$mockMessageLocalizer,
			$mockAlphabetsProvider,
			null,
			$config
		);

		$label = $renderer->getLegacyNumericMarker( $reuseIndex, $reuseCount, $parentLabel );
		$this->assertSame( $expectedLabel, $label );
	}

	public static function provideGetLegacyNumericMarker() {
		yield [ '1,2', '1', 2, 9 ];
		yield [ '1,02', '1', 2, 99 ];
		yield [ '1,002', '1', 2, 100 ];
		yield [ '1,50005', '1', 50005, 50005 ];
		yield [ '2,1', '2', 1, 1 ];
		yield [ '3.2,1', '3.2', 1, 1 ];
	}

}
