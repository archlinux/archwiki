<?php

use MediaWiki\Extension\Math\InputCheck\BaseChecker;

/**
 * @covers \MediaWiki\Extension\Math\InputCheck\BaseChecker
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MathInputCheckTest extends MediaWikiIntegrationTestCase {

	public function testAbstractClass() {
		$InputCheck = $this->getMockForAbstractClass( BaseChecker::class );
		/** @var BaseChecker $InputCheck */
		$this->assertFalse( $InputCheck->IsValid() );
		$this->assertNull( $InputCheck->getError() );
		$this->assertNull( $InputCheck->getValidTex() );
	}

}
