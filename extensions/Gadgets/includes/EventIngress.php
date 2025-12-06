<?php

namespace MediaWiki\Extension\Gadgets;

use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageDeletedEvent;
use MediaWiki\Page\Event\PageDeletedListener;
use MediaWiki\Page\Event\PageRevisionUpdatedEvent;
use MediaWiki\Page\Event\PageRevisionUpdatedListener;
use MediaWiki\Title\TitleValue;

/**
 * Event subscriber acting as an ingress for relevant events emitted
 * by MediaWiki core.
 */
class EventIngress
	extends DomainEventIngress
	implements PageRevisionUpdatedListener, PageDeletedListener
{
	public function __construct(
		private readonly GadgetRepo $gadgetRepo,
	) {
	}

	/**
	 * Handle PageRevisionUpdatedEvent
	 */
	public function handlePageRevisionUpdatedEvent(
		PageRevisionUpdatedEvent $event
	): void {
		$title = TitleValue::newFromPage( $event->getPage() );
		$this->gadgetRepo->handlePageUpdate( $title );
	}

	/**
	 * Handle PageDeletedEvent
	 */
	public function handlePageDeletedEvent(
		PageDeletedEvent $event
	): void {
		$title = TitleValue::newFromPage( $event->getPage() );
		$this->gadgetRepo->handlePageUpdate( $title );
	}

}
