/**
 * @typedef HCaptchaRenderOptions
 * @property {Function} [open-callback] Callback fired when a visual challenge opens
 * @property {Function} [close-callback] Callback fired when a visual challenge closes
 * @property {Function} [chalexpired-callback] Callback fired when a visual challenge
 *   closes due to inactivity
 * @property {Function} [expired-callback] Callback fired when a hCaptcha response token expires
 * @property {Function} [error-callback] Callback fired when the hCaptcha encounters an error
 * @property {Function} [callback] Callback fired when a hCaptcha response token is
 *   successfully generated
 * @property {string} [sitekey] The sitekey to use for the hCaptcha widget
 * @property {string} [challenge-container] The ID of a container to be used as the location
 *   of the visual challenge. Only works if using enterprise mode
 */

const ErrorWidget = require( './ErrorWidget.js' );
const ProgressIndicatorWidget = require( './ProgressIndicatorWidget.js' );
const config = require( './config.json' );

/**
 * Controls whether status widgets have been initialized.
 *
 * @type {boolean}
 */
let statusWidgetsInitialized = false;

/**
 * Holds a reference to the ErrorWidget displayed in the UI, or null if no error
 * was previously shown.
 *
 * This reference is used for hiding any previous error that may already be
 * present in the UI.
 *
 * @type {?ErrorWidget}
 */
let errorWidget = null;

/**
 * Holds a reference to the ProgressIndicatorWidget displayed in the UI, or null
 * if no progress indicator was previously shown.
 *
 * @type {?ProgressIndicatorWidget}
 */
let loadingIndicator = null;

/**
 * Conclude and emit a performance measurement in seconds via mw.track.
 *
 * @param {Window} win A reference to the object representing the browser's window
 * @param {Object} startMark An object returned by getPerformanceStartMark().
 * @return {void}
 */
