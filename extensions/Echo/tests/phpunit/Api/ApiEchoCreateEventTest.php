<?php

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\Notifications\Mapper\NotificationMapper;
use MediaWiki\Extension\Notifications\NotifUser;
use MediaWiki\Tests\Api\ApiTestCase;

/**
 * @group API
 * @group Database
 * @covers \MediaWiki\Extension\Notifications\Api\ApiEchoCreateEvent
 */
class ApiEchoCreateEventTest extends ApiTestCase {
	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( 'EchoEnableApiEvents', true );
	}

	public function testNotifySelf() {
		$result = $this->doApiRequestWithToken( [
			'action' => 'echocreateevent',
			'user' => $this->getTestUser()->getUser()->getName(),
			'header' => 'notification header',
			'content' => 'notification content',
		], null, $this->getTestUser()->getUser() );

		$this->assertEquals( 'success', $result[0]['echocreateevent']['result'] );

		$user = NotifUser::newFromUser( $this->getTestUser()->getUser() );
		$this->assertSame( 1, $user->getNotificationCount() );

		$mapper = new NotificationMapper();
		$notifs = $mapper->fetchByUser( $this->getTestUser()->getUser(), 5, null, [ 'api-notice' ] );
		$this->assertCount( 1, $notifs );
		$notif = array_values( $notifs )[0];
		$this->assertSame( 'notification header', $notif->getEvent()->getExtraParam( 'header' ) );
		$this->assertSame( 'notification content', $notif->getEvent()->getExtraParam( 'content' ) );
		$this->assertTrue( $notif->getEvent()->getExtraParam( 'noemail' ) );
	}

	public function testAlertWithEmail() {
		$result = $this->doApiRequestWithToken( [
			'action' => 'echocreateevent',
			'user' => $this->getTestUser()->getUser()->getName(),
			'header' => 'notification header',
			'content' => 'notification content',
			'email' => true,
			'section' => 'alert'
		], null, $this->getTestUser()->getUser() );
		$this->assertEquals( 'success', $result[0]['echocreateevent']['result'] );

		$mapper = new NotificationMapper();
		$notifs = $mapper->fetchByUser( $this->getTestUser()->getUser(), 5, null, [ 'api-alert' ] );
		$this->assertFalse( array_values( $notifs )[0]->getEvent()->getExtraParam( 'noemail' ) );
	}

	public function testNotifyOthers() {
		$this->setGroupPermissions( 'sysop', 'echo-create', true );

		$result = $this->doApiRequestWithToken( [
			'action' => 'echocreateevent',
			'user' => $this->getTestUser()->getUser()->getName(),
			'header' => 'notification header',
			'content' => 'notification content',
		], null, $this->getTestSysop()->getUser() );

		$this->assertEquals( 'success', $result[0]['echocreateevent']['result'] );

		$user = NotifUser::newFromUser( $this->getTestUser()->getUser() );
		$this->assertSame( 1, $user->getNotificationCount() );
	}

	public function testNotifyOthersWithoutPermission() {
		try {
			$this->doApiRequestWithToken( [
				'action' => 'echocreateevent',
				'user' => $this->getTestUser()->getUser()->getName(),
				'header' => 'notification header',
				'content' => 'notification content',
			], null, $this->getTestSysop()->getUser() );
		} catch ( ApiUsageException $ex ) {
			$this->assertApiErrorCode( 'permissiondenied', $ex );
		}
	}
}
