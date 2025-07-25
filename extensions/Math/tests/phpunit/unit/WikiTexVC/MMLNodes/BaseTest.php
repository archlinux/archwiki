<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLnodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Tag;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLDomVisitor;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\VisitorFactory;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase
 */
class BaseTest extends MediaWikiUnitTestCase {

	public function testEmpty() {
		$this->assertEquals(
			'<test/>',
			( new MMLbase( 'test' ) )->getEmpty()
		);
	}

	public function testEnd() {
		$this->assertEquals(
			'</test>',
			( new MMLbase( 'test' ) )->getEnd()
		);
	}

	public function testEncapsulateRaw() {
		$this->assertEquals(
			'<test><script>alert(document.cookies);</script></test>',
			( new MMLbase( 'test' ) )
				->encapsulateRaw( '<script>alert(document.cookies);</script>' )
		);
	}

	public function testAttributes() {
		$this->assertEquals(
			'<test data-mjx-texclass="texClass1" a="b">',
			( new MMLbase(
				'test',
				'texClass1',
				[ TAG::CLASSTAG => 'texClass2', 'a' => 'b' ] ) )
				->getStart()
		);
	}

	public function testName() {
		$this->assertEquals(
			'test',
			( new MMLbase( 'test' ) )->getName()
		);
	}

	public function testgetAttributes() {
		$mbase = new MMLbase( 'test', 'texClass1', [ 'mathvariant' => 'bold' ] );
		$this->assertEquals(
			[ 'mathvariant' => 'bold', 'data-mjx-texclass' => 'texClass1' ],
			$mbase->getAttributes()
		);
	}

	public function testAccept() {
		$visitor = new MMLDomVisitor();
		$mbase = new MMLbase( 'test', 'texClass1', [ 'mathvariant' => 'bold' ] );
		$mbase->accept( $visitor );
		$this->assertEquals(
			'<test mathvariant="bold" data-mjx-texclass="texClass1"></test>',
			$visitor->getHTML()
		);
	}

	public function testString() {
		$mbase = new MMLbase( 'test', 'texClass1', [ 'mathvariant' => 'bold' ] );
		$visitorFactory = new VisitorFactory();
		$mbase->setVisitorFactory( $visitorFactory );
		$this->assertEquals(
			'<test mathvariant="bold" data-mjx-texclass="texClass1"></test>',
			(string)$mbase
		);
	}

	public function testChildren() {
		$mbase1 = new MMLbase( 'test1', 'texClass1', [ 'mathvariant' => 'bold' ] );
		$mbase2 = new MMLbase( 'test2', 'texClass2', [ 'mathvariant' => 'bold' ] );
		$mbase3 = new MMLbase( 'test3', 'texClass3', [ 'mathvariant' => 'bold' ] );
		$base = new MMLbase( 'test4', 'texClass4', [ 'mathvariant' => 'bold' ],
			$mbase1, $mbase2, $mbase3 );
		$this->assertEquals( [ $mbase1, $mbase2, $mbase3 ], $base->getChildren() );
	}
}
