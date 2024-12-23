let isTracking = false;

/**
 * @memberof module:ext.cite.referencePreviews
 */
const LOGGING_SCHEMA = 'event.ReferencePreviewsPopups';

/**
 * Run once the preview is initialized.
 *
 * @memberof module:ext.cite.referencePreviews
 */
function initReferencePreviewsInstrumentation() {
	if ( mw.config.get( 'wgCiteReferencePreviewsActive' ) &&
		navigator.sendBeacon &&
		mw.config.get( 'wgIsArticle' ) &&
		!isTracking
	) {
		isTracking = true;
		mw.track( LOGGING_SCHEMA, { action: 'pageview' } );
	}
}

/**
 * @memberof module:ext.cite.referencePreviews
 * @return {boolean}
 */
function isTrackingEnabled() {
	return isTracking;
}

module.exports = {
	LOGGING_SCHEMA,
	initReferencePreviewsInstrumentation,
	isTrackingEnabled
};
