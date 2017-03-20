/**
 * Toolbar module for wikiEditor
 */
/*jshint onevar:false */
( function ( $, mw ) {

$.wikiEditor.modules.toolbar = {

	/**
	 * API accessible functions
	 */
	api: {
		addToToolbar: function ( context, data ) {

			var smooth = true,
				type,
				i;

			for ( type in data ) {
				switch ( type ) {
					case 'sections':
						var $sections = context.modules.toolbar.$toolbar.find( 'div.sections' ),
							$tabs = context.modules.toolbar.$toolbar.find( 'div.tabs' );
						for ( var section in data[ type ] ) {
							if ( section === 'main' ) {
								// Section
								context.modules.toolbar.$toolbar.prepend(
									$.wikiEditor.modules.toolbar.fn.buildSection(
										context, section, data[ type ][ section ]
									)
								);
								continue;
							}
							// Section
							$sections.append(
								$.wikiEditor.modules.toolbar.fn.buildSection( context, section, data[ type ][ section ] )
							);
							// Tab
							$tabs.append(
								$.wikiEditor.modules.toolbar.fn.buildTab( context, section, data[ type ][ section ] )
							);
						}
						break;
					case 'groups':
						if ( !( 'section' in data ) ) {
							continue;
						}
						var $section = context.modules.toolbar.$toolbar.find( 'div[rel="' + data.section + '"].section' );
						for ( var group in data[ type ] ) {
							// Group
							$section.append(
								$.wikiEditor.modules.toolbar.fn.buildGroup( context, group, data[ type ][ group ] )
							);
						}
						smooth = false;
						break;
					case 'tools':
						if ( !( 'section' in data && 'group' in data ) ) {
							continue;
						}
						var $group = context.modules.toolbar.$toolbar.find(
							'div[rel="' + data.section + '"].section ' + 'div[rel="' + data.group + '"].group'
						);
						for ( var tool in data[ type ] ) {
							// Tool
							$group.append( $.wikiEditor.modules.toolbar.fn.buildTool( context, tool, data[ type ][ tool ] ) );
						}
						if ( $group.children().length ) {
							$group.removeClass( 'empty' );
						}
						smooth = false;
						break;
					case 'pages':
						if ( !( 'section' in data ) ) {
							continue;
						}
						var $pages = context.modules.toolbar.$toolbar.find(
							'div[rel="' + data.section + '"].section .pages'
						);
						var $index = context.modules.toolbar.$toolbar.find(
							'div[rel="' + data.section + '"].section .index'
						);
						for ( var page in data[ type ] ) {
							// Page
							$pages.append( $.wikiEditor.modules.toolbar.fn.buildPage( context, page, data[ type ][ page ] ) );
							// Index
							$index.append(
								$.wikiEditor.modules.toolbar.fn.buildBookmark( context, page, data[ type ][ page ] )
							);
						}
						$.wikiEditor.modules.toolbar.fn.updateBookletSelection( context, data.section, $pages, $index );
						smooth = false;
						break;
					case 'rows':
						if ( !( 'section' in data && 'page' in data ) ) {
							continue;
						}
						var $table = context.modules.toolbar.$toolbar.find(
							'div[rel="' + data.section + '"].section ' + 'div[rel="' + data.page + '"].page table'
						);
						for ( i = 0; i < data.rows.length; i++ ) {
							// Row
							$table.append( $.wikiEditor.modules.toolbar.fn.buildRow( context, data.rows[ i ] ) );
						}
						smooth = false;
						break;
					case 'characters':
						if ( !( 'section' in data && 'page' in data ) ) {
							continue;
						}
						var $characters = context.modules.toolbar.$toolbar.find(
							'div[rel="' + data.section + '"].section ' + 'div[rel="' + data.page + '"].page div'
						);
						var actions = $characters.data( 'actions' );
						for ( i = 0; i < data.characters.length; i++ ) {
							// Character
							$characters
							.append(
								$( $.wikiEditor.modules.toolbar.fn.buildCharacter( data.characters[ i ], actions ) )
									.mousedown( function ( e ) {
										context.fn.saveCursorAndScrollTop();
										// No dragging!
										e.preventDefault();
										return false;
									} )
									.click( function ( e ) {
										$.wikiEditor.modules.toolbar.fn.doAction( $( this ).parent().data( 'context' ),
											$( this ).parent().data( 'actions' )[ $( this ).attr( 'rel' ) ] );
										e.preventDefault();
										return false;
									} )
							);
						}
						smooth = false;
						break;
					default: break;
				}
			}

			// Fix div.section size after adding things; if smooth is true uses a smooth
			// animation, otherwise just change height (breaking any ongoing animation)
			var $divSections = context.modules.toolbar.$toolbar.find( 'div.sections' );
			var $visibleSection = $divSections.find( '.section-visible' );
			if ( $visibleSection.size() ) {
				if ( smooth ) {
					$divSections.animate( { height: $visibleSection.outerHeight() }, 'fast' );
				} else {
					$divSections.height( $visibleSection.outerHeight() );
				}
			}
		},
		removeFromToolbar: function ( context, data ) {
			if ( typeof data.section === 'string' ) {
				// Section
				var tab = 'div.tabs span[rel="' + data.section + '"].tab';
				var target = 'div[rel="' + data.section + '"].section';
				var group = null;
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
					var index = target + ' div.index div[rel="' + data.page + '"]';
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
						$.wikiEditor.modules.toolbar.fn.updateBookletSelection(
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
					var $group = context.modules.toolbar.$toolbar.find( group );
					if ( $group.children().length === 0 ) {
						$group.addClass( 'empty' );
					}
				}
			}
		}
	},

	/**
	 * Event handlers
	 */
	evt: {
		/**
		 * @param {Object} context
		 */
		resize: function ( context ) {
			context.$ui.find( '.sections' ).height( context.$ui.find( '.sections .section-visible' ).outerHeight() );
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
			$.wikiEditor.modules.toolbar.fn.build( context, config );
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
				case 'encapsulate':
					var parts = {
						pre: $.wikiEditor.autoMsg( action.options, 'pre' ),
						peri: $.wikiEditor.autoMsg( action.options, 'peri' ),
						post: $.wikiEditor.autoMsg( action.options, 'post' )
					};
					var replace = action.type === 'replace';
					if ( 'regex' in action.options && 'regexReplace' in action.options ) {
						var selection = context.$textarea.textSelection( 'getSelection' );
						if ( selection !== '' && selection.match( action.options.regex ) ) {
							parts.peri = selection.replace( action.options.regex,
								action.options.regexReplace );
							parts.pre = parts.post = '';
							replace = true;
						}
					}
					context.$textarea.textSelection(
						'encapsulateSelection',
						$.extend( {}, action.options, parts, { replace: replace } )
					);
					break;
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
			var $group = $( '<div>' ).attr( { 'class': 'group group-' + id, rel: id } );
			var label = $.wikiEditor.autoMsg( group, 'label' );
			if ( label ) {
				var $label = $( '<span>' )
					.addClass( 'label' )
					.html( label );
				$group.append( $label );
			}
			var empty = true;
			if ( 'tools' in group ) {
				for ( var tool in group.tools ) {
					tool = $.wikiEditor.modules.toolbar.fn.buildTool( context, tool, group.tools[ tool ] );
					if ( tool ) {
						// Consider a group with only hidden tools empty as well
						// .is( ':visible' ) always returns false because tool is not attached to the DOM yet
						empty = empty && tool.css( 'display' ) === 'none';
						$group.append( tool );
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
				for ( var i = 0; i < tool.filters.length; i++ ) {
					if ( $( tool.filters[ i ] ).size() === 0 ) {
						return null;
					}
				}
			}
			var label = $.wikiEditor.autoMsg( tool, 'label' );
			switch ( tool.type ) {
				case 'button':
					var $button, offsetOrIcon;

					offsetOrIcon = $.wikiEditor.autoIconOrOffset(
						tool.icon,
						tool.offset,
						$.wikiEditor.imgPath + 'toolbar/'
					);
					$button = $( '<a>' )
						.attr( {
							href: '#',
							title: label,
							rel: id,
							role: 'button',
							'class': 'tool tool-button'
						} )
						.text( label );
					if ( typeof offsetOrIcon === 'object' ) {
						$button
						.addClass( 'wikiEditor-toolbar-spritedButton' )
						.css( 'backgroundPosition', offsetOrIcon[ 0 ] + 'px ' + offsetOrIcon[ 1 ] + 'px' );
					} else {
						$button
						.css( 'background-image', 'url(' + offsetOrIcon + ')' );
					}
					if ( 'action' in tool ) {
						$button
							.data( 'action', tool.action )
							.data( 'context', context )
							.mousedown( function ( e ) {
								context.fn.saveCursorAndScrollTop();
								// No dragging!
								e.preventDefault();
								return false;
							} )
							.click( function ( e ) {
								$.wikiEditor.modules.toolbar.fn.doAction(
									$( this ).data( 'context' ), $( this ).data( 'action' ), $( this )
								);
								e.preventDefault();
								return false;
							} );
					}
					return $button;
				case 'select':
					var $select = $( '<div>' )
						.attr( { rel: id, 'class': 'tool tool-select' } );
					var $options = $( '<div>' ).addClass( 'options' );
					if ( 'list' in tool ) {
						for ( var option in tool.list ) {
							var optionLabel = $.wikiEditor.autoMsg( tool.list[ option ], 'label' );
							$options.append(
								$( '<a>' )
									.data( 'action', tool.list[ option ].action )
									.data( 'context', context )
									.mousedown( function ( e ) {
										context.fn.saveCursorAndScrollTop();
										// No dragging!
										e.preventDefault();
										return false;
									} )
									.click( function ( e ) {
										$.wikiEditor.modules.toolbar.fn.doAction(
											$( this ).data( 'context' ), $( this ).data( 'action' ), $( this )
										);
										// Hide the dropdown
										// Sanity check: if this somehow gets called while the dropdown
										// is hidden, don't show it
										if ( $( this ).parent().is( ':visible' ) ) {
											$( this ).parent().animate( { opacity: 'toggle' }, 'fast' );
										}
										e.preventDefault();
										return false;
									} )
									.text( optionLabel )
									.addClass( 'option' )
									.attr( { rel: option, href: '#' } )
							);
						}
					}
					$select.append( $( '<div>' ).addClass( 'menu' ).append( $options ) );
					$select.append( $( '<a>' )
							.addClass( 'label' )
							.text( label )
							.data( 'options', $options )
							.attr( 'href', '#' )
							.mousedown( function ( e ) {
								// No dragging!
								e.preventDefault();
								return false;
							} )
							.click( function ( e ) {
								$( this ).data( 'options' ).animate( { opacity: 'toggle' }, 'fast' );
								e.preventDefault();
								return false;
							} )
					);
					return $select;
				default:
					return null;
			}
		},
		buildBookmark: function ( context, id, page ) {
			var label = $.wikiEditor.autoMsg( page, 'label' );
			return $( '<div>' )
				.text( label )
				.attr( 'rel', id )
				.data( 'context', context )
				.mousedown( function ( e ) {
					context.fn.saveCursorAndScrollTop();
					// No dragging!
					e.preventDefault();
					return false;
				} )
				.click( function ( event ) {
					$( this ).parent().parent().find( '.page' ).hide();
					$( this ).parent().parent().find( '.page-' + $( this ).attr( 'rel' ) ).show().trigger( 'loadPage' );
					$( this ).siblings().removeClass( 'current' );
					$( this ).addClass( 'current' );
					var section = $( this ).parent().parent().attr( 'rel' );
					$.cookie(
						'wikiEditor-' + $( this ).data( 'context' ).instance + '-booklet-' + section + '-page',
						$( this ).attr( 'rel' ),
						{ expires: 30, path: '/' }
					);
					context.fn.restoreCursorAndScrollTop();
					// No dragging!
					event.preventDefault();
					return false;
				} );
		},
		buildPage: function ( context, id, page, deferLoad ) {
			var $page = $( '<div>' ).attr( {
				'class': 'page page-' + id,
				rel: id
			} );
			if ( deferLoad ) {
				$page.one( 'loadPage', function () {
					$.wikiEditor.modules.toolbar.fn.reallyBuildPage( context, id, page, $page );
				} );
			} else {
				$.wikiEditor.modules.toolbar.fn.reallyBuildPage( context, id, page, $page );
			}
			return $page;
		},
		reallyBuildPage: function ( context, id, page, $page ) {
			var html, i;
			switch ( page.layout ) {
				case 'table':
					$page.addClass( 'page-table' );
					html =
						'<table class="table-' + id + '">';
					if ( 'headings' in page ) {
						html += $.wikiEditor.modules.toolbar.fn.buildHeading( context, page.headings );
					}
					if ( 'rows' in page ) {
						for ( i = 0; i < page.rows.length; i++ ) {
							html += $.wikiEditor.modules.toolbar.fn.buildRow( context, page.rows[ i ] );
						}
					}
					$page.html( html + '</table>' );
					break;
				case 'characters':
					$page.addClass( 'page-characters' );
					var $characters = $( '<div>' ).data( 'context', context ).data( 'actions', {} );
					var actions = $characters.data( 'actions' );
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
						html = '';
						for ( i = 0; i < page.characters.length; i++ ) {
							html += $.wikiEditor.modules.toolbar.fn.buildCharacter( page.characters[ i ], actions );
						}
						$characters
							.html( html )
							.children()
							.mousedown( function ( e ) {
								context.fn.saveCursorAndScrollTop();
								// No dragging!
								e.preventDefault();
								return false;
							} )
							.click( function ( e ) {
								$.wikiEditor.modules.toolbar.fn.doAction(
									$( this ).parent().data( 'context' ),
									$( this ).parent().data( 'actions' )[ $( this ).attr( 'rel' ) ],
									$( this )
								);
								e.preventDefault();
								return false;
							} );
					}
					$page.append( $characters );
					break;
			}
		},
		buildHeading: function ( context, headings ) {
			var html = '<tr>';
			for ( var i = 0; i < headings.length; i++ ) {
				html += '<th>' + $.wikiEditor.autoMsg( headings[ i ], [ 'html', 'text' ] ) + '</th>';
			}
			return html + '</tr>';
		},
		buildRow: function ( context, row ) {
			var html = '<tr>';
			for ( var cell in row ) {
				html += '<td class="cell cell-' + cell + '"><span>' +
					$.wikiEditor.autoMsg( row[ cell ], [ 'html', 'text' ] ) + '</span></td>';
			}
			return html + '</tr>';
		},
		buildCharacter: function ( character, actions ) {
			if ( typeof character === 'string' ) {
				character = {
					label: character,
					action: {
						type: 'replace',
						options: {
							peri: character,
							selectPeri: false
						}
					}
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
					}
				};
			}
			if ( character && 'action' in character && 'label' in character ) {
				actions[ character.label ] = character.action;
				if ( character.titleMsg !== undefined ) {
					return mw.html.element(
						'span',
						{ rel: character.label, title: mw.msg( character.titleMsg ) },
						character.label
					);
				} else {
					return mw.html.element( 'span', { rel: character.label }, character.label );
				}
			}
			mw.log( 'A character for the toolbar was undefined. This is not supposed to happen. Double check the config.' );
			// bug 31673; also an additional fix for bug 24208...
			return '';
		},
		buildTab: function ( context, id, section ) {
			var selected = $.cookie( 'wikiEditor-' + context.instance + '-toolbar-section' );
			// Re-save cookie
			if ( selected !== null ) {
				$.cookie( 'wikiEditor-' + context.instance + '-toolbar-section', selected, { expires: 30, path: '/' } );
			}
			var $link =
				$( '<a>' )
					.addClass( selected === id ? 'current' : null )
					.attr( {
						href: '#',
						role: 'button',
						'aria-pressed': 'false',
						'aria-controls': 'wikiEditor-section-' + id
					} )
					.text( $.wikiEditor.autoMsg( section, 'label' ) )
					.data( 'context', context )
					.mouseup( function () {
						$( this ).blur();
					} )
					.mousedown( function ( e ) {
						// No dragging!
						e.preventDefault();
						return false;
					} )
					.click( function ( e ) {
						// We have to set aria-pressed over here, as NVDA wont recognize it
						// if we do it in the below .each as it seems
						$( this ).attr( 'aria-pressed', 'true' );
						$( '.tab > a' ).each( function ( i, elem ) {
							if ( elem !== e.target ) {
								$( elem ).attr( 'aria-pressed', 'false' );
							}
						} );
						var $sections = $( this ).data( 'context' ).$ui.find( '.sections' );
						var $section =
							$( this ).data( 'context' ).$ui.find( '.section-' + $( this ).parent().attr( 'rel' ) );
						var show = !$section.hasClass( 'section-visible' );
						$section.parent().find( '.section-visible' )
							.css( 'position', 'absolute' )
							.attr( 'aria-expanded', 'false' )
							.removeClass( 'section-visible' )
							.animate( { opacity: 0 }, 'fast', 'linear', function () {
								$( this ).addClass( 'section-hidden' ).css( 'position', 'static' );
							} );

						$( this ).parent().parent().find( 'a' ).removeClass( 'current' );
						$sections.css( 'overflow', 'hidden' );
						if ( show ) {
							$section
								.stop()
								.removeClass( 'section-hidden' )
								.attr( 'aria-expanded', 'true' )
								.animate( { opacity: 100.0 }, 'fast', 'linear', function () {
									$( this ).addClass( 'section-visible' );
									context.fn.trigger( 'resize' );
								} );
							$sections
								.animate( { height: $section.outerHeight() }, $section.outerHeight() * 2, function () {
									$( this ).css( 'overflow', 'visible' ).css( 'height', 'auto' );
									context.fn.trigger( 'resize' );
								} );
							$( this ).addClass( 'current' );
						} else {
							$sections
								.stop()
								.css( 'height', $section.outerHeight() )
								.animate( { height: 0 }, $section.outerHeight() * 2, function () {
									$( this ).css( { overflow: 'visible' } );
									context.fn.trigger( 'resize' );
								} );
						}
						// Save the currently visible section
						$.cookie(
							'wikiEditor-' + $( this ).data( 'context' ).instance + '-toolbar-section',
							show ? $section.attr( 'rel' ) : null,
							{ expires: 30, path: '/' }
						);
						e.preventDefault();
						return false;
					} );
			return $( '<span>' )
				.attr( {
					'class': 'tab tab-' + id,
					rel: id
				} )
				.append( $link );
		},
		buildSection: function ( context, id, section ) {
			var $section = $( '<div>' ).attr( {
				'class': section.type + ' section section-' + id,
				rel: id,
				id: 'wikiEditor-section-' + id
			} );
			var selected = $.cookie( 'wikiEditor-' + context.instance + '-toolbar-section' );
			var show = selected === id;

			$.wikiEditor.modules.toolbar.fn.reallyBuildSection( context, id, section, $section, section.deferLoad );

			// Show or hide section
			if ( id !== 'main' ) {
				$section.attr( 'aria-expanded', show ? 'true' : 'false' );

				if ( show ) {
					$section.addClass( 'section-visible' );
				} else {
					$section.addClass( 'section-hidden' );
				}
			}
			return $section;
		},
		reallyBuildSection: function ( context, id, section, $section, deferLoad ) {
			context.$textarea.trigger( 'wikiEditor-toolbar-buildSection-' + $section.attr( 'rel' ), [ section ] );
			switch ( section.type ) {
				case 'toolbar':
					if ( 'groups' in section ) {
						for ( var group in section.groups ) {
							$section.append(
								$.wikiEditor.modules.toolbar.fn.buildGroup( context, group, section.groups[ group ] )
							);
						}
					}
					break;
				case 'booklet':
					var $pages = $( '<div>' ).addClass( 'pages' );
					var $index = $( '<div>' ).addClass( 'index' );
					if ( 'pages' in section ) {
						for ( var page in section.pages ) {
							$pages.append(
								$.wikiEditor.modules.toolbar.fn.buildPage( context, page, section.pages[ page ], deferLoad )
							);
							$index.append(
								$.wikiEditor.modules.toolbar.fn.buildBookmark( context, page, section.pages[ page ] )
							);
						}
					}
					$section.append( $index ).append( $pages );
					$.wikiEditor.modules.toolbar.fn.updateBookletSelection( context, id, $pages, $index );
					break;
			}
		},
		updateBookletSelection: function ( context, id, $pages, $index ) {
			var cookie = 'wikiEditor-' + context.instance + '-booklet-' + id + '-page';
			var selected = $.cookie( cookie );
			// Re-save cookie
			if ( selected !== null ) {
				$.cookie( cookie, selected, { expires: 30, path: '/' } );
			}
			var $selectedIndex = $index.find( '*[rel="' + selected + '"]' );
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
			var $tabs = $( '<div>' ).addClass( 'tabs' ).appendTo( context.modules.toolbar.$toolbar );
			var $sections = $( '<div>' ).addClass( 'sections' ).appendTo( context.modules.toolbar.$toolbar );
			context.modules.toolbar.$toolbar.append( $( '<div>' ).css( 'clear', 'both' ) );
			var sectionQueue = [];
			for ( var section in config ) {
				if ( section === 'main' ) {
					context.modules.toolbar.$toolbar.prepend(
						$.wikiEditor.modules.toolbar.fn.buildSection( context, section, config[ section ] )
					);
				} else {
					sectionQueue.push( {
						$sections: $sections,
						context: context,
						id: section,
						config: config[ section ]
					} );
					$tabs.append( $.wikiEditor.modules.toolbar.fn.buildTab( context, section, config[ section ] ) );
				}
			}
			$.eachAsync( sectionQueue, {
				bulk: 0,
				end: function () {
					context.$textarea.trigger( 'wikiEditor-toolbar-doneInitialSections' );
				},
				loop: function ( i, s ) {
					s.$sections.append( $.wikiEditor.modules.toolbar.fn.buildSection( s.context, s.id, s.config ) );
					var $section = s.$sections.find( '.section-visible' );
					if ( $section.size() ) {
						$sections.animate( { height: $section.outerHeight() }, $section.outerHeight() * 2, function ( ) {
							context.fn.trigger( 'resize' );
						} );
					}
				}
			} );
		}
	}

};

}( jQuery, mediaWiki ) );
