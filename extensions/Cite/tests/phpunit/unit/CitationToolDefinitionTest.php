<?php

namespace Cite\Tests\Unit;

use Cite\ResourceLoader\CitationToolDefinition;
use MediaWiki\ResourceLoader\Context;
use Message;

/**
 * @covers \Cite\ResourceLoader\CitationToolDefinition
 *
 * @license GPL-2.0-or-later
 */
class CitationToolDefinitionTest extends \MediaWikiUnitTestCase {

	public function testGetScript() {
		$context = $this->createResourceLoaderContext();

		$this->assertSame(
			've.ui.mwCitationTools = [{"name":"n","title":"t"}];',
			CitationToolDefinition::makeScript( $context )
		);
	}

	private function createResourceLoaderContext(): Context {
		$msg = $this->createMock( Message::class );
		$msg->method( 'inContentLanguage' )
			->willReturnSelf();
		$msg->method( 'plain' )
			->willReturnOnConsecutiveCalls( '', '[{"name":"n"}]' );
		$msg->method( 'text' )
			->willReturn( 't' );

		$context = $this->createStub( Context::class );
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
