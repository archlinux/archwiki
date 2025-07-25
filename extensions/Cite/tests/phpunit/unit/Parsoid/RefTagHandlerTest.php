<?php

namespace Cite\Tests\Unit;

use Cite\Parsoid\RefTagHandler;
use MediaWiki\Config\HashConfig;
use MediaWikiUnitTestCase;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\NodeData\DataMw;
use Wikimedia\Parsoid\NodeData\DataMwBody;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * @covers \Cite\Parsoid\RefTagHandler
 * @license GPL-2.0-or-later
 */
class RefTagHandlerTest extends MediaWikiUnitTestCase {

	public function testProcessAttributeEmbeddedHTML() {
		$doc = DOMUtils::parseHTML( '' );
		DOMDataUtils::prepareDoc( $doc );
		$elt = $doc->createElement( 'a' );
		DOMDataUtils::setDataMw( $elt, new DataMw( [
			'body' => DataMwBody::new( [ 'html' => 'old' ] ),
		] ) );

		$group = new RefTagHandler( new HashConfig( [ 'CiteSubReferencing' => false ] ) );
		$group->processAttributeEmbeddedHTML(
			$this->createNoOpMock( ParsoidExtensionAPI::class ),
			$elt,
			static fn () => 'new'
		);

		$this->assertSame( 'new', DOMDataUtils::getDataMw( $elt )->body->html );
	}

	// TODO: Incomplete, there are a few more public methods to test

}
