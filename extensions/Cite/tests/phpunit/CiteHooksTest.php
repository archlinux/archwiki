<?php

namespace Cite\Tests;

use Cite\Hooks\CiteHooks;
use Cite\Hooks\ReferencePreviewsHooks;
use Cite\ReferencePreviews\ReferencePreviewsContext;
use Cite\ReferencePreviews\ReferencePreviewsGadgetsIntegration;
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
	 * @dataProvider provideConfigVars
	 */
	public function testOnResourceLoaderGetConfigVars( $input, bool $expected ) {
		$vars = [];

		$config = new HashConfig( [
			'CiteVisualEditorOtherGroup' => $input,
			'CiteResponsiveReferences' => $input,
			'CiteSubReferencing' => $input,
			'CiteRemoveSyntheticRefsUnsafe' => $input,
		] );

		( new CiteHooks(
			$this->createNoOpMock( ExtensionRegistry::class ),
			new StaticUserOptionsLookup( [] )
		) )
			->onResourceLoaderGetConfigVars( $vars, 'vector', $config );

		$this->assertSame( [
			'wgCiteVisualEditorOtherGroup' => $expected,
			'wgCiteResponsiveReferences' => $expected,
			'wgCiteSubReferencing' => $expected,
			'wgCiteRemoveSyntheticRefsUnsafe' => $expected,
		], $vars );
	}

	public static function provideConfigVars() {
		yield [ true, true ];
		yield [ false, false ];
		yield [ 0, false ];
		yield [ 'FooBar', true ];
	}

	/**
	 * @dataProvider provideBooleans
	 */
	public function testResourceLoaderRegistration_ReferencePreviews( bool $enabled ) {
		$extensionRegistry = $this->createNoOpMock( ExtensionRegistry::class, [ 'isLoaded' ] );
		$extensionRegistry->method( 'isLoaded' )->willReturn( true );

		$resourceLoader = $this->createMock( ResourceLoader::class );
		$resourceLoader->method( 'getConfig' )
			->willReturn( new HashConfig( [ 'CiteReferencePreviews' => $enabled ] ) );
		$resourceLoader->expects( $this->exactly( (int)$enabled ) )
			->method( 'register' )
			->willReturnCallback( function ( array $modules ) {
				$this->assertArrayHasKey( 'ext.cite.referencePreviews', $modules );
			} );

		( new ReferencePreviewsHooks(
			$extensionRegistry,
			$this->createNoOpMock( ReferencePreviewsContext::class ),
			$this->createNoOpMock( ReferencePreviewsGadgetsIntegration::class )
		) )
			->onResourceLoaderRegisterModules( $resourceLoader );
	}

	/**
	 * @dataProvider provideBooleans
	 */
	public function testResourceLoaderRegistration_VisualAndWikiEditor( bool $loaded ) {
		$extensionRegistry = $this->createNoOpMock( ExtensionRegistry::class, [ 'isLoaded' ] );
		$extensionRegistry->method( 'isLoaded' )->willReturn( $loaded );

		$rlModules = [];

		$resourceLoader = $this->createNoOpMock( ResourceLoader::class, [ 'register' ] );
		$resourceLoader->expects( $this->exactly( $loaded ? 2 : 0 ) )
			->method( 'register' )
			->willReturnCallback( static function ( array $modules ) use ( &$rlModules ) {
				$rlModules += $modules;
			} );

		( new CiteHooks(
			$extensionRegistry,
			new StaticUserOptionsLookup( [] )
		) )
			->onResourceLoaderRegisterModules( $resourceLoader );

		if ( $loaded ) {
			$this->assertArrayHasKey( 'ext.cite.wikiEditor', $rlModules );
			$this->assertArrayHasKey( 'ext.cite.visualEditor', $rlModules );
		} else {
			$this->assertSame( [], $rlModules );
		}
	}

	public static function provideBooleans() {
		yield [ true ];
		yield [ false ];
	}

	public function testOnGetPreferences_noConflicts() {
		$extensionRegistry = $this->createNoOpMock( ExtensionRegistry::class, [ 'isLoaded' ] );
		$extensionRegistry->method( 'isLoaded' )->willReturn( true );

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
			$extensionRegistry,
			$this->createNoOpMock( ReferencePreviewsContext::class ),
			$gadgetsIntegrationMock,
		) )
			->onGetPreferences( $this->createNoOpMock( User::class ), $prefs );
		$this->assertEquals( $expected, $prefs );
	}

	public function testOnGetPreferences_conflictingGadget() {
		$extensionRegistry = $this->createNoOpMock( ExtensionRegistry::class, [ 'isLoaded' ] );
		$extensionRegistry->method( 'isLoaded' )->willReturn( true );

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
			$extensionRegistry,
			$this->createNoOpMock( ReferencePreviewsContext::class ),
			$gadgetsIntegrationMock,
		) )
			->onGetPreferences( $this->createNoOpMock( User::class ), $prefs );
		$this->assertEquals( $expected, $prefs );
	}

	public function testOnGetPreferences_redundantPreference() {
		$extensionRegistry = $this->createNoOpMock( ExtensionRegistry::class, [ 'isLoaded' ] );
		$extensionRegistry->method( 'isLoaded' )->willReturn( true );

		$prefs = [
			'popups-reference-previews' => [
				'type' => 'toggle',
				'label-message' => 'from-another-extension',
			]
		];
		$expected = $prefs;
		( new ReferencePreviewsHooks(
			$extensionRegistry,
			$this->createNoOpMock( ReferencePreviewsContext::class ),
			$this->createMock( ReferencePreviewsGadgetsIntegration::class )
		) )
			->onGetPreferences( $this->createNoOpMock( User::class ), $prefs );
		$this->assertEquals( $expected, $prefs );
	}

}
