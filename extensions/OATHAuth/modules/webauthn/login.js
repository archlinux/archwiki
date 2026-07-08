$( () => {
	const form = new mw.ext.webauthn.LoginFormWidget();

	const authenticator = new mw.ext.webauthn.Authenticator(
		form.getAuthInfo()
	);
	authenticator.authenticate().then(
		( credential ) => {
			form.submitWithCredential( credential );
		},
		( error ) => {
			form.dieWithError( error );
		}
	);
} );
