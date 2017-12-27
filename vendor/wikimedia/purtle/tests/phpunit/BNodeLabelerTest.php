<?php

namespace Wikimedia\Purtle\Tests;

use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use Wikimedia\Purtle\BNodeLabeler;

/**
 * @covers Wikimedia\Purtle\BNodeLabeler
 *
 * @group Purtle
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 * @author Thiemo Mättig
 */
class BNodeLabelerTest extends PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider invalidConstructorArgumentsProvider
	 */
	public function testInvalidConstructorArguments( $prefix, $start ) {
		$this->setExpectedException( InvalidArgumentException::class );
		new BNodeLabeler( $prefix, $start );
	}

	public function invalidConstructorArgumentsProvider() {
		return [
			[ null, 1 ],
			[ 1, 1 ],
			[ 'prefix', null ],
			[ 'prefix', 0 ],
			[ 'prefix', '1' ],
		];
	}

	public function testGetLabel() {
		$labeler = new BNodeLabeler( 'test', 2 );

		$this->assertEquals( 'test2', $labeler->getLabel() );
		$this->assertEquals( 'test3', $labeler->getLabel() );
		$this->assertEquals( 'foo', $labeler->getLabel( 'foo' ) );
		$this->assertEquals( 'test4', $labeler->getLabel() );
	}

}
