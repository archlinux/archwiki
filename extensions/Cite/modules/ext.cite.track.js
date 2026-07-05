'use strict';

/**
 * Adding code for analytic instruments
 */
( function () {
	/**
	 * @param {jQuery} $content
	 * @param {mw.testKitchen.InstrumentInterface} instrument
	 */
	function addTocTracking( $content, instrument ) {
		// Find the references list inside the page content
		const $referencesList = $content.find( '.mw-heading2 ~ .mw-references-wrap, .mw-heading2 ~ ol.references' ).last();
		const $headline = $referencesList.prevAll( '.mw-heading2' ).first().children( 'h2' );
		const anchor = $headline.attr( 'id' );

		if ( !anchor ) {
			return;
		}

		// Find the TOC link that points to this anchor
		const $tocLink = $( '#vector-toc a[href="#' + CSS.escape( anchor ) + '"]' );

		// Ensure we only add the handler once
		if ( $tocLink.data( 'toc-tracking-attached' ) ) {
			return;
		}
		$tocLink.data( 'toc-tracking-attached', true );

		// Add click handler
		$tocLink.on( 'click', () => {
			instrument.submitInteraction( 'click-toc-link' );
		} );
	}

	/**
	 * @param {jQuery} $content
	 * @param {mw.testKitchen.Instrument} instrument
	 */
	function addFootnoteTracking( $content, instrument ) {
		const $footnotes = $content.find( 'sup.reference a' );

		if ( $footnotes.first().data( 'footnote-tracking-attached' ) ) {
			return;
		}
		$footnotes.first().data( 'footnote-tracking-attached', true );

		// Add click handlers
		$footnotes.each( function () {
			const $anchor = $( this );
			$anchor.on( 'click', () => {
				instrument.submitInteraction( 'click-footnote-marker' );
			} );
		} );
	}

	/**
	 * Adds temporary tracking for user interactions with footnote content T415904
	 *
	 * @param {jQuery} $content
	 */
	function addFootnoteContentInstrument( $content ) {
		/** @type {mw.testKitchen.InstrumentInterface|undefined} */
		const footNoteInteractionInstrument = mw.testKitchen &&
		mw.testKitchen.getInstrument( 'cite-footnote-content-interaction' );

		if ( footNoteInteractionInstrument && footNoteInteractionInstrument.isInSample() ) {
			addTocTracking( $content, footNoteInteractionInstrument );
			addFootnoteTracking( $content, footNoteInteractionInstrument );
		}
	}

	// Adds tracking to content hook
	mw.hook( 'wikipage.content' ).add( addFootnoteContentInstrument );
}() );
