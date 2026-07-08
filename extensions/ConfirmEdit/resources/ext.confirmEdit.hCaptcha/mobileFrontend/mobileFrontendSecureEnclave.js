const utils = require( '../utils.js' );

/**
 * Holds a Promise that resolves once the call to render the captcha resolves,
 * or null if loadAndRenderHCaptcha() has not been called yet.
 *
 * When this Promise resolves, hCaptcha is already set up and is either about to
 * display a challenge or has already displayed it.
 *
 * Note that this variable will be initialized just once per save attempt
 * initiated by the end user (to get a single captcha ID from the hCaptcha API)
 * and will then be reused in case a recoverable error occurs. In case the user
 * closes the hCaptcha modal dialog and retries the submission, this variable is
 * assigned a new Promise resulting from a new call to hCaptcha render() method.
 *
 * @type {?Promise<string>}
 */
let captchaIdPromise = null;

/**
 * Load hCaptcha in Secure Enclave mode.
 *
 * The Promise returned by this method resolves after the first time the user
 * attempts to submit the form and hCaptcha finishes running.
 *
 * If either the hCaptcha SDK fails to initialize or there is an error rendering
 * the captcha, the Promise will also resolve but the function will first show
 * an error in an error widget and re-enable the Save button to let the user try
 * again.
 *
 * @param {jQuery} $hCaptchaField The hCaptcha input field within the form.
 * @param {Window} win Reference to the browser window.
 * @param {string} interfaceName The name of the interface where hCaptcha is being used.
 *
 * @return {Promise<void>} Resolves when a workflow finishes (successfully or not).
 */
async function setupHCaptcha( $hCaptchaField, win, interfaceName ) {
	/**
	 * Disables or re-enables the submit button located in the top right corner
	 * of the MobileFrontend source editor.
	 *
	 * @param {boolean} disabled
	 */
	const setSubmitButtonDisabledProp = ( disabled ) => {
		// .header-action refers to the topmost header added by the
		// MobileFrontend to hold the action buttons for the editor.
		// It is rendered by mobile.startup/headers.js.
		const $button = $( '.header-action button.save', win.document );
		$button.prop( 'disabled', disabled );
	};

	// Errors that can be recovered from by restarting the workflow.
	const recoverableErrors = utils.getRecoverableErrors( interfaceName );

	captchaIdPromise = utils.loadAndRenderHCaptcha(
		win,
		interfaceName,
		'h-captcha'
	);

	/**
	 * Trigger a single hCaptcha workflow execution.
	 *
	 * @return {Promise<void>} A promise that resolves if hCaptcha failed to
	 *                         initialize, or after hCaptcha finishes running.
	 */
	const executeWorkflow = function () {
		return captchaIdPromise.then( ( captchaId ) => {
			utils.hideError( $hCaptchaField );
			utils.showLoadingIndicator( $hCaptchaField );
			setSubmitButtonDisabledProp( true );

			return utils.executeHCaptcha( win, captchaId, interfaceName )
				.then( ( response ) => {
					// Clear out any errors from a previous workflow.
					utils.hideError( $hCaptchaField );
					utils.hideLoadingIndicator( $hCaptchaField );
					setSubmitButtonDisabledProp( false );

					// Let extensions know that the captcha was successfully verified.
					// Specifically, this makes the MobileFrontend submit the current edit.
					mw.hook( 'confirmEdit.hCaptcha.executionSuccess' ).fire( response );
				} )
				.catch( ( error ) => {
					utils.showError( $hCaptchaField, error );
					utils.hideLoadingIndicator( $hCaptchaField );
					setSubmitButtonDisabledProp( false );

					// Initiate a new workflow for recoverable errors
					// (e.g. an expired or closed challenge).
					if ( recoverableErrors.includes( error ) ) {
						return executeWorkflow();
					}
				} );
		} )
			.catch( ( error ) => {
				mw.track(
					'confirmEdit.hCaptchaRenderCallback',
					'error',
					interfaceName,
					// "error" is an hCaptcha error code (for example,
					// "rate-limited"). The full list of values can be found at
					// https://docs.hcaptcha.com/configuration/#error-codes
					error
				);

				utils.showError( $hCaptchaField, error );
				utils.hideLoadingIndicator( $hCaptchaField );
				setSubmitButtonDisabledProp( false );
			} );
	};

	return executeWorkflow();
}

/**
 * Configure hCaptcha in Secure Enclave mode for the MobileFrontend.
 *
 * This method requires callers to provide an interface name (such as
 * "mobilefrontend-editor"), which is used for instrumentation purposes.
 *
 * @param {Window} win Reference to the browser window.
 * @param {string} interfaceName interface hCaptcha is being loaded on.
 * @return {Promise<void>} A promise that resolves if hCaptcha failed to initialize,
 * or after the first time the user attempts to submit the form and hCaptcha finishes running.
 */
function mobileFrontendSecureEnclave( win, interfaceName ) {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $hCaptchaField = $( '#h-captcha' );
	if ( !$hCaptchaField.length ) {
		return Promise.resolve();
	}

	// Editing in the MobileFrontend does not trigger a form submission, so
	// the setup needs to be called explicitly.
	return setupHCaptcha( $hCaptchaField, win, interfaceName );
}

module.exports = mobileFrontendSecureEnclave;
