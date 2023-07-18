<?php

namespace MediaWiki\Extension\DiscussionTools;

use MediaWiki\Extension\DiscussionTools\ThreadItem\DatabaseThreadItem;
use MediaWiki\Linker\LinkRenderer;
use MessageLocalizer;
use TitleFormatter;
use TitleValue;

/**
 * Displays links to comments and headings represented as ThreadItems.
 */
class ThreadItemFormatter {

	private TitleFormatter $titleFormatter;
	private LinkRenderer $linkRenderer;

	public function __construct(
		TitleFormatter $titleFormatter,
		LinkRenderer $linkRenderer
	) {
		$this->titleFormatter = $titleFormatter;
		$this->linkRenderer = $linkRenderer;
	}

	/**
	 * Make a link to a thread item on the page.
	 *
	 * @param DatabaseThreadItem $item
	 * @return string
	 */
	public function makeLink( DatabaseThreadItem $item ): string {
		$title = TitleValue::newFromPage( $item->getPage() )->createFragmentTarget( $item->getId() );

		$query = [];
		if ( !$item->getRevision()->isCurrent() ) {
			$query['oldid'] = $item->getRevision()->getId();
		}

		$text = $this->titleFormatter->getPrefixedText( $title );
		$link = $this->linkRenderer->makeLink( $title, $text, [], $query );

		return $link;
	}

	/**
	 * Make a link to a thread item on the page, with additional information (used on special pages).
	 *
	 * @param DatabaseThreadItem $item
	 * @param MessageLocalizer $context
	 * @return string
	 */
	public function formatLine( DatabaseThreadItem $item, MessageLocalizer $context ): string {
		$contents = [];

		$contents[] = $this->makeLink( $item );

		if ( !$item->getRevision()->isCurrent() ) {
			$contents[] = $context->msg( 'discussiontools-findcomment-results-notcurrent' )->escaped();
		}

		if ( is_string( $item->getTranscludedFrom() ) ) {
			$contents[] = $context->msg( 'discussiontools-findcomment-results-transcluded' )->escaped();
		}

		return implode( $context->msg( 'word-separator' )->escaped(), $contents );
	}

}
