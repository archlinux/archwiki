<?php

use MediaWiki\Page\PageReferenceValue;
use Wikimedia\ScopedCallback;
use Wikimedia\TestingAccessWrapper;

/**
 * Factory for handling the special page list and generating SpecialPage objects.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @covers \MediaWiki\SpecialPage\SpecialPageFactory
 * @group SpecialPage
 */
class SpecialPageFactoryTest extends MediaWikiIntegrationTestCase {
	private function getFactory() {
		return $this->getServiceContainer()->getSpecialPageFactory();
	}

	public function testHookNotCalledTwice() {
		$count = 0;
		$this->mergeMwGlobalArrayValue( 'wgHooks', [
			'SpecialPage_initList' => [
				static function () use ( &$count ) {
					$count++;
				}
		] ] );
		$spf = $this->getServiceContainer()->getSpecialPageFactory();
		$spf->getNames();
		$spf->getNames();
		$this->assertSame( 1, $count );
	}

	public function newSpecialAllPages() {
		return new SpecialAllPages();
	}

	public function specialPageProvider() {
		$specialPageTestHelper = new SpecialPageTestHelper();

		return [
			'class name' => [ 'SpecialAllPages', false ],
			'closure' => [ static function () {
				return new SpecialAllPages();
			}, false ],
			'function' => [ [ $this, 'newSpecialAllPages' ], false ],
			'callback string' => [ 'SpecialPageTestHelper::newSpecialAllPages', false ],
			'callback with object' => [
				[ $specialPageTestHelper, 'newSpecialAllPages' ],
				false
			],
			'callback array' => [
				[ 'SpecialPageTestHelper', 'newSpecialAllPages' ],
				false
			],
			'object factory spec' => [
				[ 'class' => SpecialAllPages::class ],
				false
			]
		];
	}

	/**
	 * @covers \MediaWiki\SpecialPage\SpecialPageFactory::getPage
	 * @dataProvider specialPageProvider
	 */
	public function testGetPage( $spec, $shouldReuseInstance ) {
		$this->mergeMwGlobalArrayValue( 'wgSpecialPages', [ 'testdummy' => $spec ] );

		$factory = $this->getFactory();
		$page = $factory->getPage( 'testdummy' );
		$this->assertInstanceOf( SpecialPage::class, $page );

		$page2 = $factory->getPage( 'testdummy' );
		$this->assertEquals( $shouldReuseInstance, $page2 === $page, "Should re-use instance:" );
	}

	/**
	 * @covers \MediaWiki\SpecialPage\SpecialPageFactory::getNames
	 */
	public function testGetNames() {
		$this->mergeMwGlobalArrayValue( 'wgSpecialPages', [ 'testdummy' => SpecialAllPages::class ] );

		$names = $this->getFactory()->getNames();
		$this->assertIsArray( $names );
		$this->assertContains( 'testdummy', $names );
	}

	/**
	 * @covers \MediaWiki\SpecialPage\SpecialPageFactory::resolveAlias
	 */
	public function testResolveAlias() {
		$this->setContentLang( 'de' );

		list( $name, $param ) = $this->getFactory()->resolveAlias( 'Spezialseiten/Foo' );
		$this->assertEquals( 'Specialpages', $name );
		$this->assertEquals( 'Foo', $param );
	}

	/**
	 * @covers \MediaWiki\SpecialPage\SpecialPageFactory::getLocalNameFor
	 */
	public function testGetLocalNameFor() {
		$this->setContentLang( 'de' );

		$name = $this->getFactory()->getLocalNameFor( 'Specialpages', 'Foo' );
		$this->assertEquals( 'Spezialseiten/Foo', $name );
	}

	/**
	 * @covers \MediaWiki\SpecialPage\SpecialPageFactory::getTitleForAlias
	 */
	public function testGetTitleForAlias() {
		$this->setContentLang( 'de' );

		$title = $this->getFactory()->getTitleForAlias( 'Specialpages/Foo' );
		$this->assertEquals( 'Spezialseiten/Foo', $title->getText() );
		$this->assertEquals( NS_SPECIAL, $title->getNamespace() );
	}

	public function provideExecutePath() {
		yield [ 'BlankPage', 'intentionallyblankpage' ];

		$path = new PageReferenceValue( NS_SPECIAL, 'BlankPage', PageReferenceValue::LOCAL );
		yield [ $path, 'intentionallyblankpage' ];
	}

	/**
	 * @dataProvider provideExecutePath
	 * @covers \MediaWiki\SpecialPage\SpecialPageFactory::executePAth
	 */
	public function testExecutePath( $path, $expected ) {
		$this->setContentLang( 'qqx' );

		$context = new RequestContext();
		$context->setRequest( new FauxRequest() );

		$output = new OutputPage( $context );
		$context->setOutput( $output );

		$this->getFactory()->executePath( $path, $context );
		$this->assertStringContainsString( $expected, $output->getHTML() );
	}

