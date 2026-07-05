$( () => {
	const form = new mw.ext.webauthn.RegisterFormWidget();

	form.on( 'addKey', ( desiredName ) => {
		const passkeyMode = !!Number( new URLSearchParams( document.location.search ).get( 'passkeyMode' ) );
		const registrator = new mw.ext.webauthn.Registrator( desiredName, null, passkeyMode );
		registrator.register().then(
			( credential ) => new mw.Api().postWithToken( 'csrf', {
				action: 'webauthn',
				func: 'register',
				credential: JSON.stringify( credential ),
				friendlyname: credential.friendlyName,
				passkeyMode: passkeyMode
			} ).then(
				() => {
					window.location.href = mw.util.getUrl( 'Special:OATHManage' );
				},
				( error ) => {
					if ( error === 'webauthn-reauthenticate' ) {
						// The user's authentication has expired, and they need to reauthenticate.
						// Reload the page, which will automatically redirect the user to the
						// reauthentication flow.
						window.location.reload();
					} else {
						form.dieWithError( error );
					}
				}
			)
		);
	} );
} );
