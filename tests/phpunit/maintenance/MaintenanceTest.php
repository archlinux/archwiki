<?php

namespace MediaWiki\Tests\Maintenance;

use Config;
use HashConfig;
use Maintenance;
use MediaWiki\MediaWikiServices;
use PHPUnit\Framework\Assert;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers Maintenance
 */
class MaintenanceTest extends MaintenanceBaseTestCase {

	/**
	 * @inheritDoc
	 */
	protected function getMaintenanceClass() {
		return Maintenance::class;
	}

	/**
	 * @see MaintenanceBaseTestCase::createMaintenance
	 *
	 * Note to extension authors looking for a model to follow: This function
	 * is normally not needed in a maintenance test, it's only overridden here
	 * because Maintenance is abstract.
	 * @inheritDoc
	 */
	protected function createMaintenance() {
		$className = $this->getMaintenanceClass();
		$obj = $this->getMockForAbstractClass( $className );

		return TestingAccessWrapper::newFromObject( $obj );
	}

	// Although the following tests do not seem to be too consistent (compare for
	// example the newlines within the test.*StringString tests, or the
	// test.*Intermittent.* tests), the objective of these tests is not to describe
	// consistent behavior, but rather currently existing behavior.

	/**
	 * @dataProvider provideOutputData
	 */
	public function testOutput( $outputs, $expected, $extraNL ) {
		foreach ( $outputs as $data ) {
			if ( is_array( $data ) ) {
				[ $msg, $channel ] = $data;
			} else {
				$msg = $data;
				$channel = null;
			}
			$this->maintenance->output( $msg, $channel );
		}
		$this->assertOutputPrePostShutdown( $expected, $extraNL );
	}

	public function provideOutputData() {
		return [
			[ [ "" ], "", false ],
			[ [ "foo" ], "foo", false ],
			[ [ "foo", "bar" ], "foobar", false ],
			[ [ "foo\n" ], "foo\n", false ],
			[ [ "foo\n\n" ], "foo\n\n", false ],
			[ [ "foo\nbar" ], "foo\nbar", false ],
			[ [ "foo\nbar\n" ], "foo\nbar\n", false ],
			[ [ "foo\n", "bar\n" ], "foo\nbar\n", false ],
			[ [ "", "foo", "", "\n", "ba", "", "r\n" ], "foo\nbar\n", false ],
			[ [ "", "foo", "", "\nb", "a", "", "r\n" ], "foo\nbar\n", false ],
			[ [ [ "foo", "bazChannel" ] ], "foo", true ],
			[ [ [ "foo\n", "bazChannel" ] ], "foo", true ],

			// If this test fails, note that output takes strings with double line
			// endings (although output's implementation in this situation calls
			// outputChanneled with a string ending in a nl ... which is not allowed
			// according to the documentation of outputChanneled)
			[ [ [ "foo\n\n", "bazChannel" ] ], "foo\n", true ],
			[ [ [ "foo\nbar", "bazChannel" ] ], "foo\nbar", true ],
			[ [ [ "foo\nbar\n", "bazChannel" ] ], "foo\nbar", true ],
			[
				[
					[ "foo\n", "bazChannel" ],
					[ "bar\n", "bazChannel" ],
				],
				"foobar",
				true
			],
			[
				[
					[ "", "bazChannel" ],
					[ "foo", "bazChannel" ],
					[ "", "bazChannel" ],
					[ "\n", "bazChannel" ],
					[ "ba", "bazChannel" ],
					[ "", "bazChannel" ],
					[ "r\n", "bazChannel" ],
				],
				"foobar",
				true
			],
			[
				[
					[ "", "bazChannel" ],
					[ "foo", "bazChannel" ],
					[ "", "bazChannel" ],
					[ "\nb", "bazChannel" ],
					[ "a", "bazChannel" ],
					[ "", "bazChannel" ],
					[ "r\n", "bazChannel" ],
				],
				"foo\nbar",
				true
			],
			[
				[
					[ "foo", "bazChannel" ],
					[ "bar", "bazChannel" ],
					[ "qux", "quuxChannel" ],
					[ "corge", "bazChannel" ],
				],
				"foobar\nqux\ncorge",
				true
			],
			[
				[
					[ "foo", "bazChannel" ],
					[ "bar\n", "bazChannel" ],
					[ "qux\n", "quuxChannel" ],
					[ "corge", "bazChannel" ],
				],
				"foobar\nqux\ncorge",
				true
			],
			[
				[
					[ "foo", null ],
					[ "bar", "bazChannel" ],
					[ "qux", null ],
					[ "quux", "bazChannel" ],
				],
				"foobar\nquxquux",
				true
			],
			[
				[
					[ "foo", "bazChannel" ],
					[ "bar", null ],
					[ "qux", "bazChannel" ],
					[ "quux", null ],
				],
				"foo\nbarqux\nquux",
				false
			],
			[
				[
					[ "foo", 1 ],
					[ "bar", 1.0 ],
				],
				"foo\nbar",
				true
			],
			[ [ "foo", "", "bar" ], "foobar", false ],
			[ [ "foo", false, "bar" ], "foobar", false ],
			[
				[
					[ "qux", "quuxChannel" ],
					"foo",
					false,
					"bar"
				],
				"qux\nfoobar",
				false
			],
			[
				[
					[ "foo", "bazChannel" ],
					[ "", "bazChannel" ],
					[ "bar", "bazChannel" ],
				],
				"foobar",
				true
			],
			[
				[
					[ "foo", "bazChannel" ],
					[ false, "bazChannel" ],
					[ "bar", "bazChannel" ],
				],
				"foobar",
				true
			],
		];
	}

