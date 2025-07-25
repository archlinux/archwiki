<?php

namespace Cite\Tests\Unit;

use Cite\ReferenceStack;
use Cite\ReferenceStackItem;
use Cite\Tests\TestUtils;
use LogicException;
use MediaWiki\Parser\StripState;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \Cite\ReferenceStack
 * @license GPL-2.0-or-later
 */
class ReferenceStackTest extends \MediaWikiUnitTestCase {

	public function testPushInvalidRef() {
		$stack = $this->newStack();

		$stack->pushInvalidRef();

		$this->assertSame( [ false ], $stack->refCallStack );
	}

	/**
	 * @dataProvider providePushRef
	 */
	public function testPushRefs(
		array $refs,
		array $expectedOutputs,
		array $finalRefs,
		array $finalCallStack
	) {
		$mockStripState = $this->createMock( StripState::class );
		$mockStripState->method( 'unstripBoth' )->willReturnArgument( 0 );
		$stack = $this->newStack();

		for ( $i = 0; $i < count( $refs ); $i++ ) {
			$result = $stack->pushRef(
				$mockStripState,
				...$refs[$i]
			);

			$this->assertArrayHasKey( $i, $expectedOutputs,
				'Bad test, not enough expected outputs in fixture.' );

			$expectedRef = TestUtils::refFromArray( $expectedOutputs[$i] );
			if ( $result ) {
				// Not much extra value in testing this, just skip it
				$result->hasMainRef = null;
			}
			$this->assertEquals( $expectedRef, $result );
		}

		$finalRefs = TestUtils::refGroupsFromArray( $finalRefs );
		$this->assertEquals( $finalRefs, $stack->refs );
		$this->assertSameSize( $finalCallStack, $stack->refCallStack );
		foreach ( $stack->refCallStack as $i => $call ) {
			/** @var ReferenceStackItem $ref */
			$ref = $call[1];
			$this->assertSame(
				$finalCallStack[$i],
				// Convert into scalar values compatible with the provider
				[ $call[0], $ref->group, $ref->name ?: $ref->globalId, $call[2], $call[3] ]
			);
		}
	}

