<?php

namespace MediaWiki\Extension\SpamBlacklist;

use LogicException;
use MediaWiki\Api\ApiMessage;
use MediaWiki\Content\Content;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Content\Renderer\ContentRenderer;
use MediaWiki\Content\TextContent;
use MediaWiki\Context\IContextSource;
use MediaWiki\ExternalLinks\LinkFilter;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\Message\Message;
use MediaWiki\Page\WikiPage;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Specials\Hook\UserCanChangeEmailHook;
use MediaWiki\Status\Status;
use MediaWiki\Storage\EditResult;
use MediaWiki\Storage\Hook\MultiContentSaveHook;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Storage\Hook\ParserOutputStashForEditHook;
use MediaWiki\Storage\PageEditStash;
use MediaWiki\Title\Title;
use MediaWiki\Upload\Hook\UploadVerifyUploadHook;
use MediaWiki\Upload\UploadBase;
use MediaWiki\User\Hook\UserCanSendEmailHook;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use Wikimedia\Assert\PreconditionException;
use Wikimedia\Message\MessageSpecifier;
use Wikimedia\Message\MessageValue;

/**
 * Hooks for the spam blacklist extension
 */
class Hooks implements
	MultiContentSaveHook,
	EditFilterMergedContentHook,
	UploadVerifyUploadHook,
	PageSaveCompleteHook,
	ParserOutputStashForEditHook,
	UserCanSendEmailHook,
	UserCanChangeEmailHook
{

	public function __construct(
		private readonly PermissionManager $permissionManager,
		private readonly PageEditStash $pageEditStash,
		private readonly ContentRenderer $contentRenderer,
		private readonly IContentHandlerFactory $contentHandlerFactory,
	) {
	}

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
	public function onEditFilterMergedContent(
		IContextSource $context,
		Content $content,
		Status $status,
		$summary,
		User $user,
		$minoredit
	) {
		if ( $this->permissionManager->userHasRight( $user, 'sboverride' ) ) {
			return true;
		}

		$title = $context->getTitle();
		try {
			// Try getting the update directly
			$updater = $context->getWikiPage()->getCurrentUpdate();
			$pout = $updater->getParserOutputForMetaData();
		} catch ( PreconditionException | LogicException ) {
			$stashedEdit = $this->pageEditStash->checkCache(
				$title,
				$content,
				$user
			);
			if ( $stashedEdit ) {
				// Try getting the value from edit stash
				/** @var ParserOutput $pout */
				$pout = $stashedEdit->output;
			} else {
				// Last resort, parse the page.
				$pout = $this->contentRenderer->getParserOutput(
					$content,
					$title,
					null,
					null,
					false
				);
			}
		}
		$links = LinkFilter::getIndexedUrlsNonReversed( array_keys( $pout->getExternalLinks() ) );
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
			return false;
		}

		return true;
	}

	/**
	 * @param WikiPage $page
	 * @param Content $content
	 * @param ParserOutput $output
	 * @param string $summary
	 * @param User $user
	 */
	public function onParserOutputStashForEdit(
		$page,
		$content,
		$output,
		$summary,
		$user
	) {
		$links = LinkFilter::getIndexedUrlsNonReversed( array_keys( $output->getExternalLinks() ) );
		$spamObj = BaseBlacklist::getSpamBlacklist();
		$spamObj->warmCachesForFilter( $page->getTitle(), $links, $user );
	}

	/**
	 * Verify that the user can send emails
	 *
	 * @param User $user
	 * @param array &$hookErr
	 * @return bool
	 */
	public function onUserCanSendEmail( $user, &$hookErr ) {
		if ( $this->permissionManager->userHasRight( $user, 'sboverride' ) ) {
			return true;
		}
		$blacklist = BaseBlacklist::getEmailBlacklist();
		if ( $blacklist->checkUser( $user ) ) {
			return true;
		}

		$hookErr = [ 'spam-blacklisted-email', 'spam-blacklisted-email-text', null ];

		// No other hook handler should run
		return false;
	}

	/** @inheritDoc */
	public function onUserCanChangeEmail( $user, $oldaddr, $newaddr, &$status ) {
		if ( $this->permissionManager->userHasRight( $user, 'sboverride' ) ) {
			return true;
		}

		$blacklist = BaseBlacklist::getEmailBlacklist();
		if ( $blacklist->checkUser( $user ) ) {
			return true;
		}

		$status = Status::newFatal( 'spam-blacklisted-email-change' );
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function onMultiContentSave( $renderedRevision, $user, $summary, $flags, $status ): bool {
		$title = Title::newFromPageIdentity( $renderedRevision->getRevision()->getPage() );
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

		$content = $renderedRevision->getRevision()->getContent( SlotRecord::MAIN );
		if ( !$content instanceof TextContent ) {
			return true;
		}
		$text = $content->getText();
		$lines = explode( "\n", $text );

		$badLines = SpamRegexBatch::getBadLines( $lines, BaseBlacklist::getInstance( $type ) );
		if ( $badLines ) {
			wfDebugLog( 'SpamBlacklist',
				"Spam blacklist validator: [[$thisPageName]] given invalid input lines: " .
				implode( ', ', $badLines ) . "\n"
			);

			$badList = "*<code>" .
				implode( "</code>\n*<code>",
					array_map( wfEscapeWikiText( ... ), $badLines ) ) .
				"</code>\n";
			// We're passing a MessageValue to the StatusValue here so the parameters aren't wikitext-escaped.
			$status->fatal( MessageValue::new(
				'spam-invalid-lines-error',
				[
					Message::numParam( count( $badLines ) ),
					$badList,
				]
			) );
			return false;
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
	 */
	public function onPageSaveComplete(
		$wikiPage,
		$userIdentity,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	) {
		if ( !BaseBlacklist::isLocalSource( $wikiPage->getTitle() ) ) {
			return;
		}

		// This sucks because every Blacklist needs to be cleared
		foreach ( BaseBlacklist::getBlacklistTypes() as $type => $class ) {
			$blacklist = BaseBlacklist::getInstance( $type );
			$blacklist->clearCache();
		}
	}

	/**
	 * @param UploadBase $upload
	 * @param User $user
	 * @param array|null $props
	 * @param string $comment
	 * @param string $pageText
	 * @param array|MessageSpecifier &$error
	 */
	public function onUploadVerifyUpload(
		UploadBase $upload,
		User $user,
		?array $props,
		$comment,
		$pageText,
		&$error
	) {
		if ( $this->permissionManager->userHasRight( $user, 'sboverride' ) ) {
			return;
		}

		$title = $upload->getTitle();

		// get the link from the not-yet-saved page content.
		$content = $this->contentHandlerFactory->getContentHandler( $title->getContentModel() )
			->unserializeContent( $pageText );
		$parserOptions = ParserOptions::newFromAnon();
		$output = $this->contentRenderer->getParserOutput( $content, $title, null, $parserOptions );
		$links = LinkFilter::getIndexedUrlsNonReversed( array_keys( $output->getExternalLinks() ) );

		// HACK: treat comment as a link if it contains anything
		// that looks like it could be a URL or e-mail address.
		if ( preg_match( '/\S(\.[^\s\d]{2,}|[\/@]\S)/', $comment ) ) {
			$links[] = $comment;
		}
		if ( !$links ) {
			return;
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
	}
}
