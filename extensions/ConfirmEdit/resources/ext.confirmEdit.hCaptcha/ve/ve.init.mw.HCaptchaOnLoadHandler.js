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
	 * Load the hCaptcha SDK when a user changes content in the VisualEditor editor if
	 * hCaptcha is required for a "generic" edit.
	 *
	 * @param {ve.init.Target} target
	 * @return {void}
	 */
	ve.init.mw.HCaptchaOnLoadHandler.static.onActivationComplete = function ( target ) {
		if ( !this.shouldRun() ) {
			return;
		}

		target.surface.getModel().getDocument().once( 'transact', () => {
			this.getReadyPromise();
		} );
	};

	/**
	 * Render the hCaptcha widget if not already being rendered or has been rendered,
	 * as long as hCaptcha is required for a "generic" edit.
	 *
	 * @param {window} win
	 * @param {ve.init.Target} target
	 * @return {Promise}
	 */
	ve.init.mw.HCaptchaOnLoadHandler.static.renderHCaptcha = function ( win, target ) {
		// Return early if not enabled, if the hCaptcha widget is currently being rendered,
		// or if hCaptcha has already been rendered.
		// This is needed because this method is called when the state of the dialog changes
		// and so could be called multiple times.
		if ( !this.shouldRun() || this.isHCaptchaRendering || this.isHCaptchaRendered ) {
			return Promise.resolve();
		}

		this.isHCaptchaRendering = true;

		// Drop any other hCaptcha widget as we are going to add one ourselves in a specific place
		const saveDialog = target.saveDialog;
		saveDialog.$element.find( '.ext-confirmEdit-visualEditor-hCaptchaContainer' ).remove();

		const $hCaptchaContainer = $( '<div>' );
		this.renderHCaptchaPrivacyPolicyNotice( $hCaptchaContainer );

		const errorWidget = new ErrorWidget();
		$hCaptchaContainer.append( errorWidget.$element );

		// Add a container to hold the hCaptcha widget to the DOM, as hcaptcha.render requires
		// the container element exist in the DOM for it to work.
		$hCaptchaContainer.addClass( 'ext-confirmEdit-visualEditor-hCaptchaContainer' );
		$hCaptchaContainer.addClass( 'ext-confirmEdit-visualEditor-hCaptchaOnLoadContainer' );

		const $hCaptchaWidgetContainer = $( '<div>' );
		$hCaptchaWidgetContainer.addClass( 'ext-confirmEdit-visualEditor-hCaptchaWidgetContainer' );
		$hCaptchaContainer.append( $hCaptchaWidgetContainer );
		saveDialog.$element.find( '.ve-ui-mwSaveDialog-foot' ).append( $hCaptchaContainer );
		saveDialog.updateSize();

		// Render hCaptcha after checking that the hCaptcha SDK is definitely loaded
		const loadPromise = this.getReadyPromise();
		loadPromise.then(
			() => {
				if ( mw.config.get( 'wgConfirmEditForceShowCaptcha' ) ) {
					target.saveFields.wgConfirmEditForceShowCaptcha = () => true;
				}

				this.renderHCaptchaWidget( win, target, $hCaptchaWidgetContainer );

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
	 * @param {ve.init.Target} target
	 * @return {void}
	 */
	ve.init.mw.HCaptchaOnLoadHandler.static.onSaveWorkflowEnd = function ( target ) {
		ve.init.mw.HCaptcha.static.onSaveWorkflowEnd.call( this, target );

		this.isHCaptchaRendering = false;
		this.isHCaptchaRendered = false;
	};

	/**
	 * Destroys the hCaptcha onload widget and makes this handler stop doing anything in the
	 * save process unless re-rendered.
	 *
	 * @param {ve.init.Target} target
	 * @return {void}
	 */
	ve.init.mw.HCaptchaOnLoadHandler.static.destroyWidget = function ( target ) {
		if ( target.saveDialog ) {
			target.saveDialog.$element.find( '.ext-confirmEdit-visualEditor-hCaptchaOnLoadContainer' ).remove();
		}

		this.widgetId = null;
		this.hCaptchaResponseToken = null;
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
		return mw.config.get( 'wgConfirmEditCaptchaNeededForGenericEdit' ) === 'hcaptcha' &&
			mw.config.get( 'wgConfirmEditHCaptchaVisualEditorOnLoadIntegrationEnabled' );
	};

	/**
	 * Initialises the hCaptcha VisualEditor on load handler for the current page.
	 */
	ve.init.mw.HCaptchaOnLoadHandler.static.init = function () {
		ve.init.mw.HCaptcha.static.init.call( this );

		mw.hook( 've.newTarget' ).add( ( target ) => {
			if ( target.constructor.static.name !== 'article' ) {
				return;
			}
			target.on( 'surfaceReady', () => {
				this.onActivationComplete( target );
			} );
			target.on( 'saveWorkflowChangePanel', () => {
				this.renderHCaptcha( window, target );
			} );
		} );
	};
};
