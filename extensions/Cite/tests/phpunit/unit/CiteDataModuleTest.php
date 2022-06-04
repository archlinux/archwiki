<?php

namespace Cite\Tests\Unit;

use Cite\ResourceLoader\CiteVisualEditorModule;
use Message;
use ResourceLoaderContext;

/**
 * @covers \Cite\ResourceLoader\CiteVisualEditorModule
 *
 * @license GPL-2.0-or-later
 */
class CiteDataModuleTest extends \MediaWikiUnitTestCase {

	public function testGetScript() {
		$module = new CiteVisualEditorModule();
		$context = $this->createResourceLoaderContext();

		$this->assertSame(
			've.ui.mwCitationTools = [{"name":"n","title":"t"}];',
			$module->makePrependedScript( $context )
		);
	}

	public function testGetDefinitionSummary() {
		$module = new CiteVisualEditorModule();
		$context = $this->createResourceLoaderContext();
		$summary = $module->getDefinitionSummary( $context );

		$this->assertStringContainsString(
			'{"name":"n","title":"t"}',
			array_pop( $summary )['script']
		);
	}

	private function createResourceLoaderContext(): ResourceLoaderContext {
		$msg = $this->createMock( Message::class );
		$msg->method( 'inContentLanguage' )
			->willReturnSelf();
		$msg->method( 'plain' )
			->willReturnOnConsecutiveCalls( '', '[{"name":"n"}]' );
		$msg->method( 'text' )
			->willReturn( 't' );

		$context = $this->createStub( ResourceLoaderContext::class );
		$context->method( 'msg' )
			->withConsecutive(
				[ 'cite-tool-definition.json' ],
				[ 'visualeditor-cite-tool-definition.json' ],
				[ 'visualeditor-cite-tool-name-n' ]
			)
			->willReturn( $msg );
		$context->method( 'encodeJson' )->willReturnCallback( 'json_encode' );
		return $context;
	}

}
