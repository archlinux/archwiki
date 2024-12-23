<?php

namespace MediaWiki\Extension\ConfirmEdit\Test\Integration\AbuseFilter;

use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\ConfirmEdit\AbuseFilter\CaptchaConsequence;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\AbuseFilter\CaptchaConsequence
 */
class CaptchaConsequenceTest extends MediaWikiIntegrationTestCase {

	public function testExecute() {
		$parameters = $this->createMock( Parameters::class );
		$parameters->method( 'getAction' )->willReturn( 'edit' );
		$captchaConsequence = new CaptchaConsequence( $parameters );
		$simpleCaptcha = Hooks::getInstance();
		$this->assertFalse( $simpleCaptcha->shouldForceShowCaptcha() );
		$captchaConsequence->execute();
		$this->assertTrue( $simpleCaptcha->shouldForceShowCaptcha() );
	}

}