	/**
	 * @dataProvider provideTestConflictResolution
	 */
	public function testConflictResolution(
		$test, $aliasesList, $alias, $expectedName, $expectedAlias, $expectWarnings
	) {
		$lang = clone $this->getServiceContainer()->getContentLanguage();
		$wrappedLang = TestingAccessWrapper::newFromObject( $lang );
		$wrappedLang->mExtendedSpecialPageAliases = $aliasesList;
		$this->setMwGlobals( 'wgSpecialPages',
			array_combine( array_keys( $aliasesList ), array_keys( $aliasesList ) )
		);
		$this->setContentLang( $lang );

		// Catch the warnings we expect to be raised
		$warnings = [];
		$this->setMwGlobals( 'wgDevelopmentWarnings', true );
		set_error_handler( static function ( $errno, $errstr ) use ( &$warnings ) {
			if ( preg_match( '/First alias \'[^\']*\' for .*/', $errstr ) ||
				preg_match( '/Did not find a usable alias for special page .*/', $errstr )
			) {
				$warnings[] = $errstr;
				return true;
			}
			return false;
		} );
		$reset = new ScopedCallback( 'restore_error_handler' );

		list( $name, /*...*/ ) = $this->getFactory()->resolveAlias( $alias );
		$this->assertEquals( $expectedName, $name, "$test: Alias to name" );
		$result = $this->getFactory()->getLocalNameFor( $name );
		$this->assertEquals( $expectedAlias, $result, "$test: Alias to name to alias" );

		$gotWarnings = count( $warnings );
		if ( $gotWarnings !== $expectWarnings ) {
			$this->fail( "Expected $expectWarnings warning(s), but got $gotWarnings:\n" .
				implode( "\n", $warnings )
			);
		}
	}

	/**
	 * @dataProvider provideTestConflictResolution
	 */
	public function testConflictResolutionReversed(
		$test, $aliasesList, $alias, $expectedName, $expectedAlias, $expectWarnings
	) {
		// Make sure order doesn't matter by reversing the list
		$aliasesList = array_reverse( $aliasesList );
		return $this->testConflictResolution(
			$test, $aliasesList, $alias, $expectedName, $expectedAlias, $expectWarnings
		);
	}

	public function provideTestConflictResolution() {
		return [
			[
				'Canonical name wins',
				[ 'Foo' => [ 'Foo', 'Bar' ], 'Baz' => [ 'Foo', 'BazPage', 'Baz2' ] ],
				'Foo',
				'Foo',
				'Foo',
				1,
			],

			[
				'Doesn\'t redirect to a different special page\'s canonical name',
				[ 'Foo' => [ 'Foo', 'Bar' ], 'Baz' => [ 'Foo', 'BazPage', 'Baz2' ] ],
				'Baz',
				'Baz',
				'BazPage',
				1,
			],

			[
				'Canonical name wins even if not aliased',
				[ 'Foo' => [ 'FooPage' ], 'Baz' => [ 'Foo', 'BazPage', 'Baz2' ] ],
				'Foo',
				'Foo',
				'FooPage',
				1,
			],

			[
				'Doesn\'t redirect to a different special page\'s canonical name even if not aliased',
				[ 'Foo' => [ 'FooPage' ], 'Baz' => [ 'Foo', 'BazPage', 'Baz2' ] ],
				'Baz',
				'Baz',
				'BazPage',
				1,
			],

			[
				'First local name beats non-first',
				[ 'First' => [ 'Foo' ], 'NonFirst' => [ 'Bar', 'Foo' ] ],
				'Foo',
				'First',
				'Foo',
				0,
			],

			[
				'Doesn\'t redirect to a different special page\'s first alias',
				[
					'Foo' => [ 'Foo' ],
					'First' => [ 'Bar' ],
					'Baz' => [ 'Foo', 'Bar', 'BazPage', 'Baz2' ]
				],
				'Baz',
				'Baz',
				'BazPage',
				1,
			],

			[
				'Doesn\'t redirect wrong even if all aliases conflict',
				[
					'Foo' => [ 'Foo' ],
					'First' => [ 'Bar' ],
					'Baz' => [ 'Foo', 'Bar' ]
				],
				'Baz',
				'Baz',
				'Baz',
				2,
			],

		];
	}

	public function testGetAliasListRecursion() {
		$called = false;
		$this->mergeMwGlobalArrayValue( 'wgHooks', [
			'SpecialPage_initList' => [
				function () use ( &$called ) {
					$this->getServiceContainer()
						->getSpecialPageFactory()
						->getLocalNameFor( 'Specialpages' );
					$called = true;
				}
			],
		] );
		$this->getFactory()->getLocalNameFor( 'Specialpages' );
		$this->assertTrue( $called, 'Recursive call succeeded' );
	}

	/**
	 * @covers \MediaWiki\SpecialPage\SpecialPageFactory::getPage
	 */
	public function testSpecialPageCreationThatRequiresService() {
		$type = null;

		$this->setMwGlobals( 'wgSpecialPages',
			[ 'TestPage' => [
				'factory' => static function ( $spf ) use ( &$type ) {
					$type = get_class( $spf );

					return new class() extends SpecialPage {

					};
				},
				'services' => [
					'SpecialPageFactory'
				]
			] ]
		);

		$this->getFactory()->getPage( 'TestPage' );

		$this->assertEquals( \MediaWiki\SpecialPage\SpecialPageFactory::class, $type );
	}
}
