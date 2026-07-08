<?php

namespace MediaWiki\Linter;

use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageLatestRevisionChangedEvent;
use MediaWiki\Page\Event\PageLatestRevisionChangedListener;
use MediaWiki\Revision\SlotRecord;

/**
 * @noinspection PhpUnused
 */
class LintEventIngress extends DomainEventIngress implements PageLatestRevisionChangedListener {
	public function __construct(
		private readonly TotalsLookup $totalsLookup,
		private readonly Database $database,
	) {
	}

	/**
	 * Remove entries from the linter table upon page content model change away from wikitext
	 *
	 * @noinspection PhpUnused
	 * @param PageLatestRevisionChangedEvent $event
	 * @return void
	 */
	public function handlePageLatestRevisionChangedEvent(
		PageLatestRevisionChangedEvent $event
	): void {
		$page = $event->getPage();
		$tags = $event->getTags();

		if (
			in_array( "mw-blank", $tags ) ||
			(
				in_array( "mw-contentmodelchange", $tags ) &&
				!in_array(
					$event->getLatestRevisionAfter()->getSlot( SlotRecord::MAIN )->getModel(),
					Hooks::LINTABLE_CONTENT_MODELS
				)
			)
		) {
			$this->totalsLookup->updateStats(
				$this->database->setForPage( $page->getId(), $page->getNamespace(), [] )
			);
		}
	}
}
