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
	return page;
};

/* Methods */

/**
 * @inheritdoc
 */
ve.init.mw.TemplateDataCache.prototype.getRequestPromise = function ( subqueue ) {
	return this.api.get( {
		action: 'templatedata',
		lang: mw.config.get( 'wgUserLanguage' ),
		includeMissingTitles: '1',
		redirects: '1',
		titles: subqueue
	} );
};
