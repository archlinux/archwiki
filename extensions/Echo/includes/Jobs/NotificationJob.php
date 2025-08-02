<?php

namespace MediaWiki\Extension\Notifications\Jobs;

use MediaWiki\Extension\Notifications\Controller\NotificationController;
use MediaWiki\Extension\Notifications\Mapper\EventMapper;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\JobQueue\Job;
use MediaWiki\Title\Title;

class NotificationJob extends Job {

	public function __construct( Title $title, array $params ) {
		$command = isset( $params['jobReleaseTimestamp'] ) ? 'DelayedEchoNotificationJob' : 'EchoNotificationJob';
		parent::__construct( $command, $title, $params );
	}

	/** @inheritDoc */
	public function run() {
		if ( isset( $this->params['eventId'] ) ) {
			$eventMapper = new EventMapper();
			$event = $eventMapper->fetchById( $this->params['eventId'], true );
		} else {
			$event = Event::newFromArray( $this->params['eventData'] );
		}
		NotificationController::notify( $event, false );

		return true;
	}
}