	public static function providePushRef() {
		return [
			'Anonymous ref in default group' => [
				'refs' => [
					[ 'text', [], '', null, null, 'rtl', null ]
				],
				'expectedOutputs' => [
					[
						'count' => 1,
						'dir' => 'rtl',
						'globalId' => 1,
						'group' => '',
						'name' => null,
						'text' => 'text',
						'numberInGroup' => 1,
					]
				],
				'finalRefs' => [
					'' => [
						1 => [
							'count' => 1,
							'dir' => 'rtl',
							'globalId' => 1,
							'group' => '',
							'name' => null,
							'text' => 'text',
							'numberInGroup' => 1,
						]
					]
				],
				'finalCallStack' => [
					[ 'new', '', 1, 'text', [] ],
				]
			],
			'Anonymous ref in named group' => [
				'refs' => [
					[ 'text', [], 'foo', null, null, 'rtl', null ]
				],
				'expectedOutputs' => [
					[
						'count' => 1,
						'dir' => 'rtl',
						'globalId' => 1,
						'group' => 'foo',
						'name' => null,
						'text' => 'text',
						'numberInGroup' => 1,
					]
				],
				'finalRefs' => [
					'foo' => [
						1 => [
							'count' => 1,
							'dir' => 'rtl',
							'globalId' => 1,
							'group' => 'foo',
							'name' => null,
							'text' => 'text',
							'numberInGroup' => 1,
						]
					]
				],
				'finalCallStack' => [
					[ 'new', 'foo', 1, 'text', [] ],
				]
			],
			'Ref with text' => [
				'refs' => [
					[ 'text', [], 'foo', null, null, 'rtl', null ]
				],
				'expectedOutputs' => [
					[
						'count' => 1,
						'dir' => 'rtl',
						'globalId' => 1,
						'group' => 'foo',
						'name' => null,
						'text' => 'text',
						'numberInGroup' => 1,
					]
				],
				'finalRefs' => [
					'foo' => [
						1 => [
							'count' => 1,
							'dir' => 'rtl',
							'globalId' => 1,
							'group' => 'foo',
							'name' => null,
							'text' => 'text',
							'numberInGroup' => 1,
						]
					]
				],
				'finalCallStack' => [
					[ 'new', 'foo', 1, 'text', [] ],
				]
			],
			'Named ref with text' => [
				'refs' => [
					[ 'text', [], 'foo', 'name', null, 'rtl', null ]
				],
				'expectedOutputs' => [
					[
						'count' => 1,
						'dir' => 'rtl',
						'globalId' => 1,
						'group' => 'foo',
						'name' => 'name',
						'text' => 'text',
						'numberInGroup' => 1,
					],
				],
				'finalRefs' => [
					'foo' => [
						'name' => [
							'count' => 1,
							'dir' => 'rtl',
							'globalId' => 1,
							'group' => 'foo',
							'name' => 'name',
							'text' => 'text',
							'numberInGroup' => 1,
						]
					]
				],
				'finalCallStack' => [
					[ 'new', 'foo', 'name', 'text', [] ],
				]
			],
			'Follow after base' => [
				'refs' => [
					[ 'text-a', [], 'foo', 'a', null, 'rtl', null ],
					[ 'text-b', [], 'foo', 'b', 'a', 'rtl', null ]
				],
				'expectedOutputs' => [
					[
						'count' => 1,
						'dir' => 'rtl',
						'globalId' => 1,
						'group' => 'foo',
						'name' => 'a',
						'text' => 'text-a',
						'numberInGroup' => 1,
					],
					null
				],
				'finalRefs' => [
					'foo' => [
						'a' => [
							'count' => 1,
							'dir' => 'rtl',
							'globalId' => 1,
							'group' => 'foo',
							'name' => 'a',
							'text' => 'text-a text-b',
							'numberInGroup' => 1,
						]
					]
				],
				'finalCallStack' => [
					[ 'new', 'foo', 'a', 'text-a', [] ],
				]
			],
			'Follow with no base' => [
				'refs' => [
					[ 'text', [], 'foo', null, 'a', 'rtl', null ]
				],
				'expectedOutputs' => [
					null
				],
				'finalRefs' => [
					'foo' => [
						1 => [
							'count' => 1,
							'dir' => 'rtl',
							'globalId' => 1,
							'group' => 'foo',
							'name' => null,
							'text' => 'text',
							'follow' => 'a',
						]
					]
				],
				'finalCallStack' => [
					[ 'new', 'foo', 1, 'text', [] ],
				]
			],
			'Follow pointing to later ref' => [
				'refs' => [
					[ 'text-a', [], 'foo', 'a', null, 'rtl', null ],
					[ 'text-b', [], 'foo', null, 'c', 'rtl', null ],
					[ 'text-c', [], 'foo', 'c', null, 'rtl', null ]
				],
				'expectedOutputs' => [
					[
						'count' => 1,
						'dir' => 'rtl',
						'globalId' => 1,
						'group' => 'foo',
						'name' => 'a',
						'text' => 'text-a',
						'numberInGroup' => 1,
					],
					null,
					[
						'count' => 1,
						'dir' => 'rtl',
						'globalId' => 3,
						'group' => 'foo',
						'name' => 'c',
						'text' => 'text-c',
						'numberInGroup' => 2,
					]
				],
				'finalRefs' => [
					'foo' => [
						'a' => [
							'count' => 1,
							'dir' => 'rtl',
							'globalId' => 1,
							'group' => 'foo',
							'name' => 'a',
							'text' => 'text-a',
							'numberInGroup' => 1,
						],
						2 => [
							'count' => 1,
							'dir' => 'rtl',
							'globalId' => 2,
							'group' => 'foo',
							'name' => null,
							'text' => 'text-b',
							'follow' => 'c',
						],
						'c' => [
							'count' => 1,
							'dir' => 'rtl',
							'globalId' => 3,
							'group' => 'foo',
							'name' => 'c',
							'text' => 'text-c',
							'numberInGroup' => 2,
						]
					]
				],
				'finalCallStack' => [
					[ 'new', 'foo', 'a', 'text-a', [] ],
					[ 'new', 'foo', 2, 'text-b', [] ],
					[ 'new', 'foo', 'c', 'text-c', [] ],
				]
			],
			'Repeated ref, text in first tag' => [
				'refs' => [
					[ 'text', [], 'foo', 'a', null, 'rtl', null ],
					[ null, [], 'foo', 'a', null, 'rtl', null ]
				],
				'expectedOutputs' => [
					[
						'count' => 1,
						'dir' => 'rtl',
						'globalId' => 1,
						'group' => 'foo',
						'name' => 'a',
						'text' => 'text',
						'numberInGroup' => 1,
					],
					[
						'count' => 2,
						'dir' => 'rtl',
						'globalId' => 1,
						'group' => 'foo',
						'name' => 'a',
						'text' => 'text',
						'numberInGroup' => 1,
					],
				],
				'finalRefs' => [
					'foo' => [
						'a' => [
							'count' => 2,
							'dir' => 'rtl',
							'globalId' => 1,
							'group' => 'foo',
							'name' => 'a',
							'text' => 'text',
							'numberInGroup' => 1,
						]
					]
				],
				'finalCallStack' => [
					[ 'new', 'foo', 'a', 'text', [] ],
					[ 'increment', 'foo', 'a', null, [] ],
				]
			],
			'Repeated ref, text in second tag' => [
				'refs' => [
					[ null, [], 'foo', 'a', null, 'rtl', null ],
					[ 'text', [], 'foo', 'a', null, 'rtl', null ]
				],
				'expectedOutputs' => [
					[
						'count' => 1,
						'dir' => 'rtl',
						'globalId' => 1,
						'group' => 'foo',
						'name' => 'a',
						'text' => null,
						'numberInGroup' => 1,
					],
					[
						'count' => 2,
						'dir' => 'rtl',
						'globalId' => 1,
						'group' => 'foo',
						'name' => 'a',
						'text' => 'text',
						'numberInGroup' => 1,
					]
				],
				'finalRefs' => [
					'foo' => [
						'a' => [
							'count' => 2,
							'dir' => 'rtl',
							'globalId' => 1,
							'group' => 'foo',
							'name' => 'a',
							'text' => 'text',
							'numberInGroup' => 1,
						]
					]
				],
				'finalCallStack' => [
					[ 'new', 'foo', 'a', null, [] ],
					[ 'increment', 'foo', 'a', 'text', [] ],
				]
			],
			'Repeated ref, mismatched text' => [
				'refs' => [
					[ 'text-1', [], 'foo', 'a', null, 'rtl', null ],
					[ 'text-2', [], 'foo', 'a', null, 'rtl', null ]
				],
				'expectedOutputs' => [
					[
						'count' => 1,
						'dir' => 'rtl',
						'globalId' => 1,
						'group' => 'foo',
						'name' => 'a',
						'text' => 'text-1',
						'numberInGroup' => 1,
					],
					[
						'count' => 2,
						'dir' => 'rtl',
						'globalId' => 1,
						'group' => 'foo',
						'name' => 'a',
						'text' => 'text-1',
						'numberInGroup' => 1,
						'warnings' => [ [ 'cite_error_references_duplicate_key', 'a' ] ],
					]
				],
				'finalRefs' => [
					'foo' => [
						'a' => [
							'count' => 2,
							'dir' => 'rtl',
							'globalId' => 1,
							'group' => 'foo',
							'name' => 'a',
							'text' => 'text-1',
							'numberInGroup' => 1,
							'warnings' => [ [ 'cite_error_references_duplicate_key', 'a' ] ],
						]
					]
				],
				'finalCallStack' => [
					[ 'new', 'foo', 'a', 'text-1', [] ],
					[ 'increment', 'foo', 'a', 'text-2', [] ],
				]
			],
			'Two incomplete follows' => [
				'refs' => [
					[ 'text-a', [], 'foo', 'a', null, 'rtl', null ],
					[ 'text-b', [], 'foo', null, 'd', 'rtl', null ],
					[ 'text-c', [], 'foo', null, 'd', 'rtl', null ],
				],
				'expectedOutputs' => [
					[
						'count' => 1,
						'dir' => 'rtl',
						'globalId' => 1,
						'group' => 'foo',
						'name' => 'a',
						'text' => 'text-a',
						'numberInGroup' => 1,
					],
					null,
					null
				],
				'finalRefs' => [
					'foo' => [
						'a' => [
							'count' => 1,
							'dir' => 'rtl',
							'globalId' => 1,
							'group' => 'foo',
							'name' => 'a',
							'text' => 'text-a',
							'numberInGroup' => 1,
						],
						2 => [
							'count' => 1,
							'dir' => 'rtl',
							'globalId' => 2,
							'group' => 'foo',
							'name' => null,
							'text' => 'text-b',
							'follow' => 'd',
						],
						3 => [
							'count' => 1,
							'dir' => 'rtl',
							'globalId' => 3,
							'group' => 'foo',
							'name' => null,
							'text' => 'text-c',
							'follow' => 'd',
						],
					]
				],
				'finalCallStack' => [
					[ 'new', 'foo', 'a', 'text-a', [] ],
					[ 'new', 'foo', 2, 'text-b', [] ],
					[ 'new', 'foo', 3, 'text-c', [] ],
				]
			],
			'One subreference with inline parent' => [
				'refs' => [
					[ 'text-parent', [], 'foo', 'a', null, 'rtl', 'text-details' ],
				],
				'expectedOutputs' => [
					[
						'count' => 1,
						'dir' => 'rtl',
						'globalId' => 2,
						'group' => 'foo',
						'text' => 'text-details',
						'numberInGroup' => 1,
						'subrefCount' => 0,
						'subrefIndex' => 1,
					],
				],
				'finalRefs' => [
					'foo' => [
						'a' => [
							'count' => 0,
							'dir' => 'rtl',
							'globalId' => 1,
							'group' => 'foo',
							'name' => 'a',
							'text' => 'text-parent',
							'numberInGroup' => 1,
							'subrefCount' => 1,
						],
						2 => [
							'count' => 1,
							'dir' => 'rtl',
							'globalId' => 2,
							'group' => 'foo',
							'text' => 'text-details',
							'numberInGroup' => 1,
							'subrefCount' => 0,
							'subrefIndex' => 1,
						],
					]
				],
				'finalCallStack' => [
					[ 'new', 'foo', 2, 'text-parent', [] ],
				]
			],
		];
	}

