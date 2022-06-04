/*!
 * VisualEditor UserInterface MWTemplateTitleInputWidget class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Creates an ve.ui.MWTemplateTitleInputWidget object.
 *
 * @class
 * @extends mw.widgets.TitleInputWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 * @cfg {number} [namespace] Namespace to prepend to queries. Defaults to template namespace.
 * @cfg {boolean} [showDescriptions] Show template descriptions from the TemplateData API
 * @cfg {mw.Api} [api]
 */
ve.ui.MWTemplateTitleInputWidget = function VeUiMWTemplateTitleInputWidget( config ) {
	config = ve.extendObject( {}, {
		namespace: mw.config.get( 'wgNamespaceIds' ).template,
		// We don't need results to show up twice normalized and unnormalized
		addQueryInput: false,
		icon: 'search',
		placeholder: ve.msg( 'visualeditor-dialog-transclusion-placeholder-input-placeholder' )
	}, config );

	// Parent constructor
	ve.ui.MWTemplateTitleInputWidget.super.call( this, config );
	// All code below expects this, but ContentTranslation doesn't necessarily set it to 2
	this.api.defaults.parameters.formatversion = 2;

	this.showTemplateDescriptions = this.showDescriptions;
	// Clear the showDescriptions flag for subsequent requests as we implement
	// description fetch ourselves
	this.showDescriptions = false;

	// Properties
	this.descriptions = {};

	// Initialization
	this.$element.addClass( 've-ui-mwTemplateTitleInputWidget' );
};

/* Inheritance */

// FIXME: This should extend mw.widgets.TitleSearchWidget instead
OO.inheritClass( ve.ui.MWTemplateTitleInputWidget, mw.widgets.TitleInputWidget );

/* Methods */

// @inheritdoc mw.widgets.TitleWidget
ve.ui.MWTemplateTitleInputWidget.prototype.getApiParams = function ( query ) {
	var params = ve.ui.MWTemplateTitleInputWidget.super.prototype.getApiParams.call( this, query );

	// TODO: This should stay as a feature flag for 3rd-parties to fallback to prefixsearch
	if ( mw.config.get( 'wgVisualEditorConfig' ).cirrusSearchLookup ) {
		ve.extendObject( params, {
			generator: 'search',
			gsrsearch: params.gpssearch,
			gsrnamespace: params.gpsnamespace,
			gsrlimit: params.gpslimit
		} );
		// Adding the asterisk to emulate a prefix search behavior. It does not make sense in all
		// cases though. We're limiting it to be add only of the term ends with a letter or numeric
		// character.
		var endsWithAlpha;
		try {
			// TODO: Convert to literal when IE11 compatibility was dropped
			// eslint-disable-next-line prefer-regex-literals
			endsWithAlpha = new RegExp( '[0-9a-z\\p{L}\\p{N}]$', 'iu' );
		} catch ( e ) {
			// TODO: Remove when IE11 compatibility was dropped
			endsWithAlpha = /[0-9a-z\xC0-\uFFFF]$/i;
		}
		if ( endsWithAlpha.test( params.gsrsearch ) ) {
			params.gsrsearch += '*';
		}
		if ( this.showRedirectTargets ) {
			params.gsrprop = 'redirecttitle';
		}
		delete params.gpssearch;
		delete params.gpsnamespace;
		delete params.gpslimit;
	}
	return params;
};

