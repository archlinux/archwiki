Demo.IndexedDialog = function DemoIndexedDialog( config ) {
	Demo.IndexedDialog.parent.call( this, config );
};
OO.inheritClass( Demo.IndexedDialog, OO.ui.ProcessDialog );
Demo.IndexedDialog.static.title = 'Indexed dialog';
Demo.IndexedDialog.static.actions = [
	{ action: 'save', label: 'Done', flags: [ 'primary', 'progressive' ] },
	{ action: 'cancel', label: 'Cancel', flags: [ 'safe', 'back' ] }
];
Demo.IndexedDialog.prototype.getBodyHeight = function () {
	return 250;
};
Demo.IndexedDialog.prototype.initialize = function () {
	Demo.IndexedDialog.parent.prototype.initialize.apply( this, arguments );
	this.indexLayout = new OO.ui.IndexLayout();
	this.tabPanels = [
		new Demo.SampleTabPanel( 'first', { label: 'One' } ),
		new Demo.SampleTabPanel( 'second', { label: 'Two' } ),
		new Demo.SampleTabPanel( 'third', { label: 'Three' } ),
		new Demo.SampleTabPanel( 'fourth', { label: 'Four' } )
	];

	this.indexLayout.addTabPanels( this.tabPanels );
	this.$body.append( this.indexLayout.$element );

	this.indexLayout.getTabs().getItemFromData( 'fourth' ).setDisabled( true );
};
Demo.IndexedDialog.prototype.getActionProcess = function ( action ) {
	if ( action ) {
		return new OO.ui.Process( function () {
			this.close( { action: action } );
		}, this );
	}
	return Demo.IndexedDialog.parent.prototype.getActionProcess.call( this, action );
};
