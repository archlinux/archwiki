<?php

/**
 * @group ResourceLoader
 * @covers ResourceLoaderLessVarFileModule
 */
class ResourceLoaderLessVarFileModuleTest extends ResourceLoaderTestCase {

	public static function providerWrapAndEscapeMessage() {
		return [
			[
				"Foo", '"Foo"',
			],
			[
				"Foo bananas", '"Foo bananas"',
			],
			[
				"Who's that test? Who's that test? It's Jess!",
				'"Who\\\'s that test? Who\\\'s that test? It\\\'s Jess!"',
			],
			[
				'Hello "he" said',
				'"Hello \"he\" said"',
			],
			[
				'boo";-o-link:javascript:alert(1);color:red;content:"',
				'"boo\";-o-link:javascript:alert(1);color:red;content:\""',
			],
			[
				'"jon\'s"',
				'"\"jon\\\'s\""'
			]
		];
	}

	/**
	 * @dataProvider providerWrapAndEscapeMessage
	 * @covers ResourceLoaderLessVarFileModule::wrapAndEscapeMessage
	 */
	public function testEscapeMessage( $msg, $expected ) {
		$method = new ReflectionMethod( ResourceLoaderLessVarFileModule::class, 'wrapAndEscapeMessage' );
		$method->setAccessible( true );
		$this->assertEquals( $expected, $method->invoke( null, $msg ) );
	}

	public function testLessMessagesFound() {
		$context = $this->getResourceLoaderContext( 'qqx' );
		$basePath = __DIR__ . '/../../data/less';
		$module = new ResourceLoaderLessVarFileModule( [
			'localBasePath' => $basePath,
			'styles' => [ 'less-messages.less' ],
			'lessMessages' => [ 'pieday' ],
		] );
		$module->setMessageBlob( '{"pieday":"March 14"}', 'qqx' );

		$styles = $module->getStyles( $context );
		$this->assertStringEqualsFile( $basePath . '/less-messages-exist.css', $styles['all'] );
	}

	public function testLessMessagesFailGraceful() {
		$context = $this->getResourceLoaderContext( 'qqx' );
		$basePath = __DIR__ . '/../../data/less';
		$module = new ResourceLoaderLessVarFileModule( [
			'localBasePath' => $basePath,
			'styles' => [ 'less-messages.less' ],
			'lessMessages' => [ 'pieday' ],
		] );
		$module->setMessageBlob( '{"something":"Else"}', 'qqx' );

		$styles = $module->getStyles( $context );
		$this->assertStringEqualsFile( $basePath . '/less-messages-nonexist.css', $styles['all'] );
	}
}
