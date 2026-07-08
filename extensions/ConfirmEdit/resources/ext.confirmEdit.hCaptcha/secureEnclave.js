const utils = require( './utils.js' );

/**
 * If set, makes the next call to isSaveRequest() to return true unconditionally.
 *
 * This gets set when the Javascript configuration variable
 * wgHCaptchaTriggerFormSubmission is set, so the user does not have to manually
 * resubmit the form after an AbuseFilter consequence.
 *
 * @type {boolean}
 */
let editFormForceIsSaveRequest = false;

/**
 * Holds a Promise that resolves once the call to render the captcha resolves,
 * or null if setupHCaptcha() has not been called yet.
 *
 * When this Promise resolves, hCaptcha is already set up and would intercept
 * form submissions. Therefore, at that point it is safe to trigger a form
 * submission programmatically.
 *
 * @type {?Promise<string>}
 */
let captchaIdPromise = null;

/**
 * Load hCaptcha in Secure Enclave mode.
 *
 * @param {jQuery} $form The form to be protected by hCaptcha.
 * @param {jQuery} $hCaptchaField The hCaptcha input field within the form.
 * @param {Window} win
 * @param {string} interfaceName The name of the interface where hCaptcha is being used
 * @return {Promise<void>} A promise that resolves if hCaptcha failed to initialize,
 * or after the first time the user attempts to submit the form and hCaptcha finishes running.
 */
