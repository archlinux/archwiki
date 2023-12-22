<?php

namespace MediaWiki\Extension\Notifications\Cache;

use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageRecord;

/**
 * Cache class that maps article id to Title object.
 * @fixme There should be no need for this class. Core's PageStore should be responsible for caching, if it's
 * deemed necessary. See also T344124.
 */
class TitleLocalCache extends LocalCache {
	/**
	 * @inheritDoc
	 */
	protected function resolve( array $lookups ) {
		if ( $lookups ) {
			$titles = MediaWikiServices::getInstance()
				->getPageStore()
				->newSelectQueryBuilder()
				->wherePageIds( $lookups )
				->caller( __METHOD__ )
				->fetchPageRecords();

			/** @var PageRecord $title */
			foreach ( $titles as $title ) {
				$title = MediaWikiServices::getInstance()->getTitleFactory()->castFromPageIdentity( $title );
				yield $title->getArticleID() => $title;
			}
		}
	}

}