function trackPerformanceTiming( win, startMark ) {
	const wiki = mw.config.get( 'wgDBname' );
	const { interfaceName, topic } = startMark;

	if ( isPerformanceMarkSupported( win ) ) {
		win.performance.mark( startMark.endMarkName );
	}

	const duration = getDuration( win, startMark );

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
 * Creates a script tag pointing to the HCaptcha script URL.
 *
 * This function does not attach the tag to the DOM.
 *
 * @param {Document} doc Reference to the document the tag is being added to.
 * @param {Object} apiUrlQueryParameters Query parameters to add to the hCaptcha URL.
 * @return {HTMLScriptElement}
 */
const createHCaptchaScriptTag = ( doc, apiUrlQueryParameters ) => {
	const hCaptchaApiUrl = new URL( config.HCaptchaApiUrl );

	for ( const [ name, value ] of Object.entries( apiUrlQueryParameters ) ) {
		hCaptchaApiUrl.searchParams.set( name, value );
	}

	hCaptchaApiUrl.searchParams.set( 'onload', 'onHCaptchaSDKLoaded' );

	const script = doc.createElement( 'script' );
	script.src = hCaptchaApiUrl.toString();
	script.async = true;
	script.className = 'mw-confirmedit-hcaptcha-script';
	if ( config.HCaptchaApiUrlIntegrityHash ) {
		script.integrity = config.HCaptchaApiUrlIntegrityHash;
		script.crossOrigin = 'anonymous';
	}

	return script;
};

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
	const doc = win.document;

	// If any existing hCaptcha SDK script has already finished loading,
	// then resolve the promise as we don't need to load hCaptcha again
	const existingScriptElements = doc.querySelectorAll( '.mw-confirmedit-hcaptcha-script' );
	for ( const scriptElement of existingScriptElements ) {
		if ( scriptElement.classList.contains( 'mw-confirmedit-hcaptcha-script-loading-finished' ) ) {
			resolve();
			return;
		}
	}

	/**
	 * The number of times to attempt loading the hCaptcha SDK before giving up.
	 *
	 * @type {number}
	 */
	const MAX_LOAD_ATTEMPTS = mw.config.exists( 'wgHCaptchaMaxLoadAttempts' ) ?
		mw.config.get( 'wgHCaptchaMaxLoadAttempts' ) :
		10;

	/**
	 * The initial amount of time to wait before retrying loading the hCaptcha
	 * SDK in milliseconds.
	 *
	 * If the first attempt fails, successive attempts will be delayed this same
	 * amount of time plus an additional factor equal to this number multiplied
	 * by 2^attemptNumber.
	 *
	 * @type {number}
	 */
	const BASE_RETRY_DELAY = mw.config.exists( 'wgHCaptchaBaseRetryDelay' ) ?
		mw.config.get( 'wgHCaptchaBaseRetryDelay' ) :
		1000;

	const perfStartMark = getPerformanceStartMark(
		win,
		interfaceName,
		'hcaptcha-load'
	);

	let attempts = 0;
	let script = createHCaptchaScriptTag( doc, apiUrlQueryParameters );

	// Emit a sample in the load-attempts histogram representing the total
	// number of script-tag load attempts that were made before reaching the
	// given terminal outcome (a successful load, or giving up after
	// MAX_LOAD_ATTEMPTS). This lets us distinguish "one user retrying many
	// times" from "many users each retrying once" in the error metric.
	const trackLoadAttempts = ( outcome, totalAttempts ) => {
		mw.track(
			'stats.mediawiki_confirmedit_hcaptcha_load_attempts_total',
			totalAttempts,
			{
				wiki: mw.config.get( 'wgDBname' ),
				interfaceName: interfaceName,
				outcome: outcome
			}
		);
	};

	const onErrorCallback = () => {
		const backoffTimeout = BASE_RETRY_DELAY * Math.pow( 2, attempts );

		attempts++;

		mw.track( 'stats.mediawiki_confirmedit_hcaptcha_script_error_total', 1, {
			wiki: mw.config.get( 'wgDBname' ), interfaceName: interfaceName
		} );
		mw.errorLogger.logError(
			new Error( 'Unable to load hCaptcha script' ),
			'error.confirmedit'
		);

		if ( attempts === MAX_LOAD_ATTEMPTS ) {
			script.className = 'mw-confirmedit-hcaptcha-script mw-confirmedit-hcaptcha-script-loading-failed';

			// Emit the load-duration metric only on this terminal outcome
			// so that one flaky load does not contribute multiple samples
			// (each covering an ever-growing portion of the retry loop)
			// and skew the percentiles.
			trackPerformanceTiming( win, perfStartMark );
			trackLoadAttempts( 'failure', attempts );

			reject( 'generic-error' );

			return;
		}

		// Wait some time, then try to load the SDK again
		setTimeout( () => {
			win.document.head.removeChild( script );

			script = createHCaptchaScriptTag( doc, apiUrlQueryParameters );
			script.onerror = onErrorCallback;

			win.document.head.appendChild( script );
		}, BASE_RETRY_DELAY + backoffTimeout );
	};

	script.onerror = onErrorCallback;

	// NOTE: Use hCaptcha's onload parameter rather than the return value of getScript()
	// to run init code, as the latter would run it too early and use
	// a potentially inconsistent config.
	win.onHCaptchaSDKLoaded = function () {
		trackPerformanceTiming( win, perfStartMark );
		trackLoadAttempts( 'success', attempts + 1 );

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
	const perfStartMark = getPerformanceStartMark( win, interfaceName, 'hcaptcha-execute' );

	const trackExecutionFinished = () => trackPerformanceTiming( win, perfStartMark );

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
		'generic-error': 'hcaptcha-generic-error',
		'internal-error': 'hcaptcha-internal-error',
		'network-error': 'hcaptcha-network-error',
		'rate-limited': 'hcaptcha-rate-limited'
	};

	return Object.prototype.hasOwnProperty.call( errorMap, error ) ?
		errorMap[ error ] :
		'hcaptcha-generic-error';
};

/**
 * Return the time elapsed since the provided mark.
 *
 * @param {Window} win A reference to the object representing the browser's window
 * @param {Object} startMark An object returned by getPerformanceStartMark().
 * @return {number}
 */
function getDuration( win, startMark ) {
	let duration = null;

	if ( isPerformanceMeasureSupported( win ) ) {
		let callSucceeded = false;

		try {
			// May return undefined in old browsers
			const measure = win.performance.measure(
				startMark.topic,
				startMark.startMarkName,
				startMark.endMarkName
			);

			callSucceeded = true;

			if ( measure && 'duration' in measure ) {
				duration = measure.duration;
			}
		} catch ( e ) {
			mw.log.warn( 'performance.measure() call failed' );
		}

		if ( duration === null && callSucceeded ) {
			// (T411576) In old browsers, performance.measure() may succeed
			// without returning a value. In that case, we need to manually
			// retrieve the last entry associated with the startMarkName.
			const entries = win.performance.getEntriesByName(
				startMark.topic,
				'measure'
			);

			if ( entries && entries.length > 0 ) {
				duration = entries[ entries.length - 1 ].duration;
			}
		}
	}

	if ( duration === null ) {
		duration = mw.now() - startMark.timestamp;
	}

	return duration;
}

/**
 * Returns an opaque object representing the instant where a section whose
 * performance is being measured is called.
 *
 * @param {Window} win A reference to the object representing the browser's window
 * @param {string} interfaceName The name of the interface where hCaptcha is being used
 * @param {string} topic Unique name for the measurement to be sent to mw.track().
 * @return {Object}
 * @private
 */
