<?php

use MediaWiki\Extension\Gadgets\GadgetRepo;
use MediaWiki\Extension\Gadgets\Hooks as GadgetHooks;
use MediaWiki\Extension\Gadgets\MediaWikiGadgetsDefinitionRepo;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Gadgets
 * @group Database
 */
class GadgetHooksTest extends MediaWikiIntegrationTestCase {
	public function tearDown(): void {
		GadgetRepo::setSingleton();
		parent::tearDown();
	}

	/**
	 * @covers \MediaWiki\Extension\Gadgets\Gadget
	 * @covers \MediaWiki\Extension\Gadgets\Hooks::onGetPreferences
	 * @covers \MediaWiki\Extension\Gadgets\GadgetRepo
	 * @covers \MediaWiki\Extension\Gadgets\MediaWikiGadgetsDefinitionRepo
	 */
	public function testPreferences() {
		$prefs = [];
		$testRightAllowed = 'gadget-test-right-allowed';
		$testRightNotAllowed = 'gadget-test-right-notallowed';
		$repo = TestingAccessWrapper::newFromObject( new MediaWikiGadgetsDefinitionRepo() );
		// Force usage of a MediaWikiGadgetsDefinitionRepo
		GadgetRepo::setSingleton( $repo );

		$gadgetsDef = <<<EOT
* foo | foo.js
==keep-section1==
* bar| bar.js
==remove-section==
* baz [rights=$testRightNotAllowed] |baz.js
==keep-section2==
* quux [rights=$testRightAllowed] | quux.js
EOT;

		/** @var MediaWikiGadgetsDefinitionRepo $repo */
		$gadgets = $repo->fetchStructuredList( $gadgetsDef );
		$this->assertGreaterThanOrEqual( 2, count( $gadgets ), "Gadget list parsed" );

		$repo->definitions = $gadgets;

		$user = $this->createMock( User::class );
		$user->method( 'isAllowedAll' )
			->willReturnCallback( static function ( ...$rights ) use ( $testRightNotAllowed ): bool {
				return !in_array( $testRightNotAllowed, $rights, true );
			} );
		( new GadgetHooks() )->onGetPreferences( $user, $prefs );

		$this->assertEquals( 'check', $prefs['gadget-bar']['type'] );
		$this->assertEquals( 'api', $prefs['gadget-baz']['type'],
			'Must not show unavailable gadgets' );
		$this->assertEquals( 'gadgets/gadget-section-keep-section2', $prefs['gadget-quux']['section'] );
	}
}
