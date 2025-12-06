<?php

namespace Cite\Tests;

use Cite\Hooks\CiteHooks;
use Cite\Hooks\ReferencePreviewsHooks;
use Cite\ReferencePreviews\ReferencePreviewsGadgetsIntegration;
use MediaWiki\Api\ApiQuerySiteinfo;
use MediaWiki\Config\HashConfig;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWiki\User\User;

/**
 * @covers \Cite\Hooks\CiteHooks
 * @covers \Cite\Hooks\ReferencePreviewsHooks
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
			'CiteSubReferencing' => $enabled,
		] );

		( new CiteHooks(
			new StaticUserOptionsLookup( [] )
		) )
			->onResourceLoaderGetConfigVars( $vars, 'vector', $config );

		$this->assertSame( [
			'wgCiteVisualEditorOtherGroup' => $enabled,
			'wgCiteResponsiveReferences' => $enabled,
			'wgCiteSubReferencing' => $enabled,
		], $vars );
	}

	/**
	 * @dataProvider provideBooleans
	 */
	public function testOnResourceLoaderRegisterModules( bool $enabled ) {
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )->willReturn( $enabled );

		$rlModules = [];

		$resourceLoader = $this->createMock( ResourceLoader::class );
		$resourceLoader->method( 'getConfig' )
			->willReturn( new HashConfig( [ 'CiteReferencePreviews' => $enabled ] ) );
		$resourceLoader->method( 'register' )
			 ->willReturnCallback( static function ( array $modules ) use ( &$rlModules ) {
				 $rlModules = array_merge( $rlModules, $modules );
			 } );

		( new ReferencePreviewsHooks(
			$extensionRegistry,
			$this->getServiceContainer()->getService( 'Cite.ReferencePreviewsContext' ),
			$this->getServiceContainer()->getService( 'Cite.GadgetsIntegration' ),
		) )
			->onResourceLoaderRegisterModules( $resourceLoader );

		if ( $enabled ) {
			$this->assertArrayHasKey( 'ext.cite.wikiEditor', $rlModules );
		} else {
			$this->assertArrayNotHasKey( 'ext.cite.wikiEditor', $rlModules );
		}
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
		$this->markTestSkippedIfExtensionNotLoaded( 'Popups' );

		$expected = [
			'popups-reference-previews' => [
				'type' => 'toggle',
				'label-message' => 'cite-reference-previews-preference-label',
				'help-message' => 'popups-prefs-conflicting-gadgets-info',
				'section' => 'rendering/reading'
			]
		];
		$gadgetsIntegrationMock = $this->createMock( ReferencePreviewsGadgetsIntegration::class );
		$prefs = [];
		( new ReferencePreviewsHooks(
			ExtensionRegistry::getInstance(),
			$this->getServiceContainer()->getService( 'Cite.ReferencePreviewsContext' ),
			$gadgetsIntegrationMock,
		) )
			->onGetPreferences( $this->createMock( User::class ), $prefs );
		$this->assertEquals( $expected, $prefs );
	}

	public function testOnGetPreferences_conflictingGadget() {
		$this->markTestSkippedIfExtensionNotLoaded( 'Popups' );

		$expected = [
			'popups-reference-previews' => [
				'type' => 'toggle',
				'label-message' => 'cite-reference-previews-preference-label',
				'help-message' => [
					'cite-reference-previews-gadget-conflict-info-navpopups',
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
		( new ReferencePreviewsHooks(
			ExtensionRegistry::getInstance(),
			$this->getServiceContainer()->getService( 'Cite.ReferencePreviewsContext' ),
			$gadgetsIntegrationMock,
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
		( new ReferencePreviewsHooks(
			ExtensionRegistry::getInstance(),
			$this->getServiceContainer()->getService( 'Cite.ReferencePreviewsContext' ),
			$this->getServiceContainer()->getService( 'Cite.GadgetsIntegration' ),
		) )
			->onGetPreferences( $this->createMock( User::class ), $prefs );
		$this->assertEquals( $expected, $prefs );
	}

}
