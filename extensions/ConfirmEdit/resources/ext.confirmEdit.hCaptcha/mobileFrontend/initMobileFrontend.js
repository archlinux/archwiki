/**
 * @typedef {Object} HCaptchaDetails
 * @property {'hcaptcha'} type Captcha type identifier
 * @property {'application/javascript'} mime MIME type of the captcha
 * @property {string} key Site key to use for rendering the captcha widget
 * @property {string|null} error Error code from the last captcha attempt, or
 *   null if no error occurred. Known values: 'missing-token',
 *   'sitekey-mismatch', 'forceshowcaptcha', 'http', 'json', 'hcaptcha-api'
 */

/**
 * @typedef ConfirmEditHCaptchaConfig
 * @property {string} HCaptchaApiUrl URL to use for accessing hCaptcha API
 * @property {string} HCaptchaSiteKey SiteKey to use when accessing the hCaptcha API
 * @property {boolean} HCaptchaEnterprise Whether to use hCaptcha Enterprise
 * @property {boolean} HCaptchaSecureEnclave Whether to use Secure Enclave
 * @property {boolean} HCaptchaEnabledInMobileFrontend Whether to use hCaptcha in the MobileFrontend
 * @property {boolean} MobileHCaptchaAbuseFilterEnabled Whether an AbuseFilter filter may
 * require a stricter challenge to be used after first edit save attempt
 */

/**
 * Initialization function for hCaptcha in the MobileFrontend.
 *
 * This function is called by init.js, and is responsible for setting hook
 * handlers that modify the behavior of the MobileFrontend in order to render
 * the hCaptcha code in the SourceEditor.
 *
 * @param {string} interfaceName Name hCaptcha is being loaded for.
 * @param {ConfirmEditHCaptchaConfig} config Configuration parameters.
 * @param {Window} windowObject Reference to the browser window.
 */
