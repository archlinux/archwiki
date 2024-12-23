<?php

namespace Cite\Tests;

use Cite\Hooks\CiteHooks;
use Cite\ReferencePreviews\ReferencePreviewsGadgetsIntegration;
use MediaWiki\Api\ApiQuerySiteinfo;
use MediaWiki\Config\HashConfig;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWiki\User\User;

/**
 * @covers \Cite\Hooks\CiteHooks
 * @license GPL-2.0-or-later
 */
class CiteHooksTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideBooleans
	 */
	public function testOnResourceLoaderGetConfigVars( bool $enabled ) {
		$vars = [];

		$config = new HashConfig( [
			'CiteVisualEditorOtherGroup' => $enabled,
			'CiteResponsiveReferences' => $enabled,
			'CiteBookReferencing' => $enabled,
		] );

		( new CiteHooks(
			$this->getServiceContainer()->getService( 'Cite.ReferencePreviewsContext' ),
			$this->getServiceContainer()->getService( 'Cite.GadgetsIntegration' ),
			new StaticUserOptionsLookup( [] )
		) )
			->onResourceLoaderGetConfigVars( $vars, 'vector', $config );

		$this->assertSame( [
			'wgCiteVisualEditorOtherGroup' => $enabled,
			'wgCiteResponsiveReferences' => $enabled,
			'wgCiteBookReferencing' => $enabled,
		], $vars );
	}

	/**
	 * @dataProvider provideBooleans
	 */
	public function testOnResourceLoaderRegisterModules( bool $enabled ) {
		$this->markTestSkippedIfExtensionNotLoaded( 'Popups' );

		$resourceLoader = $this->createMock( ResourceLoader::class );
		$resourceLoader->method( 'getConfig' )
			->willReturn( new HashConfig( [ 'CiteReferencePreviews' => $enabled ] ) );
		$resourceLoader->expects( $this->exactly( (int)$enabled ) )
			->method( 'register' );

		( new CiteHooks(
			$this->getServiceContainer()->getService( 'Cite.ReferencePreviewsContext' ),
			$this->getServiceContainer()->getService( 'Cite.GadgetsIntegration' ),
			new StaticUserOptionsLookup( [] )
		) )
			->onResourceLoaderRegisterModules( $resourceLoader );
	}

	/**
	 * @dataProvider provideBooleans
	 */
	public function testOnAPIQuerySiteInfoGeneralInfo( bool $enabled ) {
		$api = $this->createMock( ApiQuerySiteinfo::class );
		$api->expects( $this->once() )
			->method( 'getConfig' )
			->willReturn( new HashConfig( [ 'CiteResponsiveReferences' => $enabled ] ) );

		$data = [];

		( new CiteHooks(
			$this->getServiceContainer()->getService( 'Cite.ReferencePreviewsContext' ),
			$this->getServiceContainer()->getService( 'Cite.GadgetsIntegration' ),
			new StaticUserOptionsLookup( [] )
		) )
			->onAPIQuerySiteInfoGeneralInfo( $api, $data );

		$this->assertSame( [ 'citeresponsivereferences' => $enabled ], $data );
	}

	public static function provideBooleans() {
		yield [ true ];
		yield [ false ];
	}

	public function testOnGetPreferences_noConflicts() {
		$expected = [
			'popups-reference-previews' => [
				'type' => 'toggle',
				'label-message' => 'popups-refpreview-user-preference-label',
				'help-message' => 'popups-prefs-conflicting-gadgets-info',
				'section' => 'rendering/reading'
			]
		];
		$gadgetsIntegrationMock = $this->createMock( ReferencePreviewsGadgetsIntegration::class );
		$prefs = [];
		( new CiteHooks(
			$this->getServiceContainer()->getService( 'Cite.ReferencePreviewsContext' ),
			$gadgetsIntegrationMock,
			new StaticUserOptionsLookup( [] )
		) )
			->onGetPreferences( $this->createMock( User::class ), $prefs );
		$this->assertEquals( $expected, $prefs );
	}

	public function testOnGetPreferences_conflictingGadget() {
		$expected = [
			'popups-reference-previews' => [
				'type' => 'toggle',
				'label-message' => 'popups-refpreview-user-preference-label',
				// 'help-message' => 'popups-prefs-conflicting-gadgets-info',
				'help-message' => [
					'popups-prefs-navpopups-gadget-conflict-info',
					'Special:Preferences#mw-prefsection-gadgets',
				],
				'section' => 'rendering/reading',
				'disabled' => true
			]
		];
		$gadgetsIntegrationMock = $this->createMock( ReferencePreviewsGadgetsIntegration::class );
		$gadgetsIntegrationMock->expects( $this->once() )
			->method( 'isNavPopupsGadgetEnabled' )
			->willReturn( true );
		$prefs = [];
		( new CiteHooks(
			$this->getServiceContainer()->getService( 'Cite.ReferencePreviewsContext' ),
			$gadgetsIntegrationMock,
			new StaticUserOptionsLookup( [] )
		) )
			->onGetPreferences( $this->createMock( User::class ), $prefs );
		$this->assertEquals( $expected, $prefs );
	}

	public function testOnGetPreferences_redundantPreference() {
		$prefs = [
			'popups-reference-previews' => [
				'type' => 'toggle',
				'label-message' => 'from-another-extension',
			]
		];
		$expected = $prefs;
		( new CiteHooks(
			$this->getServiceContainer()->getService( 'Cite.ReferencePreviewsContext' ),
			$this->getServiceContainer()->getService( 'Cite.GadgetsIntegration' ),
			new StaticUserOptionsLookup( [] )
		) )
			->onGetPreferences( $this->createMock( User::class ), $prefs );
		$this->assertEquals( $expected, $prefs );
	}

}
