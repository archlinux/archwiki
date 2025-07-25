/*!
 * VisualEditor MediaWiki DiffLoader.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/* global ve */

/**
 * Diff loader.
 *
 * @class mw.libs.ve.diffLoader
 * @singleton
 * @hideconstructor
 */
( function () {
	const revCache = {};

	mw.libs.ve = mw.libs.ve || {};

	mw.libs.ve.diffLoader = {
		/**
		 * Get a ve.dm.Document model from a Parsoid response
		 *
		 * @param {Object} response Parsoid response from the VisualEditor API
		 * @param {string|null} section Section. Null for the whole document.
		 * @return {ve.dm.Document|null} Document, or null if an invalid response
		 */
		getModelFromResponse: function ( response, section ) {
			// This method is only called after actually loading these, see `parseDocumentModulePromise`
			const targetClass = ve.init.mw.ArticleTarget,
				data = response ? ( response.visualeditor || response.visualeditoredit ) : null;
			if ( data && typeof data.content === 'string' ) {
				const doc = targetClass.static.parseDocument( data.content, 'visual', section, section !== null );
				mw.libs.ve.stripRestbaseIds( doc );
				return targetClass.static.createModelFromDom( doc, 'visual' );
			}
			return null;
		},

		/**
		 * Fetch a specific revision from Parsoid as a DM document, and cache in memory
		 *
		 * @param {number} revId Revision ID
		 * @param {string} [pageName] Page name, defaults to wgRelevantPageName
		 * @param {string|null} [section=null] Section. Null for the whole document.
		 * @param {jQuery.Promise} [parseDocumentModulePromise] Promise which resolves when Target#parseDocument is available
		 * @return {jQuery.Promise} Promise which resolves with a document model
		 */
		fetchRevision: function ( revId, pageName, section, parseDocumentModulePromise ) {
			pageName = pageName || mw.config.get( 'wgRelevantPageName' );
			parseDocumentModulePromise = parseDocumentModulePromise || $.Deferred().resolve().promise();
			section = section !== undefined ? section : null;

			const cacheKey = revId + ( section !== null ? '/' + section : '' );

			revCache[ cacheKey ] = revCache[ cacheKey ] ||
				mw.libs.ve.targetLoader.requestParsoidData(
					pageName,
					{ oldId: revId, targetName: 'diff' },
					false,
					// noMetadata, we only use `content` in getModelFromResponse
					true
				).then(
					( response ) => parseDocumentModulePromise.then( () => mw.libs.ve.diffLoader.getModelFromResponse( response, section ) ),
					( ...args ) => {
						// Clear promise. Do not cache errors.
						delete revCache[ cacheKey ];
						// Let caller handle the error code
						return $.Deferred().reject( ...args );
					}
				);

			return revCache[ cacheKey ];
		},

		/**
		 * Get a visual diff generator promise
		 *
		 * @param {number|jQuery.Promise} oldIdOrPromise Old revision ID, or document model promise
		 * @param {number|jQuery.Promise} newIdOrPromise New revision ID, or document model promise
		 * @param {jQuery.Promise} [parseDocumentModulePromise] Promise which resolves when Target#parseDocument is available
		 * @param {string} [oldPageName] Old revision's page name, defaults to wgRelevantPageName
		 * @param {string} [newPageName] New revision's page name, defaults to oldPageName
		 * @return {jQuery.Promise} Promise which resolves with a ve.dm.VisualDiff generator function
		 */
		getVisualDiffGeneratorPromise: function ( oldIdOrPromise, newIdOrPromise, parseDocumentModulePromise, oldPageName, newPageName ) {
			parseDocumentModulePromise = parseDocumentModulePromise || $.Deferred().resolve().promise();
			oldPageName = oldPageName || mw.config.get( 'wgRelevantPageName' );

			const oldRevPromise = typeof oldIdOrPromise === 'number' ? this.fetchRevision( oldIdOrPromise, oldPageName, null, parseDocumentModulePromise ) : oldIdOrPromise;
			const newRevPromise = typeof newIdOrPromise === 'number' ? this.fetchRevision( newIdOrPromise, newPageName, null, parseDocumentModulePromise ) : newIdOrPromise;

			return $.when( oldRevPromise, newRevPromise, parseDocumentModulePromise ).then( ( oldDoc, newDoc ) => {
				// TODO: Differ expects newDoc to be derived from oldDoc and contain all its store data.
				// We may want to remove that assumption from the differ?
				newDoc.getStore().merge( oldDoc.getStore() );
				return () => new ve.dm.VisualDiff( oldDoc, newDoc );
			} );
		}

	};
}() );
