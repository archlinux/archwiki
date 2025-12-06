<?php

namespace MediaWiki\Extension\Notifications\Test\Integration\Notifications;

use MediaWiki\Extension\Notifications\AttributeManager;
use MediaWiki\Extension\Notifications\ConfigNames;
use MediaWiki\Extension\Notifications\Notifications\RecentChangeNotificationMiddleware;
use MediaWiki\Notification\Notification;
use MediaWiki\Notification\NotificationEnvelope;
use MediaWiki\Notification\NotificationsBatch;
use MediaWiki\Notification\RecipientSet;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\RecentChanges\RecentChangeNotification;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;

/**
 * @covers \MediaWiki\Extension\Notifications\Notifications\RecentChangeNotificationMiddleware
 */
class RecentChangeNotificationMiddlewareTest extends \MediaWikiIntegrationTestCase {

	public function testRemovesNothingWhenConfigsAreSetToFalse() {
		$this->overrideConfigValue( ConfigNames::Notifications, [] );

		$sut = new RecentChangeNotificationMiddleware(
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getService( 'EchoAttributeManager' )
		);
		$rcMock = $this->createMock( RecentChange::class );
		$userIdent = UserIdentityValue::newRegistered( 1, 'User' );

		$batch = new NotificationsBatch(
			new NotificationEnvelope( new Notification( 'test' ), new RecipientSet( [ $userIdent ] ) ),
			new NotificationEnvelope(
				new RecentChangeNotification(
					$this->createMock( UserIdentity::class ),
					$this->createMock( PageIdentity::class ),
					$rcMock,
					'changed',
					RecentChangeNotification::WATCHLIST_NOTIFICATION
				), new RecipientSet( [ $userIdent ] )
			),
			new NotificationEnvelope(
				new RecentChangeNotification(
					$this->createMock( UserIdentity::class ),
					$this->createMock( PageIdentity::class ),
					$rcMock,
					'changed',
					RecentChangeNotification::TALK_NOTIFICATION
				), new RecipientSet( [ $userIdent ] )
			),
		);
		$calledNext = false;

		$sut->handle( $batch, static function () use ( &$calledNext ){
			$calledNext = true;
		} );
		/** @var NotificationEnvelope[] $envelopes */
		$envelopes = iterator_to_array( $batch );

		$this->assertTrue( $calledNext );
		$this->assertCount( 3, $envelopes );
		$this->assertSame( 'test', $envelopes[0]->getNotification()->getType() );
		$this->assertSame(
			RecentChangeNotification::WATCHLIST_NOTIFICATION, $envelopes[1]->getNotification()->getSource()
		);
		$this->assertSame(
			RecentChangeNotification::TALK_NOTIFICATION, $envelopes[2]->getNotification()->getSource()
		);
	}

	public function testRemovedTalkNotification() {
		$this->overrideConfigValue( ConfigNames::Notifications, [
			'edit-user-talk' => true,
		] );

		$sut = new RecentChangeNotificationMiddleware(
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getService( 'EchoAttributeManager' )
		);
		$rcMock = $this->createMock( RecentChange::class );
		$userIdent = UserIdentityValue::newRegistered( 1, 'User' );

		$batch = new NotificationsBatch(
			new NotificationEnvelope( new Notification( 'test' ), new RecipientSet( [ $userIdent ] ) ),
			new NotificationEnvelope(
				new RecentChangeNotification(
					$this->createMock( UserIdentity::class ),
					$this->createMock( PageIdentity::class ),
					$rcMock,
					'changed',
					RecentChangeNotification::WATCHLIST_NOTIFICATION
				), new RecipientSet( [ $userIdent ] )
			),
			new NotificationEnvelope(
				new RecentChangeNotification(
					$this->createMock( UserIdentity::class ),
					$this->createMock( PageIdentity::class ),
					$rcMock,
					'changed',
					RecentChangeNotification::TALK_NOTIFICATION
				), new RecipientSet( [ $userIdent ] )
			),
		);
		$calledNext = false;

		$sut->handle( $batch, static function () use ( &$calledNext ){
			$calledNext = true;
		} );
		/** @var NotificationEnvelope[] $envelopes */
		$envelopes = iterator_to_array( $batch );

		$this->assertTrue( $calledNext );
		$this->assertCount( 2, $envelopes );
		$this->assertSame( 'test', $envelopes[0]->getNotification()->getType() );
		$this->assertSame(
			RecentChangeNotification::TYPE, $envelopes[1]->getNotification()->getType()
		);
		$this->assertSame(
			RecentChangeNotification::WATCHLIST_NOTIFICATION, $envelopes[1]->getNotification()->getSource()
		);
	}

