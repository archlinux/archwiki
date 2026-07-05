/**
 * Base class for the hCaptcha VisualEditor handlers
 *
 * Returns a callback that should be executed in initPlugins.js
 */
module.exports = () => {
	// Load these here so that in QUnit tests we have a chance to mock utils.js
	const { loadHCaptcha, executeHCaptcha, mapErrorCodeToMessageKey, renderHCaptcha } = require( './../utils.js' );
	const config = require( './../config.json' );

	ve.init.mw.HCaptcha = function () {};

	OO.initClass( ve.init.mw.HCaptcha );

	/**
	 * The return value of `hcaptcha.render`, which is the widget ID of the
	 * rendered hCaptcha widget. This can be used by `executeHCaptcha`
	 * to programmatically execute hCaptcha in invisible mode.
	 *
	 * @type {string|null} `null` if no hCaptcha widget is rendered yet
	 */
	ve.init.mw.HCaptcha.static.widgetId = null;

	/**
	 * The hCaptcha response token provided by the win.hcaptcha.render callback.
	 *
	 * @type {string|null}
	 */
	ve.init.mw.HCaptcha.static.hCaptchaResponseToken = null;

	ve.init.mw.HCaptcha.static.getReadyPromise = function () {
		if ( !this.readyPromise ) {
			this.readyPromise = loadHCaptcha( window, 'visualeditor', { render: 'explicit' } );
		}

		return this.readyPromise;
	};

	/**
	 * Renders the hCaptcha privacy policy notice if invisible mode is enabled.
	 * Should ideally be called as soon as it is known that hCaptcha needs to be shown.
	 *
	 * @param {jQuery} $hCaptchaContainer The element to add the privacy policy notice to
	 */
	ve.init.mw.HCaptcha.static.renderHCaptchaPrivacyPolicyNotice = function ( $hCaptchaContainer ) {
		if ( config.HCaptchaInvisibleMode ) {
			const $privacyPolicyNotice = $( '<p>' );
			$privacyPolicyNotice.html( mw.message( 'hcaptcha-privacy-policy' ).parse() );
			$privacyPolicyNotice.addClass( 'ext-confirmEdit-hcaptcha-privacy-policy ve-ui-mwSaveDialog-license' );
			$hCaptchaContainer.append( $privacyPolicyNotice );
		}
	};

	/**
	 * Renders the hCaptcha widget in the VisualEditor save dialog.
	 *
	 * @param {Window} win
	 * @param {ve.init.Target} target
	 * @param {jQuery} $hCaptchaWidgetContainer
	 * @param {string|null} [siteKey] The sitekey to use in the rendered hCaptcha widget.
	 *   If not set or null, the default sitekey will be used.
	 * @return {Promise} A promise that resolves when the hCaptcha widget has finished executing
	 */
	ve.init.mw.HCaptcha.static.renderHCaptchaWidget = function (
		win,
		target,
		$hCaptchaWidgetContainer,
		siteKey
	) {
		let executionFinishedPromiseResolver = null;
		const executionFinishedPromise = new Promise( ( resolve ) => {
			executionFinishedPromiseResolver = resolve;
		} );

		const saveDialog = target.saveDialog;
		siteKey = siteKey || mw.config.get( 'wgConfirmEditHCaptchaSiteKey' ) || config.HCaptchaSiteKey;

		if ( config.HCaptchaInvisibleMode ) {
			$hCaptchaWidgetContainer.attr( 'data-size', 'invisible' );
		}

		// If using enterprise mode, we can show any hCaptcha challenge in a separate
		// window (which is preferable to expanding the save dialog). Otherwise,
		// we should render hCaptcha in a way which works in non-enterprise mode.
		if ( config.HCaptchaEnterprise ) {
			const removeBackdrop = () => {
				saveDialog.$element.find( '.ext-confirmEdit-hCaptcha-backdrop' ).remove();
			};

			// Remove any challenge container that already exists before rendering a new widget
			// to avoid two (or more) challenge iframes appearing next to each other
			// eslint-disable-next-line no-jquery/no-global-selector
			$( '.ext-confirmEdit-hCaptcha-challengeContainer' ).remove();

			const challengeContainerId = 'ext-confirmEdit-hCaptcha-challengeContainer';
			const $challengeContainer = $( '<div>' )
				.addClass( 'ext-confirmEdit-hCaptcha-challengeContainer' )
				.attr( 'id', challengeContainerId );
			$( win.document.body ).append( $challengeContainer );

			const closeCallbackInternal = () => {
				this.onHCaptchaChallengeClose( target );
				removeBackdrop();
			};

			this.widgetId = renderHCaptcha(
				win,
				'visualeditor',
				$hCaptchaWidgetContainer[ 0 ],
				{
					sitekey: siteKey,
					'challenge-container': challengeContainerId,
					callback: ( token ) => {
						removeBackdrop();
						executionFinishedPromiseResolver();
						this.hCaptchaResponseToken = token;
					},
					'open-callback': () => {
						removeBackdrop();
						saveDialog.$element.append(
							$( '<div>' ).addClass( 'ext-confirmEdit-hCaptcha-backdrop' )
						);
						this.onHCaptchaChallengeOpen( target );
					},
					'close-callback': closeCallbackInternal,
					'error-callback': closeCallbackInternal,
					'expired-callback': () => {
						this.hCaptchaResponseToken = null;
					},
					'chalexpired-callback': closeCallbackInternal
				}
			);
		} else {
			this.widgetId = renderHCaptcha(
				win,
				'visualeditor',
				$hCaptchaWidgetContainer[ 0 ],
				{
					sitekey: siteKey,
					callback: ( token ) => {
						executionFinishedPromiseResolver();
						this.hCaptchaResponseToken = token;
					},
					'open-callback': () => this.onHCaptchaChallengeOpen( target ),
					'close-callback': () => this.onHCaptchaChallengeClose( target ),
					'error-callback': () => this.onHCaptchaChallengeClose( target ),
					'expired-callback': () => {
						this.hCaptchaResponseToken = null;
					},
					'chalexpired-callback': () => this.onHCaptchaChallengeClose( target )
				}
			);
		}

		saveDialog.updateSize();

		return executionFinishedPromise;
	};

	/**
	 * Just before the save options are fetched for an edit submission, execute hCaptcha for
	 * the user (even if not in invisible mode).
	 *
	 * @param {ve.init.Target} target
	 * @return {Promise}
	 */
	ve.init.mw.HCaptcha.static.onSaveOptionsProcess = function ( target ) {
		if ( this.widgetId === null ) {
			return Promise.resolve();
		}

		if ( this.hCaptchaResponseToken ) {
			target.saveFields.wpCaptchaWord = () => this.hCaptchaResponseToken;
			return Promise.resolve();
		}

		return executeHCaptcha( window, this.widgetId, 'visualeditor' )
			.then( ( response ) => {
				this.hCaptchaResponseToken = response;

				target.saveFields.wpCaptchaWord = function () {
					return response;
				};

				mw.hook( 'confirmEdit.hCaptcha.executionSuccess' ).fire( response );
			} )
			.catch( ( error ) => {
				// If hCaptcha failed to execute, then show this as an error and stop
				// saving by rethrowing the error (making the rejected promise bubble up)

				// Possible message keys used here:
				// * hcaptcha-generic-error
				// * hcaptcha-challenge-closed
				// * hcaptcha-challenge-expired
				// * hcaptcha-internal-error
				// * hcaptcha-network-error
				// * hcaptcha-rate-limited
				target.showSaveError( mw.msg( mapErrorCodeToMessageKey( error ) ) );
				throw error;
			} );
	};

	/**
	 * Fires when the hCaptcha challenge opens
	 *
	 * @param {ve.init.Target} target
	 */
	ve.init.mw.HCaptcha.static.onHCaptchaChallengeOpen = function ( target ) {
		if ( !config.HCaptchaEnterprise ) {
			target.getSurface().dialogs.currentWindow.setSize( 'hCaptcha' );
		}
	};

	/**
	 * Fires when the hCaptcha challenge closed
	 *
	 * @param {ve.init.Target} target
	 */
	ve.init.mw.HCaptcha.static.onHCaptchaChallengeClose = function ( target ) {
		if ( !config.HCaptchaEnterprise ) {
			target.getSurface().dialogs.currentWindow.setSize( 'medium' );
		}
	};

	/**
	 * When the save dialog closes, remove the hCaptcha backdrop if any
	 *
	 * @param {ve.init.Target} target
	 * @return {void}
	 */
	ve.init.mw.HCaptcha.static.onSaveWorkflowEnd = function ( target ) {
		if ( target.saveDialog ) {
			target.saveDialog.$element.find( '.ext-confirmEdit-hCaptcha-backdrop' ).remove();
		}

		// Remove any challenge container that was appended to the body
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.ext-confirmEdit-hCaptcha-challengeContainer' ).remove();

		this.widgetId = null;
		this.hCaptchaResponseToken = null;
	};

	/**
	 * Initialises the hCaptcha VisualEditor handler for the current page.
	 */
	ve.init.mw.HCaptcha.static.init = function () {
		OO.ui.WindowManager.static.sizes.hCaptcha = {
			width: 600,
			height: '80%'
		};

		mw.hook( 've.newTarget' ).add( ( target ) => {
			if ( target.constructor.static.name !== 'article' ) {
				return;
			}
			target.on( 'saveWorkflowEnd', () => {
				this.onSaveWorkflowEnd( target );
			} );
			target.getSaveOptionsProcess().next( () => this.onSaveOptionsProcess( target ) );
		} );
	};
};
