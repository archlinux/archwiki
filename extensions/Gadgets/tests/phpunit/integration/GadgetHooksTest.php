<?php

use MediaWiki\Extension\Gadgets\GadgetRepo;
use MediaWiki\Extension\Gadgets\Hooks as GadgetHooks;
use MediaWiki\Extension\Gadgets\MediaWikiGadgetsDefinitionRepo;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Gadgets
 */
class GadgetHooksTest extends MediaWikiIntegrationTestCase {
	/**
	 * @var User
	 */
	protected $user;

	public function setUp(): void {
		global $wgGroupPermissions;

		parent::setUp();

		$wgGroupPermissions['unittesters'] = [
			'test' => true,
		];
		$this->user = $this->getTestUser( [ 'unittesters' ] )->getUser();
	}

	public function tearDown(): void {
		GadgetRepo::setSingleton();
		parent::tearDown();
	}

	/**
	 * @covers \MediaWiki\Extension\Gadgets\Gadget
	 * @covers \MediaWiki\Extension\Gadgets\Hooks::getPreferences
	 * @covers \MediaWiki\Extension\Gadgets\GadgetRepo
	 * @covers \MediaWiki\Extension\Gadgets\MediaWikiGadgetsDefinitionRepo
	 */
	public function testPreferences() {
		$prefs = [];
		$repo = TestingAccessWrapper::newFromObject( new MediaWikiGadgetsDefinitionRepo() );
		// Force usage of a MediaWikiGadgetsDefinitionRepo
		GadgetRepo::setSingleton( $repo );

		/** @var MediaWikiGadgetsDefinitionRepo $repo */
		$gadgets = $repo->fetchStructuredList( '* foo | foo.js
==keep-section1==
* bar| bar.js
==remove-section==
* baz [rights=embezzle] |baz.js
==keep-section2==
* quux [rights=test] | quux.js' );
		$this->assertGreaterThanOrEqual( 2, count( $gadgets ), "Gadget list parsed" );

		$repo->definitionCache = $gadgets;
		GadgetHooks::getPreferences( $this->user, $prefs );

		$this->assertArrayHasKey( 'gadget-bar', $prefs );
		$this->assertArrayNotHasKey( 'gadget-baz', $prefs,
			'Must not show unavailable gadgets' );
		$this->assertEquals( 'gadgets/gadget-section-keep-section2', $prefs['gadget-quux']['section'] );
	}
}
