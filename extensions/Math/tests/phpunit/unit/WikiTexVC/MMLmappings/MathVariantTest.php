<?php

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\MathVariant;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Variants;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\MathVariant
 */
class MathVariantTest extends TestCase {

	public function testGetJsonFile() {
		$this->assertIsString( MathVariant::getJsonFile() );
	}

	public function testRemoveMathVariantAttribute() {
		$attributes = [
			'mathvariant' => Variants::BOLD,
			'class' => 'some-class',
		];

		MathVariant::removeMathVariantAttribute( $attributes );

		$this->assertArrayNotHasKey( 'mathvariant', $attributes );
		$this->assertArrayHasKey( 'class', $attributes );
		$this->assertEquals( 'some-class', $attributes['class'] );
	}

	public function testRemoveMathVariantAttributeNormal() {
		$attributes = [
			'mathvariant' => Variants::NORMAL,
		];

		MathVariant::removeMathVariantAttribute( $attributes );

		$this->assertArrayHasKey( 'mathvariant', $attributes );
		$this->assertEquals( Variants::NORMAL, $attributes['mathvariant'] );
	}

	public function testTranslateInvalid() {
		$this->expectException( InvalidArgumentException::class );
		MathVariant::translate( 'some', 'invalid' );
	}

	/**
	 * @dataProvider provideTranslateValidData
	 */
	public function testTranslateValid( string $input, string $variant, string $expected ) {
		$this->assertEquals( $expected, MathVariant::translate( $input, $variant ) );
	}

	public static function provideTranslateValidData(): array {
			return [
				'bold translation' => [ 'A', 'bold', '𝐀' ],
				'numeric translation' => [ '1', 'double-struck', '𝟙' ],
				'italic translation' => [ 'x', 'italic', '𝑥' ],
				'arabic double-struck translation' => [ 'ب', 'double-struck', '𞺡' ],
				'non-existing fraktur' => [ '0', 'fraktur', '0' ],
				'composite fraktur' => [ '0AB', 'fraktur', '0𝔄𝔅' ],
			];
	}

	public function testGetInstance() {
		MathVariant::tearDown();
		$instance = MathVariant::getInstance();
		$this->assertInstanceOf( MathVariant::class, $instance );
	}
}
