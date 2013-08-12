<?php
/**
 * LogFormatter for renameuser/renameuser logs
 */

class RenameuserLogFormatter extends LogFormatter {

	protected function getMessageParameters() {
		$params = parent::getMessageParameters();
		/* Current format:
		 * 1,2,3: normal logformatter params
		 * 4: old username
		 *    (legaciest doesn't have this at all, all in comment)
		 *    (legacier uses this as new name and stores old name in target)
		 * 5: new username
		 * 6: number of edits the user had at the time
		 *    (not available except in newest log entries)
		 * Note that the arrays are zero-indexed, while message parameters
		 * start from 1, so substract one to get array entries below.
		 */

		if ( !isset( $params[3] ) ) {
			// The oldest format
			return $params;
		} elseif ( !isset( $params[4] ) ) {
			// See comments above
			$params[4] = $params[3];
			$params[3] = $this->entry->getTarget()->getText();
		}

		// Nice link to old user page
		$title = Title::makeTitleSafe( NS_USER, $params[3] );
		$link = $this->myPageLink( $title, $params[3] );
		$params[3] = Message::rawParam( $link );

		// Nice link to new user page
		$title = Title::makeTitleSafe( NS_USER, $params[4] );
		$link = $this->myPageLink( $title, $params[4] );
		$params[4] = Message::rawParam( $link );

		return $params;
	}

	protected function myPageLink( Title $title = null, $text ) {
		if ( !$this->plaintext ) {
			$text = htmlspecialchars( $text );
			$link = Linker::link( $title, $text );
		} else {
			if ( !$title instanceof Title ) {
				$link = "[[User:$text]]";
			} else {
				$link = '[[' . $title->getPrefixedText() . ']]';
			}
		}
		return $link;
	}

	public function getMessageKey() {
		$key = parent::getMessageKey();
		$params = $this->extractParameters();

		// Very old log format, everything in comment
		if ( !isset( $params[3] ) ) {
			return "$key-legaciest";
		} elseif ( !isset( $params[5] ) ) {
			return "$key-legacier";
		}

		return $key;
	}
}
