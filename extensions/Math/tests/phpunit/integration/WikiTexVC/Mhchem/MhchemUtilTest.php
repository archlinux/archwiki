<?php

namespace MediaWiki\Extension\Math\WikiTexVC\Mhchem;

use PHPUnit\Framework\TestCase;

/**
 * Test suite for the concatArray method in the MhchemUtil class.
 *
 * @covers \MediaWiki\Extension\Math\WikiTexVC\Mhchem\MhchemUtil
 */
class MhchemUtilTest extends TestCase {

	/**
	 * Tests issetJS with a null value.
	 */
	public function testIssetJSWithNull(): void {
		$this->assertFalse( MhchemUtil::issetJS( null ), "Null should return false in issetJS." );
	}

	/**
	 * Tests issetJS with a zero value.
	 */
	public function testIssetJSWithZero(): void {
		$this->assertFalse( MhchemUtil::issetJS( 0 ), "Zero should return false in issetJS." );
	}

	/**
	 * Tests issetJS with an empty string.
	 */
	public function testIssetJSWithEmptyString(): void {
		$this->assertFalse( MhchemUtil::issetJS( "" ), "Empty string should return false in issetJS." );
	}

	/**
	 * Tests issetJS with a non-empty string.
	 */
	public function testIssetJSWithNonEmptyString(): void {
		$this->assertTrue( MhchemUtil::issetJS( "non-empty" ), "Non-empty string should return true in issetJS." );
	}

	/**
	 * Tests issetJS with a number.
	 */
	public function testIssetJSWithNumber(): void {
		$this->assertTrue( MhchemUtil::issetJS( 123 ), "Non-zero number should return true in issetJS." );
	}

	/**
	 * Tests issetJS with an empty array.
	 */
	public function testIssetJSWithEmptyArray(): void {
		$this->assertTrue( MhchemUtil::issetJS( [] ) );
	}

	/**
	 * Tests issetJS with a non-empty array.
	 */
	public function testIssetJSWithNonEmptyArray(): void {
		$this->assertTrue( MhchemUtil::issetJS( [ 1, 2, 3 ] ), "Non-empty array should return true in issetJS." );
	}

	/**
	 * Tests concatArray with null input.
	 */
	public function testConcatArrayWithNullInput(): void {
		$array = [];
		MhchemUtil::concatArray( $array, null );
		$this->assertSame( [], $array, "Array should remain empty when null is passed." );
	}

	/**
	 * Tests concatArray with a single scalar value.
	 */
	public function testConcatArrayWithScalarValue(): void {
		$array = [ 1 ];
		MhchemUtil::concatArray( $array, 2 );
		$this->assertSame( [ 1, 2 ], $array, "Scalar value should be appended." );
	}

	/**
	 * Tests concatArray with an empty array as input.
	 */
	public function testConcatArrayWithEmptyArray(): void {
		$array = [];
		MhchemUtil::concatArray( $array, [] );
		$this->assertSame( [], $array, "Array should remain empty when empty array is passed." );
	}

	/**
	 * Tests concatArray with an associative array.
	 */
	public function testConcatArrayWithAssociativeArray(): void {
		$array = [];
		$assocArray = [ 'a' => 1, 'b' => 2 ];
		MhchemUtil::concatArray( $array, $assocArray );
		$this->assertSame( [ $assocArray ], $array, "Associative array should be appended as a single item." );
	}

	/**
	 * Tests concatArray with a sequential array.
	 */
	public function testConcatArrayWithSequentialArray(): void {
		$array = [ 1 ];
		$seqArray = [ 2, 3 ];
		MhchemUtil::concatArray( $array, $seqArray );
		$this->assertSame( [ 1, 2, 3 ], $array, "Sequential array elements should be appended individually." );
	}

	/**
	 * Tests concatArray with a mix of scalar and array values.
	 */
	public function testConcatArrayWithMixedValues(): void {
		$array = [];
		$scalar = 42;
		$seqArray = [ 1, 2 ];
		$assocArray = [ 'key' => 'value' ];

		MhchemUtil::concatArray( $array, $scalar );
		MhchemUtil::concatArray( $array, $seqArray );
		MhchemUtil::concatArray( $array, $assocArray );

		$this->assertSame(
			[ 42, 1, 2, $assocArray ],
			$array,
			"Mixed values should be appended correctly."
		);
	}
}
