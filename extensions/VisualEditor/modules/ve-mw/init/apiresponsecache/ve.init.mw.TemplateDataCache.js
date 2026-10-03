/*!
 * VisualEditor MediaWiki Initialization TemplateDataCache class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Get TemplateData.
 *
 * @class
 * @extends ve.init.mw.ApiResponseCache
 * @constructor
 * @param {mw.Api} [api]
 */
ve.init.mw.TemplateDataCache = function VeInitMwTemplateDataCache() {
	// Parent constructor
	ve.init.mw.TemplateDataCache.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.init.mw.TemplateDataCache, ve.init.mw.ApiResponseCache );

/* Static methods */

/**
 * @inheritdoc
 */
ve.init.mw.TemplateDataCache.static.processPage = function ( page ) {
	if ( page.missing ) {
		// Record missing templates in the link cache. The transclusion dialog reads it to tell
		// the user that the template does not exist (T162694).
		const missingTitle = {};
		missingTitle[ page.title ] = { missing: true };
		ve.init.platform.linkCache.setMissing( missingTitle );
	}
	return page;
};

/* Methods */

/**
 * @inheritdoc
 */
ve.init.mw.TemplateDataCache.prototype.getRequestPromise = function ( subqueue ) {
	const xhr = this.api.get( {
		action: 'templatedata',
		// ve.dm.MWTemplatePageMetadata expects a formatversion=2 response
		formatversion: 2,
		lang: mw.config.get( 'wgUserLanguage' ),
		includeMissingTitles: '1',
		redirects: '1',
		titles: subqueue
	} );
	// This API keys the pages by page id. Copy the id into each page, because
	// ve.ui.MWTemplatePage needs it for the TemplateDiscovery favorite button. A missing
	// page has no usable id.
	return xhr.then( ( data ) => {
		for ( const pageId in data.pages ) {
			if ( !data.pages[ pageId ].missing ) {
				data.pages[ pageId ].pageId = pageId;
			}
		}
		return data;
	} ).promise( { abort: xhr.abort } );
};