	/**
	 * @dataProvider provideOutputChanneledData
	 */
	public function testOutputChanneled( $outputs, $expected, $extraNL ) {
		foreach ( $outputs as $data ) {
			if ( is_array( $data ) ) {
				[ $msg, $channel ] = $data;
			} else {
				$msg = $data;
				$channel = null;
			}
			$this->maintenance->outputChanneled( $msg, $channel );
		}
		$this->assertOutputPrePostShutdown( $expected, $extraNL );
	}

	public function provideOutputChanneledData() {
		return [
			[ [ "" ], "\n", false ],
			[ [ "foo" ], "foo\n", false ],
			[ [ "foo", "bar" ], "foo\nbar\n", false ],
			[ [ "foo\nbar" ], "foo\nbar\n", false ],
			[ [ "", "foo", "", "\nb", "a", "", "r" ], "\nfoo\n\n\nb\na\n\nr\n", false ],
			[ [ [ "foo", "bazChannel" ] ], "foo", true ],
			[
				[
					[ "foo\nbar", "bazChannel" ]
				],
				"foo\nbar",
				true
			],
			[
				[
					[ "foo", "bazChannel" ],
					[ "bar", "bazChannel" ],
				],
				"foobar",
				true
			],
			[
				[
					[ "", "bazChannel" ],
					[ "foo", "bazChannel" ],
					[ "", "bazChannel" ],
					[ "\nb", "bazChannel" ],
					[ "a", "bazChannel" ],
					[ "", "bazChannel" ],
					[ "r", "bazChannel" ],
				],
				"foo\nbar",
				true
			],
			[
				[
					[ "foo", "bazChannel" ],
					[ "bar", "bazChannel" ],
					[ "qux", "quuxChannel" ],
					[ "corge", "bazChannel" ],
				],
				"foobar\nqux\ncorge",
				true
			],
			[
				[
					[ "foo", "bazChannel" ],
					[ "bar", "bazChannel" ],
					[ "qux", "quuxChannel" ],
					[ "corge", "bazChannel" ],
				],
				"foobar\nqux\ncorge",
				true
			],
			[
				[
					[ "foo", "bazChannel" ],
					[ "bar", null ],
					[ "qux", null ],
					[ "corge", "bazChannel" ],
				],
				"foo\nbar\nqux\ncorge",
				true
			],
			[
				[
					[ "foo", null ],
					[ "bar", "bazChannel" ],
					[ "qux", null ],
					[ "quux", "bazChannel" ],
				],
				"foo\nbar\nqux\nquux",
				true
			],
			[
				[
					[ "foo", "bazChannel" ],
					[ "bar", null ],
					[ "qux", "bazChannel" ],
					[ "quux", null ],
				],
				"foo\nbar\nqux\nquux\n",
				false
			],
			[
				[
					[ "foo", 1 ],
					[ "bar", 1.0 ],
				],
				"foo\nbar",
				true
			],
			[ [ "foo", "", "bar" ], "foo\n\nbar\n", false ],
			[ [ "foo", false, "bar" ], "foo\nbar\n", false ],
		];
	}