	/**
	 * @dataProvider provideRollbackRefs
	 */
	public function testRollbackRefs(
		array $initialCallStack,
		array $initialRefs,
		int $rollbackCount,
		$expectedResult,
		array $expectedRefs = []
	) {
		$initialRefs = TestUtils::refGroupsFromArray( $initialRefs );
		foreach ( $initialCallStack as &$call ) {
			if ( $call ) {
				// Convert scalar values from the provider into the actual internal format
				$call = [ $call[0], $initialRefs[$call[1]][$call[2]], $call[3], $call[4] ];
			}
		}

		$stack = $this->newStack();
		$stack->refCallStack = $initialCallStack;
		$stack->refs = $initialRefs;

		if ( is_string( $expectedResult ) ) {
			$this->expectException( LogicException::class );
			$this->expectExceptionMessage( $expectedResult );
		}
		$redoStack = $stack->rollbackRefs( $rollbackCount );
		$this->assertSame( $expectedResult, $redoStack );
		$expectedRefs = TestUtils::refGroupsFromArray( $expectedRefs );
		$this->assertEquals( $expectedRefs, $stack->refs );
	}

	public static function provideRollbackRefs() {
		return [
			'Empty stack' => [
				'initialCallStack' => [],
				'initialRefs' => [],
				'rollbackCount' => 0,
				'expectedResult' => [],
				'expectedRefs' => [],
			],
			'Attempt to overflow stack bounds' => [
				'initialCallStack' => [],
				'initialRefs' => [],
				'rollbackCount' => 1,
				'expectedResult' => [],
				'expectedRefs' => [],
			],
			'Skip invalid refs' => [
				'initialCallStack' => [ false ],
				'initialRefs' => [],
				'rollbackCount' => 1,
				'expectedResult' => [],
				'expectedRefs' => [],
			],
			'Find anonymous ref by id' => [
				'initialCallStack' => [
					[ 'new', 'foo', 1, 'text', [] ],
				],
				'initialRefs' => [ 'foo' => [
					1 => [ 'group' => 'foo', 'globalId' => 1 ],
				] ],
				'rollbackCount' => 1,
				'expectedResult' => [
					[ 'text', [] ],
				],
				'expectedRefs' => [],
			],
			'Assign text' => [
				'initialCallStack' => [
					[ 'increment', 'foo', 1, 'text-2', [] ],
				],
				'initialRefs' => [ 'foo' => [
					1 => [
						'count' => 2,
						'text' => 'text-1',
					],
				] ],
				'rollbackCount' => 1,
				'expectedResult' => [
					[ 'text-2', [] ],
				],
				'expectedRefs' => [ 'foo' => [
					1 => [
						'count' => 1,
						'text' => 'text-1',
					],
				] ],
			],
			'Increment' => [
				'initialCallStack' => [
					[ 'increment', 'foo', 1, null, [] ],
				],
				'initialRefs' => [ 'foo' => [
					1 => [
						'count' => 2,
					],
				] ],
				'rollbackCount' => 1,
				'expectedResult' => [
					[ null, [] ],
				],
				'expectedRefs' => [ 'foo' => [
					1 => [
						'count' => 1,
					],
				] ],
			],
		];
	}

