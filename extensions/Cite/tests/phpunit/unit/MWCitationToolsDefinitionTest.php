<?php

namespace Cite\Tests\Unit;

use Cite\ResourceLoader\MWCitationToolsDefinition;
use MediaWiki\Message\Message;
use MediaWiki\ResourceLoader\Context;

/**
 * @covers \Cite\ResourceLoader\MWCitationToolsDefinition
 * @license GPL-2.0-or-later
 */
class MWCitationToolsDefinitionTest extends \MediaWikiUnitTestCase {

	public function testGetScript() {
		$context = $this->createResourceLoaderContext();

		$expected = [
			[ 'name' => 'no-message', 'title' => 'Hard-coded title', 'icon' => 'browser' ],
			[ 'name' => 'missing-message', 'title' => 'missing-message' ],
			[ 'name' => 'web', 'title' => 't', 'icon' => 'browser' ],
		];
		$this->assertSame( $expected, MWCitationToolsDefinition::getTools( $context ) );
	}

	private function createResourceLoaderContext(): Context {
		$definition = [
			// We expect broken and incomplete entries to be skipped
			[],
			[ 'name' => '' ],

			[ 'name' => 'no-message', 'title' => 'Hard-coded title', 'icon' => 'ref-cite-web' ],
			[ 'name' => 'missing-message', 'icon' => null ],
			[ 'name' => 'web' ],
		];

		$msg = $this->createMock( Message::class );
		$msg->method( 'inContentLanguage' )
			->willReturnSelf();
		$msg->method( 'plain' )
			->willReturn( json_encode( $definition ) );
		$msg->method( 'text' )
			->willReturn( 't' );

		$disabled = $this->createMock( Message::class );
		$disabled->method( 'isDisabled' )->willReturn( true );

		$context = $this->createStub( Context::class );
		$context->method( 'msg' )
			->willReturnMap( [
				[ 'cite-tool-definition.json', $msg ],
				[ 'visualeditor-cite-tool-name-missing-message', $disabled ],
				[ 'visualeditor-cite-tool-name-web', $msg ]
			] );
		$context->method( 'encodeJson' )->willReturnCallback( 'json_encode' );
		return $context;
	}

}
