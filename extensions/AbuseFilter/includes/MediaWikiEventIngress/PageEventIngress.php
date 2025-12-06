<?php

namespace MediaWiki\Extension\AbuseFilter\MediaWikiEventIngress;

use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Extension\AbuseFilter\EditRevUpdater;
use MediaWiki\Page\Event\PageRevisionUpdatedEvent;
use MediaWiki\Page\Event\PageRevisionUpdatedListener;
use MediaWiki\Page\WikiPageFactory;

class PageEventIngress extends DomainEventIngress implements PageRevisionUpdatedListener {
	private EditRevUpdater $revUpdater;
	private WikiPageFactory $wikiPageFactory;

	public function __construct( EditRevUpdater $revUpdater, WikiPageFactory $wikiPageFactory ) {
		$this->revUpdater = $revUpdater;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function handlePageRevisionUpdatedEvent( PageRevisionUpdatedEvent $event ): void {
		$latestRevisionRecord = $event->getLatestRevisionAfter();
		$wikiPage = $this->wikiPageFactory->newFromTitle(
			$latestRevisionRecord->getPage()
		);
		$this->revUpdater->updateRev( $wikiPage, $latestRevisionRecord );
	}
}