module.exports = function (
	interfaceName,
	config,
	windowObject
) {
	const mobileFrontendSecureEnclave = require( './mobileFrontendSecureEnclave.js' );
	const { loadHCaptcha } = require( '../utils.js' );

	const hCaptchaPanelTemplate =
		`<div id="h-captcha-container">
			<div id="h-captcha" class="h-captcha" data-size="invisible"
				data-sitekey="{{hCaptchaSiteKey}}"></div>
			<div class="ext-confirmEdit-captcha-privacy-policy license">
				{{{hCaptchaLicenseText}}}
			</div>
		</div>`;

	function cleanupDuplicateHCaptchaContainers() {
		const containers = windowObject.document.querySelectorAll( '#h-captcha-container' );
		for ( let i = 0; i < containers.length; i++ ) {
			containers[ i ].parentNode.removeChild( containers[ i ] );
		}
	}

	if ( config.HCaptchaEnabledInMobileFrontend ) {
		mw.hook( 'mobileFrontend.sourceEditor.getDefaultOptions' ).add( ( e ) => {
			const newDefaults = Object.assign(
				{},
				e.defaults,
				{
					hCaptchaLicenseText: mw.message( 'hcaptcha-privacy-policy' ).parse(),
					hCaptchaSiteKey: config.HCaptchaSiteKey
				}
			);

			e.setDefaults( newDefaults );
		} );
		mw.hook( 'mobileFrontend.sourceEditor.getSavePanelTemplateSource' ).add( ( e ) => {
			// The usage of the "triple mustache" syntax (i.e. {{{variable}}})
			// for the license text is intentional: By not escaping these variables,
			// we prevent the template engine from tampering the value of the
			// license text, so that we can provide a value that includes HTML code
			// linking to license terms. Normally that HTML will come from a call to
			// mw.message().parse() instead of being user-provided HTML, so this is
			// considered safe.
			cleanupDuplicateHCaptchaContainers();
			e.setTemplate( hCaptchaPanelTemplate );
		} );
		mw.hook( 'mobileFrontend.sourceEditor.getCaptchaPanelTemplateSource' ).add( ( e ) => {
			// Removes the default captcha code from the MobileEditor, as the code
			// for hCaptcha is included in the EditSummary panel instead.
			e.setTemplate( '', false );
		} );
		mw.hook( 'mobileFrontend.sourceEditor.preRenderFinished' ).add( () => {
			// Preloads the hCaptcha script after the MobileFrontend templates
			// have been evaluated.
			// Intentionally ignore preload failures: a later user-triggered flow
			// will retry loading via loadHCaptcha().
			loadHCaptcha( windowObject, interfaceName ).catch( () => {} );
		} );
	}

	/** @type {Object|null} */
	let hookPayload = null;
	if ( config.HCaptchaEnabledInMobileFrontend || config.MobileHCaptchaAbuseFilterEnabled ) {

		mw.hook( 'mobileFrontend.sourceEditor.saveBegin' ).add( ( e ) => {
			hookPayload = e;

			// Skip early captcha when wgConfirmEditForceShowCaptcha is set: the save
			// will fail with a forceshowcaptcha error and handleCaptcha will show
			// exactly one captcha widget, whose resume() correctly forwards the flag.
			if ( mw.config.get( 'wgConfirmEditCaptchaNeededForGenericEdit' ) === 'hcaptcha' &&
				!mw.config.get( 'wgConfirmEditForceShowCaptcha' ) ) {
				// Stop the regular MobileFrontend save flow.
				e.stop();

				// Calling loadHCaptcha() ensures the SDK is loaded before running
				// an hCaptcha workflow. If hCaptcha was already loaded by the
				// preRenderFinished handler, it resolves immediately.
				loadHCaptcha( windowObject, interfaceName ).then(
					() => mobileFrontendSecureEnclave(
						windowObject,
						interfaceName
					)
				);
			}
		} );

		mw.hook( 'confirmEdit.hCaptcha.executionSuccess' ).add( ( token ) => {
			if ( hookPayload !== null ) {

				const payload = hookPayload;
				hookPayload = null;

				if ( payload.options === null || payload.options === undefined ) {
					payload.options = {};
				}
				payload.options.captchaWord = token;
				if ( mw.config.get( 'wgConfirmEditForceShowCaptcha' ) ) {
					payload.options.wgConfirmEditForceShowCaptcha = true;
				}
				payload.resume( payload.options );
			}
		} );
	}

	mw.hook( 'mobileFrontend.sourceEditor.handleCaptcha' ).add(
		/**
		 * @param {Object} payload
		 * @param {HCaptchaDetails} details
		 * @param {jQuery} $el
		 */
		( payload, details, $el ) => {
			if ( !config.MobileHCaptchaAbuseFilterEnabled || details.type !== 'hcaptcha' ) {
				return;
			}

			const knownErrors = [ 'missing-token', 'sitekey-mismatch', 'forceshowcaptcha' ];
			if ( details.error !== null && !knownErrors.includes( details.error ) ) {
				const normalizedError = typeof details.error === 'string' ? details.error : 'unknown';
				mw.log.warn( 'ConfirmEdit: unhandled hCaptcha error in handleCaptcha:', normalizedError );
				mw.errorLogger.logError(
					new Error( `Unhandled hCaptcha error in handleCaptcha: ${ normalizedError }` ),
					'error.confirmEdit.hcaptcha'
				);
				// Show the error in the captcha panel by stopping the save flow and
				// rendering an error message there, since both mw.notify
				// and #error-notice-container are not visible
				payload.stop();
				payload.setTemplate(
					'captcha-panel',
					'<div class="mw-message-box mw-message-box-error">' +
						mw.html.escape( mw.message( 'hcaptcha-generic-error' ).text() ) +
					'</div>',
					{}
				);
				return;
			}

			hookPayload = payload;
			payload.stop();

			const additionalTemplateArgs = {
				hCaptchaLicenseText: mw.message( 'hcaptcha-privacy-policy' ).parse(),
				// The server returns the correct site key for the current context (e.g. AbuseFilter)
				hCaptchaSiteKey: details.key
			};

			cleanupDuplicateHCaptchaContainers();
			payload.setTemplate(
				'captcha-panel',
				hCaptchaPanelTemplate,
				additionalTemplateArgs,
				() => {
					$el.find( '#h-captcha-container' ).show();

					loadHCaptcha( windowObject, interfaceName ).then(
						() => mobileFrontendSecureEnclave(
							windowObject,
							interfaceName
						)
					);
				}
			);
		}
	);
};
