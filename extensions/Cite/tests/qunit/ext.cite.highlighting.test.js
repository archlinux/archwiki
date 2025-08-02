'use strict';

/* eslint-disable no-jquery/no-global-selector */
( function () {
	QUnit.module( 'ext.cite.highlighting (Cite)', {
		beforeEach: function () {
			const $content = $( `
            <div>
                <sup id="cite_ref-foo1_2-0" class="reference">
                    <a href="#cite_note-foo1-2"><span>[</span>2<span>]</span></a>
                </sup>
                <sup id="cite_ref-foo1_2-1" class="reference">
                    <a href="#cite_note-foo1-2"><span>[</span>2<span>]</span></a>
                </sup>

                <ol class="references">
                    <li id="cite_note-foo1-2">^
                        <sup><a href="#cite_ref-foo1_2-0"><i><b>a</b></i></a></sup>
                        <sup><a href="#cite_ref-foo1_2-1"><i><b>b</b></i></a></sup>
                        <span class="reference-text">Named and reused</span>
                    </li>
                </ol>
            </div>
        ` );
			$( '#qunit-fixture' ).html( $content );
			mw.hook( 'wikipage.content' ).fire( $( '#qunit-fixture' ) );
		}
	} );

	QUnit.test( 'highlights backlink in the reference list for the clicked reference', ( assert ) => {
		const $content = $( '#qunit-fixture' );
		const $footnoteMarkerLink = $content.find( '#cite_ref-foo1_2-1 a' );
		const $backlink = $content.find( '#cite_note-foo1-2 sup:nth-child(2) a' );

		$footnoteMarkerLink.trigger( 'click' );
		assert.true( $backlink.hasClass( 'mw-cite-targeted-backlink' ) );
	} );

	QUnit.test( 'hides clickable up-arrow when jumping back from multiple used references ', ( assert ) => {
		const $content = $( '#qunit-fixture' );
		const $footnoteMarkerLink = $content.find( '#cite_ref-foo1_2-1 a' );
		$footnoteMarkerLink.trigger( 'click' );

		const $backlink = $content.find( '#cite_note-foo1-2 sup:nth-child(2) a' );
		$backlink.trigger( 'click' );
		// eslint-disable-next-line no-jquery/no-sizzle
		assert.false( $backlink.is( ':visible' ) );
	} );

	QUnit.test( 'uses the last clicked target for the clickable up arrow on multiple used references', ( assert ) => {
		const $content = $( '#qunit-fixture' );

		const $footnoteMarkerLink2 = $content.find( '#cite_ref-foo1_2-1 a' );
		const $footnoteMarkerLink1 = $content.find( '#cite_ref-foo1_2-0 a' );
		$footnoteMarkerLink2.trigger( 'click' );
		$footnoteMarkerLink1.trigger( 'click' );

		const $backlinkToFirstMarker = $content.find( 'li#cite_note-foo1-2 a.mw-cite-up-arrow-backlink' );
		assert.strictEqual( $backlinkToFirstMarker.length, 1 );

		// The backlink href points to the id of the last clicked ref marker
		assert.strictEqual(
			$backlinkToFirstMarker.attr( 'href' ),
			'#cite_ref-foo1_2-0'
		);
	} );
}() );
