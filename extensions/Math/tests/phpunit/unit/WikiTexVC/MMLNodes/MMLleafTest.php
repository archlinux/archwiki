<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLnodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLleaf;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLleaf
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLleafTest extends MediaWikiUnitTestCase {
	private function createLeafMock(
		string $name = 'mi',
		string $texclass = '',
		array $attributes = [],
		string $text = ''
	): MMLleaf {
		return $this->getMockForAbstractClass(
			MMLleaf::class,
			[ $name, $texclass, $attributes, $text ]
		);
	}

	public function testConstructorInitialization() {
		$leaf = $this->createLeafMock( 'test', 'tex-class', [ 'attr' => 'value' ], 'text' );

		$this->assertEquals( 'test', $leaf->getName() );
		$this->assertEquals( 'text', $leaf->getText() );
	}

	public function testSetTextValidValue() {
		$leaf = $this->createLeafMock();
		$leaf->setText( 'new text' );

		$this->assertEquals( 'new text', $leaf->getText() );
	}

	public function testSetTextReturnsSelf() {
		$leaf = $this->createLeafMock();
		$result = $leaf->setText( 'new text' );

		$this->assertSame( $leaf, $result );
	}

	public function testIsEmpty() {
		$leaf = $this->createLeafMock( 'test', 'tex-class', [ 'attr' => 'value' ], 'text' );
		$this->assertFalse( $leaf->isEmpty() );
	}
}
