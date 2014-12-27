<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

class SpamBlacklist extends BaseBlacklist {

	/**
	 * Returns the code for the blacklist implementation
	 *
	 * @return string
	 */
	protected function getBlacklistType() {
		return 'spam';
	}

	/**
	 * Apply some basic anti-spoofing to the links before they get filtered,
	 * see @bug 12896
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	protected function antiSpoof( $text ) {
		$text = str_replace( '．', '.', $text );
		return $text;
	}

	/**
	 * @param string[] $links An array of links to check against the blacklist
	 * @param Title  $title The title of the page to which the filter shall be applied.
	 *               This is used to load the old links already on the page, so
	 *               the filter is only applied to links that got added. If not given,
	 *               the filter is applied to all $links.
	 * @param boolean $preventLog Whether to prevent logging of hits. Set to true when
	 *               the action is testing the links rather than attempting to save them
	 *               (e.g. the API spamblacklist action)
	 *
	 * @return Array Matched text(s) if the edit should not be allowed, false otherwise
	 */
	function filter( array $links, Title $title = null, $preventLog = false ) {
		$fname = 'wfSpamBlacklistFilter';
		wfProfileIn( $fname );

		$blacklists = $this->getBlacklists();
		$whitelists = $this->getWhitelists();

		if ( count( $blacklists ) ) {
			// poor man's anti-spoof, see bug 12896
			$newLinks = array_map( array( $this, 'antiSpoof' ), $links );

			$oldLinks = array();
			if ( $title !== null ) {
				$oldLinks = $this->getCurrentLinks( $title );
				$addedLinks = array_diff( $newLinks, $oldLinks );
			} else {
				// can't load old links, so treat all links as added.
				$addedLinks = $newLinks;
			}

			wfDebugLog( 'SpamBlacklist', "Old URLs: " . implode( ', ', $oldLinks ) );
			wfDebugLog( 'SpamBlacklist', "New URLs: " . implode( ', ', $newLinks ) );
			wfDebugLog( 'SpamBlacklist', "Added URLs: " . implode( ', ', $addedLinks ) );

			$links = implode( "\n", $addedLinks );

			# Strip whitelisted URLs from the match
			if( is_array( $whitelists ) ) {
				wfDebugLog( 'SpamBlacklist', "Excluding whitelisted URLs from " . count( $whitelists ) .
					" regexes: " . implode( ', ', $whitelists ) . "\n" );
				foreach( $whitelists as $regex ) {
					wfSuppressWarnings();
					$newLinks = preg_replace( $regex, '', $links );
					wfRestoreWarnings();
					if( is_string( $newLinks ) ) {
						// If there wasn't a regex error, strip the matching URLs
						$links = $newLinks;
					}
				}
			}

			# Do the match
			wfDebugLog( 'SpamBlacklist', "Checking text against " . count( $blacklists ) .
				" regexes: " . implode( ', ', $blacklists ) . "\n" );
			$retVal = false;
			foreach( $blacklists as $regex ) {
				wfSuppressWarnings();
				$matches = array();
				$check = ( preg_match_all( $regex, $links, $matches ) > 0 );
				wfRestoreWarnings();
				if( $check ) {
					wfDebugLog( 'SpamBlacklist', "Match!\n" );
					global $wgRequest;
					$ip = $wgRequest->getIP();
					$imploded = implode( ' ', $matches[0] );
					wfDebugLog( 'SpamBlacklistHit', "$ip caught submitting spam: $imploded\n" );
					if( !$preventLog ) {
						$this->logFilterHit( $title, $imploded ); // Log it
					}
					if( $retVal === false ){
						$retVal = array();
					}
					$retVal = array_merge( $retVal, $matches[1] );
				}
			}
			if ( is_array( $retVal ) ) {
				$retVal = array_unique( $retVal );
			}
		} else {
			$retVal = false;
		}
		wfProfileOut( $fname );
		return $retVal;
	}

	/**
	 * Look up the links currently in the article, so we can
	 * ignore them on a second run.
	 *
	 * WARNING: I can add more *of the same link* with no problem here.
	 * @param $title Title
	 * @return array
	 */
	function getCurrentLinks( $title ) {
		$dbr = wfGetDB( DB_SLAVE );
		$id = $title->getArticleID(); // should be zero queries
		$res = $dbr->select( 'externallinks', array( 'el_to' ),
			array( 'el_from' => $id ), __METHOD__ );
		$links = array();
		foreach ( $res as $row ) {
			$links[] = $row->el_to;
		}
		return $links;
	}

	/**
	 * Returns the start of the regex for matches
	 *
	 * @return string
	 */
	public function getRegexStart() {
		return '/(?:https?:)?\/\/+[a-z0-9_\-.]*(';
	}

	/**
	 * Returns the end of the regex for matches
	 *
	 * @param $batchSize
	 * @return string
	 */
	public function getRegexEnd( $batchSize ) {
		return ')' . parent::getRegexEnd( $batchSize );
	}
	/**
	 * Logs the filter hit to Special:Log if
	 * $wgLogSpamBlacklistHits is enabled.
	 *
	 * @param Title $title
	 * @param string $url URL that the user attempted to add
	 */
	public function logFilterHit( $title, $url ) {
		global $wgUser, $wgLogSpamBlacklistHits;
		if ( $wgLogSpamBlacklistHits ) {
			$logEntry = new ManualLogEntry( 'spamblacklist', 'hit' );
			$logEntry->setPerformer( $wgUser );
			$logEntry->setTarget( $title );
			$logEntry->setParameters( array(
				'4::url' => $url,
			) );
			$logid = $logEntry->insert();
			$logEntry->publish( $logid, "rc" );
		}
	}
}
