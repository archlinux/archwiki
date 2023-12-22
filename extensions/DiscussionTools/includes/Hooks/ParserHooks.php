<?php
/**
 * DiscussionTools parser hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\DiscussionTools\Hooks;

use Config;
use ConfigFactory;
use MediaWiki\Extension\DiscussionTools\CommentFormatter;
use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\Hook\ParserAfterTidyHook;
use Parser;

class ParserHooks implements
	GetDoubleUnderscoreIDsHook,
	ParserAfterTidyHook
{

	private Config $config;

	public function __construct(
		ConfigFactory $configFactory
	) {
		$this->config = $configFactory->makeConfig( 'discussiontools' );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserAfterTidy
	 *
	 * @param Parser $parser
	 * @param string &$text
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		if ( $parser->getOptions()->getInterfaceMessage() ) {
			return;
		}

		$title = $parser->getTitle();
		$pout = $parser->getOutput();

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
			// This modifies $text
			CommentFormatter::addDiscussionTools( $text, $pout, $title );

			if ( $parser->getOptions()->getIsPreview() ) {
				$text = CommentFormatter::removeInteractiveTools( $text );
				// Suppress the empty state
				$pout->setExtensionData( 'DiscussionTools-isEmptyTalkPage', null );
			}

			$pout->addModuleStyles( [
				'ext.discussionTools.init.styles',
			] );
		}
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
