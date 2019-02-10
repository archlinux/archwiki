( function () {
	var input = new OO.ui.TextInputWidget(),
		list = new OO.ui.SelectWidget( {
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
		} );

	// Respond to 'enter' keypress
	input.on( 'enter', function () {
		list.addItems( [
			new OO.ui.OptionWidget( {
				data: input.getValue(),
				label: input.getValue()
			} )
		] );
	} );

	$( '.embed-app2' ).append(
		new OO.ui.FieldsetLayout( {
			id: 'tutorials-basics1-app2',
			label: 'Demo #2',
			items: [
				input,
				list
			]
		} ).$element
	);
}() );
