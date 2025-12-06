<?php

namespace Cite\Tests\Unit;

use Cite\AlphabetsProvider;
use Cite\BacklinkMarkRenderer;
use Cite\ReferenceMessageLocalizer;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CommunityConfiguration\Provider\ConfigurationProviderFactory;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Message\Message;
use StatusValue;

/**
 * @covers \Cite\BacklinkMarkRenderer
 * @license GPL-2.0-or-later
 */
class BacklinkMarkRendererTest extends \MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideCommunityConfiguration
	 */
	public function testCommunityConfigurationIntegration(
		string $expectedLabel, int $reuseIndex, ?string $communityConfigAlphabet
	) {
		if ( !class_exists( IConfigurationProvider::class ) ) {
			$this->markTestSkipped( 'Extension CommunityConfiguration is required for this test' );
		}

		$msg = $this->createMock( Message::class );
		$msg->method( 'isDisabled' )->willReturn( true );
		$messageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$messageLocalizer->method( 'msg' )->willReturn( $msg );

		$provider = $this->createMock( IConfigurationProvider::class );
		$provider->expects( $this->once() )
			->method( 'loadValidConfiguration' )
			->willReturn( StatusValue::newGood( (object)[
				'Cite_Settings' => (object)[
					'backlinkAlphabet' => $communityConfigAlphabet
				]
			] ) );
		$providerFactory = $this->createMock( ConfigurationProviderFactory::class );
		$providerFactory->method( 'newProvider' )->willReturn( $provider );

		$renderer = new BacklinkMarkRenderer(
			'de',
			$messageLocalizer,
			$this->createMock( AlphabetsProvider::class ),
			$providerFactory,
			new HashConfig( [
				'CiteBacklinkCommunityConfiguration' => true,
				'CiteDefaultBacklinkAlphabet' => null,
				'CiteUseLegacyBacklinkLabels' => false,
			] )
		);

		$label = $renderer->getBacklinkMarker( $reuseIndex );
		$this->assertSame( $expectedLabel, $label );
	}

	public static function provideCommunityConfiguration() {
		return [
			[ '', 0, 'a' ],
			[ 'one', 1, 'one two three' ],
			[ 'qr', 5, 'q r s' ],
			[ 'QSR', 20, 'Q  R  S' ],
			// Fallback to the hard-coded a…z
			[ 'e', 5, null ],
		];
	}

	/**
	 * @dataProvider provideGetBacklinkMarker
	 */
	public function testGetBacklinkMarker(
		string $expectedLabel, int $reuseIndex, ?string $customAlphabet
	) {
		$msg = $this->createMock( Message::class );
		$msg->method( 'isDisabled' )->willReturn( true );
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturn( $msg );

		$mockAlphabetsProvider = $this->createMock( AlphabetsProvider::class );
		$mockAlphabetsProvider->method( 'getIndexCharacters' )->willReturn( [ 'Z', 'Y', 'X' ] );

		$renderer = new BacklinkMarkRenderer(
			'de',
			$mockMessageLocalizer,
			$mockAlphabetsProvider,
			null,
			new HashConfig( [
				'CiteDefaultBacklinkAlphabet' => $customAlphabet,
				'CiteUseLegacyBacklinkLabels' => false,
			] )
		);

		$label = $renderer->getBacklinkMarker( $reuseIndex );
		$this->assertSame( $expectedLabel, $label );
	}

	public static function provideGetBacklinkMarker() {
		return [
			// Test cases for the code path that falls back to $wgCiteDefaultBacklinkAlphabet
			[ '', 0, 'a b c' ],
			[ 'one', 1, 'one two three' ],
			[ 'aa', 2, 'a' ],
			[ 'ab', 5, 'a b c' ],
			[ 'ACB', 20, 'A  B  C' ],

			// Test cases for the Alphabets integration from the CLDR extension
			[ 'z', 1, '' ],
			[ 'zxy', 20, null ],
		];
	}

	/**
	 * @dataProvider provideGetLegacyAlphabeticMarker
	 */
	public function testGetLegacyAlphabeticMarker(
		string $expectedLabel, string $parentLabel, ?string $customAlphabet, int $reuseIndex, int $reuseCount
	) {
		$msg = $this->createMock( Message::class );
		$msg->method( 'isDisabled' )->willReturn( $customAlphabet === null );
		$msg->method( 'plain' )->willReturn( $customAlphabet );
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturn( $msg );
		$mockMessageLocalizer->method( 'localizeSeparators' )->willReturn( ',' );
		$mockMessageLocalizer->method( 'localizeDigits' )->willReturnArgument( 0 );

		$renderer = new BacklinkMarkRenderer(
			'de',
			$mockMessageLocalizer,
			$this->createMock( AlphabetsProvider::class ),
			null,
			new HashConfig( [
				'CiteDefaultBacklinkAlphabet' => null,
				'CiteUseLegacyBacklinkLabels' => true,
			] )
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
		$msg = $this->createMock( Message::class );
		$msg->method( 'isDisabled' )->willReturn( true );
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturn( $msg );
		$mockMessageLocalizer->method( 'localizeSeparators' )->willReturn( ',' );
		$mockMessageLocalizer->method( 'localizeDigits' )->willReturnArgument( 0 );

		$renderer = new BacklinkMarkRenderer(
			'de',
			$mockMessageLocalizer,
			$this->createMock( AlphabetsProvider::class ),
			null,
			new HashConfig( [
				'CiteDefaultBacklinkAlphabet' => null,
				'CiteUseLegacyBacklinkLabels' => true,
			] )
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
