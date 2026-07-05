$( () => {
	const useSecureEnclave = require( './secureEnclave.js' );
	const initMobileFrontend = require( './mobileFrontend/initMobileFrontend.js' );
	const visualEditorInitPluginsCallback = require( './ve/initPlugins.js' );
	const config = require( './config.json' );

	// If HCaptchaEnterprise and Secure Enclave are enabled, decide whether to
	// load support for either the MobileFrontend or the Visual Editor.
	//
	// Note support for the MobileFrontend is loaded if that extension is
	// loading, loaded, executing or ready. Notably, MobileFrontend support is
	// not loaded if the extension is just "registered", as that state means
	// MobileFrontend is known but the user is using another UI (such as the
	// source editor for Desktop or the VisualEditor); if that's the case, this
	// initializes the support for the Desktop editor instead.
	if ( config.HCaptchaEnterprise && config.HCaptchaSecureEnclave ) {
		const mobileFEState = mw.loader.getState( 'mobile.editor.overlay' );
		const isMobileFEActive = [ 'loading', 'loaded', 'ready', 'executing' ].includes( mobileFEState );
		const isMobileHCaptchaAbuseFilterEnabled = mw.config.get( 'wgConfirmEditMobileHCaptchaAbuseFilterEnabled' );

		if ( isMobileFEActive || isMobileHCaptchaAbuseFilterEnabled ) {
			// Editing interfaces may require a specific key: Override the
			// general key provided by RLRegisterModulesHandler by one specific
			// for the current action if such key was provided by
			// MakeGlobalVariablesScriptHookHandler.
			const editSiteKey = mw.config.get( 'wgConfirmEditHCaptchaSiteKey' );

			const editConfig = Object.assign(
				{},
				config,
				{
					MobileHCaptchaAbuseFilterEnabled: !!isMobileHCaptchaAbuseFilterEnabled
				}
			);

			if ( editSiteKey ) {
				editConfig.HCaptchaSiteKey = editSiteKey;
			}

			initMobileFrontend( 'mobilefrontend-editor', editConfig, window );
		} else {
			// Perform initialization for other scenarios, such as the Desktop
			// editor or the account creation page.
			useSecureEnclave( window );
		}
	}

	// If VisualEditor is available, then register the hCaptcha plugins.
	//
	// The VisualEditor scripts are loaded if they are one of loaded, loading,
	// ready, or registered (the exact state depending on when this code is run).
	// If it is 'missing' then we should not need to respond to any VisualEditor
	// edit on this page.
	//
	// Note that, contrary to what happens with the MobileFrontend, this also
	// runs if the state is just "registered" since this just adds a VE module
	// that may end up not being used. However, the code above is exclusive: it
	// either loads the MobileFrontend or the Desktop support, and failing to
	// call useSecureEnclave() when the MobileFrontend is present but unused
	// would disable hCaptcha support for the source editor in Desktop.
	const visualEditorModuleState = mw.loader.getState( 'ext.visualEditor.targetLoader' );
	if ( !visualEditorModuleState || visualEditorModuleState === 'missing' || visualEditorModuleState === 'error' ) {
		return;
	}

	mw.loader.using( 'ext.visualEditor.targetLoader' ).then( () => {
		mw.libs.ve.targetLoader.addPlugin( visualEditorInitPluginsCallback );
	} );
} );