// @inheritdoc mw.widgets.TitleInputWidget
ve.ui.MWTemplateTitleInputWidget.prototype.getLookupRequest = function () {
	var widget = this,
		originalResponse,
		templateDataMessage = mw.message( 'templatedata-doc-subpage' ),
		templateDataInstalled = templateDataMessage.exists(),
		templateDocPageFragment = '/' + templateDataMessage.text(),
		promise = ve.ui.MWTemplateTitleInputWidget.super.prototype.getLookupRequest.call( this );

	if ( mw.config.get( 'wgVisualEditorConfig' ).cirrusSearchLookup ) {
		promise = promise
			.then( this.addExactMatch.bind( this ) )
			.promise( { abort: function () {} } );
	}

	if ( !this.showTemplateDescriptions ) {
		return promise;
	}

	return promise
		.then( function ( response ) {
			var redirects = ( response.query && response.query.redirects ) || [],
				origPages = ( response.query && response.query.pages ) || {},
				newPages = [];

			// Build a new array to replace response.query.pages, ensuring everything goes into
			// the order defined by the page's index key, instead of whatever random order the
			// browser would let you iterate over the old object in.
			for ( var pageId in origPages ) {
				if ( 'index' in origPages[ pageId ] ) {
					newPages[ origPages[ pageId ].index - 1 ] = origPages[ pageId ];
				} else {
					// Watch out for cases where the index is specified on the redirect object
					// rather than the page object.
					for ( var redirIndex in redirects ) {
						if ( redirects[ redirIndex ].to === origPages[ pageId ].title ) {
							newPages[ redirects[ redirIndex ].index - 1 ] = origPages[ pageId ];
							break;
						}
					}
				}
			}

			// T54448: Filter out matches which end in /doc or as configured on-wiki
			if ( templateDataInstalled ) {
				newPages = newPages.filter( function ( page ) {
					// Can't use String.endsWith() as that's ES6.
					// page.title.endsWith( templateDocPageFragment )
					return page.title.slice( 0 - templateDocPageFragment.length ) !== templateDocPageFragment;
				} );
			} else {
				// Even if not filtering /doc, collapse the sparse array
				newPages = newPages.filter( function ( page ) {
					return page;
				} );
			}

			var titles = newPages.map( function ( page ) {
				return page.title;
			} );

			ve.setProp( response, 'query', 'pages', newPages );
			originalResponse = response; // lie!

			// Also get descriptions
			// FIXME: This should go through MWTransclusionModel rather than duplicate.
			if ( titles.length > 0 ) {
				var xhr = widget.getApi().get( {
					action: 'templatedata',
					titles: titles,
					redirects: !!widget.showRedirects,
					includeMissingTitles: '1',
					lang: mw.config.get( 'wgUserLanguage' )
				} );
				return xhr.promise( { abort: xhr.abort } );
			}
		} )
		.then( function ( templateDataResponse ) {
			var pages = ( templateDataResponse && templateDataResponse.pages ) || {};
			// Look for descriptions and cache them
			for ( var index in pages ) {
				var page = pages[ index ];

				if ( page.missing ) {
					// Remmeber templates that don't exist in the link cache
					// { title: { missing: true|false }
					var missingTitle = {};
					missingTitle[ page.title ] = { missing: true };
					ve.init.platform.linkCache.setMissing( missingTitle );
				} else if ( !page.notemplatedata ) {
					// Cache descriptions
					widget.descriptions[ page.title ] = page.description;
				}
			}
			// Return the original response
			return originalResponse;
		}, function () {
			// API request failed; most likely, we're on a wiki which doesn't have TemplateData.
			return originalResponse || ve.createDeferred().reject();
		} )
		.promise( { abort: function () {} } );
};

/**
 * @private
 * @method
 * @param {Object} response Action API response from server
 * @return {Object} Modified response
 */