	public function testRemovedWatchlistNotification1() {
		$this->overrideConfigValue( ConfigNames::WatchlistNotifications, true );
		$this->overrideConfigValue( ConfigNames::Notifications, [
			'watchlist-change' => true,
		] );

		$sut = new RecentChangeNotificationMiddleware(
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getService( 'EchoAttributeManager' )
		);
		$rcMock = $this->createMock( RecentChange::class );
		$userIdent = UserIdentityValue::newRegistered( 1, 'User' );

		$batch = new NotificationsBatch(
			new NotificationEnvelope( new Notification( 'test' ), new RecipientSet( [ $userIdent ] ) ),
			new NotificationEnvelope(
				new RecentChangeNotification(
					$this->createMock( UserIdentity::class ),
					$this->createMock( PageIdentity::class ),
					$rcMock,
					'changed',
					RecentChangeNotification::WATCHLIST_NOTIFICATION
				), new RecipientSet( [ $userIdent ] )
			),
			new NotificationEnvelope(
				new RecentChangeNotification(
					$this->createMock( UserIdentity::class ),
					$this->createMock( PageIdentity::class ),
					$rcMock,
					'changed',
					RecentChangeNotification::TALK_NOTIFICATION
				), new RecipientSet( [ $userIdent ] )
			),
		);
		$calledNext = false;

		$sut->handle( $batch, static function () use ( &$calledNext ){
			$calledNext = true;
		} );
		/** @var NotificationEnvelope[] $envelopes */
		$envelopes = iterator_to_array( $batch );

		$this->assertTrue( $calledNext );
		$this->assertCount( 2, $envelopes );
		$this->assertSame( 'test', $envelopes[0]->getNotification()->getType() );
		$this->assertSame(
			RecentChangeNotification::TYPE, $envelopes[1]->getNotification()->getType()
		);
		$this->assertSame(
			RecentChangeNotification::TALK_NOTIFICATION, $envelopes[1]->getNotification()->getSource()
		);
	}

	public function testRemovedWatchlistNotification2() {
		$this->overrideConfigValue( ConfigNames::WatchlistNotifications, false );
		$this->overrideConfigValue( ConfigNames::Notifications, [] );

		$attributeManager = $this->createMock( AttributeManager::class );
		$attributeManager->method( 'getUserEnabledEvents' )->willReturn( [ 'edit-user-talk', 'edit-user-page' ] );

		$sut = new RecentChangeNotificationMiddleware(
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getUserFactory(),
			$attributeManager
		);
		$rcMock = $this->createMock( RecentChange::class );
		$userIdent = UserIdentityValue::newRegistered( 1, 'User' );

		$batch = new NotificationsBatch(
			new NotificationEnvelope( new Notification( 'test' ), new RecipientSet( [ $userIdent ] ) ),
			new NotificationEnvelope(
				new RecentChangeNotification(
					$this->createMock( UserIdentity::class ),
					PageIdentityValue::localIdentity( 1, NS_USER_TALK, $userIdent->getName() ),
					$rcMock,
					'changed',
					RecentChangeNotification::WATCHLIST_NOTIFICATION
				), new RecipientSet( [ $userIdent ] )
			),
			new NotificationEnvelope(
				new RecentChangeNotification(
					$this->createMock( UserIdentity::class ),
					$this->createMock( PageIdentity::class ),
					$rcMock,
					'changed',
					RecentChangeNotification::TALK_NOTIFICATION
				), new RecipientSet( [ $userIdent ] )
			),
		);
		$calledNext = false;

		$sut->handle( $batch, static function () use ( &$calledNext ){
			$calledNext = true;
		} );
		/** @var NotificationEnvelope[] $envelopes */
		$envelopes = iterator_to_array( $batch );

		$this->assertTrue( $calledNext );
		$this->assertCount( 2, $envelopes );
		$this->assertSame( 'test', $envelopes[0]->getNotification()->getType() );
		$this->assertSame(
			RecentChangeNotification::TYPE, $envelopes[1]->getNotification()->getType()
		);
		$this->assertSame(
			RecentChangeNotification::TALK_NOTIFICATION, $envelopes[1]->getNotification()->getSource()
		);
	}
}
