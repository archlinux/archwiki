/**
 * Defines and installs the hCaptcha error handler plugin for VisualEditor.
 * This code will only handle displaying hCaptcha if VisualEditor tries to
 * make an edit and that edit fails due to requiring hCaptcha.
 *
 * Returns a callback that should be executed in initPlugins.js after `ve.init.mw.HCaptcha`
 * is loaded
 */
module.exports = () => {
	const config = require( './../config.json' );

	ve.init.mw.HCaptchaSaveErrorHandler = function () {};

	OO.inheritClass( ve.init.mw.HCaptchaSaveErrorHandler, ve.init.mw.SaveErrorHandler );

	OO.inheritClass( ve.init.mw.HCaptchaSaveErrorHandler, ve.init.mw.HCaptcha );

	ve.init.mw.HCaptchaSaveErrorHandler.static.name = 'confirmEditHCaptcha';

	ve.init.mw.HCaptchaSaveErrorHandler.static.window = window;

	/**
	 * Whether the save error handler has already automatically pressed "Save changes".
	 * Used to avoid resubmitting hCaptcha automatically indefinitely.
	 *
	 * @type {boolean}
	 */
	ve.init.mw.HCaptchaSaveErrorHandler.static.hasAlreadyAutomaticallyResubmitted = false;

	ve.init.mw.HCaptchaSaveErrorHandler.static.matchFunction = function ( data ) {
		const captchaData = ve.getProp( data, 'visualeditoredit', 'edit', 'captcha' );

		return !!( captchaData && captchaData.type === 'hcaptcha' );
	};

	ve.init.mw.HCaptchaSaveErrorHandler.static.process = function ( data, target ) {
		// Destroy any existing hCaptcha widget from the onload handler or this handler
		// as this widget needs to be used instead
		let executeSaveAfterRender = false;
		if ( ve.init.mw.HCaptchaOnLoadHandler ) {
			ve.init.mw.HCaptchaOnLoadHandler.static.destroyWidget( target );
			if ( ve.init.mw.HCaptchaOnLoadHandler.static.shouldRun() && !this.hasAlreadyAutomaticallyResubmitted ) {
				this.hasAlreadyAutomaticallyResubmitted = true;
				executeSaveAfterRender = true;
			}
		}

		const $hCaptchaWidgetContainer = $( '<div>' ),
			$container = $( '<div>' );

		if ( config.HCaptchaInvisibleMode ) {
			const $hCaptchaEditNotice = $( '<p>' );
			$hCaptchaEditNotice.html( mw.message( 'hcaptcha-visual-editor-error-handler-warning' ).parse() );
			$hCaptchaEditNotice.addClass(
				've-ui-mwSaveDialog-license ext-confirmEdit-hcaptcha-visual-editor-error-handler-warning'
			);
			$container.append( $hCaptchaEditNotice );
		}

		this.renderHCaptchaPrivacyPolicyNotice( $container );

		$hCaptchaWidgetContainer.addClass( 'ext-confirmEdit-visualEditor-hCaptchaWidgetContainer' );
		$container.addClass( 'ext-confirmEdit-visualEditor-hCaptchaContainer' );
		$container.append( $hCaptchaWidgetContainer );

		this.getReadyPromise()
			.then( () => {
				// Drop any other hCaptcha widget as we are going to add one
				// via this code in a specific place
				target.saveDialog.$element.remove( '.ext-confirmEdit-visualEditor-hCaptchaContainer' );

				// ProcessDialog's error system isn't great for this yet.
				target.saveDialog.clearMessage( 'api-save-error' );
				target.saveDialog.showMessage( 'api-save-error', $container, { wrap: false } );

				let siteKey = null;
				const captchaData = ve.getProp( data, 'visualeditoredit', 'edit', 'captcha' );
				if ( captchaData && captchaData.type === 'hcaptcha' && captchaData.key ) {
					if ( captchaData.error === 'forceshowcaptcha' ) {
						target.saveFields.wgConfirmEditForceShowCaptcha = () => true;
					}
					siteKey = captchaData.key;
				}

				this.renderHCaptchaWidget(
					this.window,
					target,
					$hCaptchaWidgetContainer,
					siteKey
				);

				target.saveDialog.popPending();
				target.emit( 'saveErrorCaptcha' );

				if ( executeSaveAfterRender ) {
					target.saveDialog.executeAction( 'save' );
				}
			} );
	};

	/**
	 * When the save dialog is closed, we should reset the auto-resubmit code.
	 *
	 * @param {ve.init.Target} target
	 * @return {void}
	 */
	ve.init.mw.HCaptchaSaveErrorHandler.static.onSaveWorkflowEnd = function ( target ) {
		ve.init.mw.HCaptcha.static.onSaveWorkflowEnd.call( this, target );

		this.hasAlreadyAutomaticallyResubmitted = false;
	};

	ve.init.mw.saveErrorHandlerFactory.register( ve.init.mw.HCaptchaSaveErrorHandler );
};
