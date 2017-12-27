<?php

namespace Wikimedia\Purtle\Tests;

use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use Wikimedia\Purtle\RdfWriter;
use Wikimedia\Purtle\RdfWriterFactory;

/**
 * @covers Wikimedia\Purtle\RdfWriterFactory
 *
 * @uses Wikimedia\Purtle\BNodeLabeler
 * @uses Wikimedia\Purtle\N3Quoter
 * @uses Wikimedia\Purtle\N3RdfWriterBase
 * @uses Wikimedia\Purtle\NTriplesRdfWriter
 * @uses Wikimedia\Purtle\RdfWriterBase
 * @uses Wikimedia\Purtle\TurtleRdfWriter
 * @uses Wikimedia\Purtle\XmlRdfWriter
 *
 * @group Purtle
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 * @author Thiemo Mättig
 */
class RdfWriterFactoryTest extends PHPUnit_Framework_TestCase {

	public function testSupportedFormats() {
		$factory = new RdfWriterFactory();

		$formats = $factory->getSupportedFormats();

		$this->assertInternalType( 'array', $formats );
		$this->assertNotEmpty( $formats );
	}

	public function testGetWriter() {
		$factory = new RdfWriterFactory();

		foreach ( $factory->getSupportedFormats() as $format ) {
			$writer = $factory->getWriter( $format );

			$this->assertInstanceOf( RdfWriter::class, $writer );
		}
	}

	public function testGivenInvalidFormat_getWriterThrowsException() {
		$factory = new RdfWriterFactory();
		$this->setExpectedException( InvalidArgumentException::class );
		$factory->getWriter( 'invalid' );
	}

	public function testGetFormatName() {
		$factory = new RdfWriterFactory();

		foreach ( $factory->getSupportedFormats() as $format ) {
			$actual = $factory->getFormatName( $format );

			// the canonical name should just stay
			$this->assertEquals( $format, $actual );
		}
	}

	public function testGivenInvalidFormat_getFormatNameReturnsFalse() {
		$factory = new RdfWriterFactory();
		$this->assertFalse( $factory->getFormatName( 'invalid' ) );
	}

	public function provideFormats() {
		return [
			// N3 (currently falls through to turtle)
			[ 'N3', 'n3', 'n3', 'text/n3' ],
			[ 'text/n3', 'n3', 'n3', 'text/n3' ],
			[ 'text/rdf+n3', 'n3', 'n3', 'text/n3' ],

			[ 'ttl', 'turtle', 'ttl', 'text/turtle' ],
			[ 'turtle', 'turtle', 'ttl', 'text/turtle' ],
			[ 'text/turtle', 'turtle', 'ttl', 'text/turtle' ],
			[ 'application/x-turtle', 'turtle', 'ttl', 'text/turtle' ],

			[ 'nt', 'ntriples', 'nt', 'application/n-triples' ],
			[ 'ntriples', 'ntriples', 'nt', 'application/n-triples' ],
			[ 'n-triples', 'ntriples', 'nt', 'application/n-triples' ],
			[ 'text/plain', 'ntriples', 'nt', 'application/n-triples' ],
			[ 'text/n-triples', 'ntriples', 'nt', 'application/n-triples' ],
			[ 'application/ntriples', 'ntriples', 'nt', 'application/n-triples' ],
			[ 'application/n-triples', 'ntriples', 'nt', 'application/n-triples' ],

			[ 'xml', 'rdfxml', 'rdf', 'application/rdf+xml' ],
			[ 'rdf', 'rdfxml', 'rdf', 'application/rdf+xml' ],
			[ 'rdfxml', 'rdfxml', 'rdf', 'application/rdf+xml' ],
			[ 'application/rdf+xml', 'rdfxml', 'rdf', 'application/rdf+xml' ],
			[ 'application/xml', 'rdfxml', 'rdf', 'application/rdf+xml' ],
			[ 'text/xml', 'rdfxml', 'rdf', 'application/rdf+xml' ],
		];
	}

	/**
	 * @dataProvider provideFormats
	 */
	public function testFormats( $name, $canonicalName, $expectedFileExtension, $expectedMimeType ) {
		$factory = new RdfWriterFactory();

		$this->assertEquals( $canonicalName, $factory->getFormatName( $name ) );
		$this->assertEquals( $expectedFileExtension, $factory->getFileExtension( $canonicalName ) );
		$this->assertContains( $expectedMimeType, $factory->getMimeTypes( $canonicalName ) );

		$writer = $factory->getWriter( $canonicalName );
		$this->assertInstanceOf( RdfWriter::class, $writer );
	}

	public function testGetMimeTypes() {
		$factory = new RdfWriterFactory();

		foreach ( $factory->getSupportedFormats() as $format ) {
			$mimeTypes = $factory->getMimeTypes( $format );

			$this->assertInternalType( 'array', $mimeTypes );
			$this->assertNotEmpty( $mimeTypes );
		}
	}

	public function testGivenInvalidFormat_getMimeTypesThrowsException() {
		$factory = new RdfWriterFactory();
		$this->setExpectedException( InvalidArgumentException::class );
		$factory->getMimeTypes( 'invalid' );
	}

	public function testGetFileExtensions() {
		$factory = new RdfWriterFactory();

		foreach ( $factory->getSupportedFormats() as $format ) {
			$extension = $factory->getFileExtension( $format );

			$this->assertInternalType( 'string', $extension );
		}
	}

	public function testGivenInvalidFormat_getFileExtensionsThrowsException() {
		$factory = new RdfWriterFactory();
		$this->setExpectedException( InvalidArgumentException::class );
		$factory->getFileExtension( 'invalid' );
	}

}
