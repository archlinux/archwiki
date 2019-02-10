Widgets.ToDoListWidget5 = function ToDoListWidget5( config ) {
	config = config || {};
	ToDoListWidget5.parent.call( this, config );

	this.aggregate( {
		'delete': 'itemDelete'
	} );

	this.connect( this, { itemDelete: 'onItemDelete' } );
};

OO.inheritClass( Widgets.ToDoListWidget5, OO.ui.SelectWidget );

Widgets.ToDoListWidget5.prototype.onItemDelete = function ( itemWidget ) {
	this.removeItems( [ itemWidget ] );
};
