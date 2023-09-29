<?php

namespace Test\Parsoid\Utils;

use Wikimedia\Parsoid\Mocks\MockEnv;
use Wikimedia\Parsoid\NodeData\DataBag;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;

/**
 * @coversDefaultClass  \Wikimedia\Parsoid\Utils\DOMDataUtils
 */
class DOMDataUtilsTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @covers ::injectPageBundle
	 */
	public function testInjectPageBundle() {
		$doc = DOMUtils::parseHTML( "Hello, world" );
		DOMDataUtils::injectPageBundle( $doc, PHPUtils::arrayToObject( [
			'node-id' => [ 'parsoid' => [ 'rah' => 'rah' ] ],
		] ) );
		// Note that we use the 'native' getElementById, not
		// DOMCompat::getElementById, in order to test T232390
		$el = $doc->getElementById( 'mw-pagebundle' );
		$this->assertNotEquals( null, $el );
		$this->assertEquals( 'script', DOMCompat::nodeName( $el ) );
	}

	/**
	 * @covers ::storeInPageBundle
	 */
	public function testStoreInPageBundle() {
		$env = new MockEnv( [] );
		$doc = DOMUtils::parseHTML( "<p>Hello, world</p>" );
		$doc->bag = new DataBag(); // see Env::createDocument
		$p = DOMCompat::querySelector( $doc, 'p' );
		DOMDataUtils::storeInPageBundle( $p, $env, PHPUtils::arrayToObject( [
			'parsoid' => [ 'go' => 'team' ],
			'mw' => [ 'test' => 'me' ],
		] ), DOMDataUtils::usedIdIndex( $p ) );
		$id = $p->getAttribute( 'id' ) ?? '';
		$this->assertNotEquals( '', $id );
		// Use the 'native' getElementById, not DOMCompat::getElementById,
		// in order to test T232390.
		$el = $doc->getElementById( $id );
		$this->assertEquals( $p, $el );
	}

	/**
	 * @return void
	 * @throws \Wikimedia\Parsoid\Core\ClientError
	 * @covers ::extractPageBundle
	 */
	public function testExtractPageBundle() {
		$html = <<<'EOF'
<html>
  <head>
    <script id="mw-pagebundle" type="application/x-mw-pagebundle">
      {"parsoid":
      {"counter":1,"ids":{"mwAA":{"dsr":[0,13,0,0]},
      "mwAQ":{"dsr":[0,12,0,0]}},"offsetType":"byte"},"mw":{"ids":[]}}
    </script>
  </head>
  <body><p id="mwAQ">Hello, world</p>
EOF;
		$doc = DOMUtils::parseHTML( $html );
		$pb = DOMDataUtils::extractPageBundle( $doc );
		self::assertIsArray( $pb->parsoid['ids'] );
	}
}
