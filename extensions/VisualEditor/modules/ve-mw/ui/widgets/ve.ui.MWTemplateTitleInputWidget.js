/*!
 * VisualEditor UserInterface MWTemplateTitleInputWidget class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Input field for entering a template title, for example when adding a template
 * in the template dialog.  Autocomplete fetches TemplateData and performs
 * searching in the background, to display information about matching templates
 * on the wiki.
 *
 * @class
 * @extends mw.widgets.TitleInputWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 * @param {number} [config.namespace] Namespace to prepend to queries. Defaults to template namespace.
 * @param {boolean} [config.showDescriptions] Show template descriptions from the TemplateData API
 * @param {mw.Api} [config.api]
 */
ve.ui.MWTemplateTitleInputWidget = function VeUiMWTemplateTitleInputWidget( config ) {
	config = ve.extendObject( {}, {
		namespace: mw.config.get( 'wgNamespaceIds' ).template,
		showMissing: false,
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
	const params = ve.ui.MWTemplateTitleInputWidget.super.prototype.getApiParams.call( this, query );

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
		// eslint-disable-next-line es-x/no-regexp-unicode-property-escapes, prefer-regex-literals
		const endsWithAlpha = new RegExp( '[\\p{L}\\p{N}]$', 'u' );
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
	let promise = ve.ui.MWTemplateTitleInputWidget.super.prototype.getLookupRequest.call( this );
	if ( mw.config.get( 'wgVisualEditorConfig' ).cirrusSearchLookup ) {
		promise = promise
			.then( this.addExactMatch.bind( this ) )
			.promise( { abort: () => {} } );
	}

	if ( !this.showTemplateDescriptions ) {
		return promise;
	}

	const templateDataMessage = mw.message( 'templatedata-doc-subpage' ),
		templateDataInstalled = templateDataMessage.exists(),
		templateDocPageFragment = '/' + templateDataMessage.text();

	let originalResponse;
	return promise
		.then( ( response ) => {
			const redirects = ( response.query && response.query.redirects ) || [];
			let newPages = ( response.query && response.query.pages ) || [];

			newPages.forEach( ( page ) => {
				if ( !( 'index' in page ) ) {
					// Watch out for cases where the index is specified on the redirect object
					// rather than the page object.
					for ( const j in redirects ) {
						if ( redirects[ j ].to === page.title ) {
							page.index = redirects[ j ].index;
							break;
						}
					}
				}
			} );

			// T54448: Filter out matches which end in /doc or as configured on-wiki
			if ( templateDataInstalled ) {
				newPages = newPages.filter(
					// Can't use String.endsWith() as that's ES6.
					// page.title.endsWith( templateDocPageFragment )
					( page ) => page.title.slice( -templateDocPageFragment.length ) !== templateDocPageFragment
				);
			}

			// Ensure everything goes into the order defined by the page's index key
			newPages.sort( ( a, b ) => {
				// T366299: Avoid unstable sort.
				if ( a.index === undefined || b.index === undefined ) {
					return 0;
				}
				return a.index - b.index;
			} );

			const titles = newPages.map( ( page ) => page.title );

			ve.setProp( response, 'query', 'pages', newPages );
			originalResponse = response; // lie!

			// Also get descriptions
			// FIXME: This should go through MWTransclusionModel rather than duplicate.
			if ( titles.length > 0 ) {
				const xhr = this.getApi().get( {
					action: 'templatedata',
					titles: titles,
					redirects: !!this.showRedirects,
					includeMissingTitles: '1',
					lang: mw.config.get( 'wgUserLanguage' )
				} );
				return xhr.promise( { abort: xhr.abort } );
			}
		} )
		.then( ( templateDataResponse ) => {
			const pages = ( templateDataResponse && templateDataResponse.pages ) || {};
			// Look for descriptions and cache them
			for ( const i in pages ) {
				const page = pages[ i ];

				if ( page.missing ) {
					// Remember templates that don't exist in the link cache
					// { title: { missing: true|false }
					const missingTitle = {};
					missingTitle[ page.title ] = { missing: true };
					ve.init.platform.linkCache.setMissing( missingTitle );
				} else if ( !page.notemplatedata ) {
					// Cache descriptions
					this.descriptions[ page.title ] = page.description;
				}
			}
			// Return the original response
			return originalResponse;
		// API request failed; most likely, we're on a wiki which doesn't have TemplateData.
		}, () => originalResponse || ve.createDeferred().reject() )
		.promise( { abort: () => {} } );
};

/**
 * @private
 * @method
 * @param {Object} response Action API response from server
 * @return {Object} Modified response
 */
ve.ui.MWTemplateTitleInputWidget.prototype.addExactMatch = function ( response ) {
	const query = this.getQueryValue(),
		title = mw.Title.newFromText( query, this.namespace );
	// No point in trying anything when the title is invalid
	if ( !title ) {
		return response;
	}

	if ( !response.query ) {
		response.query = { pages: [] };
	}

	const lowerTitle = title.getPrefixedText().toLowerCase(),
		metadata = response.query.redirects || [],
		foundMatchingMetadata = metadata.some( ( redirect ) => redirect.from.toLowerCase() === lowerTitle );
	if ( foundMatchingMetadata ) {
		// Redirects will be carefully positioned later in TitleWidget.getOptionsFromData()
		return response;
	}

	/**
	 * @typedef {Object} PageResponse
	 * @memberof ve.ui.MWTemplateTitleInputWidget
	 * @param {number} pageId Page ID
	 * @param {number} index Page ID
	 */

	/**
	 * @param {ve.ui.MWTemplateTitleInputWidget.PageResponse[]} pages
	 * @param {number} pageId
	 * @return {boolean}
	 */
	const containsPageId = ( pages, pageId ) => pageId && pages.some( ( page ) => page.pageid === pageId );

	/**
	 * @param {ve.ui.MWTemplateTitleInputWidget.PageResponse[]} pages
	 * @param {Object} [newPage]
	 */
	const unshiftPages = ( pages, newPage ) => {
		pages.forEach( ( page ) => {
			page.index++;
		} );
		if ( newPage && newPage.title ) {
			newPage.index = 1;
			pages.unshift( newPage );
			if ( pages.length > this.limit ) {
				pages.sort( ( a, b ) => a.index - b.index ).splice( this.limit );
			}
		}
	};

	const matchingRedirects = response.query.pages.filter( ( page ) => page.redirecttitle && page.redirecttitle.toLowerCase() === lowerTitle );
	if ( matchingRedirects.length ) {
		for ( let i = matchingRedirects.length; i--; ) {
			const matchingRedirect = matchingRedirects[ i ];
			// Offer redirects as separate options when the user's input is an exact match
			unshiftPages( response.query.pages, {
				pageid: matchingRedirect.pageid,
				ns: matchingRedirect.ns,
				title: matchingRedirect.redirecttitle
			} );
		}
		return response;
	}

	const matchingTitles = response.query.pages.filter( ( page ) => page.title.toLowerCase() === lowerTitle );
	if ( matchingTitles.length ) {
		for ( let i = matchingTitles.length; i--; ) {
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
		// Try to fill with prefix matches, otherwise just the top-1 prefix match
		gpslimit: this.limit
	} ).then( ( prefixMatches ) => {
		// action=query returns page objects in `{ query: { pages: [] } }`, not keyed by page id
		const pages = prefixMatches.query && prefixMatches.query.pages || [];
		pages.sort( ( a, b ) => a.index - b.index );
		for ( const i in pages ) {
			const prefixMatch = pages[ i ];
			if ( !containsPageId( response.query.pages, prefixMatch.pageid ) ) {
				// Move prefix matches to the top, indexed from -9 to 0, relevant for e.g. {{!!}}
				// Note: Sorting happens later in mw.widgets.TitleWidget.getOptionsFromData()
				prefixMatch.index -= this.limit;
				response.query.pages.push( prefixMatch );
			}
			// Check only after the top-1 prefix match is guaranteed to be present
			if ( response.query.pages.length >= this.limit ) {
				break;
			}
		}
		return response;
	// Proceed with the unmodified response in case the additional API request failed
	}, () => response )
		.promise( { abort: () => {} } );
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
	const widget = ve.ui.MWTemplateTitleInputWidget.super.prototype.createOptionWidget.call( this, data );

	if ( data.redirecttitle ) {
		// Same conditions as in mw.widgets.TitleWidget.getOptionWidgetData()
		const title = new mw.Title( data.redirecttitle ),
			text = this.namespace !== null && this.relative ?
				title.getRelativeText( this.namespace ) :
				data.redirecttitle;

		let $desc = widget.$element.find( '.mw-widget-titleOptionWidget-description' );
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
