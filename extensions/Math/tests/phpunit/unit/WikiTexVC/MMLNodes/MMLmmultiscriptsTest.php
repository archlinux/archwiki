<?php

namespace phpunit\unit\WikiTexVC\MMLNodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmmultiscripts;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmprescripts;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmmultiscripts
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MMLmmultiscriptsTest extends MediaWikiUnitTestCase {
	private MMLbase $base;
	private MMLbase $postSub;
	private MMLbase $postSuper;
	private MMLbase $preSub;
	private MMLbase $preSuper;

	public function testConstructor() {
		$mmultiscripts = new MMLmmultiscripts( '', [ 'mathvariant' => 'bold' ] );
		$this->assertEquals( 'mmultiscripts', $mmultiscripts->getName() );
		$this->assertEquals( [ 'mathvariant' => 'bold' ], $mmultiscripts->getAttributes() );
	}

	protected function setUp(): void {
		$this->base = new MMLbase( 'mi' );
		$this->postSub = new MMLbase( 'msub' );
		$this->postSuper = new MMLbase( 'msup' );
		$this->preSub = new MMLbase( 'msub-pre' );
		$this->preSuper = new MMLbase( 'msup-pre' );
	}

	public function testBaseOnly() {
		$mm = MMLmmultiscripts::newSubtree( $this->base );
		$this->assertChildrenStructure(
			[ $this->base, null, null ],
			$mm->getChildren()
		);
	}

	public function testFullPostscripts() {
		$mm = MMLmmultiscripts::newSubtree(
			$this->base,
			$this->postSub,
			$this->postSuper
		);
		$this->assertChildrenStructure(
			[ $this->base, $this->postSub, $this->postSuper ],
			$mm->getChildren()
		);
	}

	public function testPartialPostscripts() {
		$mm = MMLmmultiscripts::newSubtree(
			$this->base,
			$this->postSub,
			null
		);
		$this->assertChildrenStructure(
			[ $this->base, $this->postSub, null ],
			$mm->getChildren()
		);
	}

	public function testPrescriptsOnly() {
		$mm = MMLmmultiscripts::newSubtree(
			$this->base,
			null,
			null,
			$this->preSub,
			$this->preSuper
		);
		$expected = [
			$this->base,
			null,
			null,
			new MMLmprescripts(),
			$this->preSub,
			$this->preSuper
		];
		$this->assertChildrenStructure( $expected, $mm->getChildren() );
	}

	public function testMixedPrescripts() {
		$mm = MMLmmultiscripts::newSubtree(
			$this->base,
			$this->postSub,
			null,
			null,
			$this->preSuper
		);
		$expected = [
			$this->base,
			$this->postSub,
			null,
			new MMLmprescripts(),
			null,
			$this->preSuper
		];
		$this->assertChildrenStructure( $expected, $mm->getChildren() );
	}

	public function testAllScriptsCombination() {
		$mm = MMLmmultiscripts::newSubtree(
			$this->base,
			$this->postSub,
			$this->postSuper,
			$this->preSub,
			$this->preSuper
		);
		$expected = [
			$this->base,
			$this->postSub,
			$this->postSuper,
			new MMLmprescripts(),
			$this->preSub,
			$this->preSuper
		];
		$this->assertChildrenStructure( $expected, $mm->getChildren() );
	}

	private function assertChildrenStructure( array $expected, array $actual ): void {
		$this->assertSameSize( $expected, $actual, 'Children count mismatch' );

		foreach ( $expected as $index => $expectedChild ) {
			if ( $expectedChild instanceof MMLbase ) {
				$this->assertInstanceOf(
					MMLbase::class,
					$actual[$index],
					"Child at index $index should be MMLbase"
				);
				$this->assertSame(
					$expectedChild->getName(),
					$actual[$index]->getName(),
					"Child at index $index name mismatch"
				);
			} else {
				$this->assertNull(
					$actual[$index],
					"Child at index $index should be null"
				);
			}
		}

		// Verify mprescripts position when present
		$mprescriptsIndex = array_search( MMLmprescripts::class, array_map(
			static fn ( $el ) => $el ? get_class( $el ) : null,
			$actual
		) );

		if ( $mprescriptsIndex !== false ) {
			$this->assertGreaterThan(
				2,
				$mprescriptsIndex,
				'Mprescripts should come after postscripts'
			);
		}
	}
}
