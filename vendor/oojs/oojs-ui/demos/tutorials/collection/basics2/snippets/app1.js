( function () {
	var input = new OO.ui.TextInputWidget( {
			placeholder: 'Add a ToDo item'
		} ),
		list = new OO.ui.SelectWidget( {
			classes: [ 'todo-list' ],
			items: [
				new OO.ui.OptionWidget( {
					label: 'Item 1',
					data: 'Item 1'
				} ),
				new OO.ui.OptionWidget( {
					label: 'Item 2',
					data: 'Item 2'
				} ),
				new OO.ui.OptionWidget( {
					label: 'Item 3',
					data: 'Item 3'
				} )
			]
		} ),
		info = new OO.ui.LabelWidget( {
			label: 'Information',
			classes: [ 'todo-info' ]
		} );

	// Respond to 'enter' keypress
	input.on( 'enter', function () {
		// Check for duplicates
		if ( list.findItemFromData( input.getValue() ) ||
				input.getValue() === '' ) {
			input.$element.addClass( 'todo-error' );
			return;
		}
		input.$element.removeClass( 'todo-error' );

		list.addItems( [
			new OO.ui.OptionWidget( {
				data: input.getValue(),
				label: input.getValue()
			} )
		] );
		input.setValue( '' );
	} );

	list.on( 'choose', function ( item ) {
		info.setLabel( item.getData() );
	} );

	$( '.embed-app1' ).append(
		new OO.ui.FieldsetLayout( {
			id: 'tutorials-basics2-app1',
			label: 'Demo #1',
			items: [
				input,
				list,
				info
			]
		} ).$element
	);
}() );
