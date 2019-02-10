Widgets.ToDoItemWidget3 = function ( config ) {
	config = config || {};
	Widgets.ToDoItemWidget3.parent.call( this, config );

	this.deleteButton = new OO.ui.ButtonWidget( {
		label: 'Delete'
	} );

	this.$element
		.addClass( 'todo-itemWidget' )
		.append( this.deleteButton.$element );
};

OO.inheritClass( Widgets.ToDoItemWidget3, Widgets.ToDoItemWidget2 );
