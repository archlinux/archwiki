<?php

namespace MediaWiki\Linter;

use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageRevisionUpdatedEvent;
use MediaWiki\Revision\SlotRecord;

class LintEventIngress extends DomainEventIngress {
	private TotalsLookup $totalsLookup;
	private Database $database;

	public function __construct( TotalsLookup $totalsLookup, Database $database ) {
		$this->totalsLookup = $totalsLookup;
		$this->database = $database;
	}

	/**
	 * Remove entries from the linter table upon page content model change away from wikitext
	 *
	 * @noinspection PhpUnused
	 * @param PageRevisionUpdatedEvent $event
	 * @return void
	 */
	public function handlePageRevisionUpdatedEvent( PageRevisionUpdatedEvent $event ) {
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
