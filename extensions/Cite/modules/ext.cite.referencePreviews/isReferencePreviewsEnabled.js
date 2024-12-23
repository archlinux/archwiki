const { TYPE_REFERENCE } = require( './constants.js' );

/**
 * @module isReferencePreviewsEnabled
 */

/**
 * Given the global state of the application, creates a function that gets
 * whether or not the user should have Reference Previews enabled.
 *
 * @param {mw.user} user The `mw.user` singleton instance
 * @param {Function} isPreviewTypeEnabled check whether preview has been disabled or enabled.
 * @param {mw.Map} config
 *
 * @return {boolean|null} Null when there is no way the popup type can be enabled at run-time.
 */
function isReferencePreviewsEnabled( user, isPreviewTypeEnabled, config ) {
	if ( !config.get( 'wgCiteReferencePreviewsActive' ) ) {
		return null;
	}

	if ( user.isAnon() ) {
		return isPreviewTypeEnabled( TYPE_REFERENCE );
	}

	return true;
}

module.exports = isReferencePreviewsEnabled;
