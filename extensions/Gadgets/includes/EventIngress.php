<?php

namespace MediaWiki\Extension\Gadgets;

use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageDeletedEvent;
use MediaWiki\Page\Event\PageDeletedListener;
use MediaWiki\Page\Event\PageLatestRevisionChangedEvent;
use MediaWiki\Page\Event\PageLatestRevisionChangedListener;
use MediaWiki\Title\TitleValue;

/**
 * Event subscriber acting as an ingress for relevant events emitted
 * by MediaWiki core.
 *
 * @noinspection PhpUnused
 */
class EventIngress
	extends DomainEventIngress
	implements PageLatestRevisionChangedListener, PageDeletedListener
{
	public function __construct(
		private readonly GadgetRepo $gadgetRepo,
	) {
	}

	/**
	 * Handle PageLatestRevisionChangedEvent
	 */
	public function handlePageLatestRevisionChangedEvent(
		PageLatestRevisionChangedEvent $event
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
