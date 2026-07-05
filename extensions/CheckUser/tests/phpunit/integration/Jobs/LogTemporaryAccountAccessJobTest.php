<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\IPContributions;

use MediaWiki\Extension\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\Jobs\LogTemporaryAccountAccessJob
 * @group CheckUser
 * @group Database
 */
class LogTemporaryAccountAccessJobTest extends MediaWikiIntegrationTestCase {
	public function testRunOnInvalidPerformer() {
		$services = $this->getServiceContainer();
		$job = new LogTemporaryAccountAccessJob(
			'unused',
			[
				'performer' => 'Template:InvalidUser#test',
				'target' => 'test', 'timestamp' => 0, 'type' => 'view-ips',
			],
			$services->getService( 'CheckUserTemporaryAccountLoggerFactory' ),
			$services->getUserIdentityLookup()
		);
		$this->assertFalse( $job->run() );
		$this->assertSame( 'Invalid performer', $job->getLastError() );
	}

	public function testRunOnInvalidType() {
		$services = $this->getServiceContainer();
		$job = new LogTemporaryAccountAccessJob(
			'unused',
			[
				'performer' => $this->getTestUser()->getUserIdentity()->getName(),
				'type' => 'invalidtype', 'target' => 'test', 'timestamp' => 0,
			],
			$services->getService( 'CheckUserTemporaryAccountLoggerFactory' ),
			$services->getUserIdentityLookup()
		);
		$this->assertFalse( $job->run() );
		$this->assertSame( "Invalid type 'invalidtype'", $job->getLastError() );
	}
}
