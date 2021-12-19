/**
 * Configuration of Dialog module for wikiEditor
 */
( function () {

	var hasOwn = Object.prototype.hasOwnProperty,
		toolbarModule = require( './jquery.wikiEditor.toolbar.js' ),
		configData = require( './data.json' );

	function triggerButtonClick( element ) {
		// The dialog action should always be a DOMElement.
		var dialogAction = $( element ).data( 'dialogaction' );
		var $button = dialogAction ? $( dialogAction ) : $( element ).find( 'button' ).first();
		// Since we're reading from data attribute, make sure we got an element before clicking.
		// Note when closing a dialog this can be false leading to TypeError: $button.trigger is not a function
		// (T261529)
		if ( $button ) {
			$button.trigger( 'click' );
		}
	}

	module.exports = {

		replaceIcons: function ( $textarea ) {
			$textarea
				.wikiEditor( 'addToToolbar', {
					section: 'main',
					group: 'insert',
					tools: {
						link: {
							labelMsg: 'wikieditor-toolbar-tool-link',
							type: 'button',
							oouiIcon: 'link',
							action: {
								type: 'dialog',
								module: 'insert-link'
							}
						},
						file: {
							labelMsg: 'wikieditor-toolbar-tool-file',
							type: 'button',
							oouiIcon: 'image',
							action: {
								type: 'dialog',
								module: 'insert-file'
							}
						},
						reference: {
							labelMsg: 'wikieditor-toolbar-tool-reference',
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
				} )
				.wikiEditor( 'addToToolbar', {
					section: 'advanced',
					group: 'insert',
					tools: {
						table: {
							labelMsg: 'wikieditor-toolbar-tool-table',
							type: 'button',
							oouiIcon: 'table',
							action: {
								type: 'dialog',
								module: 'insert-table'
							}
						}
					}
				} )
				.wikiEditor( 'addToToolbar', {
					section: 'advanced',
					groups: {
						search: {
							tools: {
								replace: {
									labelMsg: 'wikieditor-toolbar-tool-replace',
									type: 'button',
									oouiIcon: 'articleSearch',
									action: {
										type: 'dialog',
										module: 'search-and-replace'
									}
								}
							}
						}
					}
				} );
		},

		getDefaultConfig: function () {
			return { dialogs: {
				'insert-link': {
					titleMsg: 'wikieditor-toolbar-tool-link-title',
					id: 'wikieditor-toolbar-link-dialog',
					htmlTemplate: 'dialogInsertLink.html',

					init: function () {
						var api = new mw.Api();

						function isExternalLink( s ) {
							// The following things are considered to be external links:
							// * Starts with a URL protocol
							// * Starts with www.
							// All of these are potentially valid titles, and the latter two categories match about 6300
							// titles in enwiki's ns0. Out of 6.9M titles, that's 0.09%
							/* eslint-disable no-caller */
							if ( typeof arguments.callee.regex === 'undefined' ) {
								// Cache the regex
								arguments.callee.regex =
									new RegExp( '^(' + mw.config.get( 'wgUrlProtocols' ) + '|www\\.)', 'i' );
							}
							return s.match( arguments.callee.regex );
							/* eslint-enable no-caller */
						}

						// Updates the status indicator above the target link
						function updateWidget( status, reason ) {
							$( '#wikieditor-toolbar-link-int-target-status' ).children().hide();
							$( '#wikieditor-toolbar-link-int-target' ).parent()
								.removeClass(
									'status-invalid status-external status-notexists status-exists status-loading'
								);
							if ( status ) {
								$( '#wikieditor-toolbar-link-int-target-status-' + status ).show();
								// Status classes listed above
								// eslint-disable-next-line mediawiki/class-doc
								$( '#wikieditor-toolbar-link-int-target' ).parent().addClass( 'status-' + status );
							}
							if ( status === 'invalid' ) {
								// eslint-disable-next-line no-jquery/no-sizzle
								$( '.ui-dialog:visible .ui-dialog-buttonpane button' ).first()
									.prop( 'disabled', true )
									.addClass( 'disabled' );
								if ( reason ) {
									$( '#wikieditor-toolbar-link-int-target-status-invalid' ).html( reason );
								} else {
									$( '#wikieditor-toolbar-link-int-target-status-invalid' )
										.text( mw.msg( 'wikieditor-toolbar-tool-link-int-target-status-invalid' ) );
								}

							} else {
								// eslint-disable-next-line no-jquery/no-sizzle
								$( '.ui-dialog:visible .ui-dialog-buttonpane button' ).first()
									.prop( 'disabled', false )
									.removeClass( 'disabled' );
							}
						}

						// Updates the UI to show if the page title being inputted by the user exists or not
						// accepts parameter internal for bypassing external link detection
						function updateExistence( internal ) {
							// Abort previous request
							var request = $( '#wikieditor-toolbar-link-int-target-status' ).data( 'request' ),
								target = $( '#wikieditor-toolbar-link-int-target' ).val(),
								cache = $( '#wikieditor-toolbar-link-int-target-status' ).data( 'existencecache' ),
								reasoncache = $( '#wikieditor-toolbar-link-int-target-status' ).data( 'reasoncache' );
							// ensure the internal parameter is a boolean
							if ( internal !== true ) {
								internal = false;
							}
							if ( request ) {
								request.abort();
							}
							if ( hasOwn.call( cache, target ) ) {
								updateWidget( cache[ target ], reasoncache[ target ] );
								return;
							}
							if ( target.replace( /^\s+$/, '' ) === '' ) {
								// Hide the widget when the textbox is empty
								updateWidget( false );
								return;
							}
							// If the forced internal parameter was not true, check if the target is an external link
							if ( !internal && isExternalLink( target ) ) {
								updateWidget( 'external' );
								return;
							}
							// Show loading spinner while waiting for the API to respond
							updateWidget( 'loading' );
							// Call the API to check page status, saving the request object so it can be aborted if
							// necessary.
							// This used to request a page that would show whether or not the target exists, but we can
							// also check whether it has the disambiguation property and still get existence information.
							// If the Disambiguator extension is not installed then such a property won't be set.
							$( '#wikieditor-toolbar-link-int-target-status' ).data(
								'request',
								api.get( {
									formatversion: 2,
									action: 'query',
									prop: 'pageprops',
									titles: [ target ],
									ppprop: 'disambiguation',
									errorformat: 'html',
									errorlang: mw.config.get( 'wgUserLanguage' )
								} ).done( function ( data ) {
									var reason = null;
									var status;
									if ( !data.query || !data.query.pages ) {
										// This happens in some weird cases like interwiki links
										status = false;
									} else {
										var page = data.query.pages[ 0 ];
										status = 'exists';
										if ( page.missing ) {
											status = 'notexists';
										} else if ( page.invalid ) {
											status = 'invalid';
											reason = page.invalidreason && page.invalidreason.html;
										} else if ( page.pageprops ) {
											status = 'disambig';
										}
									}
									// Cache the status of the link target if the force internal
									// parameter was not passed
									if ( !internal ) {
										cache[ target ] = status;
										reasoncache[ target ] = reason;
									}
									updateWidget( status, reason );
								} )
							);
						}
						$( '#wikieditor-toolbar-link-type-int, #wikieditor-toolbar-link-type-ext' ).on( 'click', function () {
							if ( $( '#wikieditor-toolbar-link-type-ext' ).prop( 'checked' ) ) {
								// Abort previous request
								var request = $( '#wikieditor-toolbar-link-int-target-status' ).data( 'request' );
								if ( request ) {
									request.abort();
								}
								updateWidget( 'external' );
							}
							if ( $( '#wikieditor-toolbar-link-type-int' ).prop( 'checked' ) ) {
								updateExistence( true );
							}
						} );
						// Set labels of tabs based on rel values
						$( this ).find( '[rel]' ).each( function () {
							// eslint-disable-next-line mediawiki/msg-doc
							$( this ).text( mw.msg( $( this ).attr( 'rel' ) ) );
						} );
						$( '#wikieditor-toolbar-link-int-target' ).attr( 'placeholder',
							mw.msg( 'wikieditor-toolbar-tool-link-int-target-tooltip' ) );
						$( '#wikieditor-toolbar-link-int-text' ).attr( 'placeholder',
							mw.msg( 'wikieditor-toolbar-tool-link-int-text-tooltip' ) );
						// Automatically copy the value of the internal link page title field to the link text field unless the
						// user has changed the link text field - this is a convenience thing since most link texts are going to
						// be the same as the page title - Also change the internal/external radio button accordingly
						$( '#wikieditor-toolbar-link-int-target' ).on( 'change keydown paste cut', function () {
							// $( this ).val() is the old value, before the keypress - Defer this until $( this ).val() has
							// been updated
							setTimeout( function () {
								if ( isExternalLink( $( '#wikieditor-toolbar-link-int-target' ).val() ) ) {
									$( '#wikieditor-toolbar-link-type-ext' ).prop( 'checked', true );
									updateWidget( 'external' );
								} else {
									$( '#wikieditor-toolbar-link-type-int' ).prop( 'checked', true );
									updateExistence();
								}
								if ( $( '#wikieditor-toolbar-link-int-text' ).data( 'untouched' ) ) {
									$( '#wikieditor-toolbar-link-int-text' )
										.val( $( '#wikieditor-toolbar-link-int-target' ).val() )
										.trigger( 'change' );
								}
							}, 0 );
						} );
						$( '#wikieditor-toolbar-link-int-text' ).on( 'change keydown paste cut', function () {
							var oldVal = $( this ).val(),
								that = this;
							setTimeout( function () {
								if ( $( that ).val() !== oldVal ) {
									$( that ).data( 'untouched', false );
								}
							}, 0 );
						} );
						// Add images to the page existence widget, which will be shown mutually exclusively to communicate if
						// the page exists, does not exist or the title is invalid (like if it contains a | character)
						$( '#wikieditor-toolbar-link-int-target-status' )
							.append( $( '<div>' )
								.attr( 'id', 'wikieditor-toolbar-link-int-target-status-exists' )
								.text( mw.msg( 'wikieditor-toolbar-tool-link-int-target-status-exists' ) )
							)
							.append( $( '<div>' )
								.attr( 'id', 'wikieditor-toolbar-link-int-target-status-notexists' )
								.text( mw.msg( 'wikieditor-toolbar-tool-link-int-target-status-notexists' ) )
							)
							.append( $( '<div>' )
								.attr( 'id', 'wikieditor-toolbar-link-int-target-status-invalid' )
							)
							.append( $( '<div>' )
								.attr( 'id', 'wikieditor-toolbar-link-int-target-status-external' )
								.text( mw.msg( 'wikieditor-toolbar-tool-link-int-target-status-external' ) )
							)
							.append( $( '<div>' )
								.attr( 'id', 'wikieditor-toolbar-link-int-target-status-loading' )
								.attr( 'title', mw.msg( 'wikieditor-toolbar-tool-link-int-target-status-loading' ) )
							)
							.append( $( '<div>' )
								.attr( 'id', 'wikieditor-toolbar-link-int-target-status-disambig' )
								.text( mw.msg( 'wikieditor-toolbar-tool-link-int-target-status-disambig' ) )
							)
							.data( 'existencecache', {} )
							.data( 'reasoncache', {} )
							.children().hide();

						$( '#wikieditor-toolbar-link-int-target' )
							.on( 'keyup paste cut', $.debounce( 500, updateExistence ) )
							.on( 'change', updateExistence ); // update right now

						// Title suggestions
						$( '#wikieditor-toolbar-link-int-target' ).data( 'suggcache', {} ).suggestions( {
							fetch: function () {
								var that = this,
									title = $( this ).val();

								if ( isExternalLink( title ) || title.indexOf( '|' ) !== -1 || title === '' ) {
									$( this ).suggestions( 'suggestions', [] );
									return;
								}

								var cache = $( this ).data( 'suggcache' );
								if ( hasOwn.call( cache, title ) ) {
									$( this ).suggestions( 'suggestions', cache[ title ] );
									return;
								}

								var request = api.get( {
									formatversion: 2,
									action: 'opensearch',
									search: title,
									namespace: 0
								} ).done( function ( data ) {
									cache[ title ] = data[ 1 ];
									$( that ).suggestions( 'suggestions', data[ 1 ] );
								} );
								$( this ).data( 'request', request );
							},
							cancel: function () {
								var request = $( this ).data( 'request' );
								if ( request ) {
									request.abort();
								}
							}
						} );
					},
					dialog: {
						width: 500,
						dialogClass: 'wikiEditor-toolbar-dialog',
						buttons: {
							'wikieditor-toolbar-tool-link-insert': function () {
								var that = this;

								function escapeInternalText( s ) {
									return s.replace( /(\]{2,})/g, '<nowiki>$1</nowiki>' );
								}
								function escapeExternalTarget( s ) {
									return s.replace( / /g, '%20' )
										.replace( /\[/g, '%5B' )
										.replace( /\]/g, '%5D' );
								}
								function escapeExternalText( s ) {
									return s.replace( /(\]+)/g, '<nowiki>$1</nowiki>' );
								}

								var target = $( '#wikieditor-toolbar-link-int-target' ).val();
								if ( target === '' ) {
									// eslint-disable-next-line no-alert
									alert( mw.msg( 'wikieditor-toolbar-tool-link-empty' ) );
									return;
								}

								var text = $( '#wikieditor-toolbar-link-int-text' ).val();
								if ( text.trim() === '' ) {
									// [[Foo| ]] creates an invisible link
									// Instead, generate [[Foo|]]
									text = '';
								}
								var insertText = '';
								if ( $( '#wikieditor-toolbar-link-type-int' ).is( ':checked' ) ) {
									// FIXME: Exactly how fragile is this?
									// eslint-disable-next-line no-jquery/no-sizzle
									if ( $( '#wikieditor-toolbar-link-int-target-status-invalid' ).is( ':visible' ) ) {
										// Refuse to add links to invalid titles
										// eslint-disable-next-line no-alert
										alert( mw.msg( 'wikieditor-toolbar-tool-link-int-invalid' ) );
										return;
									}

									if ( target === text || !text.length ) {
										insertText = '[[' + target + ']]';
									} else {
										insertText = '[[' + target + '|' + escapeInternalText( text ) + ']]';
									}
								} else {
									target = target.trim();
									// Prepend http:// if there is no protocol
									if ( !target.match( /^[a-z]+:\/\/./ ) ) {
										target = 'http://' + target;
									}

									// Detect if this is really an internal link in disguise
									var match = target.match( $( this ).data( 'articlePathRegex' ) );
									if ( match && !$( this ).data( 'ignoreLooksInternal' ) ) {
										var buttons = {};
										buttons[ mw.msg( 'wikieditor-toolbar-tool-link-lookslikeinternal-int' ) ] =
											function () {
												$( '#wikieditor-toolbar-link-int-target' ).val( match[ 1 ] ).trigger( 'change' );
												$( this ).dialog( 'close' );
											};
										buttons[ mw.msg( 'wikieditor-toolbar-tool-link-lookslikeinternal-ext' ) ] =
											function () {
												$( that ).data( 'ignoreLooksInternal', true );
												$( that ).closest( '.ui-dialog' ).find( 'button' ).first().trigger( 'click' );
												$( that ).data( 'ignoreLooksInternal', false );
												$( this ).dialog( 'close' );
											};
										$.wikiEditor.modules.dialogs.quickDialog(
											mw.msg( 'wikieditor-toolbar-tool-link-lookslikeinternal', match[ 1 ] ),
											{ buttons: buttons }
										);
										return;
									}

									var escTarget = escapeExternalTarget( target );
									var escText = escapeExternalText( text );

									if ( escTarget === escText ) {
										insertText = escTarget;
									} else if ( text === '' ) {
										insertText = '[' + escTarget + ']';
									} else {
										insertText = '[' + escTarget + ' ' + escText + ']';
									}
								}

								var whitespace = $( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace' );
								// Preserve whitespace in selection when replacing
								if ( whitespace ) {
									insertText = whitespace[ 0 ] + insertText + whitespace[ 1 ];
								}
								$( this ).dialog( 'close' );
								toolbarModule.fn.doAction( $( this ).data( 'context' ), {
									type: 'replace',
									options: {
										pre: insertText
									}
								}, $( this ) );

								// Blank form
								$( '#wikieditor-toolbar-link-int-target, #wikieditor-toolbar-link-int-text' ).val( '' );
								$( '#wikieditor-toolbar-link-type-int, #wikieditor-toolbar-link-type-ext' )
									.prop( 'checked', false );
							},
							'wikieditor-toolbar-tool-link-cancel': function () {
								$( this ).dialog( 'close' );
							}
						},
						open: function () {
							// Obtain the server name without the protocol. wgServer may be protocol-relative
							var serverName = mw.config.get( 'wgServer' ).replace( /^(https?:)?\/\//, '' );
							// Cache the articlepath regex
							$( this ).data( 'articlePathRegex', new RegExp(
								'^https?://' + mw.util.escapeRegExp( serverName + mw.config.get( 'wgArticlePath' ) )
									.replace( /\\\$1/g, '(.*)' ) + '$'
							) );
							// Pre-fill the text fields based on the current selection
							var context = $( this ).data( 'context' );
							var selection = context.$textarea.textSelection( 'getSelection' );
							$( '#wikieditor-toolbar-link-int-target' ).trigger( 'focus' );
							// Trigger the change event, so the link status indicator is up to date
							$( '#wikieditor-toolbar-link-int-target' ).trigger( 'change' );
							$( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace', [ '', '' ] );
							if ( selection !== '' ) {
								var matches, target, text, type;
								if ( ( matches = selection.match( /^(\s*)\[\[([^\]|]+)(\|([^\]|]*))?\]\](\s*)$/ ) ) ) {
									// [[foo|bar]] or [[foo]]
									target = matches[ 2 ];
									text = ( matches[ 4 ] ? matches[ 4 ] : matches[ 2 ] );
									type = 'int';
									// Preserve whitespace when replacing
									$( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace', [ matches[ 1 ], matches[ 5 ] ] );
								} else if ( ( matches = selection.match( /^(\s*)\[([^\] ]+)( ([^\]]+))?\](\s*)$/ ) ) ) {
									// [http://www.example.com foo] or [http://www.example.com]
									target = matches[ 2 ];
									text = ( matches[ 4 ] || '' );
									type = 'ext';
									// Preserve whitespace when replacing
									$( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace', [ matches[ 1 ], matches[ 5 ] ] );
								} else {
									// Trim any leading and trailing whitespace from the selection,
									// but preserve it when replacing
									target = text = selection.trim();
									if ( target.length < selection.length ) {
										$( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace', [
											selection.substr( 0, selection.indexOf( target.charAt( 0 ) ) ),
											selection.substr(
												selection.lastIndexOf( target.charAt( target.length - 1 ) ) + 1
											) ]
										);
									}
								}

								// Change the value by calling val() doesn't trigger the change event, so let's do that
								// ourselves
								if ( typeof text !== 'undefined' ) {
									$( '#wikieditor-toolbar-link-int-text' ).val( text ).trigger( 'change' );
								}
								if ( typeof target !== 'undefined' ) {
									$( '#wikieditor-toolbar-link-int-target' ).val( target ).trigger( 'change' );
								}
								if ( typeof type !== 'undefined' ) {
									$( '#wikieditor-toolbar-link-' + type ).prop( 'checked', true );
								}
							}
							$( '#wikieditor-toolbar-link-int-text' ).data( 'untouched',
								$( '#wikieditor-toolbar-link-int-text' ).val() ===
										$( '#wikieditor-toolbar-link-int-target' ).val()
							);
							$( '#wikieditor-toolbar-link-int-target' ).suggestions();

							// don't overwrite user's text
							if ( selection !== '' ) {
								$( '#wikieditor-toolbar-link-int-text' ).data( 'untouched', false );
							}

							if ( !$( this ).data( 'dialogkeypressset' ) ) {
								$( this ).data( 'dialogkeypressset', true );
								// Execute the action associated with the first button
								// when the user presses Enter
								$( this ).closest( '.ui-dialog' ).on( 'keypress', function ( e ) {
									if ( ( e.keyCode || e.which ) === 13 ) {
										triggerButtonClick( this );
										e.preventDefault();
									}
								} );

								// Make tabbing to a button and pressing
								// Enter do what people expect
								$( this ).closest( '.ui-dialog' ).find( 'button' ).on( 'focus', function () {
									$( this ).closest( '.ui-dialog' ).data( 'dialogaction', this );
								} );
							}
						}
					}
				},
				'insert-file': {
					titleMsg: 'wikieditor-toolbar-tool-file-title',
					id: 'wikieditor-toolbar-file-dialog',
					htmlTemplate: 'dialogInsertFile.html',
					init: function () {
						var magicWordsI18N = configData.magicWords;

						$( this ).find( '[data-i18n-magic]' )
							.text( function () {
								return magicWordsI18N[ $( this ).attr( 'data-i18n-magic' ) ][ 0 ];
							} )
							.removeAttr( 'data-i18n-magic' );

						var defaultMsg = mw.msg( 'wikieditor-toolbar-file-default' );
						$( this ).find( '#wikieditor-toolbar-file-size' )
							.attr( 'placeholder', defaultMsg )
							// The message may be long in some languages
							.attr( 'size', defaultMsg.length );
						$( this ).find( '[rel]' )
							.text( function () {
								// eslint-disable-next-line mediawiki/msg-doc
								return mw.msg( $( this ).attr( 'rel' ) );
							} )
							.removeAttr( 'rel' );

						var altHelpText = mw.msg( 'wikieditor-toolbar-file-alt-help' );
						var altHelpLabel = mw.msg( 'wikieditor-toolbar-file-alt-help-label' );
						// Expandable help message for 'alt text' field
						$( this ).find( '.wikieditor-toolbar-file-alt-help' ).text( altHelpLabel );
						$( '.wikieditor-toolbar-file-alt-help' ).on( 'click', function () {
							$( this ).text( function ( i, text ) {
								return text === altHelpLabel ? altHelpText : altHelpLabel;
							} );
						} );

						// Preload modules of file upload dialog.
						mw.loader.load( [
							'mediawiki.ForeignStructuredUpload.BookletLayout',
							'mediawiki.Upload.Dialog',
							'oojs-ui-windows'
						] );
					},
					dialog: {
						resizable: false,
						dialogClass: 'wikiEditor-toolbar-dialog',
						width: 590,
						buttons: {
							'wikieditor-toolbar-tool-file-insert': function () {
								var hasPxRgx = /.+px$/,
									magicWordsI18N = configData.magicWords;
								var fileName = $( '#wikieditor-toolbar-file-target' ).val();
								var caption = $( '#wikieditor-toolbar-file-caption' ).val();
								var fileAlt = $( '#wikieditor-toolbar-file-alt' ).val();
								var fileFloat = $( '#wikieditor-toolbar-file-float' ).val();
								var fileFormat = $( '#wikieditor-toolbar-file-format' ).val();
								var fileSize = $( '#wikieditor-toolbar-file-size' ).val();
								var whitespace = $( '#wikieditor-toolbar-file-dialog' ).data( 'whitespace' );
								// Append px to end to size if not already contains it
								if ( fileSize !== '' && !hasPxRgx.test( fileSize ) ) {
									fileSize += 'px';
								}
								if ( fileName !== '' ) {
									var fileTitle = mw.Title.newFromText( fileName );
									// Append file namespace prefix to filename if not already contains it
									if ( fileTitle && fileTitle.getNamespaceId() !== 6 ) {
										fileTitle = mw.Title.makeTitle( 6, fileName );
									}
									if ( fileTitle ) {
										fileName = fileTitle.toText();
									}
								}
								var options = [ fileSize, fileFormat, fileFloat ];
								// Filter empty values
								options = options.filter( function ( val ) {
									return val.length && val !== 'default';
								} );
								if ( fileAlt.length ) {
									options.push( magicWordsI18N.img_alt[ 0 ].replace( '$1', fileAlt ) );
								}
								if ( caption.length ) {
									options.push( caption );
								}

								var fileUse = options.length === 0 ? fileName : ( fileName + '|' + options.join( '|' ) );
								$( this ).dialog( 'close' );
								toolbarModule.fn.doAction(
									$( this ).data( 'context' ),
									{
										type: 'replace',
										options: {
											pre: whitespace[ 0 ] + '[[',
											peri: fileUse,
											post: ']]' + whitespace[ 1 ],
											ownline: true
										}
									},
									$( this )
								);

								// Restore form state
								$( [ '#wikieditor-toolbar-file-target',
									'#wikieditor-toolbar-file-caption',
									'#wikieditor-toolbar-file-alt',
									'#wikieditor-toolbar-file-size' ].join( ',' )
								).val( '' );
								$( '#wikieditor-toolbar-file-float' ).val( 'default' );
								$( '#wikieditor-toolbar-file-format' ).val( magicWordsI18N.img_thumbnail[ 0 ] );
							},
							'wikieditor-toolbar-tool-file-cancel': function () {
								$( this ).dialog( 'close' );
							},
							'wikieditor-toolbar-tool-file-upload': function () {
								$( this ).dialog( 'close' );
								mw.loader.using( [
									'mediawiki.ForeignStructuredUpload.BookletLayout',
									'mediawiki.Upload.Dialog',
									'oojs-ui-windows'
								] ).then( function () {
									var windowManager = new OO.ui.WindowManager(),
										uploadDialog = new mw.Upload.Dialog( {
											bookletClass: mw.ForeignStructuredUpload.BookletLayout
										} );

									windowManager.$element.appendTo( document.body );
									windowManager.addWindows( [ uploadDialog ] );
									windowManager.openWindow( uploadDialog );

									uploadDialog.uploadBooklet.on( 'fileSaved', function ( imageInfo ) {
										uploadDialog.close();
										windowManager.$element.remove();

										$.wikiEditor.modules.dialogs.api.openDialog( this, 'insert-file' );
										$( '#wikieditor-toolbar-file-target' ).val( imageInfo.canonicaltitle );
									} );
								} );
							}
						},
						open: function () {
							var magicWordsI18N = configData.magicWords,
								fileData = {
									pre: '',
									post: '',
									fileName: '',
									caption: '',
									fileAlt: '',
									fileSize: '',
									fileFloat: 'default',
									fileFormat: magicWordsI18N.img_thumbnail[ 0 ]
								};

							var parseFileSyntax = function ( wikitext ) {
								var escapedPipe = '\u0001';
								if ( wikitext.indexOf( escapedPipe ) !== -1 ) {
									return false;
								}
								var match = /^(\s*)\[\[(.*)\]\](\s*)$/.exec( wikitext );
								if ( !match ) {
									return false;
								}
								var result = {};
								result.pre = match[ 1 ];
								result.post = match[ 3 ];
								// Escape pipes inside links and templates,
								// then split the parameters at the remaining pipes
								var params = match[ 2 ].replace( /\[\[[^[\]]*\]\]|\{\{[^{}]\}\}/g, function ( link ) {
									return link.replace( /\|/g, escapedPipe );
								} ).split( '|' );
								var file = mw.Title.newFromText( params[ 0 ] );
								if ( !file || file.getNamespaceId() !== 6 ) {
									return false;
								}
								result.fileName = file.getMainText();
								for ( var i = 1; i < params.length; i++ ) {
									var paramOrig = params[ i ];
									var param = paramOrig.toLowerCase();
									if ( magicWordsI18N.img_right.indexOf( param ) !== -1 ) {
										result.fileFloat = magicWordsI18N.img_right[ 0 ];
									} else if ( magicWordsI18N.img_left.indexOf( param ) !== -1 ) {
										result.fileFloat = magicWordsI18N.img_left[ 0 ];
									} else if ( magicWordsI18N.img_none.indexOf( param ) !== -1 ) {
										result.fileFloat = magicWordsI18N.img_none[ 0 ];
									} else if ( magicWordsI18N.img_center.indexOf( param ) !== -1 ) {
										result.fileFloat = magicWordsI18N.img_center[ 0 ];
									} else if ( magicWordsI18N.img_thumbnail.indexOf( param ) !== -1 ) {
										result.fileFormat = magicWordsI18N.img_thumbnail[ 0 ];
									} else if ( magicWordsI18N.img_framed.indexOf( param ) !== -1 ) {
										result.fileFormat = magicWordsI18N.img_framed[ 0 ];
									} else if ( magicWordsI18N.img_frameless.indexOf( param ) !== -1 ) {
										result.fileFormat = magicWordsI18N.img_frameless[ 0 ];
									} else if ( magicWordsI18N.img_alt.indexOf( param.split( '=', 2 )[ 0 ] + '=$1' ) !== -1 ) {
										result.fileAlt = paramOrig.split( '=', 2 )[ 1 ];
									} else if ( /.+px$/.test( param ) ) {
										result.fileSize = param.replace( /px$/, '' );
									} else if ( param === '' ) {
										continue;
									} else if ( i === params.length - 1 ) { // Last param -> caption
										result.caption = paramOrig.replace( new RegExp( mw.util.escapeRegExp( escapedPipe ), 'g' ), '|' );
									} else { // Unknown param
										return false;
									}
								}
								if ( !result.fileFormat ) {
									result.fileFormat = 'default';
								}
								return result;
							};

							// Retrieve the current selection
							var context = $( this ).data( 'context' );
							var selection = context.$textarea.textSelection( 'getSelection' );

							// Pre-fill the text fields based on the current selection
							if ( selection !== '' ) {
								fileData = $.extend( fileData, parseFileSyntax( selection ) );
							}

							// Initialize the form fields
							$( '#wikieditor-toolbar-file-dialog' )
								.data( 'whitespace', [ fileData.pre, fileData.post ] );
							$( '#wikieditor-toolbar-file-target' ).val( fileData.fileName );
							$( '#wikieditor-toolbar-file-caption' ).val( fileData.caption );
							$( '#wikieditor-toolbar-file-alt' ).val( fileData.fileAlt );
							$( '#wikieditor-toolbar-file-float' ).val( fileData.fileFloat );
							$( '#wikieditor-toolbar-file-format' ).val( fileData.fileFormat );
							$( '#wikieditor-toolbar-file-size' ).val( fileData.fileSize );

							// Set focus
							$( '#wikieditor-toolbar-file-target' ).trigger( 'focus' );

							if ( !( $( this ).data( 'dialogkeypressset' ) ) ) {
								$( this ).data( 'dialogkeypressset', true );
								// Execute the action associated with the first button
								// when the user presses Enter
								$( this ).closest( '.ui-dialog' ).on( 'keypress', function ( e ) {
									if ( e.which === 13 ) {
										triggerButtonClick( this );
										e.preventDefault();
									}
								} );

								// Make tabbing to a button and pressing
								// Enter do what people expect
								$( this ).closest( '.ui-dialog' ).find( 'button' ).on( 'focus', function () {
									$( this ).closest( '.ui-dialog' ).data( 'dialogaction', this );
								} );
							}
						}
					}
				},
				'insert-table': {
					titleMsg: 'wikieditor-toolbar-tool-table-title',
					id: 'wikieditor-toolbar-table-dialog',
					htmlTemplate: 'dialogInsertTable.html',
					init: function () {
						$( this ).find( '[rel]' ).each( function () {
							// eslint-disable-next-line mediawiki/msg-doc
							$( this ).text( mw.msg( $( this ).attr( 'rel' ) ) );
						} );

						$( '#wikieditor-toolbar-table-dimensions-rows' ).val( 3 );
						$( '#wikieditor-toolbar-table-dimensions-columns' ).val( 3 );
						$( '#wikieditor-toolbar-table-wikitable' ).on( 'click', function () {
							// eslint-disable-next-line no-jquery/no-class-state
							$( '.wikieditor-toolbar-table-preview' ).toggleClass( 'wikitable' );
						} );

						// Hack for sortable preview: dynamically adding
						// sortable class doesn't work, so we use a clone
						$( '#wikieditor-toolbar-table-preview' )
							.clone()
							.attr( 'id', 'wikieditor-toolbar-table-preview2' )
							.addClass( 'sortable' )
							.insertAfter( $( '#wikieditor-toolbar-table-preview' ) )
							.hide();

						mw.loader.using( 'jquery.tablesorter', function () {
							$( '#wikieditor-toolbar-table-preview2' ).tablesorter();
						} );

						$( '#wikieditor-toolbar-table-sortable' ).on( 'click', function () {
							// Swap the currently shown one clone with the other one
							$( '#wikieditor-toolbar-table-preview' )
								.hide()
								.attr( 'id', 'wikieditor-toolbar-table-preview3' );
							$( '#wikieditor-toolbar-table-preview2' )
								.attr( 'id', 'wikieditor-toolbar-table-preview' )
								.show();
							$( '#wikieditor-toolbar-table-preview3' ).attr( 'id', 'wikieditor-toolbar-table-preview2' );
						} );

						$( '#wikieditor-toolbar-table-dimensions-header' ).on( 'click', function () {
							// Instead of show/hiding, switch the HTML around
							// We do this because the sortable tables script styles the first row,
							// visible or not
							var headerHTML = $( '.wikieditor-toolbar-table-preview-header' ).html(),
								hiddenHTML = $( '.wikieditor-toolbar-table-preview-hidden' ).html();
							$( '.wikieditor-toolbar-table-preview-header' ).html( hiddenHTML );
							$( '.wikieditor-toolbar-table-preview-hidden' ).html( headerHTML );
							var $sortable = $( '#wikieditor-toolbar-table-preview, #wikieditor-toolbar-table-preview2' )
								.filter( '.sortable' );
							mw.loader.using( 'jquery.tablesorter', function () {
								$sortable.tablesorter();
							} );
						} );
					},
					dialog: {
						resizable: false,
						dialogClass: 'wikiEditor-toolbar-dialog',
						width: 590,
						buttons: {
							'wikieditor-toolbar-tool-table-insert': function () {
								var rowsVal = $( '#wikieditor-toolbar-table-dimensions-rows' ).val(),
									colsVal = $( '#wikieditor-toolbar-table-dimensions-columns' ).val(),
									rows = parseInt( rowsVal, 10 ),
									cols = parseInt( colsVal, 10 ),
									header = $( '#wikieditor-toolbar-table-dimensions-header' ).prop( 'checked' ) ? 1 : 0;
								if ( isNaN( rows ) || isNaN( cols ) || String( rows ) !== rowsVal || String( cols ) !== colsVal || rowsVal < 0 || colsVal < 0 ) {
									// eslint-disable-next-line no-alert
									alert( mw.msg( 'wikieditor-toolbar-tool-table-invalidnumber' ) );
									return;
								}
								if ( rows + header === 0 || cols === 0 ) {
									// eslint-disable-next-line no-alert
									alert( mw.msg( 'wikieditor-toolbar-tool-table-zero' ) );
									return;
								}
								if ( ( rows * cols ) > 1000 ) {
									// eslint-disable-next-line no-alert
									alert( mw.msg( 'wikieditor-toolbar-tool-table-toomany', mw.language.convertNumber( 1000 ) ) );
									return;
								}
								var captionText = mw.msg( 'wikieditor-toolbar-tool-table-example-caption' );
								var headerText = mw.msg( 'wikieditor-toolbar-tool-table-example-header' );
								var normalText = mw.msg( 'wikieditor-toolbar-tool-table-example' );
								var table = '';
								table += '|+ ' + captionText + '\n';
								for ( var r = 0; r < rows + header; r++ ) {
									table += '|-\n';
									for ( var c = 0; c < cols; c++ ) {
										var isHeader = ( header && r === 0 );
										var delim = isHeader ? '!' : '|';
										if ( c > 0 ) {
											delim += delim;
										}
										table += delim + ' ' + ( isHeader ? headerText : normalText ) + ' ';
									}
									// Replace trailing space by newline
									// table[table.length - 1] is read-only
									table = table.substr( 0, table.length - 1 ) + '\n';
								}
								var classes = [];
								if ( $( '#wikieditor-toolbar-table-wikitable' ).is( ':checked' ) ) {
									classes.push( 'wikitable' );
								}
								if ( $( '#wikieditor-toolbar-table-sortable' ).is( ':checked' ) ) {
									classes.push( 'sortable' );
								}
								var classStr = classes.length > 0 ? ' class="' + classes.join( ' ' ) + '"' : '';
								$( this ).dialog( 'close' );
								toolbarModule.fn.doAction(
									$( this ).data( 'context' ),
									{
										type: 'replace',
										options: {
											pre: '{|' + classStr + '\n',
											peri: table,
											post: '|}',
											ownline: true
										}
									},
									$( this )
								);

								// Restore form state
								$( '#wikieditor-toolbar-table-dimensions-rows' ).val( 3 );
								$( '#wikieditor-toolbar-table-dimensions-columns' ).val( 3 );
								// Simulate clicks instead of setting values, so the according
								// actions are performed
								if ( !$( '#wikieditor-toolbar-table-dimensions-header' ).is( ':checked' ) ) {
									$( '#wikieditor-toolbar-table-dimensions-header' ).trigger( 'click' );
								}
								if ( !$( '#wikieditor-toolbar-table-wikitable' ).is( ':checked' ) ) {
									$( '#wikieditor-toolbar-table-wikitable' ).trigger( 'click' );
								}
								if ( $( '#wikieditor-toolbar-table-sortable' ).is( ':checked' ) ) {
									$( '#wikieditor-toolbar-table-sortable' ).trigger( 'click' );
								}
							},
							'wikieditor-toolbar-tool-table-cancel': function () {
								$( this ).dialog( 'close' );
							}
						},
						open: function () {
							$( '#wikieditor-toolbar-table-dimensions-rows' ).trigger( 'focus' );
							if ( !( $( this ).data( 'dialogkeypressset' ) ) ) {
								$( this ).data( 'dialogkeypressset', true );
								// Execute the action associated with the first button
								// when the user presses Enter
								$( this ).closest( '.ui-dialog' ).on( 'keypress', function ( e ) {
									if ( ( e.keyCode || e.which ) === 13 ) {
										triggerButtonClick( this );
										e.preventDefault();
									}
								} );

								// Make tabbing to a button and pressing
								// Enter do what people expect
								$( this ).closest( '.ui-dialog' ).find( 'button' ).on( 'focus', function () {
									$( this ).closest( '.ui-dialog' ).data( 'dialogaction', this );
								} );
							}
						}
					}
				},
				'search-and-replace': {
					titleMsg: 'wikieditor-toolbar-tool-replace-title',
					id: 'wikieditor-toolbar-replace-dialog',
					htmlTemplate: 'dialogReplace.html',
					init: function () {
						$( this ).find( '[rel]' ).each( function () {
							// eslint-disable-next-line mediawiki/msg-doc
							$( this ).text( mw.msg( $( this ).attr( 'rel' ) ) );
						} );

						// TODO: Find a cleaner way to share this function
						$( this ).data( 'replaceCallback', function ( mode ) {
							$( '#wikieditor-toolbar-replace-nomatch, #wikieditor-toolbar-replace-success, #wikieditor-toolbar-replace-emptysearch, #wikieditor-toolbar-replace-invalidregex' ).hide();

							// Search string cannot be empty
							var searchStr = $( '#wikieditor-toolbar-replace-search' ).val();
							if ( searchStr === '' ) {
								$( '#wikieditor-toolbar-replace-emptysearch' ).show();
								return;
							}

							// Replace string can be empty
							var replaceStr = $( '#wikieditor-toolbar-replace-replace' ).val();

							// Prepare the regular expression flags
							var flags = 'm';
							var matchCase = $( '#wikieditor-toolbar-replace-case' ).is( ':checked' );
							if ( !matchCase ) {
								flags += 'i';
							}
							var isRegex = $( '#wikieditor-toolbar-replace-regex' ).is( ':checked' );
							if ( !isRegex ) {
								searchStr = mw.util.escapeRegExp( searchStr );
							}
							var matchWord = $( '#wikieditor-toolbar-replace-word' ).is( ':checked' );
							if ( matchWord ) {
								searchStr = '\\b(?:' + searchStr + ')\\b';
							}
							if ( mode === 'replaceAll' ) {
								flags += 'g';
							}

							var regex;
							try {
								regex = new RegExp( searchStr, flags );
							} catch ( e ) {
								$( '#wikieditor-toolbar-replace-invalidregex' )
									.text( mw.msg( 'wikieditor-toolbar-tool-replace-invalidregex',
										e.message ) )
									.show();
								return;
							}

							var $textarea = $( this ).data( 'context' ).$textarea;
							var text = $textarea.textSelection( 'getContents' );
							var match = false;
							var offset, textRemainder;
							if ( mode !== 'replaceAll' ) {
								if ( mode === 'replace' ) {
									offset = $( this ).data( 'matchIndex' );
								} else {
									offset = $( this ).data( 'offset' );
								}
								textRemainder = text.substr( offset );
								match = textRemainder.match( regex );
							}
							if ( !match ) {
								// Search hit BOTTOM, continuing at TOP
								// TODO: Add a "Wrap around" option.
								offset = 0;
								textRemainder = text;
								match = textRemainder.match( regex );
							}

							if ( !match ) {
								$( '#wikieditor-toolbar-replace-nomatch' ).show();
							} else if ( mode === 'replaceAll' ) {
								$textarea.textSelection( 'setContents', text.replace( regex, replaceStr ) );
								$( '#wikieditor-toolbar-replace-success' )
									.text( mw.msg( 'wikieditor-toolbar-tool-replace-success', mw.language.convertNumber( match.length ) ) )
									.show();
								$( this ).data( 'offset', 0 );
							} else {

								var start, end;
								if ( mode === 'replace' ) {

									var actualReplacement;
									if ( isRegex ) {
										// If backreferences (like $1) are used, the actual actual replacement string will be different
										actualReplacement = match[ 0 ].replace( regex, replaceStr );
									} else {
										actualReplacement = replaceStr;
									}

									if ( match ) {
										// Do the replacement
										$textarea.textSelection( 'encapsulateSelection', {
											peri: actualReplacement,
											replace: true,
											selectionStart: offset + match.index,
											selectionEnd: offset + match.index + match[ 0 ].length,
											selectPeri: true
										} );
										// Reload the text after replacement
										text = $textarea.textSelection( 'getContents' );
									}

									// Find the next instance
									offset = offset + match.index + actualReplacement.length;
									textRemainder = text.substr( offset );
									match = textRemainder.match( regex );

									if ( match ) {
										start = offset + match.index;
										end = start + match[ 0 ].length;
									} else {
										// If no new string was found, try searching from the beginning.
										// TODO: Add a "Wrap around" option.
										textRemainder = text;
										match = textRemainder.match( regex );
										if ( match ) {
											start = match.index;
											end = start + match[ 0 ].length;
										} else {
											// Give up
											start = 0;
											end = 0;
										}
									}
								} else {
									start = offset + match.index;
									end = start + match[ 0 ].length;
								}

								$( this ).data( 'matchIndex', start );

								$textarea.textSelection( 'setSelection', {
									start: start,
									end: end } );
								$textarea.textSelection( 'scrollToCaretPosition' );
								$( this ).data( 'offset', end );
								$textarea[ 0 ].focus();
							}
						} );
					},
					dialog: {
						width: 500,
						dialogClass: 'wikiEditor-toolbar-dialog',
						modal: false,
						buttons: {
							'wikieditor-toolbar-tool-replace-button-findnext': function ( e ) {
								$( this ).closest( '.ui-dialog' ).data( 'dialogaction', e.target );
								$( this ).data( 'replaceCallback' ).call( this, 'find' );
							},
							'wikieditor-toolbar-tool-replace-button-replace': function ( e ) {
								$( this ).closest( '.ui-dialog' ).data( 'dialogaction', e.target );
								$( this ).data( 'replaceCallback' ).call( this, 'replace' );
							},
							'wikieditor-toolbar-tool-replace-button-replaceall': function ( e ) {
								$( this ).closest( '.ui-dialog' ).data( 'dialogaction', e.target );
								$( this ).data( 'replaceCallback' ).call( this, 'replaceAll' );
							},
							'wikieditor-toolbar-tool-replace-close': function () {
								$( this ).dialog( 'close' );
							}
						},
						open: function () {
							var that = this;
							$( this ).data( 'offset', 0 );
							$( this ).data( 'matchIndex', 0 );

							$( '#wikieditor-toolbar-replace-search' ).trigger( 'focus' );
							$( '#wikieditor-toolbar-replace-nomatch, #wikieditor-toolbar-replace-success, #wikieditor-toolbar-replace-emptysearch, #wikieditor-toolbar-replace-invalidregex' ).hide();
							if ( !( $( this ).data( 'onetimeonlystuff' ) ) ) {
								$( this ).data( 'onetimeonlystuff', true );
								// Execute the action associated with the first button
								// when the user presses Enter
								$( this ).closest( '.ui-dialog' ).on( 'keypress', function ( e ) {
									if ( ( e.keyCode || e.which ) === 13 ) {
										triggerButtonClick( this );
										e.preventDefault();
									}
								} );
								// Make tabbing to a button and pressing
								// Enter do what people expect
								$( this ).closest( '.ui-dialog' ).find( 'button' ).on( 'focus', function () {
									$( this ).closest( '.ui-dialog' ).data( 'dialogaction', this );
								} );
							}
							var $dialog = $( this ).closest( '.ui-dialog' );
							that = this;
							var context = $( this ).data( 'context' );
							var $textbox = context.$textarea;

							$textbox
								.on( 'keypress.srdialog', function ( e ) {
									if ( e.which === 13 ) {
										// Enter
										triggerButtonClick( $dialog );
										e.preventDefault();
									} else if ( e.which === 27 ) {
										// Escape
										$( that ).dialog( 'close' );
									}
								} );
						},
						close: function () {
							var context = $( this ).data( 'context' ),
								$textbox = context.$textarea;
							$textbox.off( 'keypress.srdialog' );
							$( this ).closest( '.ui-dialog' ).data( 'dialogaction', false );
						}
					}
				}
			} };
		}

	};

}() );
