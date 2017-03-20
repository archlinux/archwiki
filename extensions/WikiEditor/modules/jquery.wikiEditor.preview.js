/* Preview module for wikiEditor */
( function ( $, mw ) {
/*jshint onevar:false */
$.wikiEditor.modules.preview = {

	/**
	 * Compatibility map
	 */
	browsers: {
		// Left-to-right languages
		ltr: {
			msie: [ [ '>=', 7 ] ],
			firefox: [ [ '>=', 3 ] ],
			opera: [ [ '>=', 9.6 ] ],
			safari: [ [ '>=', 4 ] ]
		},
		// Right-to-left languages
		rtl: {
			msie: [ [ '>=', 8 ] ],
			firefox: [ [ '>=', 3 ] ],
			opera: [ [ '>=', 9.6 ] ],
			safari: [ [ '>=', 4 ] ]
		}
	},

	/**
	 * Internally used functions
	 */
	fn: {
		/**
		 * Creates a preview module within a wikiEditor
		 *
		 * @param {Object} context Context object of editor to create module in
		 */
		create: function ( context ) {
			var api = new mw.Api();

			if ( 'initialized' in context.modules.preview ) {
				return;
			}
			context.modules.preview = {
				initialized: true,
				previewText: null,
				changesText: null
			};
			context.modules.preview.$preview = context.fn.addView( {
				name: 'preview',
				titleMsg: 'wikieditor-preview-tab',
				init: function ( context ) {
					// Gets the latest copy of the wikitext
					var wikitext = context.$textarea.textSelection( 'getContents' );
					// Aborts when nothing has changed since the last preview
					if ( context.modules.preview.previewText === wikitext ) {
						return;
					}
					context.modules.preview.$preview.find( '.wikiEditor-preview-contents' ).empty();
					context.modules.preview.$preview.find( '.wikiEditor-preview-loading' ).show();
					api.post( {
						formatversion: 2,
						action: 'parse',
						title: mw.config.get( 'wgPageName' ),
						text: wikitext,
						pst: '',
						prop: 'text|modules|jsconfigvars',
						preview: true,
						disableeditsection: true,
						uselang: mw.config.get( 'wgUserLanguage' )
					} ).done( function ( data ) {
						if ( !data.parse || !data.parse.text ) {
							return;
						}

						if ( data.parse.jsconfigvars ) {
							mw.config.set( data.parse.jsconfigvars );
						}
						var loadmodules = data.parse.modules.concat(
							data.parse.modulescripts,
							data.parse.modulestyles
						);
						mw.loader.load( loadmodules );

						context.modules.preview.previewText = wikitext;
						context.modules.preview.$preview.find( '.wikiEditor-preview-loading' ).hide();
						var $content = context.modules.preview.$preview.find( '.wikiEditor-preview-contents' )
							.detach()
							.html( data.parse.text );
						$content.append( '<div class="visualClear"></div>' )
							.find( 'a:not([href^=#])' )
								.click( false );

						mw.hook( 'wikipage.content' ).fire( $content );
						context.modules.preview.$preview.append( $content );
					} );
				}
			} );

			context.$changesTab = context.fn.addView( {
				name: 'changes',
				titleMsg: 'wikieditor-preview-changes-tab',
				init: function ( context ) {
					// Gets the latest copy of the wikitext
					var wikitext = context.$textarea.textSelection( 'getContents' );
					// Aborts when nothing has changed since the last time
					if ( context.modules.preview.changesText === wikitext ) {
						return;
					}
					context.$changesTab.find( 'table.diff tbody' ).empty();
					context.$changesTab.find( '.wikiEditor-preview-loading' ).show();

					var section = $( '[name="wpSection"]' ).val();
					var postdata = {
						formatversion: 2,
						action: 'query',
						prop: 'revisions',
						titles: mw.config.get( 'wgPageName' ),
						rvdifftotext: wikitext,
						rvdifftotextpst: true,
						rvprop: '',
						rvsection: section === '' ? undefined : section
					};
					var postPromise = api.post( postdata );

					$.when( postPromise, mw.loader.using( 'mediawiki.diff.styles' ) )
					.done( function ( postResult ) {
						try {
							var diff = postResult[ 0 ].query.pages[ 0 ]
								.revisions[ 0 ].diff.body;

							context.$changesTab.find( 'table.diff tbody' )
								.html( diff )
								.append( '<div class="visualClear"></div>' );
							mw.hook( 'wikipage.diff' )
								.fire( context.$changesTab.find( 'table.diff' ) );
							context.modules.preview.changesText = wikitext;
						} catch ( e ) {
							// "data.blah is undefined" error, ignore
						}
						context.$changesTab.find( '.wikiEditor-preview-loading' ).hide();
					} );
				}
			} );

			var loadingMsg = mw.msg( 'wikieditor-preview-loading' );
			context.modules.preview.$preview
				.add( context.$changesTab )
				.append( $( '<div>' )
					.addClass( 'wikiEditor-preview-loading' )
					.append( $( '<img>' )
						.addClass( 'wikiEditor-preview-spinner' )
						.attr( {
							src: $.wikiEditor.imgPath + 'dialogs/loading.gif',
							valign: 'absmiddle',
							alt: loadingMsg,
							title: loadingMsg
						} )
					)
					.append(
						$( '<span>' ).text( loadingMsg )
					)
				)
				.append( $( '<div>' )
					.addClass( 'wikiEditor-preview-contents' )
				);
			context.$changesTab.find( '.wikiEditor-preview-contents' )
				.html( '<table class="diff"><col class="diff-marker"/><col class="diff-content"/>' +
					'<col class="diff-marker"/><col class="diff-content"/><tbody/></table>' );
		}
	}

};

}( jQuery, mediaWiki ) );
