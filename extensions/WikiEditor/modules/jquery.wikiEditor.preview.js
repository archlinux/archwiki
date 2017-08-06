/* Preview module for wikiEditor */
( function ( $, mw ) {
	$.wikiEditor.modules.preview = {

		/**
		 * Compatibility map
		 */
		browsers: {
			// Left-to-right languages
			ltr: {
				msie: [ [ '>=', 9 ] ],
				firefox: [ [ '>=', 4 ] ],
				opera: [ [ '>=', '10.5' ] ],
				safari: [ [ '>=', 5 ] ],
				chrome: [ [ '>=', 5 ] ]
			},
			// Right-to-left languages
			rtl: {
				msie: [ [ '>=', 9 ] ],
				firefox: [ [ '>=', 4 ] ],
				opera: [ [ '>=', '10.5' ] ],
				safari: [ [ '>=', 5 ] ],
				chrome: [ [ '>=', 5 ] ]
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
				var loadingMsg,
					api = new mw.Api();

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
							var loadmodules, $content;
							if ( !data.parse || !data.parse.text ) {
								return;
							}

							if ( data.parse.jsconfigvars ) {
								mw.config.set( data.parse.jsconfigvars );
							}
							loadmodules = data.parse.modules.concat(
								data.parse.modulescripts,
								data.parse.modulestyles
							);
							mw.loader.load( loadmodules );

							context.modules.preview.previewText = wikitext;
							context.modules.preview.$preview.find( '.wikiEditor-preview-loading' ).hide();
							$content = context.modules.preview.$preview.find( '.wikiEditor-preview-contents' )
								.detach()
								.html( data.parse.text );
							$content.append( '<div class="visualClear"></div>' )
								.find( 'a:not([href^="#"])' )
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
						var section, postdata, postPromise,
							wikitext = context.$textarea.textSelection( 'getContents' );
						// Aborts when nothing has changed since the last time
						if ( context.modules.preview.changesText === wikitext ) {
							return;
						}
						context.$changesTab.find( 'table.diff tbody' ).empty();
						context.$changesTab.find( '.wikiEditor-preview-loading' ).show();

						section = $( '[name="wpSection"]' ).val();
						postdata = {
							formatversion: 2,
							action: 'query',
							prop: 'revisions',
							titles: mw.config.get( 'wgPageName' ),
							rvdifftotext: wikitext,
							rvdifftotextpst: true,
							rvprop: '',
							rvsection: section === '' ? undefined : section
						};
						postPromise = api.post( postdata );

						$.when( postPromise, mw.loader.using( 'mediawiki.diff.styles' ) )
						.done( function ( postResult ) {
							var diff;
							try {
								diff = postResult[ 0 ].query.pages[ 0 ]
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

				loadingMsg = mw.msg( 'wikieditor-preview-loading' );
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
