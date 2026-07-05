mw.ext.webauthn.Registrator = function ( friendlyName, registerData, passkeyMode ) {
	OO.EventEmitter.call( this );
	this.friendlyName = friendlyName;
	this.registerData = registerData || null;
	this.passkeyMode = passkeyMode;
};

OO.initClass( mw.ext.webauthn.Registrator );
OO.mixinClass( mw.ext.webauthn.Registrator, OO.EventEmitter );

mw.ext.webauthn.Registrator.prototype.register = function () {
	const dfd = $.Deferred();
	if ( this.registerData === null ) {
		this.getRegisterInfo().then(
			( response ) => {
				if ( !response.webauthn.hasOwnProperty( 'register_info' ) ) {
					dfd.reject( 'oathauth-webauthn-error-get-reginfo-fail' );
				}
				this.registerData = response.webauthn.register_info;
				this.registerData = JSON.parse( this.registerData );
				this.registerWithRegisterInfo( dfd );
			},
			( error ) => {
				dfd.reject( error );
			}
		);
	} else {
		this.registerWithRegisterInfo( dfd );
	}
	return dfd.promise();
};

mw.ext.webauthn.Registrator.prototype.getRegisterInfo = function () {
	return new mw.Api().get( {
		action: 'webauthn',
		func: 'getRegisterInfo',
		passkeyMode: this.passkeyMode
	} );
};

mw.ext.webauthn.Registrator.prototype.registerWithRegisterInfo = function ( dfd ) {
	this.createCredential()
		.then( ( assertion ) => {
			// FIXME should handle null?
			dfd.resolve( this.formatCredential( assertion ) );
		} )
		.catch( ( error ) => {
			mw.log.error( error );
			// This usually happens when the process gets interrupted
			// - show generic interrupt error
			dfd.reject( 'oathauth-webauthn-error-reg-generic' );
		} );
};

/**
 * @return {Promise<Credential|null>}
 */
mw.ext.webauthn.Registrator.prototype.createCredential = function () {
	const publicKey = this.registerData;
	publicKey.challenge = mw.ext.webauthn.util.base64ToByteArray( publicKey.challenge );
	publicKey.user.id = mw.ext.webauthn.util.base64ToByteArray( publicKey.user.id );

	if ( publicKey.excludeCredentials ) {
		publicKey.excludeCredentials = publicKey.excludeCredentials.map( ( data ) => Object.assign(
			data, {
				id: mw.ext.webauthn.util.base64ToByteArray( data.id )
			}
		) );
	}

	if ( this.passkeyMode ) {
		// Ask the browser to prefer storing a passkey on the device itself
		publicKey.hints = [ 'client-device' ];
	} else {
		// Ask the browser to prefer an external security key
		publicKey.hints = [ 'security-key' ];
	}

	this.emit( 'userPrompt' );
	mw.log( 'PublicKeyCredentialCreationOptions: ', publicKey );
	return navigator.credentials.create( { publicKey: publicKey } ).then( ( credential ) => {
		try {
			mw.log( 'Credential:\n' + JSON.stringify( credential, null, 4 ) );
		} catch ( e ) {
			// 1Password returns credential objects that throw an error when stringified
			// In that case, log the formatted credential object instead
			mw.log( 'Credential serialization failed' );
			mw.log( 'Formatted credential:\n' + JSON.stringify( this.formatCredential( credential ), null, 4 ) );
		}
		return credential;
	} );
};

/**
 * @param {Credential} newCredential
 * @return {Credential}
 */
mw.ext.webauthn.Registrator.prototype.formatCredential = function ( newCredential ) {
	// encoding should match PublicKeyCredentialLoader::loadArray()
	this.credential = {
		friendlyName: this.friendlyName,
		id: newCredential.id, // base64url encoded
		type: newCredential.type,
		rawId: mw.ext.webauthn.util.byteArrayToBase64( new Uint8Array( newCredential.rawId ),
			'base64', 'padded' ),
		response: {
			transports: newCredential.response.getTransports ?
				newCredential.response.getTransports() :
				// This omits AUTHENTICATOR_TRANSPORT_CABLE ('cable') for compatibility with iOS
				// Safari (tested on iOS 15, may not be needed for newer versions). (T358771)
				[ 'usb', 'nfc', 'ble', 'internal' ],
			// encoding should match CollectedClientData::createFormJson()
			clientDataJSON: mw.ext.webauthn.util.byteArrayToBase64(
				new Uint8Array( newCredential.response.clientDataJSON ), 'base64url', 'unpadded' ),
			// encoding should match AttestationObjectLoader::load()
			attestationObject: mw.ext.webauthn.util.byteArrayToBase64(
				new Uint8Array( newCredential.response.attestationObject ), 'base64', 'padded' )
		}
	};

	return this.credential;
};
