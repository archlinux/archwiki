/**
 * Configuration of Dialog module for wikiEditor
 *
 * @private
 */
const toolbarModule = require( './jquery.wikiEditor.toolbar.js' ),
	InsertLinkTitleInputField = require( './insertlink/TitleInputField.js' ),
	LinkTextField = require( './insertlink/LinkTextField.js' ),
	LinkTypeField = require( './insertlink/LinkTypeField.js' ),
	insertLinkTitleInputField = new InsertLinkTitleInputField(),
	insertLinkLinkTextField = new LinkTextField(),
	insertLinkLinkTypeField = new LinkTypeField(),
	configData = require( './data.json' );

function triggerButtonClick( $element ) {
	// The dialog action should always be a DOMElement.
	const dialogAction = $element.data( 'dialogaction' );
	const $button = dialogAction ? $( dialogAction ) : $element.find( 'button' ).first();
	// Since we're reading from data attribute, make sure we got an element before clicking.
	// Note when closing a dialog this can be false leading to TypeError: $button.trigger is not a function
	// (T261529)
	if ( $button ) {
		$button.trigger( 'click' );
	}
}

module.exports = {
	/**
	 * @param {jQuery} $textarea
	 * @memberof module:ext.wikiEditor
	 */
	replaceIcons: function ( $textarea ) {
		$textarea
			.wikiEditor( 'addToToolbar', {
				section: 'main',
				group: 'insert',
				tools: {
					link: {
						label: mw.msg( 'wikieditor-toolbar-tool-link' ),
						type: 'button',
						oouiIcon: 'link',
						action: {
							type: 'dialog',
							module: 'insert-link'
						},
						hotkey: 75 // K
					},
					file: {
						label: mw.msg( 'wikieditor-toolbar-tool-file' ),
						type: 'button',
						oouiIcon: 'image',
						action: {
							type: 'dialog',
							module: 'insert-file'
						}
					}
				}
			} )
			.wikiEditor( 'addToToolbar', {
				section: 'advanced',
				group: 'insert',
				tools: {
					table: {
						label: mw.msg( 'wikieditor-toolbar-tool-table' ),
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
								label: mw.msg( 'wikieditor-toolbar-tool-replace' ),
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

	/**
	 * @return {Object}
	 * @memberof module:ext.wikiEditor
	 */
	getDefaultConfig: function () {
		return { dialogs: {
			'insert-link': {
				title: mw.message( 'wikieditor-toolbar-tool-link-title' ),
				id: 'wikieditor-toolbar-link-dialog',
				html: $( '<fieldset>' ).append(
					insertLinkTitleInputField.$element,
					insertLinkLinkTextField.$element,
					insertLinkLinkTypeField.$element
				),

				init: function () {
					/**
					 * Convenience function for enabling/disabling the main insert button. This is a workaround for
					 * the fact that the button isn't yet in the DOM when init() is run, so we have to query for it
					 * in the event handlers.
					 *
					 * @param {boolean} enable Whether to enable or disable the button
					 */
					const setButtonState = function ( enable ) {
						$( '.wikieditor-toolbar-tool-link-insert' ).button( 'option', 'disabled', !enable );
					};
					// Automatically copy the value of the internal link page title field to the link text field unless the
					// user has changed the link text field - this is a convenience thing since most link texts are going to
					// be the same as the page title.
					insertLinkTitleInputField.connect( this, {
						change: function ( val ) {
							insertLinkLinkTypeField.setIsExternal( insertLinkTitleInputField.isExternal() );
							insertLinkLinkTextField.setValueIfUntouched( val );
							setButtonState( val !== '' );
						},
						invalid: function () {
							setButtonState( false );
						}
					} );
					// Tell the title input field when the internal/external radio changes.
					insertLinkLinkTypeField.connect( this, {
						change: function ( isExternal ) {
							const urlMode = isExternal ?
								LinkTypeField.static.LINK_MODE_EXTERNAL :
								LinkTypeField.static.LINK_MODE_INTERNAL;
							insertLinkTitleInputField.setUrlMode( urlMode );
						}
					} );
				},
				dialog: {
					width: 500,
					dialogClass: 'wikiEditor-toolbar-dialog',
					buttons: {
						'wikieditor-toolbar-tool-link-insert': {
							class: 'wikieditor-toolbar-tool-link-insert',
							text: mw.msg( 'wikieditor-toolbar-tool-link-insert' ),
							click: function () {
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

								// Make sure that this button isn't disabled.
								if ( $( '.wikieditor-toolbar-tool-link-insert' ).button( 'option', 'disabled' ) ) {
									return;
								}

								let target = insertLinkTitleInputField.getField().getValue();
								let text = insertLinkLinkTextField.getField().getValue();
								if ( text.trim() === '' ) {
									// [[Foo| ]] creates an invisible link
									// Instead, generate [[Foo|]]
									text = '';
								}
								let insertText = '';
								if ( insertLinkLinkTypeField.isInternal() ) {
									if ( target === text || !text.length ) {
										insertText = '[[' + target + ']]';
									} else {
										insertText = '[[' + target + '|' + escapeInternalText( text ) + ']]';
									}
								} else {
									target = target.trim();
									// Prepend http:// if there is no protocol
									if ( !/^[a-z]+:\/\/./.test( target ) ) {
										target = 'http://' + target;
									}

									// Detect if this is really an internal link in disguise
									const match = target.match( $( this ).data( 'articlePathRegex' ) );
									if ( match && !$( this ).data( 'ignoreLooksInternal' ) ) {
										const buttons = {};
										const linkDialog = this;
										buttons[ mw.msg( 'wikieditor-toolbar-tool-link-lookslikeinternal-int' ) ] =
											function () {
												let titleValue = match[ 1 ];
												try {
													titleValue = decodeURI( titleValue );
												} catch ( ex ) {
													// Ignore invalid URLs; use plain titleValue instead.
												}
												insertLinkTitleInputField.getField().setValue( titleValue );
												insertLinkTitleInputField.setUrlMode( LinkTypeField.static.LINK_MODE_INTERNAL );
												$( this ).dialog( 'close' );
												// Select the first match (i.e. the value set above) so that the
												// message under the title field will be updated correctly.
												insertLinkTitleInputField.getField().selectFirstMatch();
											};
										buttons[ mw.msg( 'wikieditor-toolbar-tool-link-lookslikeinternal-ext' ) ] =
											function () {
												$( linkDialog ).data( 'ignoreLooksInternal', true );
												$( linkDialog ).closest( '.ui-dialog' ).find( 'button' ).first().trigger( 'click' );
												$( linkDialog ).data( 'ignoreLooksInternal', false );
												$( this ).dialog( 'close' );
											};
										$.wikiEditor.modules.dialogs.quickDialog(
											mw.msg( 'wikieditor-toolbar-tool-link-lookslikeinternal', match[ 1 ] ),
											{ buttons: buttons }
										);
										return;
									}

									const escTarget = escapeExternalTarget( target );
									const escText = escapeExternalText( text );

									if ( escTarget === escText ) {
										insertText = escTarget;
									} else if ( text === '' ) {
										insertText = '[' + escTarget + ']';
									} else {
										insertText = '[' + escTarget + ' ' + escText + ']';
									}
								}

								const whitespace = $( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace' );
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
								insertLinkTitleInputField.reset();
								insertLinkLinkTextField.getField().setValue( '' );
								insertLinkLinkTypeField.getField().selectItem( null );
							}
						},
						'wikieditor-toolbar-tool-link-cancel': function () {
							$( this ).dialog( 'close' );
						}
					},
					open: function () {
						// Obtain the server name without the protocol. wgServer may be protocol-relative

						const serverName = mw.config.get( 'wgServer' ).replace( /^(https?:)?\/\//, '' );
						// Cache the articlepath regex

						$( this ).data( 'articlePathRegex', new RegExp(
							'^https?://' + mw.util.escapeRegExp( serverName + mw.config.get( 'wgArticlePath' ) )
								.replace( /\\\$1/g, '(.*)' ) + '$'
						) );
						// Pre-fill the text fields based on the current selection
						const context = $( this ).data( 'context' );
						const selection = context.$textarea.textSelection( 'getSelection' );

						insertLinkTitleInputField.getField().focus();
						// Trigger the change event, so the link status indicator is up to date.
						// It may be triggered again for the selection, below.
						insertLinkTitleInputField.getField().emit( 'change', insertLinkTitleInputField.getField().getValue() );

						$( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace', [ '', '' ] );
						if ( selection !== '' ) {
							let matches, target, text, isExternal;
							if ( ( matches = selection.match( /^(\s*)\[\[([^\]|]+)(\|([^\]|]*))?\]\](\s*)$/ ) ) ) {
								// [[foo|bar]] or [[foo]]
								target = matches[ 2 ];
								text = ( matches[ 4 ] ? matches[ 4 ] : matches[ 2 ] );
								isExternal = false;
								// Preserve whitespace when replacing
								$( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace', [ matches[ 1 ], matches[ 5 ] ] );
							} else if ( ( matches = selection.match( /^(\s*)\[([^\] ]+)( ([^\]]+))?\](\s*)$/ ) ) ) {
								// [http://www.example.com foo] or [http://www.example.com]
								target = matches[ 2 ];
								text = ( matches[ 4 ] || '' );
								isExternal = true;
								// Preserve whitespace when replacing
								$( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace', [ matches[ 1 ], matches[ 5 ] ] );
							} else {
								// Trim any leading and trailing whitespace from the selection,
								// but preserve it when replacing
								target = text = selection.trim();
								if ( target.length < selection.length ) {
									$( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace', [
										selection.slice( 0, selection.indexOf( target.charAt( 0 ) ) ),
										selection.slice( selection.lastIndexOf( target.charAt( target.length - 1 ) ) + 1 ) ]
									);
								}
							}

							// Change the values of the title and text fields to the parts extracted from the selection.
							if ( typeof target !== 'undefined' ) {
								insertLinkTitleInputField.getField().setValue( target );
							}
							if ( typeof text !== 'undefined' ) {
								insertLinkLinkTextField.getField().setValue( text );
							}
							// Don't overwrite values from user's selection.
							insertLinkLinkTextField.setTouched( true );

							if ( typeof isExternal !== 'undefined' ) {
								const urlMode = isExternal ?
									LinkTypeField.static.LINK_MODE_EXTERNAL :
									LinkTypeField.static.LINK_MODE_INTERNAL;
								insertLinkTitleInputField.setUrlMode( urlMode );
							}
						}

						if ( !$( this ).data( 'dialogkeypressset' ) ) {
							$( this ).data( 'dialogkeypressset', true );
							// Execute the action associated with the first button
							// when the user presses Enter
							const $dialog = $( this ).closest( '.ui-dialog' );
							$dialog.on( 'keypress', ( e ) => {
								if ( ( e.keyCode || e.which ) === 13 ) {
									triggerButtonClick( $dialog );
									e.preventDefault();
								}
							} );

							// Make tabbing to a button and pressing
							// Enter do what people expect
							$dialog.find( 'button' ).on( 'focus', ( e ) => {
								$dialog.data( 'dialogaction', e.delegateTarget );
							} );
						}
					}
				}
			},
			'insert-file': {
				title: mw.message( 'wikieditor-toolbar-tool-file-title' ),
				id: 'wikieditor-toolbar-file-dialog',
				htmlTemplate: 'dialogInsertFile.html',
				init: function () {
					const magicWordsI18N = configData.magicWords;

					$( this ).find( '[data-i18n-magic]' )
						.text( function () {
							return magicWordsI18N[ $( this ).attr( 'data-i18n-magic' ) ][ 0 ];
						} )
						.removeAttr( 'data-i18n-magic' );

					const defaultMsg = mw.msg( 'wikieditor-toolbar-file-default' );
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

					const altHelpText = mw.msg( 'wikieditor-toolbar-file-alt-help' );
					const altHelpLabel = mw.msg( 'wikieditor-toolbar-file-alt-help-label' );
					// Expandable help message for 'alt text' field
					const $altHelp = $( this ).find( '.wikieditor-toolbar-file-alt-help' )
						.text( altHelpLabel )
						.on( 'click', () => {
							$altHelp.text( ( i, text ) => text === altHelpLabel ? altHelpText : altHelpLabel );
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
							const hasPxRgx = /.+px$/,
								magicWordsI18N = configData.magicWords;
							let fileName = $( '#wikieditor-toolbar-file-target' ).val();
							const caption = $( '#wikieditor-toolbar-file-caption' ).val();
							const fileAlt = $( '#wikieditor-toolbar-file-alt' ).val();
							const fileFloat = $( '#wikieditor-toolbar-file-float' ).val();
							const fileFormat = $( '#wikieditor-toolbar-file-format' ).val();
							let fileSize = $( '#wikieditor-toolbar-file-size' ).val();
							const whitespace = $( '#wikieditor-toolbar-file-dialog' ).data( 'whitespace' );
							// Append px to end to size if not already contains it
							if ( fileSize !== '' && !hasPxRgx.test( fileSize ) ) {
								fileSize += 'px';
							}
							if ( fileName !== '' ) {
								let fileTitle = mw.Title.newFromText( fileName );
								// Append file namespace prefix to filename if not already contains it
								if ( fileTitle && fileTitle.getNamespaceId() !== 6 ) {
									fileTitle = mw.Title.makeTitle( 6, fileName );
								}
								if ( fileTitle ) {
									fileName = fileTitle.toText();
								}
							}
							let options = [ fileSize, fileFormat, fileFloat ];
							// Filter empty values
							options = options.filter( ( val ) => val.length && val !== 'default' );
							if ( fileAlt.length ) {
								options.push( magicWordsI18N.img_alt[ 0 ].replace( '$1', fileAlt ) );
							}
							if ( caption.length ) {
								options.push( caption );
							}

							const fileUse = options.length === 0 ? fileName : ( fileName + '|' + options.join( '|' ) );
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
							] ).then( () => {
								const windowManager = new OO.ui.WindowManager(),
									uploadDialog = new mw.Upload.Dialog( {
										bookletClass: mw.ForeignStructuredUpload.BookletLayout
									} );

								windowManager.$element.appendTo( document.body );
								windowManager.addWindows( [ uploadDialog ] );
								windowManager.openWindow( uploadDialog );

								uploadDialog.uploadBooklet.on( 'fileSaved', ( imageInfo ) => {
									uploadDialog.close();
									windowManager.$element.remove();

									const context = $( this ).data( 'context' );
									$.wikiEditor.modules.dialogs.api.openDialog( context, 'insert-file' );
									$( '#wikieditor-toolbar-file-target' ).val( imageInfo.canonicaltitle );
								} );
							} );
						}
					},
					open: function () {
						const magicWordsI18N = configData.magicWords,
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

						const parseFileSyntax = function ( wikitext ) {
							const escapedPipe = '\u0001';
							if ( wikitext.includes( escapedPipe ) ) {
								return false;
							}
							const match = /^(\s*)\[\[(.*)\]\](\s*)$/.exec( wikitext );
							if ( !match ) {
								return false;
							}
							// Escape pipes inside links and templates,
							// then split the parameters at the remaining pipes
							const params = match[ 2 ].replace( /\[\[[^[\]]*\]\]|\{\{[^{}]\}\}/g, ( link ) => link.replace( /\|/g, escapedPipe ) ).split( '|' );
							const file = mw.Title.newFromText( params[ 0 ] );
							if ( !file || file.getNamespaceId() !== 6 ) {
								return false;
							}
							const result = {
								pre: match[ 1 ],
								post: match[ 3 ],
								fileName: file.getMainText()
							};
							for ( let i = 1; i < params.length; i++ ) {
								const paramOrig = params[ i ];
								const param = paramOrig.toLowerCase();
								if ( magicWordsI18N.img_right.includes( param ) ) {
									result.fileFloat = magicWordsI18N.img_right[ 0 ];
								} else if ( magicWordsI18N.img_left.includes( param ) ) {
									result.fileFloat = magicWordsI18N.img_left[ 0 ];
								} else if ( magicWordsI18N.img_none.includes( param ) ) {
									result.fileFloat = magicWordsI18N.img_none[ 0 ];
								} else if ( magicWordsI18N.img_center.includes( param ) ) {
									result.fileFloat = magicWordsI18N.img_center[ 0 ];
								} else if ( magicWordsI18N.img_thumbnail.includes( param ) ) {
									result.fileFormat = magicWordsI18N.img_thumbnail[ 0 ];
								} else if ( magicWordsI18N.img_framed.includes( param ) ) {
									result.fileFormat = magicWordsI18N.img_framed[ 0 ];
								} else if ( magicWordsI18N.img_frameless.includes( param ) ) {
									result.fileFormat = magicWordsI18N.img_frameless[ 0 ];
								} else if ( magicWordsI18N.img_alt.includes( param.split( '=', 2 )[ 0 ] + '=$1' ) ) {
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
						const context = $( this ).data( 'context' );
						const selection = context.$textarea.textSelection( 'getSelection' );

						// Pre-fill the text fields based on the current selection
						if ( selection !== '' ) {
							Object.assign( fileData, parseFileSyntax( selection ) );
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
							const $dialog = $( this ).closest( '.ui-dialog' );
							$dialog.on( 'keypress', ( e ) => {
								if ( e.which === 13 ) {
									triggerButtonClick( $dialog );
									e.preventDefault();
								}
							} );

							// Make tabbing to a button and pressing
							// Enter do what people expect
							$dialog.find( 'button' ).on( 'focus', ( e ) => {
								$dialog.data( 'dialogaction', e.delegateTarget );
							} );
						}
					}
				}
			},
			'insert-table': {
				title: mw.message( 'wikieditor-toolbar-tool-table-title' ),
				id: 'wikieditor-toolbar-table-dialog',
				htmlTemplate: 'dialogInsertTable.html',
				init: function () {
					$( this ).find( '[rel]' ).each( function () {
						// eslint-disable-next-line mediawiki/msg-doc
						$( this ).text( mw.msg( $( this ).attr( 'rel' ) ) );
					} );

					$( '#wikieditor-toolbar-table-dimensions-rows' ).val( 3 );
					$( '#wikieditor-toolbar-table-dimensions-columns' ).val( 3 );
					$( '#wikieditor-toolbar-table-wikitable' ).on( 'click', () => {
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

					mw.loader.using( 'jquery.tablesorter', () => {
						$( '#wikieditor-toolbar-table-preview2' ).tablesorter();
					} );

					$( '#wikieditor-toolbar-table-sortable' ).on( 'click', () => {
						// Swap the currently shown one clone with the other one
						$( '#wikieditor-toolbar-table-preview' )
							.hide()
							.attr( 'id', 'wikieditor-toolbar-table-preview3' );
						$( '#wikieditor-toolbar-table-preview2' )
							.attr( 'id', 'wikieditor-toolbar-table-preview' )
							.show();
						$( '#wikieditor-toolbar-table-preview3' ).attr( 'id', 'wikieditor-toolbar-table-preview2' );
					} );

					$( '#wikieditor-toolbar-table-dimensions-header' ).on( 'click', () => {
						// Instead of show/hiding, switch the HTML around
						// We do this because the sortable tables script styles the first row,
						// visible or not
						const headerHTML = $( '.wikieditor-toolbar-table-preview-header' ).html(),
							hiddenHTML = $( '.wikieditor-toolbar-table-preview-hidden' ).html();
						$( '.wikieditor-toolbar-table-preview-header' ).html( hiddenHTML );
						$( '.wikieditor-toolbar-table-preview-hidden' ).html( headerHTML );
						const $sortable = $( '#wikieditor-toolbar-table-preview, #wikieditor-toolbar-table-preview2' )
							.filter( '.sortable' );
						mw.loader.using( 'jquery.tablesorter', () => {
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
							const rowsVal = $( '#wikieditor-toolbar-table-dimensions-rows' ).val(),
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
							const captionText = mw.msg( 'wikieditor-toolbar-tool-table-example-caption' );
							const headerText = mw.msg( 'wikieditor-toolbar-tool-table-example-header' );
							const normalText = mw.msg( 'wikieditor-toolbar-tool-table-example' );
							let table = '';
							table += '|+ ' + captionText + '\n';
							for ( let r = 0; r < rows + header; r++ ) {
								table += '|-\n';
								for ( let c = 0; c < cols; c++ ) {
									const isHeader = ( header && r === 0 );
									let delim = isHeader ? '!' : '|';
									if ( c > 0 ) {
										delim += delim;
									}
									table += delim + ' ' + ( isHeader ? headerText : normalText ) + ' ';
								}
								// Replace trailing space by newline
								// table[table.length - 1] is read-only
								table = table.slice( 0, table.length - 1 ) + '\n';
							}
							const classes = [];
							if ( $( '#wikieditor-toolbar-table-wikitable' ).is( ':checked' ) ) {
								classes.push( 'wikitable' );
							}
							if ( $( '#wikieditor-toolbar-table-sortable' ).is( ':checked' ) ) {
								classes.push( 'sortable' );
							}
							const classStr = classes.length > 0 ? ' class="' + classes.join( ' ' ) + '"' : '';
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
							const $dialog = $( this ).closest( '.ui-dialog' );
							$dialog.on( 'keypress', ( e ) => {
								if ( ( e.keyCode || e.which ) === 13 ) {
									triggerButtonClick( $dialog );
									e.preventDefault();
								}
							} );

							// Make tabbing to a button and pressing
							// Enter do what people expect
							$dialog.find( 'button' ).on( 'focus', ( e ) => {
								$dialog.data( 'dialogaction', e.delegateTarget );
							} );
						}
					}
				}
			},
			'search-and-replace': {
				title: mw.message( 'wikieditor-toolbar-tool-replace-title' ),
				id: 'wikieditor-toolbar-replace-dialog',
				htmlTemplate: 'dialogReplace.html',
				init: function () {
					$( this ).find( '[rel]' ).each( function () {
						// eslint-disable-next-line mediawiki/msg-doc
						$( this ).text( mw.msg( $( this ).attr( 'rel' ) ) );
					} );

					// TODO: Find a cleaner way to share this function
					$( this ).data( 'replaceCallback', ( mode ) => {
						$( '#wikieditor-toolbar-replace-nomatch, #wikieditor-toolbar-replace-success, #wikieditor-toolbar-replace-emptysearch, #wikieditor-toolbar-replace-invalidregex' ).hide();

						// Search string cannot be empty
						let searchStr = $( '#wikieditor-toolbar-replace-search' ).val();
						if ( searchStr === '' ) {
							$( '#wikieditor-toolbar-replace-emptysearch' ).show();
							return;
						}

						// Replace string can be empty
						const replaceStr = $( '#wikieditor-toolbar-replace-replace' ).val();

						// Prepare the regular expression flags
						let flags = 'm';
						const matchCase = $( '#wikieditor-toolbar-replace-case' ).is( ':checked' );
						if ( !matchCase ) {
							flags += 'i';
						}
						const isRegex = $( '#wikieditor-toolbar-replace-regex' ).is( ':checked' );
						if ( !isRegex ) {
							searchStr = mw.util.escapeRegExp( searchStr );
						}
						const matchWord = $( '#wikieditor-toolbar-replace-word' ).is( ':checked' );
						if ( matchWord ) {
							searchStr = '\\b(?:' + searchStr + ')\\b';
						}
						if ( mode === 'replaceAll' ) {
							flags += 'g';
						}

						let regex;
						try {
							regex = new RegExp( searchStr, flags );
						} catch ( e ) {
							$( '#wikieditor-toolbar-replace-invalidregex' )
								.text( mw.msg( 'wikieditor-toolbar-tool-replace-invalidregex',
									e.message ) )
								.show();
							return;
						}

						const $textarea = $( this ).data( 'context' ).$textarea;
						let text = $textarea.textSelection( 'getContents' );
						let match = false;
						let offset, textRemainder;
						if ( mode !== 'replaceAll' ) {
							offset = $( this ).data( mode === 'replace' ? 'matchIndex' : 'offset' );
							textRemainder = text.slice( offset );
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

							if ( mode === 'replace' ) {

								let actualReplacement;
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
								textRemainder = text.slice( offset );
								match = textRemainder.match( regex );
								if ( !match ) {
									// If no new string was found, try searching from the beginning.
									// TODO: Add a "Wrap around" option.
									offset = 0;
									textRemainder = text;
									match = textRemainder.match( regex );
								}
								if ( !match ) {
									// Give up
									match = { index: 0, 0: { length: 0 } };
								}
							}
							const start = offset + match.index;
							const end = start + match[ 0 ].length;

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
						$( this ).data( { offset: 0, matchIndex: 0 } );

						const $dialog = $( this ).closest( '.ui-dialog' );
						$( '#wikieditor-toolbar-replace-search' ).trigger( 'focus' );
						$( '#wikieditor-toolbar-replace-nomatch, #wikieditor-toolbar-replace-success, #wikieditor-toolbar-replace-emptysearch, #wikieditor-toolbar-replace-invalidregex' ).hide();
						if ( !( $( this ).data( 'onetimeonlystuff' ) ) ) {
							$( this ).data( 'onetimeonlystuff', true );
							// Execute the action associated with the first button
							// when the user presses Enter
							$dialog.on( 'keypress', ( e ) => {
								if ( ( e.keyCode || e.which ) === 13 ) {
									triggerButtonClick( $dialog );
									e.preventDefault();
								}
							} );
							// Make tabbing to a button and pressing
							// Enter do what people expect
							$dialog.find( 'button' ).on( 'focus', ( e ) => {
								$dialog.data( 'dialogaction', e.delegateTarget );
							} );
						}
						const $textarea = $( this ).data( 'context' ).$textarea;
						$textarea
							.on( 'keypress.srdialog', ( e ) => {
								if ( e.which === 13 ) {
									// Enter
									triggerButtonClick( $textarea );
									e.preventDefault();
								} else if ( e.which === 27 ) {
									// Escape
									$( this ).dialog( 'close' );
								}
							} );
					},
					close: function () {
						$( this ).data( 'context' ).$textarea
							.off( 'keypress.srdialog' );
						$( this ).closest( '.ui-dialog' ).data( 'dialogaction', false );
					}
				}
			}
		} };
	}

};
