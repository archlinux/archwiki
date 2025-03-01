let /** @type {MwApi} */ api;

/**
 * @param {Object<string,string|number>} options
 * @return {JQuery.Promise<Object>}
 */
function saveOptions( options ) {
	api = api || new mw.Api();
	// @ts-ignore
	return api.saveOptions( options, {
		global: 'update'
	} );
}

module.exports = {
	saveOptions
};
