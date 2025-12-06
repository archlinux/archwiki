/**
 * Defines and installs the hCaptcha plugin for VisualEditor that displays hCaptcha
 * when a user needs to complete hCaptcha for a "generic" edit.
 * A generic edit is an edit where a user needing to fill out a captcha is not
 * dependent on the content of the edit.
 *
 * Returns a callback that should be executed in initPlugins.js after `ve.init.mw.HCaptcha`
 * is loaded
 */
module.exports = () => {
	// Load these here so that in QUnit tests we have a chance to mock utils.js
	const config = require( './../config.json' );
	const ErrorWidget = require( '../ErrorWidget.js' );
	const { mapErrorCodeToMessageKey } = require( './../utils.js' );

	ve.init.mw.HCaptchaOnLoadHandler = function () {};

	OO.inheritClass( ve.init.mw.HCaptchaOnLoadHandler, ve.init.mw.HCaptcha );

	/**
	 * Whether the hCaptcha widget is in the process of being rendered in the save dialog
	 *
	 * @type {boolean}
	 */
	ve.init.mw.HCaptchaOnLoadHandler.static.isHCaptchaRendering = false;

	/**
	 * Whether the hCaptcha widget has been rendered in the save dialog
	 *
	 * @type {boolean}
	 */
	ve.init.mw.HCaptchaOnLoadHandler.static.isHCaptchaRendered = false;

	/**
	 * The return value of `hcaptcha.render`, which is the widget ID of the
	 * rendered hCaptcha widget. This can be used by `executeHCaptcha`
	 * to programmatically execute hCaptcha in invisible mode.
	 *
	 * @type {string|null} `null` if no hCaptcha widget is rendered yet
	 */
	ve.init.mw.HCaptchaOnLoadHandler.static.widgetId = null;

	/**
	 * Load the hCaptcha SDK when a user changes content in the VisualEditor editor if
	 * hCaptcha is required for a "generic" edit.
	 *
	 * @return {void}
	 */
	ve.init.mw.HCaptchaOnLoadHandler.static.onActivationComplete = function () {
		if ( !this.shouldRun() ) {
			return;
		}

		const surface = ve.init.target.surface;
		surface.getModel().getDocument().once( 'transact', () => {
			this.getReadyPromise();
		} );
	};

	/**
	 * Render the hCaptcha widget if not already being rendered or has been rendered,
	 * as long as hCaptcha is required for a "generic" edit.
	 *
	 * @param {window} win
	 * @return {Promise}
	 */
	ve.init.mw.HCaptchaOnLoadHandler.static.renderHCaptcha = function ( win ) {
		// Return early if not enabled, if the hCaptcha widget is currently being rendered,
		// or if hCaptcha has already been rendered.
		// This is needed because this method is called when the state of the dialog changes
		// and so could be called multiple times.
		if ( !this.shouldRun() || this.isHCaptchaRendering || this.isHCaptchaRendered ) {
			return Promise.resolve();
		}

		this.isHCaptchaRendering = true;

		// Drop any other hCaptcha widget as we are going to add one ourselves in a specific place
		const saveDialog = ve.init.target.saveDialog;
		saveDialog.$element.find( '.ext-confirmEdit-visualEditor-hCaptchaContainer' ).remove();

		const $hCaptchaContainer = $( '<div>' );

		// If in secure enclave mode, we should add the hCaptcha privacy policy text
		// now to make the text appear as soon as possible.
		if ( config.HCaptchaInvisibleMode ) {
			const $privacyPolicyNotice = $( '<div>' );
			$privacyPolicyNotice.html( mw.message( 'hcaptcha-privacy-policy' ).parse() );
			$privacyPolicyNotice.addClass( 'ext-confirmEdit-hcaptcha-privacy-policy ve-ui-mwSaveDialog-license' );
			$hCaptchaContainer.append( $privacyPolicyNotice );
		}

		const errorWidget = new ErrorWidget();
		$hCaptchaContainer.append( errorWidget.$element );

		// Add a container to hold the hCaptcha widget to the DOM, as hcaptcha.render requires
		// the container element exist in the DOM for it to work.
		$hCaptchaContainer.addClass( 'ext-confirmEdit-visualEditor-hCaptchaContainer' );

		const $hCaptchaWidgetContainer = $( '<div>' );
		$hCaptchaWidgetContainer.addClass( 'ext-confirmEdit-visualEditor-hCaptchaWidgetContainer' );
		$hCaptchaContainer.append( $hCaptchaWidgetContainer );
		saveDialog.$element.find( '.ve-ui-mwSaveDialog-foot' ).append( $hCaptchaContainer );
		saveDialog.updateSize();

		// Render hCaptcha after checking that the hCaptcha SDK is definitely loaded
		const loadPromise = this.getReadyPromise();
		loadPromise.then(
			() => {
				this.widgetId = win.hcaptcha.render( $hCaptchaWidgetContainer[ 0 ], {
					sitekey: mw.config.get( 'wgConfirmEditHCaptchaSiteKey' )
				} );
				saveDialog.updateSize();

				this.isHCaptchaRendering = false;
				this.isHCaptchaRendered = true;
			},
			( error ) => {
				// Possible message keys used here:
				// * hcaptcha-generic-error
				// eslint-disable-next-line mediawiki/msg-doc
				errorWidget.show( mw.msg( mapErrorCodeToMessageKey( error ) ) );
				saveDialog.updateSize();

				this.isHCaptchaRendering = false;
				this.isHCaptchaRendered = false;
			}
		);

		return loadPromise;
	};

	/**
	 * When the save dialog is closed, we no longer have a rendered hCaptcha widget and so should
	 * keep track of that so that if it is opened again the hCaptcha widget is re-rendered.
	 *
	 * @return {void}
	 */
	ve.init.mw.HCaptchaOnLoadHandler.static.onSaveWorkflowEnd = function () {
		this.isHCaptchaRendering = false;
		this.isHCaptchaRendered = false;
	};

	/**
	 * Returns whether this code should do anything. If it returns false,
	 * then the code is essentially a no-op.
	 *
	 * Intended to ensure that hCaptcha is only loaded when needed and when the
	 * definitely needs to solve a captcha to make the edit.
	 *
	 * @return {boolean}
	 */
	ve.init.mw.HCaptchaOnLoadHandler.static.shouldRun = function () {
		return mw.config.get( 'wgConfirmEditCaptchaNeededForGenericEdit' ) === 'hcaptcha';
	};

	/**
	 * Initialises the hCaptcha VisualEditor on load handler for the current page.
	 */
	ve.init.mw.HCaptchaOnLoadHandler.static.init = function () {
		mw.hook( 've.activationComplete' ).add( () => {
			ve.init.mw.HCaptchaOnLoadHandler.static.onActivationComplete();
			ve.init.target.connect( this, { saveWorkflowEnd: 'onSaveWorkflowEnd' } );
		} );
		mw.hook( 've.saveDialog.stateChanged' ).add( () => {
			ve.init.mw.HCaptchaOnLoadHandler.static.renderHCaptcha( window );
		} );
	};
};