function getPerformanceStartMark( win, interfaceName, topic ) {
	const startMarkName = `${ topic }-start`;
	if ( isPerformanceMarkSupported( win ) ) {
		win.performance.mark( startMarkName );
	}

	return {
		interfaceName: interfaceName,
		startMarkName: startMarkName,
		endMarkName: `${ topic }-complete`,
		topic: topic,
		// mw.now() will automatically use window.performance.now() if available,
		// falling back to Date.now() otherwise. This value is in milliseconds.
		timestamp: mw.now()
	};
}

/**
 * Checks whether the browser has support for performance.mark().
 *
 * @param {Window} win A reference to the object representing the browser's window
 * @return {boolean}
 * @private
 */
function isPerformanceMarkSupported( win ) {
	return Object.prototype.hasOwnProperty.call( win, 'performance' ) &&
		( typeof win.performance.mark === 'function' );
}

/**
 * Checks whether the browser has support for performance.measure().
 *
 * @param {Window} win A reference to the object representing the browser's window
 * @return {boolean}
 * @private
 */
function isPerformanceMeasureSupported( win ) {
	return Object.prototype.hasOwnProperty.call( win, 'performance' ) &&
		( typeof win.performance.measure === 'function' );
}

/**
 * Lists codes for errors from the hCaptcha SDK that can be recovered from by
 * restarting the workflow (which makes the UI to show a new captcha).
 *
 * Specifically, the recoverable errors are challenge-expired, internal-error,
 * network-error and rate-limited.
 *
 * Additionally, challenge-closed is considered recoverable (which triggers a
 * new challenge automatically) for all UIs but the MobileFrontend, in which
 * closing the challenge lets the user stop sending the edit when that error is
 * triggered. Given that challenge-closed in the MobileFrontend happens if the
 * user clicks outside the captcha popup, handling it as recoverable would make
 * it show the popup again with a new challenge immediately after clicking
 * outside it, therefore making it impossible to dismiss the dialog in order to
 * make changes in the edit summary.
 *
 * @param {string} interfaceName Name of the interface hCaptcha is being used on
 * @return {string[]} List of error codes from the hCaptcha SDK.
 */
function getRecoverableErrors( interfaceName ) {
	const recoverableErrors = [
		'challenge-expired',
		'internal-error',
		'network-error',
		'rate-limited'
	];

	if ( interfaceName !== 'mobilefrontend-editor' ) {
		recoverableErrors.push( 'challenge-closed' );
	}

	return recoverableErrors;
}

/**
 * Loads the hCaptcha SDK and renders it in the container with the provided ID.
 *
 * Specifically, this function acts as a wrapper that first calls {@link loadHCaptcha}
 * and then calls {@link renderHCaptcha}. Callers who need to customise the {@link loadHCaptcha}
 * call should call it directly and then call {@link renderHCaptcha}.
 *
 * @param {Window} win Reference to the browser window.
 * @param {string} interfaceName Name of the interface where hCaptcha is being used.
 * @param {string|Element} container The container to render the hCaptcha widget in or the ID
 *   of that container
 * @return {Promise<string>} An ID to be used to call {@link executeHCaptcha}.
 */
function loadAndRenderHCaptcha( win, interfaceName, container ) {
	return loadHCaptcha( win, interfaceName ).then(
		() => renderHCaptcha( win, interfaceName, container )
	);
}

/**
 * Renders hCaptcha in the provided container.
 *
 * Caller calling this should ensure that {@link loadHCaptcha} has already been called
 * and the promise it returned succeeded.
 *
 * @param {Window} win Reference to the browser window.
 * @param {string} interfaceName Name of the interface where hCaptcha is being used.
 * @param {string|Element} container The container to render the hCaptcha widget in or the ID of that container
 * @param {HCaptchaRenderOptions} [renderOptions] The value of the second argument to
 *   pass to hcaptcha.render() used to customise the widget and listen for events
 * @return {string} An ID to be used to call {@link executeHCaptcha}
 */
