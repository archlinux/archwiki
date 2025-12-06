/**
 * Defines and installs the hCaptcha error handler plugin for VisualEditor.
 * This code will only handle displaying hCaptcha if VisualEditor tries to
 * make an edit and that edit fails due to requiring hCaptcha.
 *
 * Returns a callback that should be executed in initPlugins.js after `ve.init.mw.HCaptcha`
 * is loaded
 */
module.exports = () => {
	// Load these here so that in QUnit tests we have a chance to mock utils.js
	ve.init.mw.HCaptchaSaveErrorHandler = function () {};

	OO.inheritClass( ve.init.mw.HCaptchaSaveErrorHandler, ve.init.mw.SaveErrorHandler );

	OO.inheritClass( ve.init.mw.HCaptchaSaveErrorHandler, ve.init.mw.HCaptcha );

	ve.init.mw.HCaptchaSaveErrorHandler.static.name = 'confirmEditHCaptcha';

	ve.init.mw.HCaptchaSaveErrorHandler.static.matchFunction = function ( data ) {
		const captchaData = ve.getProp( data, 'visualeditoredit', 'edit', 'captcha' );

		return !!( captchaData && captchaData.type === 'hcaptcha' );
	};

	ve.init.mw.HCaptchaSaveErrorHandler.static.process = function ( data, target ) {
		const self = this,
			siteKey = mw.config.get( 'wgConfirmEditHCaptchaSiteKey' ),
			$container = $( '<div>' );

		// Register extra fields
		target.saveFields.wpCaptchaWord = function () {
			// eslint-disable-next-line no-jquery/no-global-selector
			return $( '[name=h-captcha-response]' ).val();
		};

		this.getReadyPromise()
			.then( () => {
				// Drop any other hCaptcha widget as we are going to add one
				// via this code in a specific place
				target.saveDialog.$element.remove( '.ext-confirmEdit-visualEditor-hCaptchaContainer' );

				// ProcessDialog's error system isn't great for this yet.
				target.saveDialog.clearMessage( 'api-save-error' );
				target.saveDialog.showMessage( 'api-save-error', $container, { wrap: false } );
				self.widgetId = window.hcaptcha.render( $container[ 0 ], {
					sitekey: siteKey,
					callback: function () {
						target.saveDialog.executeAction( 'save' );
					},
					'expired-callback': function () {},
					'error-callback': function () {}
				} );
				target.saveDialog.popPending();
				target.saveDialog.updateSize();

				target.emit( 'saveErrorCaptcha' );
			} );
	};

	ve.init.mw.saveErrorHandlerFactory.register( ve.init.mw.HCaptchaSaveErrorHandler );
};
