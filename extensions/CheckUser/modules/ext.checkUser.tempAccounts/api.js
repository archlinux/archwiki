/**
 * Get formatted block details for the current user via the API.
 *
 * @return {mw.Api~AbortablePromise}
 */
function getFormattedBlockDetails() {
	const api = new mw.Api();

	return api.get( {
		action: 'query',
		meta: 'checkuserformattedblockinfo',
		format: 'json',
		formatversion: '2'
	} );
}

module.exports = { getFormattedBlockDetails };
