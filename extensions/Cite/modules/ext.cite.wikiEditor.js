'use strict';

/**
 * Add Cite-specific functionality to the WikiEditor toolbar.
 * Adds a button to insert <ref> tags, and adds a help section
 * about how to use references to WikiEditor's help panel.
 *
 * @author Jon Harald Søby
 */

mw.hook( 'wikiEditor.toolbarReady' ).add( ( $textarea ) => {
	/* Add the <ref></ref> button to the toolbar */
	$textarea.wikiEditor( 'addToToolbar', {
		section: 'main',
		group: 'insert',
		tools: {
			reference: {
				label: mw.msg( 'cite-wikieditor-tool-reference' ),
				filters: [ 'body.ns-subject' ],
				type: 'button',
				oouiIcon: 'reference',
				action: {
					type: 'encapsulate',
					options: {
						pre: '<ref>',
						post: '</ref>'
					}
				}
			}
		}
	} );

	/* Add reference help to the Help section */
	const parsedRef = ( number ) => $( '<sup>' )
		.addClass( 'reference' )
		.append( $( '<a>' )
			.text( mw.message( 'cite-wikieditor-help-content-reference-example-ref-result',
				number
			).text() )
		);

	const helpRows = [
		{
			description: { html: mw.message( 'cite-wikieditor-help-content-reference-description' ).parse() },
			syntax: {
				html: mw.html.escape(
					mw.message( 'cite-wikieditor-help-content-reference-example-text1', mw.message( 'cite-wikieditor-help-content-reference-example-ref-normal', mw.message( 'cite-wikieditor-help-content-reference-example-text2', 'https://www.example.org/' ).plain() ).plain() ).plain()
				)
			},
			result: {
				html: mw.message( 'cite-wikieditor-help-content-reference-example-text1',
					parsedRef( mw.language.convertNumber( 1 ) )
				).parse()
			}
		},
		{
			description: { html: mw.message( 'cite-wikieditor-help-content-named-reference-description' ).parse() },
			syntax: {
				html: mw.html.escape(
					mw.message( 'cite-wikieditor-help-content-reference-example-text1', mw.message( 'cite-wikieditor-help-content-reference-example-ref-named', mw.message( 'cite-wikieditor-help-content-reference-example-ref-id' ).plain(), mw.message( 'cite-wikieditor-help-content-reference-example-text3' ).plain() ).plain() ).plain()
				)
			},
			result: {
				html: mw.message( 'cite-wikieditor-help-content-reference-example-text1',
					parsedRef( mw.language.convertNumber( 2 ) )
				).parse()
			}
		},
		{
			description: { html: mw.message( 'cite-wikieditor-help-content-rereference-description' ).parse() },
			syntax: {
				html: mw.html.escape(
					mw.message( 'cite-wikieditor-help-content-reference-example-text1', mw.message( 'cite-wikieditor-help-content-reference-example-ref-reuse', mw.message( 'cite-wikieditor-help-content-reference-example-ref-id' ).plain() ).plain() ).plain()
				)
			},
			result: {
				html: mw.message( 'cite-wikieditor-help-content-reference-example-text1',
					parsedRef( mw.language.convertNumber( 2 ) )
				).parse()
			}
		},
		{
			description: {
				html: mw.message( 'cite-wikieditor-help-content-sub-reference-description' ).parse()
			},
			syntax: {
				html: mw.html.escape(
					mw.message( 'cite-wikieditor-help-content-reference-example-text1',
						mw.message( 'cite-wikieditor-help-content-reference-example-ref-details',
							mw.message( 'cite-wikieditor-help-content-reference-example-ref-id' ).plain(),
							mw.message( 'cite-wikieditor-help-content-reference-example-extra-details' ).plain()
						).plain()
					).plain()
				)
			},
			result: {
				html: mw.message( 'cite-wikieditor-help-content-reference-example-text1',
					// The dot in the sub-ref number is currently also hard-coded everywhere else
					parsedRef( mw.language.convertNumber( 2 ) + '.' + mw.language.convertNumber( 1 ) )
				).parse()
			}
		},
		{
			description: { html: mw.message( 'cite-wikieditor-help-content-showreferences-description' ).parse() },
			syntax: {
				html: mw.message( 'cite-wikieditor-help-content-reference-example-reflist' ).escaped()
			},
			result: {
				html: '<ol class="references" style="margin-top: 0; margin-left: 1.2em;">' +
					'<li><span class="mw-cite-backlink"><a>' +
					mw.message( 'cite_reference_backlink_symbol' ).parse() + '</a></span> ' +
					mw.message( 'cite-wikieditor-help-content-reference-example-text2', window.location.href + '#wikiEditor-ui-toolbar' ).parse() +
					'</li>' +
					'<li><span class="mw-cite-backlink"><a>' +
					mw.message( 'cite_reference_backlink_symbol' ).parse() +
					'</a></span> ' +
					mw.message( 'cite-wikieditor-help-content-reference-example-text3' ).parse() +

					( mw.config.get( 'wgCiteSubReferencing' ) ?
						'<ol class="mw-subreference-list" style="margin-top: 0;">' +
						'<li>' +
						mw.message( 'cite-wikieditor-help-content-reference-example-extra-details' ).parse() +
						'</li></ol>' :
						'' ) +

					'</li></ol>'
			}
		}
	];

	if ( !mw.config.get( 'wgCiteSubReferencing' ) ) {
		helpRows.splice( -2, 1 );
	}

	$textarea.wikiEditor( 'addToToolbar', {
		section: 'help',
		pages: {
			references: {
				label: mw.msg( 'cite-wikieditor-help-page-references' ),
				layout: 'table',
				headings: [
					{ msg: 'wikieditor-toolbar-help-heading-description' },
					{ msg: 'wikieditor-toolbar-help-heading-syntax' },
					{ msg: 'wikieditor-toolbar-help-heading-result' }
				],
				rows: helpRows
			}
		}
	} );
} );
