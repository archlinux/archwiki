<?php

use MediaWiki\Extension\Notifications\Push\NotificationRequestJob;
use MediaWiki\Extension\Notifications\Push\PushNotifier;
use MediaWiki\Extension\Notifications\Push\Utils;
use Wikimedia\TestingAccessWrapper;

/** @covers \MediaWiki\Extension\Notifications\Push\PushNotifier */
class PushNotifierTest extends MediaWikiIntegrationTestCase {

	public function testCreateJob(): void {
		$notifier = TestingAccessWrapper::newFromClass( PushNotifier::class );
		$user = $this->getTestUser()->getUser();
		$centralId = Utils::getPushUserId( $user );
		$job = $notifier->createJob( $user );
		$this->assertInstanceOf( NotificationRequestJob::class, $job );
		$this->assertSame( 'EchoPushNotificationRequest', $job->getType() );
		$this->assertSame( $centralId, $job->getParams()['centralId'] );
	}

}
