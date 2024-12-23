QUnit.module( 'ext.echo.dm - NotificationGroupsList' );

QUnit.test( 'Constructing the model', ( assert ) => {
	const model = new mw.echo.dm.NotificationGroupsList();

	assert.strictEqual(
		model.getTimestamp(),
		0,
		'Empty group has timestamp 0'
	);
} );

QUnit.test( 'Managing lists', ( assert ) => {
	const model = new mw.echo.dm.NotificationGroupsList();
	const groupDefinitions = [
		{
			name: 'foo',
			sourceData: {
				title: 'Foo Wiki',
				base: 'http://foo.wiki.sample/$1'
			},
			items: [
				new mw.echo.dm.NotificationItem( 0 ),
				new mw.echo.dm.NotificationItem( 1 ),
				new mw.echo.dm.NotificationItem( 2 )
			]
		},
		{
			name: 'bar',
			sourceData: {
				title: 'Bar Wiki',
				base: 'http://bar.wiki.sample/$1'
			},
			items: [
				new mw.echo.dm.NotificationItem( 3 ),
				new mw.echo.dm.NotificationItem( 4 ),
				new mw.echo.dm.NotificationItem( 5 ),
				new mw.echo.dm.NotificationItem( 6 )
			]
		},
		{
			name: 'baz',
			sourceData: {
				title: 'Baz Wiki',
				base: 'http://baz.wiki.sample/$1'
			},
			items: [
				new mw.echo.dm.NotificationItem( 7 )
			]
		}
	];

	groupDefinitions.forEach( ( def, i ) => {
		model.addGroup(
			def.name,
			def.sourceData,
			def.items
		);

		assert.strictEqual(
			model.getItemCount(),
			i + 1,
			'Group number increases after addGroup ("' + def.name + '")'
		);

		const result = model.getGroupByName( def.name );
		assert.strictEqual(
			result.getName(),
			def.name,
			'Group exists after addGroup ("' + def.name + '")'
		);
	} );

	// Remove group
	model.removeGroup( groupDefinitions[ 0 ].name );

	assert.strictEqual(
		model.getItemCount(),
		groupDefinitions.length - 1,
		'Group number decreased after removeGroup'
	);
	assert.strictEqual(
		model.getGroupByName( groupDefinitions[ 0 ] ),
		null,
		'Removed group is no longer in the list'
	);

	// Removing the last item from a group should remove the group
	const group = model.getGroupByName( 'baz' );
	group.discardItems( groupDefinitions[ 2 ].items );
	assert.strictEqual(
		model.getGroupByName( 'baz' ),
		null,
		'Empty group is no longer in the list'
	);
} );

QUnit.test( 'Emitting discard event', ( assert ) => {
	const results = [];
	const model = new mw.echo.dm.NotificationGroupsList();
	const groups = {
		first: [
			new mw.echo.dm.NotificationItem( 0 ),
			new mw.echo.dm.NotificationItem( 1 ),
			new mw.echo.dm.NotificationItem( 2 )
		],
		second: [
			new mw.echo.dm.NotificationItem( 3 ),
			new mw.echo.dm.NotificationItem( 4 ),
			new mw.echo.dm.NotificationItem( 5 )
		],
		third: [
			new mw.echo.dm.NotificationItem( 6 ),
			new mw.echo.dm.NotificationItem( 7 )
		],
		fourth: [
			new mw.echo.dm.NotificationItem( 8 ),
			new mw.echo.dm.NotificationItem( 9 )
		]
	};

	// Listen to the event
	model
		.on( 'discard', ( g ) => {
			results.push( g.getName() );
		} );

	// Fill the list
	for ( const group in groups ) {
		model.addGroup( group, {}, groups[ group ] );
	}

	// Trigger events
	model.removeGroup( 'first' ); // [ 'first' ]
	model.removeGroup( 'fourth' ); // [ 'first', 'fourth' ]
	// Group doesn't exist, no change
	model.removeGroup( 'first' ); // [ 'first', 'fourth' ]
	// Discard of an item in a group (no event on the list model)
	model.getGroupByName( 'third' ).discardItems( groups.third[ 0 ] ); // [ 'first', 'fourth' ]
	// Discard of the last item in a group (trigger discard event on the list model)
	model.getGroupByName( 'third' ).discardItems( groups.third[ 1 ] ); // [ 'first', 'fourth', 'third' ]

	assert.deepEqual(
		// Actual
		results,
		// Expected:
		[ 'first', 'fourth', 'third' ],
		// Message
		'Discard events emitted'
	);
} );
