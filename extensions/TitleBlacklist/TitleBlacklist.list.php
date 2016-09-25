<?php
/**
 * Title Blacklist class
 * @author Victor Vasiliev
 * @copyright © 2007-2010 Victor Vasiliev et al
 * @license GNU General Public License 2.0 or later
 * @file
 */

//@{
/**
 * @ingroup Extensions
 */

/**
 * Implements a title blacklist for MediaWiki
 */
class TitleBlacklist {
	private $mBlacklist = null, $mWhitelist = null;
	const VERSION = 3;	// Blacklist format

	/**
	 * Get an instance of this class
	 *
	 * @return TitleBlacklist
	 */
	public static function singleton() {
		static $instance = null;

		if ( $instance === null ) {
			$instance = new self;
		}
		return $instance;
	}

	/**
	 * Load all configured blacklist sources
	 */
	public function load() {
		global $wgTitleBlacklistSources, $wgTitleBlacklistCaching;

		$cache = ObjectCache::getMainWANInstance();
		// Try to find something in the cache
		$cachedBlacklist = $cache->get( wfMemcKey( "title_blacklist_entries" ) );
		if ( is_array( $cachedBlacklist ) && count( $cachedBlacklist ) > 0 && ( $cachedBlacklist[0]->getFormatVersion() == self::VERSION ) ) {
			$this->mBlacklist = $cachedBlacklist;
			return;
		}

		$sources = $wgTitleBlacklistSources;
		$sources['local'] = array( 'type' => 'message' );
		$this->mBlacklist = array();
		foreach( $sources as $sourceName => $source ) {
			$this->mBlacklist = array_merge( $this->mBlacklist, $this->parseBlacklist( $this->getBlacklistText( $source ), $sourceName ) );
		}
		$cache->set( wfMemcKey( "title_blacklist_entries" ), $this->mBlacklist, $wgTitleBlacklistCaching['expiry'] );
		wfDebugLog( 'TitleBlacklist-cache', 'Updated ' . wfMemcKey( "title_blacklist_entries" )
			. ' with ' . count( $this->mBlacklist ) . ' entries.' );
	}

	/**
	 * Load local whitelist
	 */
	public function loadWhitelist() {
		global $wgTitleBlacklistCaching;

		$cache = ObjectCache::getMainWANInstance();
		$cachedWhitelist = $cache->get( wfMemcKey( "title_whitelist_entries" ) );
		if ( is_array( $cachedWhitelist ) && count( $cachedWhitelist ) > 0 && ( $cachedWhitelist[0]->getFormatVersion() != self::VERSION ) ) {
			$this->mWhitelist = $cachedWhitelist;
			return;
		}
		$this->mWhitelist = $this->parseBlacklist( wfMessage( 'titlewhitelist' )
				->inContentLanguage()->text(), 'whitelist' );
		$cache->set( wfMemcKey( "title_whitelist_entries" ), $this->mWhitelist, $wgTitleBlacklistCaching['expiry'] );
	}

	/**
	 * Get the text of a blacklist from a specified source
	 *
	 * @param $source A blacklist source from $wgTitleBlacklistSources
	 * @return The content of the blacklist source as a string
	 */
	private static function getBlacklistText( $source ) {
		if ( !is_array( $source ) || count( $source ) <= 0 ) {
			return '';	// Return empty string in error case
		}

		if ( $source['type'] == 'message' ) {
			return wfMessage( 'titleblacklist' )->inContentLanguage()->text();
		} elseif ( $source['type'] == 'localpage' && count( $source ) >= 2 ) {
			$title = Title::newFromText( $source['src'] );
			if ( is_null( $title ) ) {
				return '';
			}
			if ( $title->getNamespace() == NS_MEDIAWIKI ) {
				$msg = wfMessage( $title->getText() )->inContentLanguage();
				if ( !$msg->isDisabled() ) {
					return $msg->text();
				} else {
					return '';
				}
			} else {
				$article = new Article( $title );
				if ( $article->exists() ) {
					$article->followRedirect();
					return $article->getContent();
				}
			}
		} elseif ( $source['type'] == 'url' && count( $source ) >= 2 ) {
			return self::getHttp( $source['src'] );
		} elseif ( $source['type'] == 'file' && count( $source ) >= 2 ) {
			if ( file_exists( $source['src'] ) ) {
				return file_get_contents( $source['src'] );
			} else {
				return '';
			}
		}

		return '';
	}

