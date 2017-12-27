<?php

namespace Wikimedia\Purtle\Tests;

use Wikimedia\Purtle\XmlRdfWriter;
use Wikimedia\Purtle\RdfWriter;

/**
 * @covers Wikimedia\Purtle\XmlRdfWriter
 * @covers Wikimedia\Purtle\RdfWriterBase
 *
 * @uses Wikimedia\Purtle\BNodeLabeler
 *
 * @group Purtle
 * @group RdfWriter
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class XmlRdfWriterTest extends RdfWriterTestBase {

	protected function getFileSuffix() {
		return 'rdf';
	}

	/**
	 * @return RdfWriter
	 */
	protected function newWriter() {
		return new XmlRdfWriter();
	}

}
