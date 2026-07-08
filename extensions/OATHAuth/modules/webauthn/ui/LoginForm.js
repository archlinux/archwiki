mw.ext.webauthn.LoginFormWidget = function () {
	mw.ext.webauthn.LoginFormWidget.parent.call( this, {
		// eslint-disable-next-line no-jquery/no-global-selector
		$form: $( '#userloginForm' ).find( 'form' )
	} );
};

OO.inheritClass( mw.ext.webauthn.LoginFormWidget, mw.ext.webauthn.CredentialForm );

mw.ext.webauthn.LoginFormWidget.prototype.setControls = function () {
	mw.ext.webauthn.LoginFormWidget.parent.prototype.setControls.call( this );
	this.$authInfo = this.$form.find( 'input[name="auth_info"]' );
};

mw.ext.webauthn.LoginFormWidget.prototype.getAuthInfo = function () {
	if ( !this.$authInfo ) {
		return {};
	}
	const authInfo = this.$authInfo.val();
	return JSON.parse( authInfo );
};
