<?php
/**
 * DiscussionTools parser hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\DiscussionTools\Hooks;

use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Extension\DiscussionTools\BatchModifyElements;
use MediaWiki\Extension\DiscussionTools\CommentFormatter;
use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Hook\ParserAfterTidyHook;
use MediaWiki\Parser\Hook\ParserOutputPostCacheTransformHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\ParserOutputFlags;
use MediaWiki\Title\Title;

class ParserHooks implements
	ParserOutputPostCacheTransformHook,
	GetDoubleUnderscoreIDsHook,
	ParserAfterTidyHook
{

	private readonly Config $config;

	public function __construct(
		ConfigFactory $configFactory
	) {
		$this->config = $configFactory->makeConfig( 'discussiontools' );
	}

	private function transformHtml(
		ParserOutput $pout, string &$html, Title $title, bool $isPreview
	): void {
		// This condition must be unreliant on current enablement config or user preference.
		// In other words, include parser output of talk pages with DT disabled.
		//
		// This is similar to HookUtils::isAvailableForTitle, but instead of querying the
		// database for the latest metadata of a page that exists, we check metadata of
		// the given ParserOutput object only (this runs before the edit is saved).
		if ( $title->isTalkPage() || $pout->getNewSection() ) {
			$talkExpiry = $this->config->get( 'DiscussionToolsTalkPageParserCacheExpiry' );
			// Override parser cache expiry of talk pages (T280605).
			// Note, this can only shorten it. MediaWiki ignores values higher than the default.
			// NOTE: this currently has no effect for Parsoid read
			// views, since parsoid executes this method as a
			// post-cache transform.  *However* future work may allow
			// caching of intermediate results of the "post cache"
			// transformation pipeline, in which case this code will
			// again be effective. (More: T350626)
			if ( $talkExpiry > 0 ) {
				$pout->updateCacheExpiry( $talkExpiry );
			}
		}

		// Always apply the DOM transform if DiscussionTools are available for this page,
		// to allow linking to individual comments from Echo 'mention' and 'edit-user-talk'
		// notifications (T253082, T281590), and to reduce parser cache fragmentation (T279864).
		// The extra buttons are hidden in CSS (ext.discussionTools.init.styles module) when
		// the user doesn't have DiscussionTools features enabled.
		if ( HookUtils::isAvailableForTitle( $title ) ) {
			// This modifies $html
			CommentFormatter::addDiscussionTools( $html, $pout, $title );

			if ( $isPreview ) {
				$batchModifyElements = new BatchModifyElements();
				CommentFormatter::removeInteractiveTools( $batchModifyElements );
				$html = $batchModifyElements->apply( $html );
				// Suppress the empty state
				$pout->setExtensionData( 'DiscussionTools-isEmptyTalkPage', null );
				$pout->setExtensionData( 'DiscussionTools-isPreview', true );
			}

			$pout->addModuleStyles( [ 'ext.discussionTools.init.styles' ] );
		}
	}

	/**
	 * For now, this hook only runs on Parsoid HTML. Eventually, this is likely
	 * to be run for legacy HTML but that requires ParserCache storage to be allocated
	 * for DiscussionTools HTML which will be purused separately.
	 *
	 * @inheritDoc
	 */
	public function onParserOutputPostCacheTransform( $parserOutput, &$text, &$options ): void {
		$popts = $options[ 'parserOptions' ] ?? null;
		if ( $popts instanceof ParserOptions && $popts->isMessage() ) {
			return;
		}

		// as per Id73a1b5751cfc055e84188bcb19583c72b84032f, this is always set when transforming HTML
		// in DiscussionTools, so it's a reasonable way to not execute it twice for legacy content coming
		// from the ParserCache
		if ( ( $parserOutput->getExtensionData( 'DiscussionTools-isEmptyTalkPage' ) !== null ||
				// well, there is an exception to that rule: if we're in preview mode, AND we're previewing in legacy
				// mode, we reset DiscussionTools-isEmptyTalkPage to null - so in that case we also set isPreview, so
				// that we can catch this case here. By definition this can't come from the cache; so there's no risk
				// that the newly introduced flag isn't set if it is needed.
				// TODO this MUST disappear once ParserAfterTidy is removed - this is only a temporary fix that won't
				// be necessary once that happens.
				$parserOutput->getExtensionData( 'DiscussionTools-isPreview' ) ) &&
			 // T419830: sometimes parsoid recursive processes a small
			 // component of the page?  But we should always run this pass
			 // if we're using Parsoid.
			 !( $popts instanceof ParserOptions && $popts->getUseParsoid() )
		) {
			return;
		}

		$linkTarget = $parserOutput->getTitle();
		if ( !$linkTarget ) {
			return;
		}

		$isPreview = $parserOutput->getOutputFlag( ParserOutputFlags::IS_PREVIEW );
		$title = Title::newFromLinkTarget( $linkTarget );
		$this->transformHtml( $parserOutput, $text, $title, $isPreview );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserAfterTidy
	 *
	 * @param Parser $parser
	 * @param string &$text
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		$pOpts = $parser->getOptions();
		if ( $pOpts->isMessage() ) {
			return;
		}

		$output = $parser->getOutput();
		// if we have a post-processing cache for legacy parses, we use the post-processing pipeline instead
		// (and cache it there)
		// we also don't want to try to do the post-processing if we're getting a page from the cache that
		// doesn't yet hold its title.
		if ( $output->getTitle() !== null &&
			MediaWikiServices::getInstance()->getMainConfig()->get( MainConfigNames::UsePostprocCacheLegacy ) ) {
			return;
		}
		// Don't invoke this hook from the ::parseExtensionTagAsTopLevelDoc()
		// method in Parsoid, either.
		if ( $pOpts->getUseParsoid() ) {
			return;
		}

		$this->transformHtml(
			$output, $text, $parser->getTitle(), $pOpts->getIsPreview()
		);
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetDoubleUnderscoreIDs
	 *
	 * @param string[] &$doubleUnderscoreIDs
	 * @return bool|void
	 */
	public function onGetDoubleUnderscoreIDs( &$doubleUnderscoreIDs ) {
		$doubleUnderscoreIDs[] = 'archivedtalk';
		$doubleUnderscoreIDs[] = 'notalk';
	}
}