	/**
	 * Parse blacklist from a string
	 *
	 * @param $list string Text of a blacklist source
	 * @return array of TitleBlacklistEntry entries
	 */
	public static function parseBlacklist( $list, $sourceName ) {
		$lines = preg_split( "/\r?\n/", $list );
		$result = array();
		foreach ( $lines as $line ) {
			$line = TitleBlacklistEntry :: newFromString( $line, $sourceName );
			if ( $line ) {
				$result[] = $line;
			}
		}

		return $result;
	}

	/**
	 * Check whether the blacklist restricts given user
	 * performing a specific action on the given Title
	 *
	 * @param $title Title to check
	 * @param $user User to check
	 * @param $action string Action to check; 'edit' if unspecified
	 * @param $override bool If set to true, overrides work
	 * @return TitleBlacklistEntry|bool The corresponding TitleBlacklistEntry if
	 * blacklisted; otherwise false
	 */
	public function userCannot( $title, $user, $action = 'edit', $override = true ) {
		$entry = $this->isBlacklisted( $title, $action );
		if ( !$entry ) {
			return false;
		}
		$params = $entry->getParams();
		if ( isset( $params['autoconfirmed'] ) && $user->isAllowed( 'autoconfirmed' ) ) {
			return false;
		}
		if ( $override && self::userCanOverride( $user, $action ) ) {
			return false;
		}
		return $entry;
	}

	/**
	 * Check whether the blacklist restricts
	 * performing a specific action on the given Title
	 *
	 * @param $title Title to check
	 * @param $action string Action to check; 'edit' if unspecified
	 * @return TitleBlacklistEntry|bool The corresponding TitleBlacklistEntry if blacklisted;
	 *         otherwise FALSE
	 */
	public function isBlacklisted( $title, $action = 'edit' ) {
		if ( !( $title instanceof Title ) ) {
			$title = Title::newFromText( $title );
			if ( !( $title instanceof Title ) ) {
				// The fact that the page name is invalid will stop whatever
				// action is going through. No sense in doing more work here.
				return false;
			}
		}
		$blacklist = $this->getBlacklist();
		$autoconfirmedItem = false;
		foreach ( $blacklist as $item ) {
			if ( $item->matches( $title->getFullText(), $action ) ) {
				if ( $this->isWhitelisted( $title, $action ) ) {
					return false;
				}
				$params = $item->getParams();
				if ( !isset( $params['autoconfirmed'] ) ) {
					return $item;
				}
				if ( !$autoconfirmedItem ) {
					$autoconfirmedItem = $item;
				}
			}
		}
		return $autoconfirmedItem;
	}