	public function testCleanupChanneledClean() {
		$this->maintenance->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "", false );
	}

	public function testCleanupChanneledAfterOutput() {
		$this->maintenance->output( "foo" );
		$this->maintenance->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foo", false );
	}

	public function testCleanupChanneledAfterOutputWNullChannel() {
		$this->maintenance->output( "foo", null );
		$this->maintenance->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foo", false );
	}

	public function testCleanupChanneledAfterOutputWChannel() {
		$this->maintenance->output( "foo", "bazChannel" );
		$this->maintenance->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foo\n", false );
	}

	public function testCleanupChanneledAfterNLOutput() {
		$this->maintenance->output( "foo\n" );
		$this->maintenance->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foo\n", false );
	}

	public function testCleanupChanneledAfterNLOutputWNullChannel() {
		$this->maintenance->output( "foo\n", null );
		$this->maintenance->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foo\n", false );
	}

	public function testCleanupChanneledAfterNLOutputWChannel() {
		$this->maintenance->output( "foo\n", "bazChannel" );
		$this->maintenance->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foo\n", false );
	}

	public function testCleanupChanneledAfterOutputChanneledWOChannel() {
		$this->maintenance->outputChanneled( "foo" );
		$this->maintenance->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foo\n", false );
	}

	public function testCleanupChanneledAfterOutputChanneledWNullChannel() {
		$this->maintenance->outputChanneled( "foo", null );
		$this->maintenance->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foo\n", false );
	}

	public function testCleanupChanneledAfterOutputChanneledWChannel() {
		$this->maintenance->outputChanneled( "foo", "bazChannel" );
		$this->maintenance->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foo\n", false );
	}

	public function testMultipleMaintenanceObjectsInteractionOutput() {
		$m2 = $this->createMaintenance();

		$this->maintenance->output( "foo" );
		$m2->output( "bar" );

		$this->assertEquals( "foobar", $this->getActualOutput(),
			"Output before shutdown simulation (m2)" );
		$m2->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foobar", false );
	}

	public function testMultipleMaintenanceObjectsInteractionOutputWNullChannel() {
		$m2 = $this->createMaintenance();

		$this->maintenance->output( "foo", null );
		$m2->output( "bar", null );

		$this->assertEquals( "foobar", $this->getActualOutput(),
			"Output before shutdown simulation (m2)" );
		$m2->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foobar", false );
	}

	public function testMultipleMaintenanceObjectsInteractionOutputWChannel() {
		$m2 = $this->createMaintenance();

		$this->maintenance->output( "foo", "bazChannel" );
		$m2->output( "bar", "bazChannel" );

		$this->assertEquals( "foobar", $this->getActualOutput(),
			"Output before shutdown simulation (m2)" );
		$m2->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foobar\n", true );
	}

	public function testMultipleMaintenanceObjectsInteractionOutputWNullChannelNL() {
		$m2 = $this->createMaintenance();

		$this->maintenance->output( "foo\n", null );
		$m2->output( "bar\n", null );

		$this->assertEquals( "foo\nbar\n", $this->getActualOutput(),
			"Output before shutdown simulation (m2)" );
		$m2->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foo\nbar\n", false );
	}

	public function testMultipleMaintenanceObjectsInteractionOutputWChannelNL() {
		$m2 = $this->createMaintenance();

		$this->maintenance->output( "foo\n", "bazChannel" );
		$m2->output( "bar\n", "bazChannel" );

		$this->assertEquals( "foobar", $this->getActualOutput(),
			"Output before shutdown simulation (m2)" );
		$m2->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foobar\n", true );
	}

	public function testMultipleMaintenanceObjectsInteractionOutputChanneled() {
		$m2 = $this->createMaintenance();

		$this->maintenance->outputChanneled( "foo" );
		$m2->outputChanneled( "bar" );

		$this->assertEquals( "foo\nbar\n", $this->getActualOutput(),
			"Output before shutdown simulation (m2)" );
		$m2->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foo\nbar\n", false );
	}

	public function testMultipleMaintenanceObjectsInteractionOutputChanneledWNullChannel() {
		$m2 = $this->createMaintenance();

		$this->maintenance->outputChanneled( "foo", null );
		$m2->outputChanneled( "bar", null );

		$this->assertEquals( "foo\nbar\n", $this->getActualOutput(),
			"Output before shutdown simulation (m2)" );
		$m2->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foo\nbar\n", false );
	}

	public function testMultipleMaintenanceObjectsInteractionOutputChanneledWChannel() {
		$m2 = $this->createMaintenance();

		$this->maintenance->outputChanneled( "foo", "bazChannel" );
		$m2->outputChanneled( "bar", "bazChannel" );

		$this->assertEquals( "foobar", $this->getActualOutput(),
			"Output before shutdown simulation (m2)" );
		$m2->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foobar\n", true );
	}

	public function testMultipleMaintenanceObjectsInteractionCleanupChanneledWChannel() {
		$m2 = $this->createMaintenance();

		$this->maintenance->outputChanneled( "foo", "bazChannel" );
		$m2->outputChanneled( "bar", "bazChannel" );

		$this->assertEquals( "foobar", $this->getActualOutput(),
			"Output before first cleanup" );
		$this->maintenance->cleanupChanneled();
		$this->assertEquals( "foobar\n", $this->getActualOutput(),
			"Output after first cleanup" );
		$m2->cleanupChanneled();
		$this->assertEquals( "foobar\n\n", $this->getActualOutput(),
			"Output after second cleanup" );

		$m2->cleanupChanneled();
		$this->assertOutputPrePostShutdown( "foobar\n\n", false );
	}

	/**
	 * @covers Maintenance::getConfig
	 */
	public function testGetConfig() {
		$this->assertInstanceOf( Config::class, $this->maintenance->getConfig() );
		$this->assertSame(
			MediaWikiServices::getInstance()->getMainConfig(),
			$this->maintenance->getConfig()
		);
	}

	/**
	 * @covers Maintenance::setConfig
	 */
	public function testSetConfig() {
		$conf = new HashConfig();
		$this->maintenance->setConfig( $conf );
		$this->assertSame( $conf, $this->maintenance->getConfig() );
	}

	public function testParseWithMultiArgs() {
		// Create an option with an argument allowed to be specified multiple times
		$this->maintenance->addOption( 'multi', 'This option does stuff', false, true, false, true );
		$this->maintenance->loadWithArgv( [ '--multi', 'this1', '--multi', 'this2' ] );

		$this->assertEquals( [ 'this1', 'this2' ], $this->maintenance->getOption( 'multi' ) );
		$this->assertEquals( [ [ 'multi', 'this1' ], [ 'multi', 'this2' ] ],
			$this->maintenance->orderedOptions );
	}

	public function testParseMultiOption() {
		$this->maintenance->addOption( 'multi', 'This option does stuff', false, false, false, true );
		$this->maintenance->loadWithArgv( [ '--multi', '--multi' ] );

		$this->assertEquals( [ 1, 1 ], $this->maintenance->getOption( 'multi' ) );
		$this->assertEquals( [ [ 'multi', 1 ], [ 'multi', 1 ] ], $this->maintenance->orderedOptions );
	}

	public function testParseArgs() {
		$this->maintenance->addOption( 'multi', 'This option doesn\'t actually support multiple occurrences' );
		$this->maintenance->loadWithArgv( [ '--multi=yo' ] );

		$this->assertEquals( 'yo', $this->maintenance->getOption( 'multi' ) );
		$this->assertEquals( [ [ 'multi', 'yo' ] ], $this->maintenance->orderedOptions );
	}

	public function testOptionGetters() {
		$this->assertSame(
			false,
			$this->maintenance->hasOption( 'somearg' ),
			'Non existent option not found'
		);
		$this->assertSame(
			'default',
			$this->maintenance->getOption( 'somearg', 'default' ),
			'Non existent option falls back to default'
		);
		$this->assertSame(
			false,
			$this->maintenance->hasOption( 'somearg' ),
			'Non existent option not found after getting'
		);
		$this->assertSame(
			'newdefault',
			$this->maintenance->getOption( 'somearg', 'newdefault' ),
			'Non existent option falls back to a new default'
		);
	}

	public function testLegacyOptionsAccess() {
		$maintenance = new class () extends Maintenance {
			/**
			 * Tests need to be inside the class in order to have access to protected members.
			 * Setting fields in protected arrays doesn't work via TestingAccessWrapper, triggering
			 * an PHP warning ("Indirect modification of overloaded property").
			 */
			public function execute() {
				$this->setOption( 'test', 'foo' );
				Assert::assertSame( 'foo', $this->getOption( 'test' ) );
				Assert::assertSame( 'foo', $this->mOptions['test'] );

				$this->mOptions['test'] = 'bar';
				Assert::assertSame( 'bar', $this->getOption( 'test' ) );

				$this->setArg( 1, 'foo' );
				Assert::assertSame( 'foo', $this->getArg( 1 ) );
				Assert::assertSame( 'foo', $this->mArgs[1] );

				$this->mArgs[1] = 'bar';
				Assert::assertSame( 'bar', $this->getArg( 1 ) );
			}
		};

		$maintenance->execute();
	}
}
