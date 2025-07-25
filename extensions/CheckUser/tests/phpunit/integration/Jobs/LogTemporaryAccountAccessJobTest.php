<?php

namespace MediaWiki\CheckUser\Tests\Integration\IPContributions;

use MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob
 * @group CheckUser
 * @group Database
 */
class LogTemporaryAccountAccessJobTest extends MediaWikiIntegrationTestCase {
	public function testRunOnInvalidPerformer() {
		$job = new LogTemporaryAccountAccessJob( 'unused', [
			'performer' => 'Template:InvalidUser#test',
			'target' => 'test', 'timestamp' => 0, 'type' => 'view-ips',
		] );
		$this->assertFalse( $job->run() );
		$this->assertSame( 'Invalid performer', $job->getLastError() );
	}

	public function testRunOnInvalidType() {
		$job = new LogTemporaryAccountAccessJob( 'unused', [
			'performer' => $this->getTestUser()->getUserIdentity()->getName(),
			'type' => 'invalidtype', 'target' => 'test', 'timestamp' => 0,
		] );
		$this->assertFalse( $job->run() );
		$this->assertSame( "Invalid type 'invalidtype'", $job->getLastError() );
	}
}
