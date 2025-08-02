<?php
namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLVisitor;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\VisitorFactory;
use MediaWikiUnitTestCase;

/**
 * Test the results of MathFormatter
 *
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\VisitorFactory
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class VisitorFactoryTest extends MediaWikiUnitTestCase {
	public function testCreateVisitor() {
		$factory = new VisitorFactory();
		$this->assertInstanceOf( MMLVisitor::class, $factory->createVisitor() );
	}
}
