mw.ext.webauthn.RegisterFormWidget = function () {
	mw.ext.webauthn.RegisterFormWidget.parent.call( this, {
		$form: $( '#webauthn-add-key-form' )
	} );

	this.$form.on( 'submit', this.submitForm.bind( this ) );
	this.addKeyButton.on( 'click', this.validateAndEmit.bind( this ) );
	this.readyToSubmit = false;
};

OO.inheritClass( mw.ext.webauthn.RegisterFormWidget, mw.ext.webauthn.CredentialForm );

mw.ext.webauthn.RegisterFormWidget.prototype.setControls = function () {
	mw.ext.webauthn.RegisterFormWidget.parent.prototype.setControls.call( this );
	this.name = OO.ui.TextInputWidget.static.infuse( this.$form.find( 'div#key_name' ) );
	this.addKeyButton = OO.ui.ButtonWidget.static.infuse( this.$form.find( 'span#button_add_key' ) );

	this.name.setDisabled( false );
	this.addKeyButton.setDisabled( false );
};

mw.ext.webauthn.RegisterFormWidget.prototype.submitForm = function ( e ) {
	if ( !this.readyToSubmit ) {
		e.preventDefault();
		this.validateAndEmit();
	}
};

mw.ext.webauthn.RegisterFormWidget.prototype.validateAndEmit = function () {
	this.emit( 'addKey', this.name.getValue() );
};
