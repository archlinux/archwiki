<?php

namespace Wikimedia\Purtle\Tests;

use Wikimedia\Purtle\RdfWriter;
use Wikimedia\Purtle\TurtleRdfWriter;

/**
 * @covers Wikimedia\Purtle\TurtleRdfWriter
 * @covers Wikimedia\Purtle\N3RdfWriterBase
 * @covers Wikimedia\Purtle\RdfWriterBase
 *
 * @uses Wikimedia\Purtle\BNodeLabeler
 * @uses Wikimedia\Purtle\N3Quoter
 *
 * @group Purtle
 * @group RdfWriter
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 * @author Thiemo Mättig
 */
class TurtleRdfWriterTest extends RdfWriterTestBase {

	protected function getFileSuffix() {
		return 'ttl';
	}

	/**
	 * @return RdfWriter
	 */
	protected function newWriter() {
		return new TurtleRdfWriter();
	}

	public function testTrustIRIs() {
		$writer = new TurtleRdfWriter();
		$this->assertTrue( $writer->getTrustIRIs(), 'initialy enabled' );
		$writer->setTrustIRIs( false );
		$this->assertFalse( $writer->getTrustIRIs(), 'disabled' );
	}

}
