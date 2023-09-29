<?php

namespace MediaWiki\Extension\DiscussionTools;

use RedirectSpecialPage;
use SpecialPage;
use Title;

class SpecialGoToComment extends RedirectSpecialPage {

	private ThreadItemStore $threadItemStore;

	public function __construct(
		ThreadItemStore $threadItemStore
	) {
		parent::__construct( 'GoToComment' );
		$this->threadItemStore = $threadItemStore;
	}

	/**
	 * @inheritDoc
	 */
	public function getRedirect( $subpage ) {
		$results = [];

		// Search for all thread items with the given ID or name, returning results from the latest
		// revision of each page they appeared on.
		//
		// If there is exactly one result which is not transcluded from another page and in the current
		// revision of its page, redirect to it.
		//
		// Otherwise, redirect to full search results on Special:FindComment.

		if ( $subpage ) {
			$threadItems = $this->threadItemStore->findNewestRevisionsById( $subpage );
			foreach ( $threadItems as $item ) {
				if ( $item->getRevision()->isCurrent() && !is_string( $item->getTranscludedFrom() ) ) {
					$results[] = $item;
				}
			}
			$threadItems = $this->threadItemStore->findNewestRevisionsByName( $subpage );
			foreach ( $threadItems as $item ) {
				if ( $item->getRevision()->isCurrent() && !is_string( $item->getTranscludedFrom() ) ) {
					$results[] = $item;
				}
			}
		}

		if ( count( $results ) === 1 ) {
			return Title::castFromPageIdentity( $results[0]->getPage() )->createFragmentTarget( $results[0]->getId() );
		} else {
			return SpecialPage::getTitleFor( 'FindComment', $subpage ?: false );
		}
	}
}
