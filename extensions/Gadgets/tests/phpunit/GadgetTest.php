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

	private function getModule( Gadget $g ) {
		$module = TestingAccessWrapper::newFromObject(
			new GadgetResourceLoaderModule( array( 'id' => null ) )
		);
		$module->gadget = $g;
		return $module;
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

	public function testPosition() {
		$g = $this->create( '* foo[ResourceLoader]|bar.js' );
		$this->assertEquals( 'bottom', $g->getPosition(), 'Default position' );

		$g = $this->create( '* foo[ResourceLoader|top]|bar.js' );
		$this->assertEquals( 'top', $g->getPosition(), 'Position top' );
	}

	public static function provideGetType() {
		return array(
			array(
				'Default (mixed)',
				'* foo[ResourceLoader]|bar.css|bar.js',
				'',
				ResourceLoaderModule::LOAD_GENERAL,
			),
			array(
				'Default (styles only)',
				'* foo[ResourceLoader]|bar.css',
				'styles',
				ResourceLoaderModule::LOAD_STYLES,
			),
			array(
				'Default (scripts only)',
				'* foo[ResourceLoader]|bar.js',
				'general',
				ResourceLoaderModule::LOAD_GENERAL,
			),
			array(
				'Styles type (mixed)',
				'* foo[ResourceLoader|type=styles]|bar.css|bar.js',
				'styles',
				ResourceLoaderModule::LOAD_STYLES,
			),
			array(
				'Styles type (styles only)',
				'* foo[ResourceLoader|type=styles]|bar.css',
				'styles',
				ResourceLoaderModule::LOAD_STYLES,
			),
			array(
				'Styles type (scripts only)',
				'* foo[ResourceLoader|type=styles]|bar.js',
				'styles',
				ResourceLoaderModule::LOAD_STYLES,
			),
			array(
				'General type (mixed)',
				'* foo[ResourceLoader|type=general]|bar.css|bar.js',
				'general',
				ResourceLoaderModule::LOAD_GENERAL,
			),
			array(
				'General type (styles only)',
				'* foo[ResourceLoader|type=general]|bar.css',
				'general',
				ResourceLoaderModule::LOAD_GENERAL,
			),
			array(
				'General type (scripts only)',
				'* foo[ResourceLoader|type=general]|bar.js',
				'general',
				ResourceLoaderModule::LOAD_GENERAL,
			),
		);
	}

	/**
	 * @dataProvider provideGetType
	 */
	public function testType( $message, $definition, $gType, $mType ) {
		$g = $this->create( $definition );
		$this->assertEquals( $gType, $g->getType(), "Gadget: $message" );
		$this->assertEquals( $mType, $this->getModule( $g )->getType(), "Module: $message" );
	}

	public function testIsHidden() {
		$g = $this->create( '* foo[hidden]|bar.js' );
		$this->assertTrue( $g->isHidden() );

		$g = $this->create( '* foo[ResourceLoader|hidden]|bar.js' );
		$this->assertTrue( $g->isHidden() );

		$g = $this->create( '* foo[ResourceLoader]|bar.js' );
		$this->assertFalse( $g->isHidden() );
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
		$this->assertFalse( isset( $options['⧼gadget-section-remove-section⧽'] ), 'Must not show empty sections' );
		$this->assertTrue( isset( $options['⧼gadget-section-keep-section1⧽'] ) );
		$this->assertTrue( isset( $options['⧼gadget-section-keep-section2⧽'] ) );
	}

	public function tearDown() {
		GadgetRepo::setSingleton();
		parent::tearDown();
	}
}
