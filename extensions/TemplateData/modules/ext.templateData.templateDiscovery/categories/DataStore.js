const mwConfig = require( '../mwConfig.json' );

/**
 * @class
 * @constructor
 */
function DataStore() {
	this.namespaceIds = [];
	this.namespaceIds.push( ...mwConfig.TemplateDataEditorNamespaces );
	this.catNsId = mw.config.get( 'wgNamespaceIds' ).category;
	this.namespaceIds.push( this.catNsId );
	this.api = new mw.Api();
}

/**
 * @param {string} categoryTitle
 * @param {string} cmcontinue
 * @return {Promise}
 */
DataStore.prototype.getColumnData = function ( categoryTitle, cmcontinue ) {
	return Promise.resolve( this.api.get( {
		action: 'query',
		list: 'categorymembers',
		cmcontinue: cmcontinue,
		cmlimit: 40,
		cmtitle: categoryTitle,
		cmprop: 'title|sortkey|ids',
		cmnamespace: this.namespaceIds.join( '|' ),
		errorformat: 'html',
		formatversion: 2
	} ) ).then( ( data ) => {
		const out = [];
		if ( data.query.categorymembers === undefined ) {
			return out;
		}
		for ( const page of data.query.categorymembers ) {
			const pageTitle = new mw.Title( page.title );
			out.push( {
				data: {
					pageId: page.pageid,
					isCategory: page.ns === this.catNsId,
					value: pageTitle.getPrefixedText()
				},
				label: pageTitle.getMainText(),
				icon: page.ns !== this.catNsId ? 'puzzle' : null,
				// @todo Use 'next' indicator after figuring out how.
				indicator: page.ns === this.catNsId ? 'down' : null
			} );
		}
		if ( data.continue !== undefined ) {
			out.push( {
				label: mw.msg( 'templatedata-category-browser-loadmore' ),
				data: { cmcontinue: data.continue.cmcontinue },
				icon: null,
				indicator: null
			} );
		}
		return out;
	} );
};

/**
 * @param {number} pageId
 * @return {Promise}
 */
DataStore.prototype.getItemData = function ( pageId ) {
	// @todo It it really not possible to do these in the same request?
	return Promise.all( [
		this.api.get( {
			action: 'templatedata',
			includeMissingTitles: 1,
			pageids: pageId,
			lang: mw.config.get( 'wgUserLanguage' ),
			redirects: 1,
			formatversion: 2
		} ),
		this.api.get( {
			action: 'query',
			format: 'json',
			prop: 'categories',
			pageids: pageId,
			formatversion: 2,
			clprop: 'hidden|sortkey'
		} )
	] ).then( ( responses ) => {
		const out = {};
		if ( Object.keys( responses[ 0 ].pages ).length > 0 ) {
			const gotPageId = Object.keys( responses[ 0 ].pages )[ 0 ];
			if ( parseInt( gotPageId ) !== pageId ) {
				pageId = parseInt( gotPageId );
			}
			out.templatedata = responses[ 0 ].pages[ pageId ];
			// Set the pageId for easier access later.
			out.templatedata.pageId = pageId;
		}
		if ( responses[ 1 ].query.pages[ 0 ] !== undefined ) {
			out.categories = responses[ 1 ].query.pages[ 0 ];
		}
		return out;
	} );
};

module.exports = DataStore;
