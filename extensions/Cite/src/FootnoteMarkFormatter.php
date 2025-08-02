<?php

namespace Cite;

use MediaWiki\Parser\Sanitizer;

/**
 * Footnote markers in the context of the Cite extension are the numbers in the article text, e.g.
 * [1], that can be hovered or clicked to be able to read the attached footnote.
 *
 * @license GPL-2.0-or-later
 */
class FootnoteMarkFormatter {

	private AnchorFormatter $anchorFormatter;
	private MarkSymbolRenderer $markSymbolRenderer;
	private ReferenceMessageLocalizer $messageLocalizer;

	public function __construct(
		AnchorFormatter $anchorFormatter,
		MarkSymbolRenderer $markSymbolRenderer,
		ReferenceMessageLocalizer $messageLocalizer
	) {
		$this->anchorFormatter = $anchorFormatter;
		$this->markSymbolRenderer = $markSymbolRenderer;
		$this->messageLocalizer = $messageLocalizer;
	}

	/**
	 * Generates the clickable <sup>[1]</sup> wikitext snippets for the numeric footnote markers
	 * in an article.
	 *
	 * @param ReferenceStackItem $ref
	 * @return string Wikitext
	 */
	public function linkRef( ReferenceStackItem $ref ): string {
		$label = $this->markSymbolRenderer->makeLabel( $ref->group, $ref->numberInGroup, $ref->subrefIndex );
		return $this->messageLocalizer->msg(
			'cite_reference_link',
			$this->anchorFormatter->backLinkTarget( $ref->name, $ref->globalId, $ref->count ),
			$this->anchorFormatter->jumpLink( $ref->name, $ref->globalId ),
			Sanitizer::safeEncodeAttribute( $label )
		)->plain();
	}

}
