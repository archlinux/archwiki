mw.ext = mw.ext || {};
mw.ext.webauthn = mw.ext.webauthn || {};

mw.ext.webauthn.util = {
	/**
	 * Convert between various base64 flavors.
	 *
	 * @param {string} input
	 * @param {'base64'|'base64url'} type
	 * @param {'padded'|'unpadded'} padding
	 * @return {string}
	 */
	convertBase64: function ( input, type, padding ) {
		if ( type === 'base64' ) {
			input = input
				.replace( /-/g, '+' )
				.replace( /_/g, '/' );
		} else if ( type === 'base64url' ) {
			input = input
				.replace( /\+/g, '-' )
				.replace( /\//g, '_' );
		} else {
			throw new Error( 'invalid base64 encoding type: ' + type );
		}

		if ( padding === 'padded' ) {
			input = input.replace( /=/g, '' );
			input = input.padEnd( 4 * Math.ceil( input.length / 4 ), '=' );
		} else if ( padding === 'unpadded' ) {
			input = input.replace( /=/g, '' );
		} else {
			throw new Error( 'invalid padding type: ' + padding );
		}

		return input;
	},

	/**
	 * @param {Uint8Array} array
	 * @param {'base64'|'base64url'} type
	 * @param {'padded'|'unpadded'} padding
	 * @return {string}
	 */
	byteArrayToBase64: function ( array, type, padding ) {
		let stringified = '';
		for ( let i = 0; i < array.length; i++ ) {
			stringified += String.fromCharCode( array[ i ] );
		}
		array = window.btoa( stringified );
		return mw.ext.webauthn.util.convertBase64( array, type, padding );
	},

	base64ToByteArray: function ( str ) {
		return Uint8Array.from(
			window.atob( mw.ext.webauthn.util.convertBase64( str, 'base64', 'padded' ) ),
			( c ) => c.charCodeAt( 0 )
		);
	}
};
