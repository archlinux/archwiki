<?php

namespace Cite;

use MediaWiki\Html\Html;
use MediaWiki\Parser\Parser;

/**
 * Renderer for the actual list of references in place of the <references /> tag at the end of an
 * article.
 *
 * @license GPL-2.0-or-later
 */
class ReferenceListFormatter {

	private ErrorReporter $errorReporter;
	private AnchorFormatter $anchorFormatter;
	private ReferenceMessageLocalizer $messageLocalizer;
	private BacklinkMarkRenderer $backlinkMarkRenderer;

	public function __construct(
		ErrorReporter $errorReporter,
		AnchorFormatter $anchorFormatter,
		BacklinkMarkRenderer $backlinkMarkRenderer,
		ReferenceMessageLocalizer $messageLocalizer
	) {
		$this->errorReporter = $errorReporter;
		$this->anchorFormatter = $anchorFormatter;
		$this->messageLocalizer = $messageLocalizer;
		$this->backlinkMarkRenderer = $backlinkMarkRenderer;
	}

	/**
	 * @param Parser $parser
	 * @param array<string|int,ReferenceStackItem> $groupRefs
	 * @param bool $responsive
	 * @param bool $isSectionPreview
	 *
	 * @return string HTML
	 */
	public function formatReferences(
		Parser $parser,
		array $groupRefs,
		bool $responsive,
		bool $isSectionPreview
	): string {
		if ( !$groupRefs ) {
			return '';
		}

		$wikitext = $this->formatRefsList( $parser, $groupRefs, $isSectionPreview );
		$html = $parser->recursiveTagParse( $wikitext );
		$html = Html::rawElement( 'ol', [ 'class' => 'references' ], $html );

		if ( $responsive ) {
			$wrapClasses = [ 'mw-references-wrap' ];
			if ( count( $groupRefs ) > 10 ) {
				$wrapClasses[] = 'mw-references-columns';
			}
			// Use a DIV wrap because column-count on a list directly is broken in Chrome.
			// See https://bugs.chromium.org/p/chromium/issues/detail?id=498730.
			return Html::rawElement( 'div', [ 'class' => $wrapClasses ], $html );
		}

		return $html;
	}

	/**
	 * @param Parser $parser
	 * @param array<string|int,ReferenceStackItem> $groupRefs
	 * @param bool $isSectionPreview
	 *
	 * @return string Wikitext
	 */
	private function formatRefsList(
		Parser $parser,
		array $groupRefs,
		bool $isSectionPreview
	): string {
		// After sorting the list, we can assume that references are in the same order as their
		// numbering.  Subreferences will come immediately after their parent.
		uasort(
			$groupRefs,
			static function ( ReferenceStackItem $a, ReferenceStackItem $b ): int {
				$cmp = ( $a->numberInGroup ?? 0 ) - ( $b->numberInGroup ?? 0 );
				return $cmp ?: ( $a->subrefIndex ?? 0 ) - ( $b->subrefIndex ?? 0 );
			}
		);

		// Add new lines between the list items (ref entries) to avoid confusing tidy (T15073).
		// Note: This builds a string of wikitext, not html.
		$parserInput = "\n";
		/** @var string|bool $indented */
		$indented = false;
		foreach ( $groupRefs as $ref ) {
			if ( !$indented && $ref->hasMainRef ) {
				// Create nested list before processing the first subref.
				// The nested <ol> must be inside the parent's <li>
				if ( preg_match( '#</li>\s*$#D', $parserInput, $matches, PREG_OFFSET_CAPTURE ) ) {
					$parserInput = substr( $parserInput, 0, $matches[0][1] );
				}
				$parserInput .= Html::openElement( 'ol', [ 'class' => 'mw-subreference-list' ] );
				$indented = $matches[0][0] ?? true;
			} elseif ( $indented && !$ref->hasMainRef ) {
				// End nested list.
				$parserInput .= $this->closeIndention( $indented );
				$indented = false;
			}
			$parserInput .= $this->formatListItem( $parser, $ref, $isSectionPreview ) . "\n";
		}
		$parserInput .= $this->closeIndention( $indented );
		return $parserInput;
	}

	/**
	 * @param string|bool $closingLi
	 *
	 * @return string
	 */
	private function closeIndention( $closingLi ): string {
		if ( !$closingLi ) {
			return '';
		}

		return Html::closeElement( 'ol' ) . ( is_string( $closingLi ) ? $closingLi : '' );
	}

