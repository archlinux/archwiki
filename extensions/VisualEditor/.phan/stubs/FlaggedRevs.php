<?php

// @phpcs:disable MediaWiki.Files.ClassMatchesFilename.NotMatch

/**
 * Phan stub for the soft dependency to FlaggedRevs extension
 * There is no hard dependency and VisualEditor is a dependency to many other extensions,
 * so this class is stubbed and not verified against the original class
 */
class FlaggablePageView extends ContextSource {

	/**
	 * @return self
	 */
	public static function singleton() {
	}

	/**
	 * @return true
	 */
	public function displayTag() {
	}

	/**
	 * @param bool &$outputDone
	 * @param bool &$useParserCache
	 * @return bool
	 */
	public function setPageContent( &$outputDone, &$useParserCache ) {
	}

}
