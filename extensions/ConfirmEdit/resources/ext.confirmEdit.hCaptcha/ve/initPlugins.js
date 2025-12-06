/**
 * Initialises the hCaptcha plugins for VisualEditor.
 * This file is used to avoid side effects in QUnit tests on the
 * files that are included.
 *
 * This should be called only when VisualEditor is loaded and ideally
 * called by providing this callback to `mw.libs.ve.targetLoader.addPlugin`
 */
module.exports = () => {
	require( './ve.init.mw.HCaptcha.js' )();
	require( './ve.init.mw.HCaptchaSaveErrorHandler.js' )();
	require( './ve.init.mw.HCaptchaOnLoadHandler.js' )();

	ve.init.mw.HCaptchaOnLoadHandler.static.init();
};
