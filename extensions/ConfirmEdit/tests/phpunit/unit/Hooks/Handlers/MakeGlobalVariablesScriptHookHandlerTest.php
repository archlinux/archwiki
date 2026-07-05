<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Unit\Hooks\Handlers;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\ConfirmEdit\Hooks\Handlers\MakeGlobalVariablesScriptHookHandler;
use MediaWiki\Output\OutputPage;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\Hooks\Handlers\MakeGlobalVariablesScriptHookHandler
 */
class MakeGlobalVariablesScriptHookHandlerTest extends MediaWikiUnitTestCase {

	public function testWhenVisualEditorAndMobileFrontendNotInstalled() {
		// Mock that all extensions are not installed
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->willReturn( false );

		// Run the hook and expect the hook to not add any items to the $vars array
		$vars = [];
		$objectUnderTest = new MakeGlobalVariablesScriptHookHandler(
			$extensionRegistry,
			new HashConfig( [ 'HCaptchaVisualEditorOnLoadIntegrationEnabled' => true ] )
		);
		$objectUnderTest->onMakeGlobalVariablesScript( $vars, $this->createMock( OutputPage::class ) );
		$this->assertArrayEquals( [], $vars );
	}
}
