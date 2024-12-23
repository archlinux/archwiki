<?php

namespace MediaWiki\Extension\Notifications\Test\API;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Tests\Api\ApiTestCase;

/**
 * @group medium
 * @group API
 * @covers \MediaWiki\Extension\Notifications\Push\Api\ApiEchoPushSubscriptions
 */
class ApiEchoPushSubscriptionsTest extends ApiTestCase {

	public function testRequiresToken(): void {
		$this->overrideConfigValue( 'EchoEnablePush', true );
		$params = [
			'action' => 'echopushsubscriptions',
			'command' => 'create',
			'platform' => 'apns',
			'platformtoken' => 'ABC123',
		];
		$this->expectException( ApiUsageException::class );
		$this->doApiRequest( $params );
	}

}
