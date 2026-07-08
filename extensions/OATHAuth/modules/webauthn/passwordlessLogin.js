/* global PublicKeyCredential:false */
if ( window.PublicKeyCredential && PublicKeyCredential.isConditionalMediationAvailable ) {
	PublicKeyCredential.isConditionalMediationAvailable().then( ( isAvailable ) => {
		if ( !isAvailable ) {
			return;
		}

		$( () => {
			if ( $( '.mw-userlogin-username' ).length === 0 ) {
				return;
			}
			const form = new mw.ext.webauthn.LoginFormWidget();

			const authenticator = new mw.ext.webauthn.Authenticator(
				form.getAuthInfo(),
				true // passwordless login
			);
			authenticator.authenticate().then(
				( credential ) => {
					form.submitWithCredential( credential );
				},
				( error ) => {
					// Don't display errors, these tend to be about failing to set up conditional
					// auth. Instead just silently don't display the conditional auth UI.
					mw.log( 'WebAuthn conditional authentication failed', error );
				}
			);
		} );
	} );
}
