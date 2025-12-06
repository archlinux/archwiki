<?php

namespace MediaWiki\Extension\Notifications\Test;

use MediaWiki\Extension\Notifications\Mapper\EventMapper;
use MediaWiki\Extension\Notifications\Mapper\NotificationMapper;
use MediaWiki\Extension\Notifications\MediaWikiEventIngress\PageEventIngress;
use MediaWiki\Page\Event\PageDeletedEvent;
use MediaWiki\Page\ExistingPageRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

class PageIngressTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers \MediaWiki\Extension\Notifications\MediaWikiEventIngress\PageEventIngress
	 */
	public function testDeletedPage() {
		$pageRecordBefore = $this->createMock( ExistingPageRecord::class );
		$pageRecordBefore->method( 'exists' )->willReturn( true );
		$latestRevisionBefore = $this->createMock( RevisionRecord::class );
		$user = new UserIdentityValue( 0, "User" );
		$event = new PageDeletedEvent(
			$pageRecordBefore,
			$latestRevisionBefore,
			$user,
			[], [], "", "", 1
		);

		$revisionStore = $this->createMock( RevisionStore::class );
		$userEditTracker = $this->createMock( UserEditTracker::class );
		$eventMapper = $this->createMock( EventMapper::class );
		$eventIdsForModeration = [ 1, 2, 3 ];
		$eventMapper->expects( $this->once() )
			->method( 'fetchIdsByPage' )
			->willReturn( $eventIdsForModeration );
		$eventMapper->expects( $this->once() )
			->method( 'toggleDeleted' )
			->with( $eventIdsForModeration, true );
		$this->setService( 'EchoEventMapper', $eventMapper );

		$notificationMapper = $this->createMock( NotificationMapper::class );
		$notificationMapper->expects( $this->once() )
			->method( 'fetchUsersWithNotificationsForEvents' )
			->with( $eventIdsForModeration )
			->willReturn( [] );
		$this->setService( 'EchoNotificationMapper', $notificationMapper );

		$pageEventIngress = new PageEventIngress(
			$revisionStore, $userEditTracker,
			$eventMapper
		);
		$pageEventIngress->handlePageDeletedEvent( $event );
	}
}