function renderHCaptcha( win, interfaceName, container, renderOptions ) {
	renderOptions = renderOptions || {};

	/**
	 * Fires when a visible challenge is displayed.
	 */
	const onOpen = function () {
		mw.track( 'stats.mediawiki_confirmedit_hcaptcha_open_callback_total', 1, {
			wiki: mw.config.get( 'wgDBname' ),
			interfaceName: interfaceName
		} );
		// Fire an event that can be used in WikimediaEvents for associating
		// challenge opens with a user.
		mw.track( 'confirmEdit.hCaptchaRenderCallback', 'open', interfaceName );
	};

	const options = {
		'open-callback': () => {
			if ( renderOptions[ 'open-callback' ] ) {
				renderOptions[ 'open-callback' ]();
			}
			onOpen();
		},
		'close-callback': () => {
			if ( renderOptions[ 'close-callback' ] ) {
				renderOptions[ 'close-callback' ]();
			}
			mw.track( 'confirmEdit.hCaptchaRenderCallback', 'close', interfaceName );
		},
		'chalexpired-callback': () => {
			if ( renderOptions[ 'chalexpired-callback' ] ) {
				renderOptions[ 'chalexpired-callback' ]();
			}
			mw.track( 'confirmEdit.hCaptchaRenderCallback', 'chalexpired', interfaceName );
		},
		'expired-callback': () => {
			if ( renderOptions[ 'expired-callback' ] ) {
				renderOptions[ 'expired-callback' ]();
			}
			mw.track( 'confirmEdit.hCaptchaRenderCallback', 'expired', interfaceName );
		},
		'error-callback': ( errCode ) => {
			if ( renderOptions[ 'error-callback' ] ) {
				renderOptions[ 'error-callback' ]();
			}
			mw.track( 'confirmEdit.hCaptchaRenderCallback', 'error', interfaceName, errCode );
		}
	};

	if ( renderOptions.callback ) {
		options.callback = renderOptions.callback;
	}

	if ( renderOptions.sitekey ) {
		options.sitekey = renderOptions.sitekey;
	}

	if ( renderOptions[ 'challenge-container' ] ) {
		options[ 'challenge-container' ] = renderOptions[ 'challenge-container' ];
	}

	return win.hcaptcha.render( container, options );
}

/**
 * @param {jQuery} $hCaptchaField The hCaptcha input field within the form.
 * @return {void}
 * @private
 */
function initializeStatusWidgets( $hCaptchaField ) {
	if ( statusWidgetsInitialized ) {
		return;
	}

	statusWidgetsInitialized = true;
	errorWidget = new ErrorWidget();
	loadingIndicator = new ProgressIndicatorWidget(
		mw.msg( 'hcaptcha-loading-indicator-label' )
	);

	loadingIndicator.$element.addClass( 'ext-confirmEdit-hCaptchaLoadingIndicator' );
	loadingIndicator.$element.hide();
	errorWidget.$element.hide();

	$hCaptchaField.after( loadingIndicator.$element, errorWidget.$element );
}

/**
 * Displays an error occurring in an hCaptcha workflow in a user-friendly way.
 *
 * @param {jQuery} $hCaptchaField The hCaptcha input field within the form.
 * @param {string} error The error as returned by `executeHCaptcha` or `loadHCaptcha`
 * @return {void}
 */
function showError( $hCaptchaField, error ) {
	initializeStatusWidgets( $hCaptchaField );

	// Possible message keys used here:
	// * hcaptcha-generic-error
	// * hcaptcha-challenge-closed
	// * hcaptcha-challenge-expired
	// * hcaptcha-internal-error
	// * hcaptcha-network-error
	// * hcaptcha-rate-limited
	errorWidget.show(
		mw.msg( mapErrorCodeToMessageKey( error ) )
	);
}

/**
 * Hides any previous error that occurred in an hCaptcha workflow.
 *
 * @param {jQuery} $hCaptchaField The hCaptcha input field within the form.
 * @return {void}
 */
function hideError( $hCaptchaField ) {
	initializeStatusWidgets( $hCaptchaField );
	errorWidget.hide();
}

/**
 * Shows a loading indicator after the field passed as an argument.
 *
 * @param {jQuery} $hCaptchaField The hCaptcha input field within the form.
 * @return {void}
 */
function showLoadingIndicator( $hCaptchaField ) {
	initializeStatusWidgets( $hCaptchaField );
	loadingIndicator.$element.show();
}

/**
 * Hides any previous loading indicator added after the field provided as an
 * argument.
 *
 * @param {jQuery} $hCaptchaField The hCaptcha input field within the form.
 * @return {void}
 */
function hideLoadingIndicator( $hCaptchaField ) {
	initializeStatusWidgets( $hCaptchaField );
	loadingIndicator.$element.hide();
}

/**
 * Used by QUnit tests in order to reset the internal state.
 * Should not be called by non-test code.
 *
 * @internal
 * @return {void}
 */
function reset() {
	statusWidgetsInitialized = false;
	errorWidget = null;
	loadingIndicator = null;
}

module.exports = {
	executeHCaptcha: executeHCaptcha,
	getRecoverableErrors: getRecoverableErrors,
	loadHCaptcha: loadHCaptcha,
	mapErrorCodeToMessageKey: mapErrorCodeToMessageKey,
	loadAndRenderHCaptcha: loadAndRenderHCaptcha,
	renderHCaptcha: renderHCaptcha,
	showError: showError,
	hideError: hideError,
	showLoadingIndicator: showLoadingIndicator,
	hideLoadingIndicator: hideLoadingIndicator,
	reset: reset
};