	public function testRemovals() {
		$stack = $this->newStack();
		$stack->refs = [ 'group1' => [], 'group2' => [] ];

		$this->assertSame( [], $stack->popGroup( 'group1' ) );
		$this->assertSame( [ 'group2' => [] ], $stack->refs );
	}

	public function testGetGroups() {
		$stack = $this->newStack();
		$stack->refs = [ 'havenot' => [], 'have' => [ [ 'ref etc' ] ] ];

		$this->assertSame( [ 'have' ], $stack->getGroups() );
	}

	public function testHasGroup() {
		$stack = $this->newStack();
		$stack->refs = [ 'present' => [ [ 'ref etc' ] ], 'empty' => [] ];

		$this->assertFalse( $stack->hasGroup( 'absent' ) );
		$this->assertTrue( $stack->hasGroup( 'present' ) );
		$this->assertFalse( $stack->hasGroup( 'empty' ) );
	}

	public function testGetGroupRefs() {
		$stack = $this->newStack();
		$stack->refs = [ 'present' => [ [ 'ref etc' ] ], 'empty' => [] ];

		$this->assertSame( [], $stack->getGroupRefs( 'absent' ) );
		$this->assertSame( [ [ 'ref etc' ] ], $stack->getGroupRefs( 'present' ) );
		$this->assertSame( [], $stack->getGroupRefs( 'empty' ) );
	}

	/**
	 * @return ReferenceStack
	 */
	private function newStack() {
		return TestingAccessWrapper::newFromObject( new ReferenceStack() );
	}

}
