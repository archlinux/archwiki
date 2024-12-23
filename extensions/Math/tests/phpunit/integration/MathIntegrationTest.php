<?php
namespace MediaWiki\Extension\Math\Tests;

use MediaWiki\Extension\Math\Math;
use MediaWiki\Extension\Math\MathConfig;

/**
 * @covers \MediaWiki\Extension\Math\Math
 */
class MathIntegrationTest extends \MediaWikiIntegrationTestCase {
	public function testGetMathConfigNull() {
		$config = Math::getMathConfig();
		$this->assertInstanceOf( MathConfig::class, $config );
	}
}
