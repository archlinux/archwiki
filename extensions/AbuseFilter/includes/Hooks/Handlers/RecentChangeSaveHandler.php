<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagger;
use MediaWiki\RecentChanges\Hook\RecentChange_saveHook;

class RecentChangeSaveHandler implements RecentChange_saveHook {

	public function __construct( private readonly ChangeTagger $changeTagger ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onRecentChange_save( $recentChange ) {
		$tags = $this->changeTagger->getTagsForRecentChange( $recentChange );
		if ( $tags ) {
			$recentChange->addTags( $tags );
		}
	}
}
