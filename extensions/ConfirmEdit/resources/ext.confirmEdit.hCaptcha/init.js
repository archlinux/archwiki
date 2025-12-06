$( () => {
	const useSecureEnclave = require( './secureEnclave.js' );
	const config = require( './config.json' );
	const visualEditorInitPluginsCallback = require( './ve/initPlugins.js' );

	if ( config.HCaptchaEnterprise && config.HCaptchaSecureEnclave ) {
		useSecureEnclave( window );
	}

	// If VisualEditor is available, then register the hCaptcha plugins.
	//
	// The VisualEditor scripts are loaded if they are one of loaded, loading, ready,
	// or registered (the exact state depending on when this code is run).
	// If it is 'missing' then we should not need to respond to any
	// VisualEditor edit on this page.
	const veState = mw.loader.getState( 'ext.visualEditor.targetLoader' );
	const validStates = [ 'loading', 'loaded', 'ready', 'registered' ];
	if ( validStates.includes( veState ) ) {
		mw.loader.using( 'ext.visualEditor.targetLoader' ).then( () => {
			mw.libs.ve.targetLoader.addPlugin( visualEditorInitPluginsCallback );
		} );
	}
} );
