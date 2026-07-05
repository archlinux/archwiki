<?php

namespace MediaWiki\Extension\AbuseFilter\MediaWikiEventIngress;

use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Extension\AbuseFilter\EditRevUpdater;
use MediaWiki\Page\Event\PageLatestRevisionChangedEvent;
use MediaWiki\Page\Event\PageLatestRevisionChangedListener;
use MediaWiki\Page\WikiPageFactory;

/**
 * @noinspection PhpUnused
 */
class PageEventIngress extends DomainEventIngress implements PageLatestRevisionChangedListener {

	public function __construct(
		private readonly EditRevUpdater $revUpdater,
		private readonly WikiPageFactory $wikiPageFactory
	) {
	}

	/** @inheritDoc */
	public function handlePageLatestRevisionChangedEvent(
		PageLatestRevisionChangedEvent $event
	): void {
		$latestRevisionRecord = $event->getLatestRevisionAfter();
		$wikiPage = $this->wikiPageFactory->newFromTitle(
			$latestRevisionRecord->getPage()
		);
		$this->revUpdater->updateRev( $wikiPage, $latestRevisionRecord );
	}
}
