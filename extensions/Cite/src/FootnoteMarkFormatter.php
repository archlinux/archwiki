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

	public function __construct(
		private readonly AnchorFormatter $anchorFormatter,
		private readonly MarkSymbolRenderer $markSymbolRenderer,
		private readonly ReferenceMessageLocalizer $messageLocalizer,
	) {
	}

	/**
	 * Generates the clickable <sup>[1]</sup> wikitext snippets for the numeric footnote markers
	 * in an article.
	 *
	 * @param ReferenceStackItem $ref
	 * @return string Wikitext
	 */
	public function linkRef( ReferenceStackItem $ref ): string {
		$label = $this->markSymbolRenderer->renderFootnoteMarkLabel(
			$ref->group, $ref->numberInGroup, $ref->subrefIndex );
		return $this->messageLocalizer->msg(
			'cite_reference_link',
			$this->anchorFormatter->backlinkTarget( $ref->name, $ref->globalId, $ref->count ),
			$this->anchorFormatter->wikitextSafeNoteLink( $ref->name, $ref->globalId ),
			Sanitizer::safeEncodeAttribute( $label )
		)->plain();
	}

}
