<?php

namespace MediaWiki\CheckUser\Tests\Unit\Jobs;

use MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob
 * @group CheckUser
 */
class LogTemporaryAccountAccessJobTest extends MediaWikiUnitTestCase {
	public function testShouldCreateValidSpecification(): void {
		ConvertibleTimestamp::setFakeTime( '20240101000000' );

		$performer = new UserIdentityValue( 1, 'TestPerformer' );
		$target = '~2024-9';
		$type = TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP;

		$spec = LogTemporaryAccountAccessJob::newSpec( $performer, $target, $type );

		$this->assertSame( LogTemporaryAccountAccessJob::TYPE, $spec->getType() );
		$this->assertSame( $performer->getName(), $spec->getParams()['performer'] );
		$this->assertSame( $type, $spec->getParams()['type'] );
		$this->assertSame( 1704067200, $spec->getParams()['timestamp'] );
	}
}
