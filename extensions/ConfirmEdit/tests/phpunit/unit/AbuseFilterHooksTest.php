<?php

namespace MediaWiki\Extension\ConfirmEdit\Test\Unit\AbuseFilter;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\ConfirmEdit\AbuseFilterHooks;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\AbuseFilterHooks
 */
class AbuseFilterHooksTest extends MediaWikiUnitTestCase {

	public function testOnAbuseFilterCustomActions() {
		$config = new HashConfig( [ 'ConfirmEditEnabledAbuseFilterCustomActions' => [ 'showcaptcha' ] ] );
		$abuseFilterHooks = new AbuseFilterHooks( $config );
		$actions = [];
		$abuseFilterHooks->onAbuseFilterCustomActions( $actions );
		$this->assertArrayHasKey( 'showcaptcha', $actions );
	}
}
