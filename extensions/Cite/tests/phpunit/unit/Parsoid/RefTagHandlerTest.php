<?php

namespace Cite\Tests\Unit;

use Cite\Parsoid\RefTagHandler;
use MediaWiki\Config\HashConfig;
use MediaWikiUnitTestCase;
use Wikimedia\Parsoid\Ext\ContentUtils;
use Wikimedia\Parsoid\Ext\DOMDataUtils;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Mocks\MockEnv;
use Wikimedia\Parsoid\NodeData\DataMw;
use Wikimedia\Parsoid\NodeData\DataMwBody;

/**
 * @covers \Cite\Parsoid\RefTagHandler
 * @license GPL-2.0-or-later
 */
class RefTagHandlerTest extends MediaWikiUnitTestCase {

	public function testProcessAttributeEmbeddedDom() {
		$env = new MockEnv( [] );
		$extApi = new ParsoidExtensionAPI( $env );
		$doc = ContentUtils::createAndLoadDocument( '' );
		$elt = $doc->createElement( 'a' );
		$df = $doc->createDocumentFragment();
		$df->textContent = 'old';
		$body = new DataMwBody;
		$body->setHtml( $extApi, $df );
		DOMDataUtils::setDataMw( $elt, new DataMw( [
			'body' => $body,
		] ) );

		$group = new RefTagHandler( new HashConfig( [ 'CiteSubReferencing' => false ] ) );
		$group->processAttributeEmbeddedDom(
			$extApi,
			$elt,
			static function ( $df ) {
				$df->textContent = 'new';
				return true;
			}
		);

		$actual = DOMDataUtils::getDataMw( $elt )->body->getHtml( $extApi );
		$this->assertSame( 'new', $actual->textContent );
	}

	// TODO: Incomplete, there are a few more public methods to test

}
