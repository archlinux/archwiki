Demo.CapsuleNumberPopupMultiselectWidget = function DemoCapsuleNumberPopupMultiselectWidget( config ) {
	// Properties
	this.capsulePopupWidget = new OO.ui.NumberInputWidget( {
		isInteger: true
	} );

	// Parent constructor
	Demo.CapsuleNumberPopupMultiselectWidget.parent.call( this, $.extend( {}, config, {
		allowArbitrary: true,
		popup: { $content: this.capsulePopupWidget.$element }
	} ) );

	// Events
	this.capsulePopupWidget.connect( this, { enter: 'onPopupEnter' } );
};

OO.inheritClass( Demo.CapsuleNumberPopupMultiselectWidget, OO.ui.CapsuleMultiselectWidget );

Demo.CapsuleNumberPopupMultiselectWidget.prototype.onPopupEnter = function () {
	if ( !isNaN( this.capsulePopupWidget.getNumericValue() ) ) {
		this.addItemsFromData( [ this.capsulePopupWidget.getNumericValue() ] );
		this.capsulePopupWidget.setValue( '' );
	}
};
