mw.ext.webauthn.CredentialForm = function ( cfg ) {
	cfg = cfg || {};
	this.$form = cfg.$form;
	this.$form.addClass( 'webauth-credential-form' );

	if ( !window.PublicKeyCredential ) {
		this.dieWithError(
			'oathauth-webauthn-error-browser-unsupported',
			'oathauth-webauthn-error-browser-unsupported-console'
		);
	}

	this.setControls();
	OO.EventEmitter.call( this );
};

OO.initClass( mw.ext.webauthn.CredentialForm );
OO.mixinClass( mw.ext.webauthn.CredentialForm, OO.EventEmitter );

mw.ext.webauthn.CredentialForm.prototype.setControls = function () {
	this.$credential = this.$form.find( 'input[name="credential"]' );
};

mw.ext.webauthn.CredentialForm.prototype.dieWithError = function ( message, consoleMsg ) {
	consoleMsg = consoleMsg || message;

	// Unrecoverable in this load - remove all content
	// TODO: We should really have a "try again" button instead of a reload button here (T404773)
	// eslint-disable-next-line no-jquery/no-sizzle
	this.$form
		.children()
		// HACK: Don't remove the "switch to" buttons for other auth methods on the login page
		.not( 'div:has(button[name="newModule"])' )
		.hide();

	const errorMessage = new OO.ui.MessageWidget( {
		type: 'error',
		label: new OO.ui.HtmlSnippet( this.getErrorText( message || '' ) )
	} );

	const reloadButton = new OO.ui.ButtonWidget( {
		label: mw.message( 'oathauth-webauthn-ui-reload-page-label' ).text()
	} );
	reloadButton.connect( this, {
		click: function () {
			window.location.reload();
		}
	} );

	this.$form.prepend(
		errorMessage.$element,
		$( '<p>' ).append( reloadButton.$element )
	);

	throw new Error( this.getErrorText( consoleMsg || '' ) );
};

/**
 * Render an error message as HTML. If the error message is an i18n message key, that message will
 * be used; otherwise the error message will be escaped and displayed as-is.
 *
 * @param {string} error Error message name or text
 * @return {string} HTML
 */
mw.ext.webauthn.CredentialForm.prototype.getErrorText = function ( error ) {
	const message = mw.message( error );
	if ( message.exists() ) {
		return message.parse();
	}
	return mw.html.escape( error );
};

mw.ext.webauthn.CredentialForm.prototype.setCredential = function ( credential ) {
	credential = JSON.stringify( credential );
	this.$credential.val( credential );
};

mw.ext.webauthn.CredentialForm.prototype.submitWithCredential = function ( credential ) {
	this.setCredential( credential );

	this.$form.trigger( 'submit' );
};
