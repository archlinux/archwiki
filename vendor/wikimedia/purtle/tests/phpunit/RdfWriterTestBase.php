<?php

namespace Wikimedia\Purtle\Tests;

use PHPUnit_Framework_TestCase;
use Wikimedia\Purtle\RdfWriter;

/**
 * Base class for tests for RdfWriter implementations.
 * Provides a common test suite for all implementations.
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 * @author Thiemo Mättig
 */
abstract class RdfWriterTestBase extends PHPUnit_Framework_TestCase {

	abstract protected function getFileSuffix();

	/**
	 * @return RdfWriter
	 */
	abstract protected function newWriter();

	public function testGetMimeType() {
		$mimeType = $this->newWriter()->getMimeType();
		$this->assertInternalType( 'string', $mimeType );
		$this->assertRegExp( '/^\w+\/[\w-]+(\+xml)?(; charset=UTF-8)?$/', $mimeType );
	}

	public function testTriples() {
		$writer = $this->newWriter();

		$writer->prefix( 'acme', 'http://acme.test/' );
		$writer->start();

		$writer->about( 'http://foobar.test/Bananas' )
			->say( 'a' )->is( 'http://foobar.test/Fruit' ); // shorthand name "a"

		$writer->about( 'acme', 'Nuts' )
			->say( 'acme', 'weight' )->value( '5.5', 'xsd', 'decimal' );

		// redundant about( 'acme', 'Nuts' )
		$writer->about( 'acme', 'Nuts' )
			->say( 'acme', 'color' )->value( 'brown' );
		$writer->finish();

		$rdf = $writer->drain();
		$this->assertOutputLines( 'Triples', $rdf );
	}

	public function testPredicates() {
		$writer = $this->newWriter();

		$writer->prefix( '', 'http://acme.test/' ); // empty prefix
		$writer->start();

		$writer->about( 'http://foobar.test/Bananas' )
			->a( 'http://foobar.test/Fruit' ) // shorthand function a()
			->say( '', 'name' ) // empty prefix
				->text( 'Banana' )
			->say( '', 'name' ) // redundant say( '', 'name' )
				->text( 'Banane', 'de' );

		$writer->about( 'http://foobar.test/Apples' )
			->say( '', 'name' ) // subsequent call to say( '', 'name' ) for a different subject
				->text( 'Apple' );
		$writer->finish();

		$rdf = $writer->drain();
		$this->assertOutputLines( 'Predicates', $rdf );
	}

	public function testPredicates_drain() {
		$writer = $this->newWriter();

		$writer->prefix( '', 'http://acme.test/' ); // empty prefix
		$writer->start();

		$writer->about( 'http://foobar.test/Bananas' )
			->a( 'http://foobar.test/Fruit' ) // shorthand function a()
			->say( '', 'name' ) // empty prefix
			->text( 'Banana' )
			->say( '', 'name' ) // redundant say( '', 'name' )
			->text( 'Banane', 'de' );

		$rdf1 = $writer->drain();
		$this->assertNotEmpty( $rdf1 );

		$writer->about( 'http://foobar.test/Apples' )
			->say( '', 'name' ) // subsequent call to say( '', 'name' ) for a different subject
			->text( 'Apple' );
		$writer->finish();

		$rdf2 = $writer->drain();
		$this->assertNotEmpty( $rdf2 );

		$this->assertOutputLines( 'Predicates', $rdf1 . $rdf2 );
	}

	public function testPredicates_sub() {
		$writer = $this->newWriter();

		$writer->prefix( '', 'http://acme.test/' ); // empty prefix
		$writer->start();

		$sub = $writer->sub();

		// output of the sub writer will appear after the output of the main writer.
		$sub->about( 'http://foobar.test/Apples' )
			->say( '', 'name' ) // subsequent call to say( '', 'name' ) for a different subject
			->text( 'Apple' );

		$writer->about( 'http://foobar.test/Bananas' )
			->a( 'http://foobar.test/Fruit' ) // shorthand function a()
			->say( '', 'name' ) // empty prefix
			->text( 'Banana' )
			->say( '', 'name' ) // redundant say( '', 'name' )
			->text( 'Banane', 'de' );

		$writer->finish();

		$rdf = $writer->drain();
		$this->assertOutputLines( 'Predicates', $rdf );
	}

