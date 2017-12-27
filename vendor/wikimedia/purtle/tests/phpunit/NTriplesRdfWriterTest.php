<?php

namespace Wikimedia\Purtle\Tests;

use Wikimedia\Purtle\NTriplesRdfWriter;
use Wikimedia\Purtle\RdfWriter;

/**
 * @covers Wikimedia\Purtle\NTriplesRdfWriter
 * @covers Wikimedia\Purtle\N3RdfWriterBase
 * @covers Wikimedia\Purtle\RdfWriterBase
 *
 * @uses Wikimedia\Purtle\BNodeLabeler
 * @uses Wikimedia\Purtle\N3Quoter
 * @uses Wikimedia\Purtle\UnicodeEscaper
 *
 * @group Purtle
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class NTriplesRdfWriterTest extends RdfWriterTestBase {

	protected function getFileSuffix() {
		return 'nt';
	}

	/**
	 * @return RdfWriter
	 */
	protected function newWriter() {
		return new NTriplesRdfWriter();
	}

}