	/**
	 * @param Parser $parser
	 * @param ReferenceStackItem $ref
	 * @param bool $isSectionPreview
	 *
	 * @return string Wikitext, wrapped in a single <li> element
	 */
	private function formatListItem(
		Parser $parser, ReferenceStackItem $ref, bool $isSectionPreview
	): string {
		$text = $this->renderTextAndWarnings( $parser, $ref, $isSectionPreview );

		// Special case for an incomplete follow="…". This is valid e.g. in the Page:… namespace on
		// Wikisource. Note this returns a <p>, not an <li> as expected!
		if ( $ref->follow !== null ) {
			return "<p>$text</p>";
		}

		// Parameter $4 in the cite_references_link_one and cite_references_link_many messages
		$extraAttributes = '';
		if ( $ref->dir !== null ) {
			// The following classes are generated here:
			// * mw-cite-dir-ltr
			// * mw-cite-dir-rtl
			$extraAttributes = Html::expandAttributes( [ 'class' => 'mw-cite-dir-' . $ref->dir ] );
		}

		if ( $ref->count === 1 ) {
			$backlinkId = $this->anchorFormatter->backLink( $ref->name, $ref->globalId, $ref->count );
			return $this->messageLocalizer->msg(
				'cite_references_link_one',
				$this->anchorFormatter->jumpLinkTarget( $ref->name, $ref->globalId ),
				$backlinkId,
				$text,
				$extraAttributes
			)->plain();
		}

		$backlinks = [];
		for ( $i = 0; $i < $ref->count; $i++ ) {
			if ( $this->backlinkMarkRenderer->isLegacyMode() ) {
				// FIXME: parent mark should be explicitly markSymbolRenderer'd if it
				// stays here.
				$parentLabel = $this->messageLocalizer->localizeDigits( (string)$ref->numberInGroup );

				$backlinks[] = $this->messageLocalizer->msg(
					'cite_references_link_many_format',
					$this->anchorFormatter->backLink( $ref->name, $ref->globalId, $i + 1 ),
					$this->backlinkMarkRenderer->getLegacyNumericMarker( $i, $ref->count, $parentLabel ),
					$this->backlinkMarkRenderer->getLegacyAlphabeticMarker( $i + 1, $ref->count, $parentLabel )
				)->plain();
			} else {
				$backlinkLabel = $this->backlinkMarkRenderer->getBacklinkMarker( $i + 1 );

				$backlinks[] = $this->messageLocalizer->msg(
					'cite_references_link_many_format',
					$this->anchorFormatter->backLink( $ref->name, $ref->globalId, $i + 1 ),
					$backlinkLabel,
					$backlinkLabel
				)->plain();
			}
		}

		// The parent of a subref might actually be unused and therefore have zero backlinks
		$linkTargetId = $ref->count > 0 ?
			$this->anchorFormatter->jumpLinkTarget( $ref->name, $ref->globalId ) : '';
		return $this->messageLocalizer->msg(
			'cite_references_link_many',
			$linkTargetId,
			$this->listToText( $backlinks ),
			$text,
			$extraAttributes
		)->plain();
	}

	/**
	 * @param Parser $parser
	 * @param ReferenceStackItem $ref
	 * @param bool $isSectionPreview
	 *
	 * @return string Wikitext
	 */
	private function renderTextAndWarnings(
		Parser $parser, ReferenceStackItem $ref, bool $isSectionPreview
	): string {
		if ( $ref->text === null ) {
			$ref->warnings[] = [
				$isSectionPreview
					? 'cite_warning_sectionpreview_no_text'
					: 'cite_error_references_no_text',
				$ref->name
			];
		}

		$text = $ref->text ?? '';
		foreach ( $ref->warnings as $warning ) {
			// @phan-suppress-next-line PhanParamTooFewUnpack
			$text .= ' ' . $this->errorReporter->plain( $parser, ...$warning );
			// FIXME: We could use a StatusValue object to get rid of duplicates
			break;
		}

		return '<span class="reference-text">' . rtrim( $text, "\n" ) . "</span>\n";
	}

	/**
	 * This does approximately the same thing as
	 * Language::listToText() but due to this being used for a
	 * slightly different purpose (people might not want , as the
	 * first separator and not 'and' as the second, and this has to
	 * use messages from the content language) I'm rolling my own.
	 *
	 * @param string[] $arr The array to format
	 *
	 * @return string Wikitext
	 */
	private function listToText( array $arr ): string {
		$lastElement = array_pop( $arr );

		if ( $arr === [] ) {
			return (string)$lastElement;
		}

		$sep = $this->messageLocalizer->msg( 'cite_references_link_many_sep' )->plain();
		$and = $this->messageLocalizer->msg( 'cite_references_link_many_and' )->plain();
		return implode( $sep, $arr ) . $and . $lastElement;
	}

}
