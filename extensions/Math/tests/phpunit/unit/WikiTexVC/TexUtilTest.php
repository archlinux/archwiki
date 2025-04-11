<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC;

use InvalidArgumentException;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseParsing;
use MediaWiki\Extension\Math\WikiTexVC\TexUtil;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\TexUtil
 */
class TexUtilTest extends MediaWikiUnitTestCase {

	/**
	 * Basic test for TexUtil
	 */
	public function testTexUtil() {
		TexUtil::removeInstance();
		$tu = TexUtil::getInstance();
		// Testing all functions
		$this->assertTrue( $tu->getAllFunctionsAt( "\\AA" ) );
		$this->assertFalse( $tu->getAllFunctionsAt( "\\notlisted" ) );
		// Testing other functions
		$this->assertTrue( $tu->mhchem_macro_2pc( "\\color" ) );
		$this->assertFalse( $tu->mhchem_macro_2pc( "not listed" ) );
	}

	public function testInvalidCall() {
		TexUtil::removeInstance();
		$tu = TexUtil::getInstance();
		// Testing all functions
		$this->expectException( InvalidArgumentException::class );
		$tu->__call( '\\notlisted', [] );
	}

	public function testUnicodeDefined() {
		$tu = TexUtil::getInstance();
		$sets = [ 'nullary_macro_in_mbox' ];
		foreach ( $sets as $set ) {
			$baseElements = $tu->getBaseElements();
			foreach ( $baseElements[$set] as $key => $value ) {
				$this->assertIsString( $tu->unicode_char( $key ),
					"unicode_char should return a string for $key ($set)" );
			}
		}
	}

	public function testBaseElements() {
		$tu = TexUtil::getInstance();

		$sets = [
			'ams_required',
			'big_literals',
			'box_functions',
			'callback',
			'cancel_required',
			'color',
			'color_function',
			'color_required',
			'declh_function',
			'definecolor_function',
			'delimiter',
			'deprecated_nullary_macro_aliase',
			'euro_required',
			'fun_ar1',
			'fun_ar1nb',
			'fun_ar1opt',
			'fun_ar2',
			'fun_ar2nb',
			'fun_ar4',
			'fun_infix',
			'fun_mhchem',
			'hline_function',
			'identifier',
			'ignore_identifier',
			'intent_required',
			'is_letter_mod',
			'is_literal',
			'latex_function_names',
			'left_function',
			'mathchar',
			'mathoid_required',
			'mediawiki_function_names',
			'mhchem_bond',
			'mhchem_macro_1p',
			'mhchem_macro_2p',
			'mhchem_macro_2pc',
			'mhchem_macro_2pu',
			'mhchem_required',
			'mhchem_single_macro',
			'mhchemtexified_required',
			'nullary_macro',
			'nullary_macro_aliase',
			'nullary_macro_in_mbox',
			'operator',
			'operator_rendering',
			'other_delimiters1',
			'other_delimiters2',
			'other_fun_ar1',
			'over_operator',
			'right_function',
			'stix_required',
			'teubner_required',
			'unicode_char',
		];
		$baseElements = $tu->getBaseElements();
		ksort( $baseElements );
		$this->assertEquals( $sets, array_keys( $baseElements ) );
	}

	/**
	 * Testing a checksum for the parsed object against a checksum of the json file contents.
	 * @return void
	 */
	public function testChecksum() {
		$tu = TexUtil::getInstance();

		$out = [];
		$baseElements = $tu->getBaseElements();

		// Reading data from TexUtil.
		foreach ( $baseElements as $group => $set ) {
			foreach ( $set as $key => $value ) {
				if ( !array_key_exists( $key, $out ) ) {
					$out[$key] = [];
				}
				$out[$key][$group] = $value;
			}
		}

		// Sorting output alphabetically encP - out not sorted correctly
		ksort( $out );
		foreach ( $out as &$op ) {
			ksort( $op );
		}

		// Loading local json file
		$file = TexUtil::getJsonFile();
		// json_encode cannot generate tabs required by WMF convention https://github.com/php/php-src/issues/8864
		$encP = json_encode( $out, JSON_PRETTY_PRINT );
		$encP = preg_replace( '/\n\s+/', "\n", $encP ) . "\n";
		// unescape slashes for comparison as escaping is not allowed in the json file
		$encP = str_replace( '\/', '/', $encP );
		$file = preg_replace( '/\n\s+/', "\n", $file );
		$hashOutput = $this->getHash( $encP );
		$hashFile = $this->getHash( $file );
		// uncomment the following lines to spot differences in your IDE
		// $this->assertEquals( $encP, $file );
		$this->assertEquals( $hashFile, $hashOutput );
	}

	public function testMethodNamesExist() {
		$tu = TexUtil::getInstance();
		$sets = [
			'callback',
		];
		foreach ( $sets as $set ) {
			$functions = $tu->getBaseElements()[ $set ];
			foreach ( $functions as $symbol => $function ) {
					$methodName = is_array( $function ) ? $function[0] : $function;

					$this->assertTrue( method_exists( BaseParsing::class, $methodName ),
						'Method ' . $methodName . ' for symbol ' . $symbol . ' does not exist in BaseParsing' );
			}
		}
	}

	public function testDataType() {
		$types = [
			'mathchar' => 'string',
			'color' => 'string',
		];
		$tu = TexUtil::getInstance();
		$baseElements = $tu->getBaseElements();
		foreach ( $types as $set => $type ) {
			foreach ( $baseElements[$set] as $key => $value ) {
				$this->assertEquals( $type, gettype( $tu->$set( $key ) ),
					"$set should return a $type for $key" );
			}
		}
	}

	public function testGetOperatorByKey() {
		$this->assertEquals( '&#x221A;', TexUtil::getInstance()->operator_rendering( '\\surd' )[0] );
		$this->assertEquals( '&#x2212;', TexUtil::getInstance()->operator_rendering( '-' )[0] );
	}

	public function testGetColorByKey() {
		$this->assertEquals( '#ED1B23', TexUtil::getInstance()->color( 'Red' ) );
	}

	private function getHash( $input ) {
		return hash( 'sha256', $input );
	}
}
