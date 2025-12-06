const ProgressIndicatorWidget = require( './ProgressIndicatorWidget.js' );
const ErrorWidget = require( './ErrorWidget.js' );
const wiki = mw.config.get( 'wgDBname' );
const { loadHCaptcha, executeHCaptcha, mapErrorCodeToMessageKey } = require( './utils.js' );

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
	const loadingIndicator = new ProgressIndicatorWidget(
		mw.msg( 'hcaptcha-loading-indicator-label' )
	);
	loadingIndicator.$element.addClass( 'ext-confirmEdit-hCaptchaLoadingIndicator' );
	loadingIndicator.$element.hide();

	const errorWidget = new ErrorWidget();

	$hCaptchaField.after( loadingIndicator.$element, errorWidget.$element );

	const hCaptchaLoaded = loadHCaptcha( win, interfaceName );

	// Errors that can be recovered from by restarting the workflow.
	const recoverableErrors = [
		'challenge-closed',
		'challenge-expired'
	];

	/**
	 * Fires when a visible challenge is displayed.
	 */
	const onOpen = function () {
		mw.track( 'stats.mediawiki_confirmedit_hcaptcha_open_callback_total', 1, {
			wiki: wiki
		} );
		// Fire an event that can be used in WikimediaEvents for associating
		// challenge opens with a user.
		mw.track( 'confirmEdit.hCaptchaRenderCallback', 'open', interfaceName );
	};

	const captchaIdPromise = hCaptchaLoaded.then( () => win.hcaptcha.render( 'h-captcha', {
		'open-callback': onOpen,
		'close-callback': () => {
			mw.track( 'confirmEdit.hCaptchaRenderCallback', 'close', interfaceName );
		},
		'chalexpired-callback': () => {
			mw.track( 'confirmEdit.hCaptchaRenderCallback', 'chalexpired', interfaceName );
		},
		'expired-callback': () => {
			mw.track( 'confirmEdit.hCaptchaRenderCallback', 'expired', interfaceName );
		},
		'error-callback': () => {
			mw.track( 'confirmEdit.hCaptchaRenderCallback', 'error', interfaceName );
		}
	} ) );

	/**
	 * Trigger a single hCaptcha workflow execution.
	 *
	 * @return {Promise<void>} A promise that resolves if hCaptcha failed to initialize,
	 * or after the first time the user attempts to submit the form and hCaptcha finishes running.
	 */
	const executeWorkflow = async function () {
		$form.off( 'submit.hCaptcha' );

		const formSubmitted = new Promise( ( resolve ) => {
			$form.on( 'submit.hCaptcha', function ( event ) {
				event.preventDefault();

				resolve( this );
			} );
		} );

		/**
		 * Displays an error returned by attempting to load or execute hCaptcha
		 * in a user-friendly way
		 *
		 * @param {string} error The error as returned by `executeHCaptcha` or `loadHCaptcha`
		 */
		const displayErrorInErrorWidget = ( error ) => {
			// Possible message keys used here:
			// * hcaptcha-generic-error
			// * hcaptcha-challenge-closed
			// * hcaptcha-challenge-expired
			errorWidget.show( mw.msg( mapErrorCodeToMessageKey( error ) ) );
		};

		return Promise.all( [ captchaIdPromise, formSubmitted ] )
			.then( ( [ captchaId, form ] ) => {
				loadingIndicator.$element.show();

				return executeHCaptcha( win, captchaId, interfaceName )
					.then( ( response ) => {
						// Clear out any errors from a previous workflow.
						errorWidget.hide();
						// Set the hCaptcha response input field, which does not yet exist
						$form.append( $( '<input>' )
							.attr( 'type', 'hidden' )
							.attr( 'name', 'h-captcha-response' )
							.attr( 'id', 'h-captcha-response' )
							.val( response ) );

						// Hide the loading indicator as we have finished hCaptcha
						// and are submitting the form
						loadingIndicator.$element.hide();

						mw.hook( 'confirmEdit.hCaptcha.executionSuccess' ).fire( response );

						form.submit();
					} )
					.catch( ( error ) => {
						loadingIndicator.$element.hide();

						displayErrorInErrorWidget( error );

						// Initiate a new workflow for recoverable errors
						// (e.g. an expired or closed challenge).
						if ( recoverableErrors.includes( error ) ) {
							return executeWorkflow();
						}
					} );
			} )
			.catch( ( error ) => {
				displayErrorInErrorWidget( error );
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

	// Work our what interface we are loading hCaptcha on, currently only used
	// for instrumentation purposes
	let interfaceName = 'unknown';
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'CreateAccount' ) {
		interfaceName = 'createaccount';
	}
	if ( mw.config.get( 'wgAction' ) === 'edit' ) {
		interfaceName = 'edit';
	}

	// Load hCaptcha the first time the user interacts with the form.
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
	} );
}

module.exports = useSecureEnclave;
