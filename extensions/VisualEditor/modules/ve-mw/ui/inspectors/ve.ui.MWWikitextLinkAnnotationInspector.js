/*!
 * VisualEditor UserInterface MWWikitextLinkAnnotationInspector class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Inspector for applying and editing labeled MediaWiki internal and external links.
 *
 * @class
 * @extends ve.ui.MWLinkAnnotationInspector
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWWikitextLinkAnnotationInspector = function VeUiMWWikitextLinkAnnotationInspector( config ) {
	// Parent constructor
	ve.ui.MWWikitextLinkAnnotationInspector.super.call( this, config );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWWikitextLinkAnnotationInspector, ve.ui.MWLinkAnnotationInspector );

/* Static properties */

ve.ui.MWWikitextLinkAnnotationInspector.static.name = 'wikitextLink';

ve.ui.MWWikitextLinkAnnotationInspector.static.modelClasses = [];

ve.ui.MWWikitextLinkAnnotationInspector.static.handlesSource = true;

// TODO: Support [[linktrail]]s & [[pipe trick|]]
ve.ui.MWWikitextLinkAnnotationInspector.static.internalLinkParser = ( function () {
	const openLink = '\\[\\[',
		closeLink = '\\]\\]',
		noCloseLink = '(?:(?!' + closeLink + ').)*',
		noCloseLinkOrPipe = '(?:(?!' + closeLink + ')[^|])*';

	return new RegExp(
		openLink +
			'(' + noCloseLinkOrPipe + ')' +
			'(?:\\|(' + noCloseLink + '))?' +
		closeLink,
		'g'
	);
}() );

ve.ui.MWWikitextLinkAnnotationInspector.static.externalLinkParser = ( function () {
	const protocols = ve.init.platform.getUnanchoredExternalLinkUrlProtocolsRegExp().source;
	return new RegExp( '\\[(' + protocols + '\\S+) ?([^\\]]*)(\\])', 'ig' );
}() );

