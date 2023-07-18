<?php
/**
 * DiscussionTools data updates hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\DiscussionTools\Hooks;

use DeferrableUpdate;
use MediaWiki\Extension\DiscussionTools\ThreadItemStore;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Storage\Hook\RevisionDataUpdatesHook;
use MWCallableUpdate;
use MWExceptionHandler;
use Throwable;
use Title;

class DataUpdatesHooks implements RevisionDataUpdatesHook {

	private ThreadItemStore $threadItemStore;

	public function __construct(
		ThreadItemStore $threadItemStore
	) {
		$this->threadItemStore = $threadItemStore;
	}

	/**
	 * @param Title $title
	 * @param RenderedRevision $renderedRevision
	 * @param DeferrableUpdate[] &$updates
	 * @return bool|void
	 */
	public function onRevisionDataUpdates( $title, $renderedRevision, &$updates ) {
		// This doesn't trigger on action=purge, only on automatic purge after editing a template or
		// transcluded page, and API action=purge&forcelinkupdate=1.

		// TODO Deduplicate work between this and the Echo hook (make it use Parsoid too)
		$rev = $renderedRevision->getRevision();
		if ( HookUtils::isAvailableForTitle( $title ) ) {
			$method = __METHOD__;
			$updates[] = new MWCallableUpdate( function () use ( $rev, $method ) {
				try {
					$threadItemSet = HookUtils::parseRevisionParsoidHtml( $rev, $method );
					$this->threadItemStore->insertThreadItems( $rev, $threadItemSet );
				} catch ( Throwable $e ) {
					// Catch errors, so that they don't cause other updates to fail (T315383), but log them.
					MWExceptionHandler::logException( $e );
				}
			}, __METHOD__ );
		}
	}
}
