const createReferenceGateway = require( './createReferenceGateway.js' );
const createReferencePreview = require( './createReferencePreview.js' );
const TYPE_REFERENCE = 'reference';

/**
 * Given the global state of the application, creates a function that gets
 * whether or not the user should have Reference Previews enabled.
 *
 * @param {mw.user} user The `mw.user` singleton instance
 * @param {Function} isPreviewTypeEnabled check whether preview has been disabled or enabled.
 * @param {mw.Map} config
 *
 * @return {boolean|null} Null when there is no way the popup type can be enabled at run-time.
 * @memberof module:ext.cite.referencePreviews
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

const referencePreviewsState = isReferencePreviewsEnabled(
	mw.user,
	mw.popups.isEnabled,
	mw.config
);

/**
 * Create the relevant config to register the preview type in the Popups extension.
 *
 * @see mw.popups.register()
 * @return {Object}
 * @memberof module:ext.cite.referencePreviews
 */
function createReferencePreviewsType() {
	return {
		type: TYPE_REFERENCE,
		selector: '#mw-content-text .reference a[ href*="#" ]',
		delay: 150,
		gateway: createReferenceGateway(),
		renderFn: createReferencePreview
	};
}

module.exports = referencePreviewsState !== null ? createReferencePreviewsType() : null;

// Expose private methods for QUnit tests
if ( typeof QUnit !== 'undefined' ) {
	module.exports = { private: {
		createReferenceGateway: require( './createReferenceGateway.js' ),
		createReferencePreview: require( './createReferencePreview.js' ),
		isReferencePreviewsEnabled
	} };
}
