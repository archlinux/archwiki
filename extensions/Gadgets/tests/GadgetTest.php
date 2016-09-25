<?php
/**
 * @group Gadgets
 */

class GadgetsTest extends MediaWikiTestCase {
	/**
	 * @param string $line
	 * @return Gadget
	 */
	private function create( $line ) {
		$repo = new MediaWikiGadgetsDefinitionRepo();
		$g = $repo->newFromDefinition( $line, 'misc' );
		$this->assertInstanceOf( 'Gadget', $g );

		return $g;
	}

	public function testInvalidLines() {
		$repo = new MediaWikiGadgetsDefinitionRepo();
		$this->assertFalse( $repo->newFromDefinition( '', 'misc' ) );
		$this->assertFalse( $repo->newFromDefinition( '<foo|bar>', 'misc' ) );
	}

	public function testSimpleCases() {
		$g = $this->create( '* foo bar| foo.css|foo.js|foo.bar' );
		$this->assertEquals( 'foo_bar', $g->getName() );
		$this->assertEquals( 'ext.gadget.foo_bar', Gadget::getModuleName( $g->getName() ) );
		$this->assertEquals( array( 'MediaWiki:Gadget-foo.js' ), $g->getScripts() );
		$this->assertEquals( array( 'MediaWiki:Gadget-foo.css' ), $g->getStyles() );
		$this->assertEquals( array( 'MediaWiki:Gadget-foo.js', 'MediaWiki:Gadget-foo.css' ),
			$g->getScriptsAndStyles() );
		$this->assertEquals( array( 'MediaWiki:Gadget-foo.js' ), $g->getLegacyScripts() );
		$this->assertFalse( $g->supportsResourceLoader() );
		$this->assertTrue( $g->hasModule() );
	}

	public function testRLtag() {
		$g = $this->create( '*foo [ResourceLoader]|foo.js|foo.css' );
		$this->assertEquals( 'foo', $g->getName() );
		$this->assertTrue( $g->supportsResourceLoader() );
		$this->assertEquals( 0, count( $g->getLegacyScripts() ) );
	}

	public function testDependencies() {
		$g = $this->create( '* foo[ResourceLoader|dependencies=jquery.ui]|bar.js' );
		$this->assertEquals( array( 'MediaWiki:Gadget-bar.js' ), $g->getScripts() );
		$this->assertTrue( $g->supportsResourceLoader() );
		$this->assertEquals( array( 'jquery.ui' ), $g->getDependencies() );
	}

	public function testPreferences() {
		$prefs = array();
		$repo = TestingAccessWrapper::newFromObject( new MediaWikiGadgetsDefinitionRepo() );
		// Force usage of a MediaWikiGadgetsDefinitionRepo
		GadgetRepo::setSingleton( $repo );

		$gadgets = $repo->fetchStructuredList( '* foo | foo.js
==keep-section1==
* bar| bar.js
==remove-section==
* baz [rights=embezzle] |baz.js
==keep-section2==
* quux [rights=read] | quux.js' );
		$this->assertGreaterThanOrEqual( 2, count( $gadgets ), "Gadget list parsed" );

		$repo->definitionCache = $gadgets;
		$this->assertTrue( GadgetHooks::getPreferences( new User, $prefs ), 'GetPrefences hook should return true' );

		$options = $prefs['gadgets']['options'];
		$this->assertFalse( isset( $options['&lt;gadget-section-remove-section&gt;'] ), 'Must not show empty sections' );
		$this->assertTrue( isset( $options['&lt;gadget-section-keep-section1&gt;'] ) );
		$this->assertTrue( isset( $options['&lt;gadget-section-keep-section2&gt;'] ) );
	}

	public function tearDown() {
		GadgetRepo::setSingleton();
		parent::tearDown();
	}
}