	public function testPredicates_sub_drain() {
		$writer = $this->newWriter();

		$writer->prefix( '', 'http://acme.test/' ); // empty prefix
		$writer->start();

		$sub = $writer->sub();

		$writer->about( 'http://foobar.test/Bananas' )
			->a( 'http://foobar.test/Fruit' ) // shorthand function a()
			->say( '', 'name' ) // empty prefix
			->text( 'Banana' )
			->say( '', 'name' ) // redundant say( '', 'name' )
			->text( 'Banane', 'de' );

		$rdf1 = $writer->drain();
		$this->assertNotEmpty( $rdf1 );

		// sub-writer should still be usable after drain()
		$sub->about( 'http://foobar.test/Apples' )
			->say( '', 'name' ) // subsequent call to say( '', 'name' ) for a different subject
			->text( 'Apple' );

		$writer->finish();

		$rdf2 = $writer->drain();
		$this->assertNotEmpty( $rdf2 );

		$this->assertOutputLines( 'Predicates', $rdf1 . $rdf2 );
	}

	public function testValues() {
		$writer = $this->newWriter();

		$writer->prefix( 'acme', 'http://acme.test/' );
		$writer->start();

		$writer->about( 'http://foobar.test/Bananas' )
			->say( 'acme', 'multi' )
				->value( 'A' )
				->value( 'B' )
				->value( 'C' )
			->say( 'acme', 'type' )
				->value( 'foo', 'acme', 'thing' )
				->value( '-5', 'xsd', 'integer' )
				->value( '-5', 'xsd', 'decimal' )
				->value( '-5', 'xsd', 'double' )
				->value( 'true', 'xsd', 'boolean' )
				->value( 'false', 'xsd', 'boolean' )
			->say( 'acme', 'autotype' )
				->value( -5 )
				->value( 3.14 )
				->value( true )
				->value( false )
			->say( 'acme', 'no-autotype' )
				->value( -5, 'xsd', 'decimal' )
				->value( 3.14, 'xsd', 'string' )
				->value( true, 'xsd', 'string' )
				->value( false, 'xsd', 'string' )
			->say( 'acme', 'shorthand' )->value( 'foo' )
			->say( 'acme', 'typed-shorthand' )->value( 'foo', 'acme', 'thing' );
		$writer->finish();

		$rdf = $writer->drain();
		$this->assertOutputLines( 'Values', $rdf );
	}

	public function testResources() {
		$writer = $this->newWriter();

		$writer->prefix( 'acme', 'http://acme.test/' );
		$writer->start();

		$writer->about( 'acme', 'Bongos' )
			->say( 'acme', 'sounds' )
				->is( 'acme', 'Bing' )
				->is( 'http://foobar.test/sound/Bang' );
		$writer->finish();

		$rdf = $writer->drain();
		$this->assertOutputLines( 'Resources', $rdf );
	}

	public function testTexts() {
		$writer = $this->newWriter();

		$writer->prefix( 'acme', 'http://acme.test/' );
		$writer->start();

		$writer->about( 'acme', 'Bongos' )
			->say( 'acme', 'sounds' )
				->text( 'Bom', 'de' )
				->text( 'Bam', 'en' )
				->text( 'Como estas', 'es-419' )
				->text( 'What?', 'bad tag' );
		$writer->finish();

		$rdf = $writer->drain();
		$this->assertOutputLines( 'Texts', $rdf );
	}

	public function testNumbers() {
		$writer = $this->newWriter();

		$writer->prefix( 'acme', 'http://acme.test/' );
		$writer->start();

		$writer->about( 'acme', 'Bongos' )
			->say( 'acme', 'stock' )->value( 5, 'xsd', 'integer' )
				->value( 7 )
		->about( 'acme', 'Tablas' )
			->say( 'acme', 'stock' )->value( 6 );
		$writer->finish();

		$rdf = $writer->drain();
		$this->assertOutputLines( 'Numbers', $rdf );
	}

	public function testEricMiller() {
		// exampel taken from http://www.w3.org/2007/02/turtle/primer/

		$writer = $this->newWriter();

		$writer->prefix( 'contact', 'http://www.w3.org/2000/10/swap/pim/contact#' );
		$writer->start();

		$writer->about( 'http://www.w3.org/People/EM/contact#me' )
			->say( 'rdf', 'type' )->is( 'contact', 'Person' )
			->say( 'contact', 'fullName' )->text( 'Eric Miller' )
			->say( 'contact', 'mailbox' )->is( 'mailto:em@w3.org' )
			->say( 'contact', 'personalTitle' )->text( 'Dr.' );
		$writer->finish();

		$rdf = $writer->drain();
		$this->assertOutputLines( 'EricMiller', $rdf );
	}

