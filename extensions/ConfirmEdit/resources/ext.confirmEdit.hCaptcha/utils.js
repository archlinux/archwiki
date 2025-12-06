const config = require( './config.json' );

/**
 * Conclude and emit a performance measurement in seconds via mw.track.
 *
 * @param {string} interfaceName The name of the interface where hCaptcha is being used
 * @param {string} topic Unique name for the measurement to be sent to mw.track().
 * @param {string} startName Name of the performance mark denoting the start of the measurement.
 * @param {string} endName Name of the performance mark denoting the end of the measurement.
 */
function trackPerformanceTiming( interfaceName, topic, startName, endName ) {
	const wiki = mw.config.get( 'wgDBname' );
	performance.mark( endName );

	const { duration } = performance.measure( topic, startName, endName );

	// We also track the account creation timings separately
	// as their own metric for backwards compatability
	if ( interfaceName === 'createaccount' ) {
		mw.track( 'specialCreateAccount.performanceTiming', topic, duration / 1000 );

		// Possible metric names used here:
		// * mediawiki_special_createaccount_hcaptcha_load_duration_seconds
		// * mediawiki_special_createaccount_hcaptcha_execute_duration_seconds
		// NOTE: while the metric value is in milliseconds, the statsd handler in WikimediaEvents
		// will handle unit conversion.
		mw.track(
			`stats.mediawiki_special_createaccount_${ topic.replace( /-/g, '_' ) }_duration_seconds`,
			duration,
			{ wiki: wiki }
		);
	}

	// Possible metric names used here:
	// * mediawiki_confirmedit_hcaptcha_load_duration_seconds
	// * mediawiki_confirmedit_hcaptcha_execute_duration_seconds
	// NOTE: while the metric value is in milliseconds, the statsd handler in WikimediaEvents
	// will handle unit conversion.
	mw.track(
		`stats.mediawiki_confirmedit_${ topic.replace( /-/g, '_' ) }_duration_seconds`,
		duration,
		{ wiki: wiki, interfaceName: interfaceName }
	);
}

/**
 * Load the hCaptcha script.
 *
 * This method does not execute hCaptcha unless hCaptcha is configured to run when loaded.
 * For example, when hCaptcha is loaded with render=explicit the caller should explicitly
 * render hCaptcha with a win.hcaptcha.render() call.
 *
 * @param {Window} win
 * @param {string} interfaceName The name of the interface where hCaptcha is being used,
 *   only used for instrumentation
 * @param {Object.<string, string>} apiUrlQueryParameters Query parameters to append to the API URL
 *   For example, `{ 'render' => 'explicit' }` when always wanting to render explicitly.
 * @return {Promise<void>} A promise that resolves when hCaptcha loads and
 *   rejects if hCaptcha does not load
 */
const loadHCaptcha = (
	win, interfaceName, apiUrlQueryParameters = {}
) => new Promise( ( resolve, reject ) => {
	// If any existing hCaptcha SDK script has already finished loading,
	// then resolve the promise as we don't need to load hCaptcha again
	const existingScriptElements = document.querySelectorAll( '.mw-confirmedit-hcaptcha-script' );
	for ( const scriptElement of existingScriptElements ) {
		if ( scriptElement.classList.contains( 'mw-confirmedit-hcaptcha-script-loading-finished' ) ) {
			resolve();
			return;
		}
	}

	performance.mark( 'hcaptcha-load-start' );

	const hCaptchaApiUrl = new URL( config.HCaptchaApiUrl );

	for ( const [ name, value ] of Object.entries( apiUrlQueryParameters ) ) {
		hCaptchaApiUrl.searchParams.set( name, value );
	}

	hCaptchaApiUrl.searchParams.set( 'onload', 'onHCaptchaSDKLoaded' );

	const script = document.createElement( 'script' );
	script.src = hCaptchaApiUrl.toString();
	script.async = true;
	script.className = 'mw-confirmedit-hcaptcha-script';
	if ( config.HCaptchaApiUrlIntegrityHash ) {
		script.integrity = config.HCaptchaApiUrlIntegrityHash;
		script.crossOrigin = 'anonymous';
	}

	script.onerror = () => {
		trackPerformanceTiming(
			interfaceName,
			'hcaptcha-load',
			'hcaptcha-load-start',
			'hcaptcha-load-complete'
		);

		mw.track( 'stats.mediawiki_confirmedit_hcaptcha_script_error_total', 1, {
			wiki: mw.config.get( 'wgDBname' ), interfaceName: interfaceName
		} );
		mw.errorLogger.logError(
			new Error( 'Unable to load hCaptcha script' ),
			'error.confirmedit'
		);

		script.className = 'mw-confirmedit-hcaptcha-script mw-confirmedit-hcaptcha-script-loading-failed';

		reject( 'generic-error' );
	};

	// NOTE: Use hCaptcha's onload parameter rather than the return value of getScript()
	// to run init code, as the latter would run it too early and use
	// a potentially inconsistent config.
	win.onHCaptchaSDKLoaded = function () {
		trackPerformanceTiming(
			interfaceName,
			'hcaptcha-load',
			'hcaptcha-load-start',
			'hcaptcha-load-complete'
		);

		// Store that the hCaptcha script has been loaded via CSS classes.
		// We avoid using a global variable to make testing easier (as the DOM gets
		// cleared between tests)
		script.className = 'mw-confirmedit-hcaptcha-script mw-confirmedit-hcaptcha-script-loading-finished';

		resolve();
	};

	win.document.head.appendChild( script );
} );