	/**
	 * Check whether it has been explicitly whitelisted that the
	 * current User may perform a specific action on the given Title
	 *
	 * @param $title Title to check
	 * @param $action string Action to check; 'edit' if unspecified
	 * @return bool True if whitelisted; otherwise false
	 */
	public function isWhitelisted( $title, $action = 'edit' ) {
		if ( !( $title instanceof Title ) ) {
			$title = Title::newFromText( $title );
		}
		$whitelist = $this->getWhitelist();
		foreach ( $whitelist as $item ) {
			if ( $item->matches( $title->getFullText(), $action ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the current blacklist
	 *
	 * @return TitleBlacklistEntry[]
	 */
	public function getBlacklist() {
		if ( is_null( $this->mBlacklist ) ) {
			$this->load();
		}
		return $this->mBlacklist;
	}

	/**
	 * Get the current whitelist
	 *
	 * @return Array of TitleBlacklistEntry items
	 */
	public function getWhitelist() {
		if ( is_null( $this->mWhitelist ) ) {
			$this->loadWhitelist();
		}
		return $this->mWhitelist;
	}

	/**
	 * Get the text of a blacklist source via HTTP
	 *
	 * @param $url string URL of the blacklist source
	 * @return string The content of the blacklist source as a string
	 */
	private static function getHttp( $url ) {
		global $messageMemc, $wgTitleBlacklistCaching;
		$key = "title_blacklist_source:" . md5( $url ); // Global shared
		$warnkey = wfMemcKey( "titleblacklistwarning", md5( $url ) );
		$result = $messageMemc->get( $key );
		$warn = $messageMemc->get( $warnkey );
		if ( !is_string( $result ) || ( !$warn && !mt_rand( 0, $wgTitleBlacklistCaching['warningchance'] ) ) ) {
			$result = Http::get( $url );
			$messageMemc->set( $warnkey, 1, $wgTitleBlacklistCaching['warningexpiry'] );
			$messageMemc->set( $key, $result, $wgTitleBlacklistCaching['expiry'] );
		}
		return $result;
	}

	/**
	 * Invalidate the blacklist cache
	 */
	public function invalidate() {
		$cache = ObjectCache::getMainWANInstance();
		$cache->delete( wfMemcKey( "title_blacklist_entries" ) );
	}

	/**
	 * Validate a new blacklist
	 *
	 * @param $blacklist array
	 * @return Array of bad entries; empty array means blacklist is valid
	 */
	public function validate( $blacklist ) {
		$badEntries = array();
		foreach ( $blacklist as $e ) {
			wfSuppressWarnings();
			$regex = $e->getRegex();
			if ( preg_match( "/{$regex}/u", '' ) === false ) {
				$badEntries[] = $e->getRaw();
			}
			wfRestoreWarnings();
		}
		return $badEntries;
	}

	/**
	 * Inidcates whether user can override blacklist on certain action.
	 *
	 * @param $action Action
	 *
	 * @return bool
	 */
	public static function userCanOverride( $user, $action ) {
		return $user->isAllowed( 'tboverride' ) ||
			( $action == 'new-account' && $user->isAllowed( 'tboverride-account' ) );
	}
}


/**
 * Represents a title blacklist entry
 */
class TitleBlacklistEntry {
	private
		$mRaw,           ///< Raw line
		$mRegex,         ///< Regular expression to match
		$mParams,        ///< Parameters for this entry
		$mFormatVersion, ///< Entry format version
		$mSource;        ///< Source of this entry

	/**
	 * Construct a new TitleBlacklistEntry.
	 *
	 * @param $regex string Regular expression to match
	 * @param $params array Parameters for this entry
	 * @param $raw string Raw contents of this line
	 */
	private function __construct( $regex, $params, $raw, $source ) {
		$this->mRaw = $raw;
		$this->mRegex = $regex;
		$this->mParams = $params;
		$this->mFormatVersion = TitleBlacklist::VERSION;
		$this->mSource = $source;
	}

	/**
	 * Returns whether this entry is capable of filtering new accounts.
	 */
	private function filtersNewAccounts() {
		global $wgTitleBlacklistUsernameSources;

		if( $wgTitleBlacklistUsernameSources === '*' ) {
			return true;
		}

		if( !$wgTitleBlacklistUsernameSources ) {
			return false;
		}

		if( !is_array( $wgTitleBlacklistUsernameSources ) ) {
			throw new Exception(
				'$wgTitleBlacklistUsernameSources must be "*", false or an array' );
		}

		return in_array( $this->mSource, $wgTitleBlacklistUsernameSources, true );
	}

	/**
	 * Check whether a user can perform the specified action
	 * on the specified Title
	 *
	 * @param $title string to check
	 * @param $action %Action to check
	 * @return bool TRUE if the the regex matches the title, and is not overridden
	 * else false if it doesn't match (or was overridden)
	 */
	public function matches( $title, $action ) {
		if ( !$title ) {
			return false;
		}

		if( $action == 'new-account' && !$this->filtersNewAccounts() ) {
			return false;
		}

		if ( isset( $this->mParams['antispoof'] ) && is_callable( 'AntiSpoof::checkUnicodeString' ) ) {
			list( $ok, $norm ) = AntiSpoof::checkUnicodeString( $title );
			if ( $ok == "OK" ) {
				list( $ver, $title ) = explode( ':', $norm, 2 );
			} else {
				wfDebugLog( 'TitleBlacklist', 'AntiSpoof could not normalize "' . $title . '".' );
			}
		}

		wfSuppressWarnings();
		$match = preg_match( "/^(?:{$this->mRegex})$/us" . ( isset( $this->mParams['casesensitive'] ) ? '' : 'i' ), $title );
		wfRestoreWarnings();

		if ( $match ) {
			if ( isset( $this->mParams['moveonly'] ) && $action != 'move' ) {
				return false;
			}
			if ( isset( $this->mParams['newaccountonly'] ) && $action != 'new-account' ) {
				return false;
			}
			if ( !isset( $this->mParams['noedit'] ) && $action == 'edit' ) {
				return false;
			}
			if ( isset( $this->mParams['reupload'] ) && $action == 'upload' ) {
				// Special:Upload also checks 'create' permissions when not reuploading
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * Create a new TitleBlacklistEntry from a line of text
	 *
	 * @param $line String containing a line of blacklist text
	 * @return TitleBlacklistEntry
	 */
	public static function newFromString( $line, $source ) {
		$raw = $line; // Keep line for raw data
		$options = array();
		// Strip comments
		$line = preg_replace( "/^\\s*([^#]*)\\s*((.*)?)$/", "\\1", $line );
		$line = trim( $line );
		// Parse the rest of message
		preg_match( '/^(.*?)(\s*<([^<>]*)>)?$/', $line, $pockets );
		@list( $full, $regex, $null, $opts_str ) = $pockets;
		$regex = trim( $regex );
		$regex = str_replace( '_', ' ', $regex ); // We'll be matching against text form
		$opts_str = trim( $opts_str );
		// Parse opts
		$opts = preg_split( '/\s*\|\s*/', $opts_str );
		foreach ( $opts as $opt ) {
			$opt2 = strtolower( $opt );
			if ( $opt2 == 'autoconfirmed' ) {
				$options['autoconfirmed'] = true;
			}
			if ( $opt2 == 'moveonly' ) {
				$options['moveonly'] = true;
			}
			if ( $opt2 == 'newaccountonly' ) {
				$options['newaccountonly'] = true;
			}
			if ( $opt2 == 'noedit' ) {
				$options['noedit'] = true;
			}
			if ( $opt2 == 'casesensitive' ) {
				$options['casesensitive'] = true;
			}
			if ( $opt2 == 'reupload' ) {
				$options['reupload'] = true;
			}
			if ( preg_match( '/errmsg\s*=\s*(.+)/i', $opt, $matches ) ) {
				$options['errmsg'] = $matches[1];
			}
			if ( $opt2 == 'antispoof' ) {
				$options['antispoof'] = true;
			}
		}
		// Process magic words
		preg_match_all( '/{{\s*([a-z]+)\s*:\s*(.+?)\s*}}/', $regex, $magicwords, PREG_SET_ORDER );
		foreach ( $magicwords as $mword ) {
			global $wgParser;	// Functions we're calling don't need, nevertheless let's use it
			switch( strtolower( $mword[1] ) ) {
				case 'ns':
					$cpf_result = CoreParserFunctions::ns( $wgParser, $mword[2] );
					if ( is_string( $cpf_result ) ) {
						$regex = str_replace( $mword[0], $cpf_result, $regex );	// All result will have the same value, so we can just use str_seplace()
					}
					break;
				case 'int':
					$cpf_result = wfMessage( $mword[2] )->inContentLanguage()->text();
					if ( is_string( $cpf_result ) ) {
						$regex = str_replace( $mword[0], $cpf_result, $regex );
					}
			}
		}
		// Return result
		if( $regex ) {
			return new TitleBlacklistEntry( $regex, $options, $raw, $source );
		} else {
			return null;
		}
	}

	/**
	 * @return string This entry's regular expression
	 */
	public function getRegex() {
		return $this->mRegex;
	}

	/**
	 * @return string This entry's raw line
	 */
	public function getRaw() {
		return $this->mRaw;
	}

	/**
	 * @return array This entry's parameters
	 */
	public function getParams() {
		return $this->mParams;
	}

	/**
	 * @return string Custom message for this entry
	 */
	public function getCustomMessage() {
		return isset( $this->mParams['errmsg'] ) ? $this->mParams['errmsg'] : null;
	}

	/**
	 * @return string The format version
	 */
	public function getFormatVersion() { return $this->mFormatVersion; }

	/**
	 * Set the format version
	 *
	 * @param $v string New version to set
	 */
	public function setFormatVersion( $v ) { $this->mFormatVersion = $v; }

	/**
	 * Return the error message name for the blacklist entry.
	 *
	 * @param $operation string Operation name (as in titleblacklist-forbidden message name)
	 *
	 * @return string The error message name
	 */
	public function getErrorMessage( $operation ) {
		$message = $this->getCustomMessage();
		// For grep:
		// titleblacklist-forbidden-edit, titleblacklist-forbidden-move,
		// titleblacklist-forbidden-upload, titleblacklist-forbidden-new-account
		return $message ? $message : "titleblacklist-forbidden-{$operation}";
	}
}

//@}
