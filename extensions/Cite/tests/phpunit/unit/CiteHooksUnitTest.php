<?php

namespace Cite\Tests\Unit;

use Cite\Hooks\CiteHooks;
use Cite\ReferencePreviews\ReferencePreviewsContext;
use Cite\ReferencePreviews\ReferencePreviewsGadgetsIntegration;
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
			$this->createMock( ReferencePreviewsContext::class ),
			$this->createMock( ReferencePreviewsGadgetsIntegration::class ),
			new StaticUserOptionsLookup( [] )
		) )
			->onContentHandlerDefaultModelFor( $title, $model );

		$this->assertSame( CONTENT_MODEL_JSON, $model );
	}

}