	public function testLabeledBlankNode() {
		// exampel taken from http://www.w3.org/2007/02/turtle/primer/

		$writer = $this->newWriter();

		$writer->prefix( 'exterms', 'http://www.example.org/terms/' );
		$writer->prefix( 'exstaff', 'http://www.example.org/staffid/' );
		$writer->start();

		$writer->about( 'exstaff', '85740' )
			->say( 'exterms', 'address' )->is( '_', $label = $writer->blank( 'johnaddress' ) )
		->about( '_', $label )
			->say( 'exterms', 'street' )->text( '1501 Grant Avenue' )
			->say( 'exterms', 'city' )->text( 'Bedfort' )
			->say( 'exterms', 'state' )->text( 'Massachusetts' )
			->say( 'exterms', 'postalCode' )->text( '01730' );
		$writer->finish();

		$rdf = $writer->drain();
		$this->assertOutputLines( 'LabeledBlankNode', $rdf );
	}

	public function testNumberedBlankNodes() {
		// exampel taken from http://www.w3.org/2007/02/turtle/primer/

		$writer = $this->newWriter();

		$writer->prefix( 'exterms', 'http://www.example.org/terms/' );
		$writer->prefix( 'exstaff', 'http://www.example.org/staffid/' );
		$writer->prefix( 'ex', 'http://example.org/packages/vocab#' );
		$writer->start();

		$writer->about( 'exstaff', 'Sue' )
			->say( 'exterms', 'publication' )->is( '_', $label1 = $writer->blank() );
		$writer->about( '_', $label1 )
			->say( 'exterms', 'title' )->text( 'Antology of Time' );

		$writer->about( 'exstaff', 'Jack' )
			->say( 'exterms', 'publication' )->is( '_', $label2 = $writer->blank() );
		$writer->about( '_', $label2 )
			->say( 'exterms', 'title' )->text( 'Anthony of Time' );
		$writer->finish();

		$rdf = $writer->drain();
		$this->assertOutputLines( 'NumberedBlankNode', $rdf );
	}

	public function testQuotesAndSpecials() {
		$writer = $this->newWriter();
		$writer->prefix( 'exterms', 'http://www.example.org/terms/' );
		$writer->start();

		$writer->about( 'exterms', 'Duck' )->say( 'exterms', 'says' )
			->text( 'Duck says: "Quack!"' );
		$writer->about( 'exterms', 'Cow' )->say( 'exterms', 'says' )
			->text( "Cow says:\n\r 'Moo! \\Moo!'" );
		$writer->about( 'exterms', 'Bear' )->say( 'exterms', 'says' )
			->text( 'Bear says: Превед!' );

		$rdf = $writer->drain();
		$this->assertOutputLines( 'TextWithSpecialChars', $rdf );
	}

	/**
	 * @param string $datasetName
	 * @param string[]|string $actual
	 */
	private function assertOutputLines( $datasetName, $actual ) {
		$path = __DIR__ . '/../data/' . $datasetName . '.' . $this->getFileSuffix();

		$this->assertNTriplesEquals(
			file_get_contents( $path ),
			$actual,
			"Result mismatches data in $path"
		);
	}

	/**
	 * @param string[]|string $nTriples
	 *
	 * @return string[] Sorted alphabetically.
	 */
	protected function normalizeNTriples( $nTriples ) {
		if ( is_string( $nTriples ) ) {
			// Trim and ignore newlines at the end of the file only.
			$nTriples = explode( "\n", rtrim( $nTriples, "\n" ) );
		}

		sort( $nTriples );

		return $nTriples;
	}

	/**
	 * @param string[]|string $expected
	 * @param string[]|string $actual
	 * @param string $message
	 */
	protected function assertNTriplesEquals( $expected, $actual, $message = '' ) {
		$expected = $this->normalizeNTriples( $expected );
		$actual = $this->normalizeNTriples( $actual );

		// Comparing $expected and $actual directly would show triples that are present in both but
		// shifted in position. That makes the output hard to read. Calculating the $missing and
		// $extra sets helps.
		$extra = array_diff( $actual, $expected );
		$missing = array_diff( $expected, $actual );

		// Cute: $missing and $extra can be equal only if they are empty. Comparing them here
		// directly looks a bit odd in code, but produces meaningful output, especially if the input
		// was sorted.
		$this->assertEquals( $missing, $extra, $message );
	}

	//FIXME: test non-ascii literals!
	//FIXME: test uerl-encoding
	//FIXME: test IRIs!
}