ve.ui.MWWikitextLinkAnnotationInspector.static.magicLinkParser = ( function () {
	const protocols = ve.init.platform.getUnanchoredExternalLinkUrlProtocolsRegExp().source;
	return new RegExp( '\\b(' + protocols + '\\S+\\w)', 'ig' );
}() );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWWikitextLinkAnnotationInspector.prototype.getSetupProcess = function ( data ) {
	// Annotation inspector stages the annotation, so call its parent
	// Call grand-parent
	return ve.ui.AnnotationInspector.super.prototype.getSetupProcess.call( this, data )
		.next( () => {
			const wgNamespaceIds = mw.config.get( 'wgNamespaceIds' ),
				internalLinkParser = this.constructor.static.internalLinkParser,
				externalLinkParser = this.constructor.static.externalLinkParser,
				magicLinkParser = this.constructor.static.magicLinkParser;

			// Only supports linear selections
			if ( !( this.initialFragment && this.initialFragment.getSelection() instanceof ve.dm.LinearSelection ) ) {
				return ve.createDeferred().reject().promise();
			}

			let fragment = this.getFragment();
			let linkMatches, isExternal;
			// Initialize range
			if ( !data.noExpand ) {
				if ( !fragment.getSelection().isCollapsed() ) {
					// Trim whitespace
					fragment = fragment.trimLinearSelection();
				}
				// Expand to existing link, if present
				// Find all links in the paragraph and see which one contains
				// the current selection.
				const contextFragment = fragment.expandLinearSelection( 'siblings' );
				const contextRange = contextFragment.getSelection().getCoveringRange();
				const range = fragment.getSelection().getCoveringRange();
				const text = contextFragment.getText();
				internalLinkParser.lastIndex = 0;
				let matches;
				while ( ( matches = internalLinkParser.exec( text ) ) !== null ) {
					const matchTitle = mw.Title.newFromText( matches[ 1 ] );
					if ( !matchTitle ) {
						continue;
					}
					const linkRange = new ve.Range(
						contextRange.start + matches.index,
						contextRange.start + matches.index + matches[ 0 ].length
					);
					const namespaceId = mw.Title.newFromText( matches[ 1 ] ).getNamespaceId();
					if (
						linkRange.containsRange( range ) && !(
							// Ignore File:/Category:, but not :File:/:Category:
							(
								namespaceId === wgNamespaceIds.file ||
								namespaceId === wgNamespaceIds.category
							) &&
							!matches[ 1 ].startsWith( ':' )
						)
					) {
						linkMatches = matches;
						fragment = fragment.getSurface().getLinearFragment( linkRange );
						break;
					}
				}
				if ( !linkMatches ) {
					externalLinkParser.lastIndex = 0;
					while ( ( matches = externalLinkParser.exec( text ) ) !== null ) {
						const matchURL = matches[ 1 ];
						if ( !matchURL ) {
							continue;
						}
						const linkRange = new ve.Range(
							contextRange.start + matches.index,
							contextRange.start + matches.index + matches[ 0 ].length
						);
						if ( linkRange.containsRange( range ) ) {
							linkMatches = matches;
							fragment = fragment.getSurface().getLinearFragment( linkRange );
							isExternal = true;
							break;
						}
					}
				}
				if ( !linkMatches ) {
					magicLinkParser.lastIndex = 0;
					while ( ( matches = magicLinkParser.exec( text ) ) !== null ) {
						if ( !matches[ 0 ] ) {
							continue;
						}
						const linkRange = new ve.Range(
							contextRange.start + matches.index,
							contextRange.start + matches.index + matches[ 0 ].length
						);
						if ( linkRange.containsRange( range ) ) {
							linkMatches = matches;
							fragment = fragment.getSurface().getLinearFragment( linkRange );
							isExternal = true;
							break;
						}
					}
				}
			}
			if ( !linkMatches ) {
				if ( !data.noExpand && fragment.getSelection().isCollapsed() ) {
					// expand to nearest word
					fragment = fragment.expandLinearSelection( 'word' );
				} else {
					// Trim whitespace
					fragment = fragment.trimLinearSelection();
				}
			}

			// Update selection
			fragment.select();

			this.initialSelection = fragment.getSelection();
			this.fragment = fragment;
			this.initialLabel = this.fragment.getText();

			if ( isExternal ) {
				// can't get here without linkMatches
				// linkMatches: [ whole match, url, protocol, label, closing bracket ]
				if ( linkMatches[ 4 ] ) {
					// Link came in []
					this.initialLabel = linkMatches[ 3 ];
					this.initialLabel = this.initialLabel.replace( /<nowiki>(\]{2,})<\/nowiki>/g, '$1' );
					if ( !linkMatches[ 3 ] ) {
						// Didn't have a label
						this.initialAnnotation = this.newExternalLinkAnnotation( {
							type: 'link/mwNumberedExternal',
							attributes: {
								href: linkMatches[ 1 ]
							}
						} );
					} else {
						// Has a label
						this.initialAnnotation = this.newExternalLinkAnnotation( {
							type: 'link/mwExternal',
							attributes: {
								href: linkMatches[ 1 ]
							}
						} );
					}
				} else {
					// Just an autolinked URL
					this.initialLabel = '';
					this.initialAnnotation = this.newExternalLinkAnnotation( {
						type: 'link/mwMagic',
						attributes: {
							href: linkMatches[ 1 ]
						}
					} );
				}
			} else {
				let title;
				if ( linkMatches ) {
					// Group 1 is the link target, group 2 is the label after | if present
					title = mw.Title.newFromText( linkMatches[ 1 ] );
					this.initialLabel = linkMatches[ 2 ] || linkMatches[ 1 ];
					// HACK: Remove escaping probably added by this tool.
					// We should really do a full parse from wikitext to HTML if
					// we see any syntax
					this.initialLabel = this.initialLabel.replace( /<nowiki>(\]{2,})<\/nowiki>/g, '$1' );
				} else {
					title = mw.Title.newFromText( this.initialLabel );
				}
				if ( title ) {
					this.initialAnnotation = this.newInternalLinkAnnotationFromTitle( title );
				}
			}

			// We've skipped ve.ui.AnnotationInspector#getSetupProcess. Set isNew here so
			// that getInsertionData works correctly.
			this.isNew = !linkMatches;

			const inspectorTitle = ve.msg(
				this.isReadOnly() ?
					'visualeditor-linkinspector-title' : (
						!linkMatches ?
							'visualeditor-linkinspector-title-add' :
							'visualeditor-linkinspector-title-edit'
					)
			);

			this.title.setLabel( inspectorTitle ).setTitle( inspectorTitle );
			this.labelInput.setValue( this.initialLabel );

			this.annotationInput.setReadOnly( this.isReadOnly() );

			this.actions.setMode( this.getMode() );
			this.linkTypeIndex.setTabPanel(
				this.initialAnnotation instanceof ve.dm.MWExternalLinkAnnotation ? 'external' : 'internal'
			);
			this.annotationInput.setAnnotation( this.initialAnnotation );

			this.updateActions();
		} );
};