async function setupHCaptcha( $form, $hCaptchaField, win, interfaceName ) {
	const setSubmitButtonDisabledProp = ( disabled ) => {
		if ( interfaceName === 'edit' ) {
			// On wikitext editor, use OOUI widget
			const $wpSaveWidget = $form.find( '#wpSaveWidget' );
			const saveButtonWidget = OO.ui.infuse( $wpSaveWidget );
			saveButtonWidget.setDisabled( disabled );
			return;
		}
		// For Special:CreateAccount use disabled attribute
		$form.find( 'input[type="submit"], button[type="submit"]' ).prop( 'disabled', disabled );
	};

	// Errors that can be recovered from by restarting the workflow.
	const recoverableErrors = utils.getRecoverableErrors( interfaceName );

	captchaIdPromise = utils.loadAndRenderHCaptcha(
		win,
		interfaceName,
		'h-captcha'
	);

	/**
	 * Determines if the given form submission is a "save" request.
	 *
	 * For the form used for editing a page, the request is considered a
	 * "save" request if was indeed sent for saving the edit (as opposed to
	 * requesting a preview or a diff).
	 *
	 * Form submissions other than page edits are always considered "save"
	 * requests.
	 *
	 * This is used to determine whether a captcha challenge is required.
	 *
	 * @param {Object} event The jQuery form submission event
	 * @return {boolean}
	 */
	const isSaveRequest = ( event ) => {
		if ( editFormForceIsSaveRequest ) {
			editFormForceIsSaveRequest = false;

			return true;
		}

		let result = true;

		if ( $form.attr( 'id' ) === 'editform' ) {
			result = false;

			let originalEvent = event;

			if ( Object.hasOwnProperty.call( event, 'originalEvent' ) ) {
				originalEvent = event.originalEvent;
			}

			if ( typeof originalEvent.submitter === 'object' ) {
				result = ( originalEvent.submitter.id === 'wpSave' );
			}
		}

		return result;
	};

	/**
	 * Trigger a single hCaptcha workflow execution.
	 *
	 * @return {Promise<void>} A promise that resolves if hCaptcha failed to initialize,
	 * or after the first time the user attempts to submit the form and hCaptcha finishes running.
	 */
	const executeWorkflow = function () {
		$form.off( 'submit.hCaptcha' );

		const formSubmitted = new Promise( ( resolve ) => {
			$form.on( 'submit.hCaptcha', function ( event ) {
				if ( isSaveRequest( event ) ) {
					event.preventDefault();

					resolve( this );
				}
			} );
		} );

		return Promise.all( [ captchaIdPromise, formSubmitted ] )
			.then( ( [ captchaId, form ] ) => {
				utils.hideError( $hCaptchaField );
				utils.showLoadingIndicator( $hCaptchaField );
				setSubmitButtonDisabledProp( true );

				return utils.executeHCaptcha( win, captchaId, interfaceName )
					.then( ( response ) => {
						// Set the hCaptcha response input field, which does not yet exist
						$form.append( $( '<input>' )
							.attr( 'type', 'hidden' )
							.attr( 'name', 'h-captcha-response' )
							.attr( 'id', 'h-captcha-response' )
							.val( response ) );

						// Clear out any errors from a previous workflow
						utils.hideError( $hCaptchaField );
						utils.hideLoadingIndicator( $hCaptchaField );
						setSubmitButtonDisabledProp( false );

						mw.hook( 'confirmEdit.hCaptcha.executionSuccess' ).fire( response );

						form.submit();
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

				// Note: If we end up reaching this point (caused by an error
				// obtaining captchaId), the user won't be able to submit the
				// form anymore since captchaIdPromise and formSubmitted have
				// already been resolved.
				utils.showError( $hCaptchaField, error );
				utils.hideLoadingIndicator( $hCaptchaField );
				setSubmitButtonDisabledProp( false );
			} );
	};

	return executeWorkflow();
}

/**
 * Configure hCaptcha in Secure Enclave mode.
 *
 * @param {Window} win
 * @return {Promise<void>} A promise that resolves if hCaptcha failed to initialize,
 * or after the first time the user attempts to submit the form and hCaptcha finishes running.
 */
async function useSecureEnclave( win ) {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $hCaptchaField = $( '#h-captcha' );
	if ( !$hCaptchaField.length ) {
		return;
	}

	const $form = $hCaptchaField.closest( 'form' );
	if ( !$form.length ) {
		return;
	}

	// Work out what interface we are loading hCaptcha on
	let interfaceName = 'unknown';
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'CreateAccount' ) {
		interfaceName = 'createaccount';
	}
	if ( mw.config.get( 'wgAction' ) === 'edit' || mw.config.get( 'wgAction' ) === 'submit' ) {
		interfaceName = 'edit';
	}

	// Load hCaptcha the first time the user interacts with the form, or load it
	// immediately if wgHCaptchaTriggerFormSubmission is set.
	return new Promise( ( resolve ) => {
		const $inputs = $form.find( 'input, textarea' );

		// Catch and prevent form submissions that occur before hCaptcha was initialized.
		$form.one( 'submit.hCaptchaLoader', ( event ) => {
			event.preventDefault();

			$inputs.off( 'input.hCaptchaLoader focus.hCaptchaLoader' );
			$form.off( 'submit.hCaptchaLoader' );

			resolve( setupHCaptcha( $form, $hCaptchaField, win, interfaceName ) );
		} );

		$inputs.one( 'input.hCaptchaLoader focus.hCaptchaLoader', () => {
			$inputs.off( 'input.hCaptchaLoader focus.hCaptchaLoader' );
			$form.off( 'submit.hCaptchaLoader' );

			resolve( setupHCaptcha( $form, $hCaptchaField, win, interfaceName ) );
		} );

		// If the backend requested to submit the form once the page is loaded,
		// trigger the setup immediately, wait a bit so it has a chance to load
		// the SDK, and then trigger the form submission programmatically.
		if ( mw.config.get( 'wgHCaptchaTriggerFormSubmission' ) ) {
			editFormForceIsSaveRequest = true;

			// Note setupHCaptcha() is not awaited here since it won't resolve
			// until the form is submitted, but the submission is triggered
			// in the next line once loading hCaptcha completes.
			setupHCaptcha( $form, $hCaptchaField, win, interfaceName );

			// Note that although captchaIdPromise is initialized by the async
			// function setupHCaptcha, that function does not await any promise
			// before it does so and, therefore, it is guaranteed that a Promise
			// is assigned to captchaIdPromise before we call .then() here.
			captchaIdPromise.then( () => $form.trigger( 'submit' ) );
		}
	} );
}

module.exports = useSecureEnclave;
