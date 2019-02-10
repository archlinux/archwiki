Widgets.ToDoItemWidget5 = function ( config ) {
	config = config || {};
	Widgets.ToDoItemWidget5.parent.call( this, config );

	this.deleteButton.connect( this, { click: 'onDeleteButtonClick' } );
};

OO.inheritClass( Widgets.ToDoItemWidget5, Widgets.ToDoItemWidget3 );

Widgets.ToDoItemWidget5.prototype.onDeleteButtonClick = function () {
	this.emit( 'delete' );
};
