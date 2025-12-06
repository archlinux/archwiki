<?php

namespace MediaWiki\Extension\Math\WikiTexVC\Mhchem\Tests;

use MediaWiki\Extension\Math\WikiTexVC\Mhchem\MhchemParser;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the MhchemParser::toTex method.
 *
 * The toTex method converts chemical notations or physical unit strings into TeX output.
 *
 * @covers \MediaWiki\Extension\Math\WikiTexVC\Mhchem\MhchemParser
 * @covers \MediaWiki\Extension\Math\WikiTexVC\Mhchem\MhchemTexify
 */
class MhchemParserTest extends TestCase {

	/**
	 * Test the toTex method with a simple chemical equation.
	 */
	public function testToTexWithSimpleChemicalEquation() {
		$parser = new MhchemParser();
		$input = 'H2O';
		$type = 'ce';
		$result = $parser->toTex( $input, $type );

		$this->assertIsString( $result );
		$this->assertEquals( '{\mathrm{H}{\vphantom{A}}_{\smash[t]{2}}\mathrm{O}}', $result );
	}

	/**
	 * Test the toTex method with physical unit parsing.
	 */
	public function testToTexWithPhysicalUnit() {
		$parser = new MhchemParser();
		$input = 'm/s^2';
		$type = 'pu';
		$result = $parser->toTex( $input, $type );

		$this->assertIsString( $result );
		$this->assertEquals( '{\mathrm{m}/\mathrm{s^{2}}}', $result,
			'The TeX output for physical units should match expected value' );
	}

	/**
	 * Test the toTex method with an empty input.
	 */
	public function testToTexWithEmptyInput() {
		$parser = new MhchemParser();
		$input = '';
		$type = 'ce';
		$result = $parser->toTex( $input, $type );

		$this->assertIsString( $result, 'The output should be a string' );
		$this->assertSame( '', $result, 'The output for empty input should be empty' );
	}

	/**
	 * Test the toTex method with optimization for TeX VC enabled.
	 */
	public function testToTexWithOptimizationForTexVC() {
		$parser = new MhchemParser();
		$input = 'C\bond{-~-}D';
		$type = 'ce';
		$optimizeMhchemForTexVC = true;
		$result = $parser->toTex( $input, $type, $optimizeMhchemForTexVC );

		$this->assertIsString( $result, 'The output should be a string' );
		$this->assertEquals( '{\mathrm{C}{\rlap{\lower{.2em}{-}}\rlap{\raise{.2em}{-}}\tripledash}\mathrm{D}}',
			$result, 'The TeX output should include extra curlies for optimization' );
	}
}
