<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Page\DeletePage;
use MediaWiki\Title\Title;

/**
 * @newable
 * @since 1.32
 * @ingroup JobQueue
 */
class DeletePageJob extends Job implements GenericParameterJob {
	public function __construct( array $params ) {
		parent::__construct( 'deletePage', $params );

		$this->title = Title::makeTitle( $params['namespace'], $params['title'] );
	}

	public function run() {
		$services = MediaWikiServices::getInstance();
		// Failure to load the page is not job failure.
		// A parallel deletion operation may have already completed the page deletion.
		$wikiPage = $services->getWikiPageFactory()->newFromID( $this->params['wikiPageId'] );
		if ( $wikiPage ) {
			$deletePage = $services->getDeletePageFactory()->newDeletePage(
				$wikiPage,
				$services->getUserFactory()->newFromId( $this->params['userId'] )
			);
			$deletePage
				->setSuppress( $this->params['suppress'] )
				->setTags( json_decode( $this->params['tags'] ) )
				->setLogSubtype( $this->params['logsubtype'] )
				->setDeletionAttempted()
				->deleteInternal(
					$wikiPage,
					// Use a fallback for BC with queued jobs.
					$this->params['pageRole'] ?? DeletePage::PAGE_BASE,
					$this->params['reason'],
					$this->getRequestId()
				);
		}
		return true;
	}
}
