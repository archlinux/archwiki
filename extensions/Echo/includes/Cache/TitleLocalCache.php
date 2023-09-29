<?php

namespace MediaWiki\Extension\Notifications\Cache;

use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageRecord;

/**
 * Cache class that maps article id to Title object
 */
class TitleLocalCache extends LocalCache {

	/**
	 * @var TitleLocalCache
	 */
	private static $instance;

	/**
	 * @return TitleLocalCache
	 */
	public static function create() {
		if ( !self::$instance ) {
			self::$instance = new TitleLocalCache();
		}

		return self::$instance;
	}

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
