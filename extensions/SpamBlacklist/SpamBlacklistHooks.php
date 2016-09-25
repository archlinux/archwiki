<?php

use MediaWiki\Auth\AuthManager;

/**
 * Hooks for the spam blacklist extension
 */
class SpamBlacklistHooks {

    /**
     * T99257: Extension registration does not properly support 2d arrays so set it as a global for now
     */
	public static function registerExtension() {
		global $wgSpamBlacklistFiles, $wgBlacklistSettings, $wgSpamBlacklistSettings;
		global $wgDisableAuthManager, $wgAuthManagerAutoConfig;

		$wgBlacklistSettings = array(
			'spam' => array(
				'files' => array( "https://meta.wikimedia.org/w/index.php?title=Spam_blacklist&action=raw&sb_ver=1" )
			)
		);

		/**
		 * @deprecated
		 */
		$wgSpamBlacklistFiles =& $wgBlacklistSettings['spam']['files'];

		/**
		 * @deprecated
		 */
		$wgSpamBlacklistSettings =& $wgBlacklistSettings['spam'];

		if ( class_exists( AuthManager::class ) && !$wgDisableAuthManager ) {
			$wgAuthManagerAutoConfig['preauth'][SpamBlacklistPreAuthenticationProvider::class] =
				[ 'class' => SpamBlacklistPreAuthenticationProvider::class ];
		} else {
			Hooks::register( 'AbortNewAccount', 'SpamBlacklistHooks::abortNewAccount' );
		}
	}

	/**
	 * Hook function for EditFilterMergedContent
	 *
	 * @param IContextSource $context
	 * @param Content        $content
	 * @param Status         $status
	 * @param string         $summary
	 * @param User           $user
	 * @param bool           $minoredit
	 *
	 * @return bool
	 */
	static function filterMergedContent( IContextSource $context, Content $content, Status $status, $summary, User $user, $minoredit ) {
		$title = $context->getTitle();

		if ( isset( $title->spamBlackListFiltered ) && $title->spamBlackListFiltered ) {
			// already filtered
			return true;
		}

		// get the link from the not-yet-saved page content.
		$editInfo = $context->getWikiPage()->prepareContentForEdit( $content );
		$pout = $editInfo->output;
		$links = array_keys( $pout->getExternalLinks() );

		// HACK: treat the edit summary as a link if it contains anything
		// that looks like it could be a URL or e-mail address.
		if ( preg_match( '/\S(\.[^\s\d]{2,}|[\/@]\S)/', $summary ) ) {
			$links[] = $summary;
		}
		if ( !$links ) {
			return true;
		}

		$spamObj = BaseBlacklist::getInstance( 'spam' );
		$matches = $spamObj->filter( $links, $title );

		if ( $matches !== false ) {
			$status->fatal( 'spamprotectiontext' );

			foreach ( $matches as $match ) {
				$status->fatal( 'spamprotectionmatch', $match );
			}
		}

		// Always return true, EditPage will look at $status->isOk().
		return true;
	}

	public static function onParserOutputStashForEdit( WikiPage $page ) {
		$spamObj = BaseBlacklist::getInstance( 'spam' );
		$spamObj->warmCachesForFilter( $page->getTitle() );
	}

	/**
	 * Hook function for APIEditBeforeSave.
	 * This allows blacklist matches to be reported directly in the result structure
	 * of the API call.
	 *
	 * @param $editPage EditPage
	 * @param $text string
	 * @param $resultArr array
	 * @return bool
	 */
	static function filterAPIEditBeforeSave( $editPage, $text, &$resultArr ) {
		$title = $editPage->mArticle->getTitle();

		// get the links from the not-yet-saved page content.
		$content = ContentHandler::makeContent(
			$text,
			$editPage->getTitle(),
			$editPage->contentModel,
			$editPage->contentFormat
		);
		$editInfo = $editPage->mArticle->prepareContentForEdit( $content, null, null, $editPage->contentFormat );
		$pout = $editInfo->output;
		$links = array_keys( $pout->getExternalLinks() );

		// HACK: treat the edit summary as a link
		$summary = $editPage->summary;
		if ( $summary !== '' ) {
			$links[] = $summary;
		}

		$spamObj = BaseBlacklist::getInstance( 'spam' );
		$matches = $spamObj->filter( $links, $title );

		if ( $matches !== false ) {
			$resultArr['spamblacklist'] = implode( '|', $matches );
		}

		// mark the title, so filterMergedContent can skip it.
		$title->spamBlackListFiltered = true;

		// return convention for hooks is the inverse of $wgFilterCallback
		return ( $matches === false );
	}

