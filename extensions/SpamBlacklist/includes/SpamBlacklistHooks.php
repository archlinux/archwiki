<?php

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;

/**
 * Hooks for the spam blacklist extension
 */
class SpamBlacklistHooks {
	/**
	 * Hook function for EditFilterMergedContent
	 *
	 * @param IContextSource $context
	 * @param Content $content
	 * @param Status $status
	 * @param string $summary
	 * @param User $user
	 * @param bool $minoredit
	 *
	 * @return bool
	 */
	public static function filterMergedContent(
		IContextSource $context,
		Content $content,
		Status $status,
		$summary,
		User $user,
		$minoredit
	) {
		$title = $context->getTitle();

		// get the link from the not-yet-saved page content.
		$editInfo = $context->getWikiPage()->prepareContentForEdit( $content );
		$pout = $editInfo->output;
		$links = array_keys( $pout->getExternalLinks() );

		// HACK: treat the edit summary as a link if it contains anything
		// that looks like it could be a URL or e-mail address.
		if ( preg_match( '/\S(\.[^\s\d]{2,}|[\/@]\S)/', $summary ) ) {
			$links[] = $summary;
		}

		$spamObj = BaseBlacklist::getSpamBlacklist();
		$matches = $spamObj->filter( $links, $title, $user );

		if ( $matches !== false ) {
			$error = new ApiMessage(
				wfMessage( 'spam-blacklisted-link', Message::listParam( $matches ) ),
				'spamblacklist',
				[
					'spamblacklist' => [ 'matches' => $matches ],
				]
			);
			$status->fatal( $error );
		}

		// Always return true, EditPage will look at $status->isOk().
		return true;
	}

	public static function onParserOutputStashForEdit(
		WikiPage $page,
		Content $content,
		ParserOutput $output,
		$summary,
		User $user
	) {
		$links = array_keys( $output->getExternalLinks() );
		$spamObj = BaseBlacklist::getSpamBlacklist();
		$spamObj->warmCachesForFilter( $page->getTitle(), $links, $user );
	}

	/**
	 * Verify that the user can send emails
	 *
	 * @param User &$user
	 * @param array &$hookErr
	 * @return bool
	 */
	public static function userCanSendEmail( &$user, &$hookErr ) {
		$blacklist = BaseBlacklist::getEmailBlacklist();
		if ( $blacklist->checkUser( $user ) ) {
			return true;
		}

		$hookErr = [ 'spam-blacklisted-email', 'spam-blacklisted-email-text', null ];

		return false;
	}

	/**
	 * Hook function for EditFilter
	 * Confirm that a local blacklist page being saved is valid,
	 * and toss back a warning to the user if it isn't.
	 *
	 * @param EditPage $editPage
	 * @param string $text
	 * @param string $section
	 * @param string &$hookError
	 * @return bool
	 */
	public static function validate( EditPage $editPage, $text, $section, &$hookError ) {
		$title = $editPage->getTitle();
		$thisPageName = $title->getPrefixedDBkey();

		if ( !BaseBlacklist::isLocalSource( $title ) ) {
			wfDebugLog( 'SpamBlacklist',
				"Spam blacklist validator: [[$thisPageName]] not a local blacklist\n"
			);
			return true;
		}

		$type = BaseBlacklist::getTypeFromTitle( $title );
		if ( $type === false ) {
			return true;
		}

		$lines = explode( "\n", $text );

		$badLines = SpamRegexBatch::getBadLines( $lines, BaseBlacklist::getInstance( $type ) );
		if ( $badLines ) {
			wfDebugLog( 'SpamBlacklist',
				"Spam blacklist validator: [[$thisPageName]] given invalid input lines: " .
					implode( ', ', $badLines ) . "\n"
			);

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
			wfDebugLog( 'SpamBlacklist',
				"Spam blacklist validator: [[$thisPageName]] ok or empty blacklist\n"
			);
		}

		return true;
	}

	/**
	 * Hook function for PageSaveComplete
	 * Clear local spam blacklist caches on page save.
	 *
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $userIdentity
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 *
	 * @return bool
	 */
	public static function pageSaveContent(
		WikiPage $wikiPage,
		UserIdentity $userIdentity,
		string $summary,
		int $flags,
		RevisionRecord $revisionRecord,
		EditResult $editResult
	) {
		if ( !BaseBlacklist::isLocalSource( $wikiPage->getTitle() ) ) {
			return true;
		}

		// This sucks because every Blacklist needs to be cleared
		foreach ( BaseBlacklist::getBlacklistTypes() as $type => $class ) {
			$blacklist = BaseBlacklist::getInstance( $type );
			$blacklist->clearCache();
		}

		return true;
	}

	/**
	 * @param UploadBase $upload
	 * @param User $user
	 * @param array|null $props
	 * @param string $comment
	 * @param string $pageText
	 * @param array|ApiMessage &$error
	 * @return bool
	 */
	public static function onUploadVerifyUpload(
		UploadBase $upload,
		User $user,
		$props,
		$comment,
		$pageText,
		&$error
	) {
		$title = $upload->getTitle();

		// get the link from the not-yet-saved page content.
		$content = ContentHandler::makeContent( $pageText, $title );
		$parserOptions = ParserOptions::newCanonical( 'canonical' );
		$output = $content->getParserOutput( $title, null, $parserOptions );
		$links = array_keys( $output->getExternalLinks() );

		// HACK: treat comment as a link if it contains anything
		// that looks like it could be a URL or e-mail address.
		if ( preg_match( '/\S(\.[^\s\d]{2,}|[\/@]\S)/', $comment ) ) {
			$links[] = $comment;
		}
		if ( !$links ) {
			return true;
		}

		$spamObj = BaseBlacklist::getSpamBlacklist();
		$matches = $spamObj->filter( $links, $title, $user );

		if ( $matches !== false ) {
			$error = new ApiMessage(
				wfMessage( 'spam-blacklisted-link', Message::listParam( $matches ) ),
				'spamblacklist',
				[
					'spamblacklist' => [ 'matches' => $matches ],
				]
			);
		}

		return true;
	}
}