/**
 * @inheritdoc
 */
ve.ui.MWWikitextLinkAnnotationInspector.prototype.getTeardownProcess = function ( data = {} ) {
	// Call grand-parent
	return ve.ui.FragmentInspector.prototype.getTeardownProcess.call( this, data )
		.first( () => {
			const wgNamespaceIds = mw.config.get( 'wgNamespaceIds' ),
				annotation = this.getAnnotation(),
				fragment = this.getFragment();

			const insertionText = this.getInsertionText();
			const replace = !this.isNew;
			const insertText = this.initialSelection.isCollapsed() && insertionText.length;

			if ( data && data.action === 'done' && annotation ) {
				// Build internal links locally
				if ( annotation instanceof ve.dm.MWInternalLinkAnnotation ) {
					let labelText;
					if ( replace || insertText ) {
						labelText = insertionText;
					} else {
						labelText = this.initialLabel;
					}
					if ( labelText.includes( ']]' ) ) {
						labelText = labelText.replace( /(\]{2,})/g, '<nowiki>$1</nowiki>' );
					}
					const labelTitle = mw.Title.newFromText( labelText );
					let targetText;
					if ( !labelTitle || labelTitle.getPrefixedText() !== annotation.getAttribute( 'normalizedTitle' ) ) {
						targetText = annotation.getAttribute( 'normalizedTitle' ) + '|';
					} else {
						targetText = '';
					}
					const targetTitle = mw.Title.newFromText( annotation.getAttribute( 'normalizedTitle' ) );
					const namespaceId = targetTitle.getNamespaceId();
					let prefix;
					if (
						( targetText + labelText )[ 0 ] !== ':' && (
							namespaceId === wgNamespaceIds.file ||
							namespaceId === wgNamespaceIds.category
						)
					) {
						prefix = ':';
					} else {
						prefix = '';
					}

					fragment.insertContent( '[[' + prefix + targetText + labelText + ']]' );
				} else if ( annotation instanceof ve.dm.MWExternalLinkAnnotation ) {
					if ( this.initialAnnotation && this.initialAnnotation.element.type === 'link/mwMagic' ) {
						fragment.insertContent( annotation.element.attributes.href );
					} else {
						let labelText = '';
						if ( replace || insertText ) {
							labelText = insertionText;
						} else if ( annotation.name === 'link/mwExternal' ) {
							labelText = this.initialLabel;
						}
						if ( labelText ) {
							labelText = ' ' + labelText;
						}
						fragment.insertContent( '[' + annotation.element.attributes.href + labelText + ']' );
					}
				} else {
					if ( replace || this.shouldInsertText() ) {
						fragment.insertContent( this.getInsertionData() );
					}
					// Annotating the surface will send the content to Parsoid before
					// it is inserted into the wikitext document. It is slower but it
					// will handle all cases.
					// Where possible we should generate the wikitext locally.
					fragment.annotateContent( 'set', annotation );
				}

				// Fix selection after annotating is complete
				fragment.getPending().then( () => {
					if ( insertText ) {
						fragment.collapseToEnd().select();
					} else {
						fragment.select();
					}
				} );
			} else if ( !data.action ) {
				// Restore selection to what it was before we expanded it
				this.initialFragment.select();
			}
		} )
		.next( () => {
			// Reset state
			this.initialSelection = null;
			this.initialAnnotation = null;

			// Parent resets
			this.allowProtocolInInternal = false;
			this.internalAnnotationInput.setAnnotation( null );
			this.externalAnnotationInput.setAnnotation( null );
		} );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWWikitextLinkAnnotationInspector );

ve.ui.wikitextCommandRegistry.register(
	new ve.ui.Command(
		'link', 'window', 'open',
		{ args: [ 'wikitextLink' ], supportedSelections: [ 'linear' ] }
	)
);
