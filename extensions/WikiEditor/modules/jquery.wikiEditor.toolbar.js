/**
 * Toolbar module for wikiEditor
 *
 * @memberof module:ext.wikiEditor
 */

/* This feature was added to ve before it was contemplated for WikiEditor,
   so we wound up using a slightly awkward key */
const RECENTKEY = 'visualeditor-symbolList-recentlyUsed-specialCharacters';

const toolbarModule = {

	/**
	 * API accessible functions
	 */
	api: {
		addToToolbar: function ( context, data ) {

			for ( const type in data ) {
				switch ( type ) {
					case 'sections': {
						const $sections = context.modules.toolbar.$toolbar.find( 'div.sections' );
						const $tabs = context.modules.toolbar.$toolbar.find( 'div.tabs' );
						for ( const section in data[ type ] ) {
							if ( section === 'main' || section === 'secondary' ) {
								// Section
								context.modules.toolbar.$toolbar.prepend(
									toolbarModule.fn.buildSection(
										context, section, data[ type ][ section ]
									)
								);
								continue;
							}
							// Section
							$sections.append(
								toolbarModule.fn.buildSection( context, section, data[ type ][ section ] )
							);
							// Tab
							$tabs.append(
								toolbarModule.fn.buildTab( context, section, data[ type ][ section ] )
							);
						}
						break;
					}
					case 'groups': {
						if ( !( 'section' in data ) ) {
							continue;
						}
						const $section = context.modules.toolbar.$toolbar.find( 'div[rel="' + data.section + '"].section' );
						for ( const group in data[ type ] ) {
							// Group
							$section.append(
								toolbarModule.fn.buildGroup( context, group, data[ type ][ group ] )
							);
						}
						break;
					}
					case 'tools': {
						if ( !( 'section' in data && 'group' in data ) ) {
							continue;
						}
						const $group = context.modules.toolbar.$toolbar.find(
							'div[rel="' + data.section + '"].section ' +
							'div[rel="' + data.group + '"].group'
						);
						for ( const tool in data[ type ] ) {
							// Tool
							$group.append( toolbarModule.fn.buildTool( context, tool, data[ type ][ tool ] ) );
						}
						if ( $group.children().length ) {
							$group.removeClass( 'empty' );
						}
						break;
					}
					case 'pages': {
						if ( !( 'section' in data ) ) {
							continue;
						}
						const $pages = context.modules.toolbar.$toolbar.find(
							'div[rel="' + data.section + '"].section .pages'
						);
						const $index = context.modules.toolbar.$toolbar.find(
							'div[rel="' + data.section + '"].section .index'
						);
						for ( const page in data[ type ] ) {
							// Page
							$pages.append( toolbarModule.fn.buildPage( context, page, data[ type ][ page ] ) );
							// Index
							$index.append(
								toolbarModule.fn.buildBookmark( context, page, data[ type ][ page ] )
							);
						}
						toolbarModule.fn.updateBookletSelection( context, data.section, $pages, $index );
						break;
					}
					case 'rows': {
						if ( !( 'section' in data && 'page' in data ) ) {
							continue;
						}
						const $table = context.modules.toolbar.$toolbar.find(
							'div[rel="' + data.section + '"].section ' +
							'div[rel="' + data.page + '"].page table'
						);
						for ( let i = 0; i < data.rows.length; i++ ) {
							// Row
							$table.append( toolbarModule.fn.buildRow( context, data.rows[ i ] ) );
						}
						break;
					}
					case 'characters': {
						if ( !( 'section' in data && 'page' in data ) ) {
							continue;
						}
						const $characters = context.modules.toolbar.$toolbar.find(
							'div[rel="' + data.section + '"].section ' +
							'div[rel="' + data.page + '"].page div'
						);
						const actions = $characters.data( 'actions' );
						for ( let i = 0; i < data.characters.length; i++ ) {
							// Character
							$characters.append(
								toolbarModule.fn.buildCharacter( data.characters[ i ], actions )
									.on( 'mousedown', ( e ) => {
										// No dragging!
										e.preventDefault();
										return false;
									} )
									.on( 'click', ( e ) => {
										const $character = $( e.target );
										toolbarModule.fn.doAction( $character.parent().data( 'context' ),
											$character.parent().data( 'actions' )[ $character.attr( 'rel' ) ] );
										e.preventDefault();
										return false;
									} )
							);
						}
						break;
					}
					default: break;
				}
			}
		},
		removeFromToolbar: function ( context, data ) {
			if ( typeof data.section === 'string' ) {
				// Section
				const tab = 'div.tabs span[rel="' + data.section + '"].tab';
				let target = 'div[rel="' + data.section + '"].section';
				let group = null;
				if ( typeof data.group === 'string' ) {
					// Toolbar group
					target += ' div[rel="' + data.group + '"].group';
					if ( typeof data.tool === 'string' ) {
						// Save for later checking if empty
						group = target;
						// Tool
						target = target + ' [rel="' + data.tool + '"].tool';
					}
				} else if ( typeof data.page === 'string' ) {
					// Booklet page
					const index = target + ' div.index div[rel="' + data.page + '"]';
					target += ' div.pages div[rel="' + data.page + '"].page';
					if ( typeof data.character === 'string' ) {
						// Character
						target += ' span[rel="' + data.character + '"]';
					} else if ( typeof data.row === 'number' ) {
						// Table row
						target += ' table tr:not(:has(th)):eq(' + data.row + ')';
					} else {
						// Just a page, remove the index too!
						context.modules.toolbar.$toolbar.find( index ).remove();
						toolbarModule.fn.updateBookletSelection(
							context,
							data.section,
							context.modules.toolbar.$toolbar.find( target ),
							context.modules.toolbar.$toolbar.find( index )
						);
					}
				} else {
					// Just a section, remove the tab too!
					context.modules.toolbar.$toolbar.find( tab ).remove();
				}
				context.modules.toolbar.$toolbar.find( target ).remove();
				// Hide empty groups
				if ( group ) {
					const $group = context.modules.toolbar.$toolbar.find( group );
					if ( $group.children().length === 0 ) {
						$group.addClass( 'empty' );
					}
				}
			}
		}
	},

	/**
	 * Internally used functions
	 */
	fn: {
		/**
		 * Creates a toolbar module within a wikiEditor
		 *
		 * @param {Object} context Context object of editor to create module in
		 * @param {Object} config Configuration object to create module from
		 */
		create: function ( context, config ) {
			if ( '$toolbar' in context.modules.toolbar ) {
				return;
			}
			context.modules.toolbar.$toolbar = $( '<div>' )
				.addClass( 'wikiEditor-ui-toolbar' )
				.attr( 'id', 'wikiEditor-ui-toolbar' );
			toolbarModule.fn.build( context, config );
			context.$ui.find( '.wikiEditor-ui-top' ).append( context.modules.toolbar.$toolbar );
		},
		/**
		 * Performs an operation based on parameters
		 *
		 * @param {Object} context
		 * @param {Object} action
		 */
		doAction: function ( context, action ) {
			switch ( action.type ) {
				case 'replace':
				case 'encapsulate': {
					if ( context.$textarea.prop( 'readonly' ) ) {
						break;
					}
					const parts = {
						pre: action.options.pre,
						peri: action.options.peri,
						post: action.options.post
					};
					let replace = action.type === 'replace';
					if ( 'regex' in action.options && 'regexReplace' in action.options ) {
						const selection = context.$textarea.textSelection( 'getSelection' );
						if ( selection !== '' && action.options.regex.test( selection ) ) {
							parts.peri = selection.replace( action.options.regex,
								action.options.regexReplace );
							parts.pre = parts.post = '';
							replace = true;
						}
					}
					context.$textarea.textSelection(
						'encapsulateSelection',
						Object.assign( {}, action.options, parts, { replace: replace } )
					);
					break;
				}
				case 'callback':
					if ( typeof action.execute === 'function' ) {
						action.execute( context );
					}
					break;
				case 'dialog':
					context.fn.saveSelection();
					context.$textarea.wikiEditor( 'openDialog', action.module );
					break;
				default: break;
			}
		},
		buildGroup: function ( context, id, group ) {
			const $group = $( '<div>' ).attr( { class: 'group group-' + id, rel: id } ),
				label = group.label;
			if ( label ) {
				$( '<span>' ).addClass( 'label' ).text( label ).appendTo( $group );
			}
			let empty = true;
			if ( 'tools' in group ) {
				for ( const tool in group.tools ) {
					const $tool = toolbarModule.fn.buildTool( context, tool, group.tools[ tool ] );
					if ( $tool ) {
						// Consider a group with only hidden tools empty as well
						// .is( ':visible' ) always returns false because tool is not attached to the DOM yet
						empty = empty && $tool.css( 'display' ) === 'none';
						$group.append( $tool );
					}
				}
			}
			if ( empty ) {
				$group.addClass( 'empty' );
			}
			return $group;
		},
		buildTool: function ( context, id, tool ) {
			if ( 'filters' in tool ) {
				for ( let i = 0; i < tool.filters.length; i++ ) {
					if ( $( tool.filters[ i ] ).length === 0 ) {
						return null;
					}
				}
			}
			const label = tool.label;
			switch ( tool.type ) {
				case 'button':
				case 'toggle': {
					let $button;
					if ( tool.oouiIcon ) {
						const config = {
							framed: false,
							classes: [ 'tool' ],
							icon: tool.oouiIcon,
							title: label
						};
						let oouiButton;
						if ( tool.type === 'button' ) {
							oouiButton = new OO.ui.ButtonWidget( config );
						} else if ( tool.type === 'toggle' ) {
							oouiButton = new OO.ui.ToggleButtonWidget( config );
						}
						$button = oouiButton.$element
							.attr( 'rel', id )
							.data( 'ooui', oouiButton );
					} else {
						$button = $( '<a>' )
							.attr( {
								tabindex: 0,
								title: label,
								rel: id,
								role: 'button',
								class: 'tool tool-button'
							} )
							.text( label );
						if ( tool.icon ) {
							const icon = $.wikiEditor.autoIcon(
								tool.icon,
								$.wikiEditor.imgPath + 'toolbar/'
							);
							$button.css( 'background-image', 'url(' + icon + ')' );
						}
					}
					$button.data( 'setActive', ( active ) => {
						$button.toggleClass( 'tool-active', active );

						// OOUI button
						if ( $button.data( 'ooui' ) && tool.type === 'toggle' ) {
							$button.data( 'ooui' ).setValue( active );
							// Use progressive icon in WMUI theme
							if ( OO.ui.WikimediaUITheme && OO.ui.theme instanceof OO.ui.WikimediaUITheme ) {
								// Wait for updateElementClasses to run
								setTimeout( () => {
									$button.data( 'ooui' ).$icon.toggleClass( 'oo-ui-image-progressive', active );
								} );
							}
						}
					} );
					if ( 'action' in tool ) {
						$button
							.data( 'action', tool.action )
							.data( 'context', context )
							.on( 'mousedown', ( e ) => {
								// No dragging!
								e.preventDefault();
								return false;
							} );
						if ( $button.data( 'ooui' ) ) {
							$button.data( 'ooui' ).on( 'click', () => {
								toolbarModule.fn.doAction(
									context, tool.action
								);
							} );
						} else {
							$button.on( 'click keydown', ( e ) => {
								if (
									e.type === 'click' ||
									e.type === 'keydown' && e.key === 'Enter'
								) {
									toolbarModule.fn.doAction(
										context, tool.action
									);
									e.preventDefault();
									return false;
								}
							} );
						}
						if ( 'hotkey' in tool ) {
							toolbarModule.fn.ctrlShortcuts[ tool.hotkey ] = tool;
						}
					}
					return $button;
				}
				case 'select': {
					const menuId = 'menu-' + Date.now();
					const $select = $( '<div>' )
						.attr( { rel: id, class: 'tool tool-select' } );
					const $options = $( '<div>' ).addClass( 'options' );
					if ( 'list' in tool ) {
						for ( const option in tool.list ) {
							const optionLabel = tool.list[ option ].label;
							$options.append(
								$( '<a>' )
									.data( 'action', tool.list[ option ].action )
									.data( 'context', context )
									.on( 'mousedown', ( e ) => {
										// No dragging!
										e.preventDefault();
										return false;
									} )
									.on( 'click keydown', ( e ) => {
										if (
											e.type === 'click' ||
											e.type === 'keydown' && e.key === 'Enter'
										) {
											const $link = $( e.target );
											toolbarModule.fn.doAction(
												$link.data( 'context' ), $link.data( 'action' ), $link
											);
											// Hide the dropdown
											$link.closest( '.tool-select' ).removeClass( 'options-shown' );
											e.preventDefault();
											return false;
										}
									} )
									.text( optionLabel )
									.addClass( 'option' )
									.attr( { rel: option, tabindex: 0, role: 'menuitem' } )
							);
						}
					}
					$select.append( $( '<a>' )
						.addClass( 'label skin-invert' )
						.text( label )
						.data( 'options', $options )
						.attr( { role: 'button', tabindex: 0, 'aria-expanded': false, 'aria-controls': menuId, 'aria-haspopup': 'menu' } )
						.on( 'mousedown', ( e ) => {
							// No dragging!
							e.preventDefault();
							return false;
						} )
						.on( 'click keydown', ( e ) => {
							if (
								e.type === 'click' ||
								e.type === 'keydown' && e.key === 'Enter'
							) {
								const $link = $( e.target );
								const $opts = $link.data( 'options' );
								// eslint-disable-next-line no-jquery/no-class-state
								const canShowOptions = !$opts.closest( '.tool-select' ).hasClass( 'options-shown' );
								$opts.closest( '.tool-select' ).toggleClass( 'options-shown', canShowOptions );
								$link.attr( 'aria-expanded', canShowOptions.toString() );
								e.preventDefault();
								return false;
							}
						} )
					);
					$select.append( $( '<div>' ).addClass( 'menu' ).append( $options ) );
					return $select;
				}
				case 'element': {
					// A raw 'element' type can be {htmlString|Element|Text|Array|jQuery|OO.ui.HTMLSnippet|function}.
					let $element;
					if ( tool.element instanceof OO.ui.HtmlSnippet ) {
						$element = tool.element.toString();
					} else if ( typeof tool.element === 'function' ) {
						$element = tool.element( context );
					} else {
						$element = tool.element;
					}
					return $( '<div>' )
						.attr( { rel: id, class: 'tool tool-element' } )
						.append( $element );
				}
				default:
					return null;
			}
		},
		buildBookmark: function ( context, id, page ) {
			const label = page.label;
			const $bookmark = $( '<div>' );
			return $bookmark
				.text( label )
				.attr( {
					rel: id,
					role: 'option'
				} )
				.data( 'context', context )
				.on( 'mousedown', ( e ) => {
					// No dragging!
					e.preventDefault();
					return false;
				} )
				.on( 'click', ( event ) => {
					$bookmark.parent().parent().find( '.page' ).hide();
					$bookmark.parent().parent().find( '.page-' + $bookmark.attr( 'rel' ) ).show().trigger( 'loadPage' );
					$bookmark
						.addClass( 'current' )
						.siblings().removeClass( 'current' );
					const section = $bookmark.parent().parent().attr( 'rel' );
					$.cookie(
						'wikiEditor-' + $bookmark.data( 'context' ).instance + '-booklet-' + section + '-page',
						$bookmark.attr( 'rel' ),
						{ expires: 30, path: '/' }
					);
					// No dragging!
					event.preventDefault();
					return false;
				} );
		},
		buildPage: function ( context, id, page, deferLoad ) {
			const $page = $( '<div>' ).attr( {
				class: 'page page-' + id,
				rel: id
			} );
			if ( deferLoad ) {
				if ( id === 'recent' ) {
					$page.on( 'loadPage', () => {
						try {
							page.characters = JSON.parse( mw.user.options.get( RECENTKEY ) || '[]' );
						} catch ( e ) {
							page.characters = [];
						}
						toolbarModule.fn.reallyBuildPage( context, id, page, $page );
					} );
				} else {
					$page.one( 'loadPage', () => {
						toolbarModule.fn.reallyBuildPage( context, id, page, $page );
					} );
				}
			} else {
				toolbarModule.fn.reallyBuildPage( context, id, page, $page );
			}
			return $page;
		},
		reallyBuildPage: function ( context, id, page, $page ) {
			switch ( page.layout ) {
				case 'table': {
					// The following classes are used here:
					// * table-format
					// * table-link
					// * table-heading
					// * table-list
					// * table-file
					// * table-discussion
					const $table = $( '<table>' ).addClass( 'table-' + id );
					if ( 'headings' in page ) {
						$table.append( toolbarModule.fn.buildHeading( context, page.headings ) );
					}
					if ( 'rows' in page ) {
						for ( let i = 0; i < page.rows.length; i++ ) {
							$table.append( toolbarModule.fn.buildRow( context, page.rows[ i ] ) );
						}
					}
					$page.addClass( 'page-table' ).append( $table );
					break;
				}
				case 'characters': {
					$page.addClass( 'page-characters' );
					const $characters = $( '<div>' ).data( 'context', context ).data( 'actions', {} );
					const actions = $characters.data( 'actions' );
					if ( 'language' in page ) {
						$characters.attr( 'lang', page.language );
					}
					if ( 'direction' in page ) {
						$characters.attr( 'dir', page.direction );
					} else {
						// By default it should be explicit ltr for all scripts.
						// Without this some conjoined ltr characters look
						// weird in rtl wikis.
						$characters.attr( 'dir', 'ltr' );
					}
					if ( 'characters' in page ) {
						for ( let i = 0; i < page.characters.length; i++ ) {
							$characters.append(
								toolbarModule.fn.buildCharacter( page.characters[ i ], actions )
							);
						}
						$characters
							.children()
							.attr( 'role', 'option' )
							.on( 'mousedown', ( e ) => {
								// No dragging!
								e.preventDefault();
								return false;
							} )
							.on( 'click', ( e ) => {
								const $character = $( e.target );
								let clickActions = actions[ $character.attr( 'rel' ) ];
								if ( !( Array.isArray( clickActions ) ) ) {
									clickActions = [ clickActions ];
								}
								for ( const action of clickActions ) {
									toolbarModule.fn.doAction(
										context,
										action
									);
								}
								e.preventDefault();
								return false;
							} );
					}
					/* Usually we do not build a page more than once, but in
					   the case of recent characters, we do */
					$page.empty();
					$page.append( $characters );
					break;
				}
			}
		},
		buildHeading: function ( context, headings ) {
			const $row = $( '<tr>' );
			for ( let i = 0; i < headings.length; i++ ) {
				$row.append(
					headings[ i ].msg ?
						// eslint-disable-next-line mediawiki/msg-doc
						$( '<th>' ).append( mw.message( headings[ i ].msg ).parseDom() ) :
						// Deprecated backward compatibility
						$( '<th>' ).html( $.wikiEditor.autoSafeMsg( headings[ i ], [ 'html', 'text' ] ) )
				);
			}
			return $row;
		},
		buildRow: function ( context, row ) {
			const $row = $( '<tr>' );
			for ( const cell in row ) {
				$row.append(
					// The following classes are used here:
					// * cell-description
					// * cell-syntax
					// * cell-result
					$( '<td>' ).addClass( 'cell cell-' + cell ).append(
						$( '<span>' ).html( row[ cell ].html )
					)
				);
			}
			return $row;
		},
		buildCharacter: function ( character, actions ) {
			const configRepresentation = character; // For recently used
			if ( typeof character === 'string' ) {
				character = {
					label: character,
					action: {
						type: 'replace',
						options: {
							peri: character,
							selectPeri: false
						}
					},
					configRepresentation
				};
			// In some cases the label for the character isn't the same as the
			// character that gets inserted (e.g. Hebrew vowels)
			} else if ( character && 0 in character && 1 in character ) {
				character = {
					label: character[ 0 ],
					action: {
						type: 'replace',
						options: {
							peri: character[ 1 ],
							selectPeri: false
						}
					},
					configRepresentation
				};
			}
			if ( character && 'action' in character && 'label' in character ) {
				/* Helper for updating the list of recently-used characters */
				const updateRecentAction = {
					type: 'callback',
					character,
					execute: function () {
						const maxRecentlyUsed = 32;
						let cache;
						try {
							cache = JSON.parse( mw.user.options.get( RECENTKEY ) || '[]' );
						} catch ( e ) {
							cache = [];
						}
						const storeAs = this.character.configRepresentation ? this.character.configRepresentation : this.character;
						const i = cache.findIndex( ( item ) => ( JSON.stringify( storeAs ) === JSON.stringify( item ) ) );
						if ( i !== -1 ) {
							cache.splice( i, 1 );
						}
						cache.unshift( storeAs );
						cache = cache.slice( 0, maxRecentlyUsed );
						( new mw.Api() ).saveOption( RECENTKEY, JSON.stringify( cache ) );
						mw.user.options.set( RECENTKEY, JSON.stringify( cache ) );
					}
				};
				actions[ character.label ] = [
					character.action,
					updateRecentAction
				];
				// eslint-disable-next-line mediawiki/msg-doc
				const title = character.titleMsg ? mw.msg( character.titleMsg ) : character.title;
				return $( '<span>' )
					.attr( {
						rel: character.label,
						title: title
					} )
					.text( character.label );
			}
			mw.log( 'A character for the toolbar was undefined. This is not supposed to happen. Double check the config.' );
			// bug 31673; also an additional fix for bug 24208...
			return $();
		},
		buildTab: function ( context, id, section ) {
			const selected = $.cookie( 'wikiEditor-' + context.instance + '-toolbar-section' );
			// Re-save cookie
			if ( selected !== null ) {
				$.cookie( 'wikiEditor-' + context.instance + '-toolbar-section', selected, { expires: 30, path: '/' } );
			}
			const $link =
				$( '<a>' )
					.addClass( selected === id ? 'current' : null )
					.addClass( 'skin-invert' )
					.attr( {
						tabindex: 0,
						role: 'button',
						'aria-expanded': ( selected === id ).toString(),
						'aria-controls': 'wikiEditor-section-' + id
					} )
					.text( section.label )
					.data( 'context', context )
					.on( 'mouseup', () => {
						$link.trigger( 'blur' );
					} )
					.on( 'mousedown', ( e ) => {
						// No dragging!
						e.preventDefault();
						return false;
					} )
					.on( 'click keydown', ( e ) => {
						if (
							e.type !== 'click' &&
							( e.type !== 'keydown' || e.key !== 'Enter' )
						) {
							return;
						}
						// We have to set aria-pressed over here, as NVDA wont recognize it
						// if we do it in the below .each as it seems
						$link.attr( 'aria-pressed', 'true' );
						$( '.tab > a' ).each( ( i, elem ) => {
							if ( elem !== e.target ) {
								$( elem ).attr( 'aria-pressed', 'false' );
							}
						} );
						const $sections = $link.data( 'context' ).$ui.find( '.sections' );
						const $section = $sections.find( '.section-' + $link.parent().attr( 'rel' ) );
						// eslint-disable-next-line no-jquery/no-class-state
						const show = !$section.hasClass( 'section-visible' );
						$sections.find( '.section-visible' )
							.removeClass( 'section-visible' )
							.addClass( 'section-hidden' );

						$link
							.attr( 'aria-expanded', 'false' )
							.parent().parent().find( 'a' ).removeClass( 'current' );
						if ( show ) {
							$section
								.removeClass( 'section-hidden' )
								.attr( 'aria-expanded', 'true' )
								.addClass( 'section-visible' );

							$link.attr( 'aria-expanded', 'true' )
								.addClass( 'current' );
						}

						// Save the currently visible section
						$.cookie(
							'wikiEditor-' + $link.data( 'context' ).instance + '-toolbar-section',
							show ? $section.attr( 'rel' ) : null,
							{ expires: 30, path: '/' }
						);

						e.preventDefault();
						return false;
					} );
			return $( '<span>' )
				.attr( {
					class: 'tab tab-' + id,
					rel: id
				} )
				.append( $link );
		},
		buildSection: function ( context, id, section ) {
			const $section = $( '<div>' ).attr( {
				class: section.type + ' section section-' + id,
				rel: id,
				id: 'wikiEditor-section-' + id
			} );
			const selected = $.cookie( 'wikiEditor-' + context.instance + '-toolbar-section' );
			const show = selected === id;

			toolbarModule.fn.reallyBuildSection( context, id, section, $section, section.deferLoad );

			// Show or hide section
			if ( id !== 'main' && id !== 'secondary' ) {
				$section
					.attr( 'aria-expanded', show.toString() )
					.addClass( show ? 'section-visible' : 'section-hidden' );
			}
			return $section;
		},
		reallyBuildSection: function ( context, id, section, $section, deferLoad ) {
			context.$textarea.trigger( 'wikiEditor-toolbar-buildSection-' + $section.attr( 'rel' ), [ section ] );
			switch ( section.type ) {
				case 'toolbar': {
					if ( 'groups' in section ) {
						for ( const group in section.groups ) {
							$section.append(
								toolbarModule.fn.buildGroup( context, group, section.groups[ group ] )
							);
						}
					}
					break;
				}
				case 'booklet': {
					const $pages = $( '<div>' )
						.addClass( 'pages' )
						.attr( {
							tabindex: '0',
							role: 'listbox'
						} )
						.on( 'keydown', ( event ) => {
							const $selected = $pages.children().filter( function () {
								return $( this ).css( 'display' ) !== 'none';
							} );
							$.wikiEditor.modules.toolbar.fn.handleKeyDown( $selected.children().first(), event, $pages );
						} );
					const $index = $( '<div>' )
						.addClass( 'index' )
						.attr( {
							tabindex: '0',
							role: 'listbox'
						} )
						.on( 'keydown', ( event ) => {
							$.wikiEditor.modules.toolbar.fn.handleKeyDown( $index, event, $index );
						} );
					if ( 'pages' in section ) {
						for ( const page in section.pages ) {
							$pages.append(
								toolbarModule.fn.buildPage( context, page, section.pages[ page ], deferLoad )
							);
							$index.append(
								toolbarModule.fn.buildBookmark( context, page, section.pages[ page ] )
							);
						}
					}
					$section.append( $index, $pages );
					toolbarModule.fn.updateBookletSelection( context, id, $pages, $index );
					break;
				}
			}
		},
		updateBookletSelection: function ( context, id, $pages, $index ) {
			const cookie = 'wikiEditor-' + context.instance + '-booklet-' + id + '-page';
			let selected = $.cookie( cookie );
			// Re-save cookie
			if ( selected !== null ) {
				$.cookie( cookie, selected, { expires: 30, path: '/' } );
			}
			let $selectedIndex = $index.find( '*[rel="' + selected + '"]' );
			if ( $selectedIndex.length === 0 ) {
				$selectedIndex = $index.children().eq( 0 );
				selected = $selectedIndex.attr( 'rel' );
			}
			$pages.children().hide();
			$pages.find( '*[rel="' + selected + '"]' ).show().trigger( 'loadPage' );
			$index.children().removeClass( 'current' );
			$selectedIndex.addClass( 'current' );
		},
		build: function ( context, config ) {
			const $tabs = $( '<div>' ).addClass( 'tabs' ).appendTo( context.modules.toolbar.$toolbar ),
				$sections = $( '<div>' ).addClass( 'sections' ).appendTo( context.modules.toolbar.$toolbar );
			context.modules.toolbar.$toolbar.append( $( '<div>' ).css( 'clear', 'both' ) );
			for ( const section in config ) {
				if ( section === 'main' || section === 'secondary' ) {
					context.modules.toolbar.$toolbar.prepend(
						toolbarModule.fn.buildSection( context, section, config[ section ] )
					);
				} else {
					$sections.append( toolbarModule.fn.buildSection( context, section, config[ section ] ) );
					$tabs.append( toolbarModule.fn.buildTab( context, section, config[ section ] ) );
				}
			}
			setTimeout( () => {
				context.$textarea.trigger( 'wikiEditor-toolbar-doneInitialSections' );
				// Use hook for attaching new toolbar tools to avoid race conditions
				mw.hook( 'wikiEditor.toolbarReady' ).fire( context.$textarea );
			} );
			toolbarModule.fn.setupShortcuts( context );
		},
		ctrlShortcuts: {},
		setupShortcuts: function ( context ) {
			const platform = $.client.profile().platform;
			const platformModifier = platform === 'mac' ? 'metaKey' : 'ctrlKey';
			const otherModifier = platform === 'mac' ? 'ctrlKey' : 'metaKey';

			context.$textarea.on( 'keydown', ( e ) => {
				// Check if the primary modifier key is pressed and that others aren't
				const target = e[ platformModifier ] && !e[ otherModifier ] && !e.altKey && !e.shiftKey &&
					toolbarModule.fn.ctrlShortcuts[ e.which ];
				if ( target ) {
					e.preventDefault();
					toolbarModule.fn.doAction( context, target.action );
				}
			} );
		},
		handleKeyDown: function ( $element, event, $parent ) {
			const $currentItem = $element.find( '.wikiEditor-character-highlighted' ),
				optionOffset = $parent.find( '.wikiEditor-character-highlighted' ).offset(),
				optionTop = optionOffset ? optionOffset.top : 0,
				selectTop = $parent.offset().top;

			let $nextItem;
			switch ( event.keyCode ) {
				// Up arrow
				case 38:
					if ( $currentItem.length ) {
						$currentItem.removeClass( 'wikiEditor-character-highlighted' );
						$nextItem = $currentItem.prev();
						$nextItem = $nextItem.length ? $nextItem : $currentItem;
						$nextItem.addClass( 'wikiEditor-character-highlighted' );
					} else {
						$element.children().first().addClass( 'wikiEditor-character-highlighted' );
					}
					event.preventDefault();
					event.stopPropagation();
					break;
				// Down arrow
				case 40:
					if ( $currentItem.length ) {
						$currentItem.removeClass( 'wikiEditor-character-highlighted' );
						$nextItem = $currentItem.next();
						$nextItem = $nextItem.length ? $nextItem : $currentItem;
						$nextItem.addClass( 'wikiEditor-character-highlighted' );
					} else {
						$element.children().first().addClass( 'wikiEditor-character-highlighted' );
					}
					event.preventDefault();
					event.stopPropagation();
					break;
				// Enter
				case 13:
					$currentItem.trigger( 'click' );
					break;
			}
			$parent.scrollTop( $parent.scrollTop() + ( optionTop - selectTop ) );
		}
	}
};

module.exports = toolbarModule;