/**
 * Trigger a single hCaptcha workflow execution asynchronously. This may cause a challenge
 * to the user that will interrupt any user flow, so should not be run unless the user
 * has taken an action to submit the form.
 *
 * A promise will be returned that will be rejected if the hCaptcha execution failed
 * and resolved if the hCaptcha execution succeeded. If the hCaptcha execution succeeds
 * then the h-captcha-response token will be returned as the value. On failure, the
 * value will be the associated error.
 *
 * @param {Window} win The window object, which can be changed for testing purposes
 * @param {string} captchaId The ID of the hCaptcha instance which has
 *   been rendered by `hcaptcha.render`
 * @param {string} interfaceName The name of the interface where hCaptcha is being used,
 *   only used for instrumentation
 * @return {Promise<string>} A promise that resolves if hCaptcha failed to initialize,
 * or after the first time the user attempts to submit the form and hCaptcha finishes running.
 */
const executeHCaptcha = ( win, captchaId, interfaceName ) => new Promise( ( resolve, reject ) => {
	const wiki = mw.config.get( 'wgDBname' );
	performance.mark( 'hcaptcha-execute-start' );

	const trackExecutionFinished = () => {
		trackPerformanceTiming(
			interfaceName,
			'hcaptcha-execute',
			'hcaptcha-execute-start',
			'hcaptcha-execute-complete'
		);
	};

	try {
		mw.track( 'stats.mediawiki_confirmedit_hcaptcha_execute_total', 1, {
			wiki: wiki, interfaceName: interfaceName
		} );
		win.hcaptcha.execute( captchaId, { async: true } )
			.then( ( { response } ) => {
				mw.track( 'stats.mediawiki_confirmedit_hcaptcha_form_submit_total', 1, {
					wiki: wiki, interfaceName: interfaceName
				} );
				trackExecutionFinished();
				resolve( response );
			} )
			.catch( ( error ) => {
				trackExecutionFinished();
				mw.track(
					'stats.mediawiki_confirmedit_hcaptcha_execute_workflow_error_total', 1, {
						code: error.replace( /-/g, '_' ),
						wiki: wiki,
						interfaceName: interfaceName
					}
				);
				reject( error );
			} );
	} catch ( error ) {
		mw.errorLogger.logError( error, 'error.confirmedit' );
		mw.track(
			'stats.mediawiki_confirmedit_hcaptcha_execute_workflow_error_total', 1, {
				code: error.message.replace( /-/g, '_' ),
				wiki: wiki,
				interfaceName: interfaceName
			}
		);
		trackExecutionFinished();
		reject( error.message );
	}
} );

/**
 * Maps an error code returned by `loadHCaptcha` or `executeHCaptcha` to
 * a message key that should be used to tell the user about the error.
 *
 * @param {string} error
 * @return {'hcaptcha-challenge-closed'|'hcaptcha-challenge-expired'|'hcaptcha-generic-error'}
 *   Message key that can be passed to `mw.msg` or `mw.message`
 */
const mapErrorCodeToMessageKey = ( error ) => {
	// Map of hCaptcha error codes to error message keys.
	const errorMap = {
		'challenge-closed': 'hcaptcha-challenge-closed',
		'challenge-expired': 'hcaptcha-challenge-expired',
		'generic-error': 'hcaptcha-generic-error'
	};

	return Object.prototype.hasOwnProperty.call( errorMap, error ) ?
		errorMap[ error ] :
		'hcaptcha-generic-error';
};

module.exports = {
	loadHCaptcha: loadHCaptcha,
	executeHCaptcha: executeHCaptcha,
	mapErrorCodeToMessageKey: mapErrorCodeToMessageKey
};