ve.ui.MWTemplateTitleInputWidget.prototype.addExactMatch = function ( response ) {
	var widget = this,
		query = this.getQueryValue(),
		title = mw.Title.newFromText( query, this.namespace );
	// No point in trying anything when the title is invalid
	if ( !response.query || !title ) {
		return response;
	}

	var lowerTitle = title.getPrefixedText().toLowerCase(),
		metadata = response.query.redirects || [],
		foundMatchingMetadata = metadata.some( function ( redirect ) {
			return redirect.from.toLowerCase() === lowerTitle;
		} );
	if ( foundMatchingMetadata ) {
		// Redirects will be carefully positioned later in TitleWidget.getOptionsFromData()
		return response;
	}

	/**
	 * @param {{pageid: number}[]} pages
	 * @param {number} pageId
	 * @return {boolean}
	 */
	function containsPageId( pages, pageId ) {
		return pageId && pages.some( function ( page ) {
			return page.pageid === pageId;
		} );
	}

	/**
	 * @param {{index: number}[]} pages
	 * @param {Object} [newPage]
	 */
	function unshiftPages( pages, newPage ) {
		pages.forEach( function ( page ) {
			page.index++;
		} );
		if ( newPage && newPage.title ) {
			newPage.index = 1;
			pages.unshift( newPage );
			if ( pages.length > widget.limit ) {
				pages.sort( function ( a, b ) {
					return a.index - b.index;
				} ).splice( widget.limit );
			}
		}
	}

	var i,
		matchingRedirects = response.query.pages.filter( function ( page ) {
			return page.redirecttitle && page.redirecttitle.toLowerCase() === lowerTitle;
		} );
	if ( matchingRedirects.length ) {
		for ( i = matchingRedirects.length; i--; ) {
			var matchingRedirect = matchingRedirects[ i ];
			// Offer redirects as separate options when the user's input is an exact match
			unshiftPages( response.query.pages, {
				pageid: matchingRedirect.pageid,
				ns: matchingRedirect.ns,
				title: matchingRedirect.redirecttitle
			} );
		}
		return response;
	}

	var matchingTitles = response.query.pages.filter( function ( page ) {
		return page.title.toLowerCase() === lowerTitle;
	} );
	if ( matchingTitles.length ) {
		for ( i = matchingTitles.length; i--; ) {
			// Make sure exact matches are at the very top
			unshiftPages( response.query.pages );
			matchingTitles[ i ].index = 1;
		}
		return response;
	}

	return this.getApi().get( {
		action: 'query',
		// Can't use a direct lookup by title because we need this to be case-insensitive
		generator: 'prefixsearch',
		gpssearch: query,
		gpsnamespace: this.namespace,
		gpslimit: 1
	} ).then( function ( prefixMatches ) {
		// action=query returns page objects in `{ query: { pages: [] } }`, not keyed by page id
		if ( prefixMatches.query ) {
			for ( var index in prefixMatches.query.pages ) {
				var prefixMatch = prefixMatches.query.pages[ index ];
				if ( !containsPageId( response.query.pages, prefixMatch.pageid ) ) {
					unshiftPages( response.query.pages, prefixMatch );
				}
			}
		}
		return response;
	}, function () {
		// Proceed with the unmodified response in case the additional API request failed
		return response;
	} )
		.promise( { abort: function () {} } );
};

// @inheritdoc mw.widgets.TitleWidget
ve.ui.MWTemplateTitleInputWidget.prototype.getOptionWidgetData = function ( title, data ) {
	return ve.extendObject(
		ve.ui.MWTemplateTitleInputWidget.super.prototype.getOptionWidgetData.apply( this, arguments ),
		{
			description: this.descriptions[ title ],
			redirecttitle: data.originalData.redirecttitle
		}
	);
};

// @inheritdoc mw.widgets.TitleWidget
ve.ui.MWTemplateTitleInputWidget.prototype.createOptionWidget = function ( data ) {
	var widget = ve.ui.MWTemplateTitleInputWidget.super.prototype.createOptionWidget.call( this, data );

	if ( data.redirecttitle ) {
		// Same conditions as in mw.widgets.TitleWidget.getOptionWidgetData()
		var title = new mw.Title( data.redirecttitle ),
			text = this.namespace !== null && this.relative ?
				title.getRelativeText( this.namespace ) :
				data.redirecttitle;

		var $desc = widget.$element.find( '.mw-widget-titleOptionWidget-description' );
		if ( !$desc.length ) {
			$desc = $( '<span>' )
				.addClass( 'mw-widget-titleOptionWidget-description' )
				.appendTo( widget.$element );
		}
		$desc.prepend( $( '<div>' )
			.addClass( 've-ui-mwTemplateTitleInputWidget-redirectedfrom' )
			.text( mw.msg( 'redirectedfrom', text ) ) );
	}

	return widget;
};
