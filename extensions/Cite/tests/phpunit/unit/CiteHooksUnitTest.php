<?php

namespace Cite\Tests\Unit;

use Cite\Hooks\CiteHooks;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\StaticUserOptionsLookup;

/**
 * @covers \Cite\Hooks\CiteHooks
 * @license GPL-2.0-or-later
 */
class CiteHooksUnitTest extends \MediaWikiUnitTestCase {

	public function testOnContentHandlerDefaultModelFor() {
		$title = $this->createMock( Title::class );
		$title->method( 'inNamespace' )
			->willReturn( true );
		$title->method( 'getText' )
			->willReturn( 'Cite-tool-definition.json' );

		( new CiteHooks(
			$this->createNoOpMock( ExtensionRegistry::class ),
			new StaticUserOptionsLookup( [] )
		) )
			->onContentHandlerDefaultModelFor( $title, $model );

		$this->assertSame( CONTENT_MODEL_JSON, $model );
	}

}
