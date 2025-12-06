<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLnodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Tag;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Variants;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLDomVisitor;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\VisitorFactory;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase
 */
class BaseTest extends MediaWikiUnitTestCase {

	public function testAttributes() {
		$mbase = new MMLbase(
			'test',
			'texClass1',
			[ TAG::CLASSTAG => 'texClass2', 'a' => 'b' ] );
		$visitorFactory = new VisitorFactory();
		$mbase->setVisitorFactory( $visitorFactory );
		$this->assertStringContainsString(
			'<test data-mjx-texclass="texClass1" a="b">',
			$mbase );
	}

	public function testName() {
		$this->assertEquals(
			'test',
			( new MMLbase( 'test' ) )->getName()
		);
	}

	public function testgetAttributes() {
		$mbase = new MMLbase( 'test', 'texClass1', [ 'mathvariant' => Variants::BOLD ] );
		$this->assertEquals(
			[ 'mathvariant' => Variants::BOLD, 'data-mjx-texclass' => 'texClass1' ],
			$mbase->getAttributes()
		);
	}

	public function testAccept() {
		$visitor = new MMLDomVisitor();
		$mbase = new MMLbase( 'test', 'texClass1', [ 'mathvariant' => Variants::BOLD ] );
		$mbase->accept( $visitor );
		$this->assertEquals(
			'<test mathvariant="bold" data-mjx-texclass="texClass1"></test>',
			$visitor->getHTML()
		);
	}

	public function testString() {
		$mbase = new MMLbase( 'test', 'texClass1', [ 'mathvariant' => Variants::BOLD ] );
		$visitorFactory = new VisitorFactory();
		$mbase->setVisitorFactory( $visitorFactory );
		$this->assertEquals(
			'<test mathvariant="bold" data-mjx-texclass="texClass1"></test>',
			(string)$mbase
		);
	}

	public function testChildren() {
		$mbase1 = new MMLbase( 'test1', 'texClass1', [ 'mathvariant' => Variants::BOLD ] );
		$mbase2 = new MMLbase( 'test2', 'texClass2', [ 'mathvariant' => Variants::BOLD ] );
		$mbase3 = new MMLbase( 'test3', 'texClass3', [ 'mathvariant' => Variants::BOLD ] );
		$base = new MMLbase( 'test4', 'texClass4', [ 'mathvariant' => Variants::BOLD ],
			$mbase1, $mbase2, $mbase3 );
		$this->assertEquals( [ $mbase1, $mbase2, $mbase3 ], $base->getChildren() );
	}

	public function testAddChildren() {
		$mbase1 = new MMLbase( 'test1', 'texClass1', [ 'mathvariant' => Variants::BOLD ] );
		$mbase2 = new MMLbase( 'test2', 'texClass2', [ 'mathvariant' => Variants::BOLD ] );
		$mbase3 = new MMLbase( 'test3', 'texClass3', [ 'mathvariant' => Variants::BOLD ] );
		$base = new MMLbase( 'test4', 'texClass4', [ 'mathvariant' => Variants::BOLD ] );
		$base->addChild( $mbase1 );
		$base->addChild( $mbase2, $mbase3 );
		$this->assertEquals( [ $mbase1, $mbase2, $mbase3 ], $base->getChildren() );
	}

	public function testHasChildren() {
		$mbase1 = new MMLbase( 'test1', 'texClass1', [] );
		$base = new MMLbase( 'test2', 'texClass2', [] );
		$base->addChild( $mbase1 );
		$this->assertTrue( $base->hasChildren() );
	}

	public function testHasNoChildren() {
		$base = new MMLbase( 'test', 'texClass', [] );
		$this->assertFalse( $base->hasChildren() );
	}
}