	/**
	 * Verify that the user can send emails
	 *
	 * @param $user User
	 * @param $hookErr array
	 * @return bool
	 */
	public static function userCanSendEmail( &$user, &$hookErr ) {
		/** @var $blacklist EmailBlacklist */
		$blacklist = BaseBlacklist::getInstance( 'email' );
		if ( $blacklist->checkUser( $user ) ) {
			return true;
		}

		$hookErr = array( 'spam-blacklisted-email', 'spam-blacklisted-email-text', null );

		return false;
	}

	/**
	 * Processes new accounts for valid email addresses
	 *
	 * @param $user User
	 * @param $abortError
	 * @return bool
	 */
	public static function abortNewAccount( $user, &$abortError ) {
		/** @var $blacklist EmailBlacklist */
		$blacklist = BaseBlacklist::getInstance( 'email' );
		if ( $blacklist->checkUser( $user ) ) {
			return true;
		}

		$abortError = wfMessage( 'spam-blacklisted-email-signup' )->escaped();
		return false;
	}

	/**
	 * Hook function for EditFilter
	 * Confirm that a local blacklist page being saved is valid,
	 * and toss back a warning to the user if it isn't.
	 *
	 * @param $editPage EditPage
	 * @param $text string
	 * @param $section string
	 * @param $hookError string
	 * @return bool
	 */
	static function validate( $editPage, $text, $section, &$hookError ) {
		$thisPageName = $editPage->mTitle->getPrefixedDBkey();

		if( !BaseBlacklist::isLocalSource( $editPage->mTitle ) ) {
			wfDebugLog( 'SpamBlacklist', "Spam blacklist validator: [[$thisPageName]] not a local blacklist\n" );
			return true;
		}

		$type = BaseBlacklist::getTypeFromTitle( $editPage->mTitle );
		if ( $type === false ) {
			return true;
		}

		$lines = explode( "\n", $text );

		$badLines = SpamRegexBatch::getBadLines( $lines, BaseBlacklist::getInstance( $type ) );
		if( $badLines ) {
			wfDebugLog( 'SpamBlacklist', "Spam blacklist validator: [[$thisPageName]] given invalid input lines: " .
				implode( ', ', $badLines ) . "\n" );

			$badList = "*<code>" .
				implode( "</code>\n*<code>",
					array_map( 'wfEscapeWikiText', $badLines ) ) .
				"</code>\n";
			$hookError =
				"<div class='errorbox'>" .
					wfMessage( 'spam-invalid-lines' )->numParams( $badLines )->text() . "<br />" .
					$badList .
					"</div>\n" .
					"<br clear='all' />\n";
		} else {
			wfDebugLog( 'SpamBlacklist', "Spam blacklist validator: [[$thisPageName]] ok or empty blacklist\n" );
		}

		return true;
	}

	/**
	 * Hook function for PageContentSaveComplete
	 * Clear local spam blacklist caches on page save.
	 *
	 * @param Page $wikiPage
	 * @param User     $user
	 * @param Content  $content
	 * @param string   $summary
	 * @param bool     $isMinor
	 * @param bool     $isWatch
	 * @param string   $section
	 * @param int      $flags
	 * @param Revision|null $revision
	 * @param Status   $status
	 * @param int      $baseRevId
	 *
	 * @return bool
	 */
	static function pageSaveContent(
		Page $wikiPage,
		User $user,
		Content $content,
		$summary,
		$isMinor,
		$isWatch,
		$section,
		$flags,
		$revision,
		Status $status,
		$baseRevId
	) {
		if ( !BaseBlacklist::isLocalSource( $wikiPage->getTitle() ) ) {
			return true;
		}

		// This sucks because every Blacklist needs to be cleared
		foreach ( BaseBlacklist::getBlacklistTypes() as $type => $class ) {
			$blacklist = BaseBlacklist::getInstance( $type );
			$blacklist->clearCache();
		}

		if ( $revision ) {
			BaseBlacklist::getInstance( 'spam' )
				->doLogging( $user, $wikiPage->getTitle(), $revision );
		}

		return true;
	}
}
