/**
 * Base class for the hCaptcha VisualEditor handlers
 *
 * Returns a callback that should be executed in initPlugins.js
 */
module.exports = () => {
	// Load these here so that in QUnit tests we have a chance to mock utils.js
	const { loadHCaptcha } = require( './../utils.js' );

	ve.init.mw.HCaptcha = function () {};

	OO.initClass( ve.init.mw.HCaptcha );

	ve.init.mw.HCaptcha.static.getReadyPromise = function () {
		if ( !this.readyPromise ) {
			this.readyPromise = loadHCaptcha( window, 'visualeditor', { render: 'explicit' } );
		}

		return this.readyPromise;
	};
};
