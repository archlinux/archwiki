QUnit.module( 'ext.echo.dm - CrossWikiNotificationItem' );

QUnit.test.each( 'Constructing the model', {
	'Default values': {
		params: {
			id: -1,
			config: {}
		},
		expected: {}
	},
	'Overriding model name': {
		params: {
			id: -1,
			config: { modelName: 'foo' }
		},
		expected: {
			getModelName: 'foo'
		}
	},
	'Overriding model count': {
		params: {
			id: -1,
			config: { count: 10 }
		},
		expected: {
			getCount: 10
		}
	}
}, ( assert, data ) => {
	const defaults = {
		getModelName: 'xwiki',
		getSourceNames: [],
		getCount: 0,
		hasUnseen: false,
		getItems: [],
		isEmpty: true
	};
	const expected = $.extend( true, {}, defaults, data.expected );

	const model = new mw.echo.dm.CrossWikiNotificationItem(
		data.params.id,
		data.params.config
	);

	for ( const method in defaults ) {
		assert.deepEqual(
			// Run the method
			model[ method ](),
			// Expected value
			expected[ method ],
			// Message
			method
		);
	}
} );

QUnit.test( 'Managing notification lists', ( assert ) => {
	const model = new mw.echo.dm.CrossWikiNotificationItem( 1 );
	const groupDefinitions = [
		{
			name: 'foo',
			sourceData: {
				title: 'Foo Wiki',
				base: 'http://foo.wiki.sample/$1'
			},
			items: [
				new mw.echo.dm.NotificationItem( 0, { source: 'foo', read: false, seen: false, timestamp: '201601010100' } ),
				new mw.echo.dm.NotificationItem( 1, { source: 'foo', read: false, seen: false, timestamp: '201601010200' } ),
				new mw.echo.dm.NotificationItem( 2, { source: 'foo', read: false, seen: false, timestamp: '201601010300' } )
			]
		},
		{
			name: 'bar',
			sourceData: {
				title: 'Bar Wiki',
				base: 'http://bar.wiki.sample/$1'
			},
			items: [
				new mw.echo.dm.NotificationItem( 3, { source: 'bar', read: false, seen: false, timestamp: '201601020000' } ),
				new mw.echo.dm.NotificationItem( 4, { source: 'bar', read: false, seen: false, timestamp: '201601020100' } ),
				new mw.echo.dm.NotificationItem( 5, { source: 'bar', read: false, seen: false, timestamp: '201601020200' } ),
				new mw.echo.dm.NotificationItem( 6, { source: 'bar', read: false, seen: false, timestamp: '201601020300' } )
			]
		},
		{
			name: 'baz',
			sourceData: {
				title: 'Baz Wiki',
				base: 'http://baz.wiki.sample/$1'
			},
			items: [
				new mw.echo.dm.NotificationItem( 7, { source: 'baz', timestamp: '201601050100' } )
			]
		}
	];

	// Add groups to model
	groupDefinitions.forEach( ( def ) => {
		model.getList().addGroup(
			def.name,
			def.sourceData,
			def.items
		);
	} );

	assert.deepEqual(
		model.getSourceNames(),
		[ 'baz', 'bar', 'foo' ],
		'Model source names exist in order'
	);
	assert.strictEqual(
		model.hasUnseen(),
		true,
		'hasUnseen is true if there are unseen items in any group'
	);

	// Mark all items as seen except one
	groupDefinitions.forEach( ( def ) => {
		def.items.forEach( ( item ) => {
			item.toggleSeen( true );
		} );
	} );
	groupDefinitions[ 0 ].items[ 0 ].toggleSeen( false );
	assert.strictEqual(
		model.hasUnseen(),
		true,
		'hasUnseen is true even if only one item in one group is unseen'
	);

	groupDefinitions[ 0 ].items[ 0 ].toggleSeen( true );
	assert.strictEqual(
		model.hasUnseen(),
		false,
		'hasUnseen is false if there are no unseen items in any of the groups'
	);

	// Discard group
	model.getList().removeGroup( 'foo' );
	assert.deepEqual(
		model.getSourceNames(),
		[ 'baz', 'bar' ],
		'Group discarded successfully'
	);
} );

QUnit.test( 'Update seen state', ( assert ) => {
	const model = new mw.echo.dm.CrossWikiNotificationItem( 1 );
	const groupDefinitions = [
		{
			name: 'foo',
			sourceData: {
				title: 'Foo Wiki',
				base: 'http://foo.wiki.sample/$1'
			},
			items: [
				new mw.echo.dm.NotificationItem( 0, { source: 'foo', read: false, seen: false, timestamp: '201601010100' } ),
				new mw.echo.dm.NotificationItem( 1, { source: 'foo', read: false, seen: false, timestamp: '201601010200' } ),
				new mw.echo.dm.NotificationItem( 2, { source: 'foo', read: false, seen: false, timestamp: '201601010300' } )
			]
		},
		{
			name: 'bar',
			sourceData: {
				title: 'Bar Wiki',
				base: 'http://bar.wiki.sample/$1'
			},
			items: [
				new mw.echo.dm.NotificationItem( 3, { source: 'bar', read: false, seen: false, timestamp: '201601020000' } ),
				new mw.echo.dm.NotificationItem( 4, { source: 'bar', read: false, seen: false, timestamp: '201601020100' } ),
				new mw.echo.dm.NotificationItem( 5, { source: 'bar', read: false, seen: false, timestamp: '201601020200' } ),
				new mw.echo.dm.NotificationItem( 6, { source: 'bar', read: false, seen: false, timestamp: '201601020300' } )
			]
		},
		{
			name: 'baz',
			sourceData: {
				title: 'Baz Wiki',
				base: 'http://baz.wiki.sample/$1'
			},
			items: [
				new mw.echo.dm.NotificationItem( 7, { source: 'baz', timestamp: '201601050100' } )
			]
		}
	];

	// Count all actual items
	const numAllItems = groupDefinitions.reduce( ( prev, curr ) => prev + curr.items.length, 0 );

	// Add groups to model
	for ( let i = 0; i < groupDefinitions.length; i++ ) {
		model.getList().addGroup(
			groupDefinitions[ i ].name,
			groupDefinitions[ i ].sourceData,
			groupDefinitions[ i ].items
		);
	}

	let numUnseenItems = model.getItems().filter( ( item ) => !item.isSeen() ).length;
	assert.strictEqual(
		numUnseenItems,
		numAllItems,
		'Starting state: all items are unseen'
	);

	// Update seen time to be bigger than 'foo' but smaller than the other groups
	model.updateSeenState( '201601010400' );

	numUnseenItems = model.getItems().filter( ( item ) => !item.isSeen() ).length;
	assert.strictEqual(
		numUnseenItems,
		numAllItems - groupDefinitions[ 0 ].items.length,
		'Only some items are seen'
	);

	// Update seen time to be bigger than all
	model.updateSeenState( '201701010000' );

	numUnseenItems = model.getItems().filter( ( item ) => !item.isSeen() ).length;
	assert.strictEqual(
		numUnseenItems,
		0,
		'All items are seen'
	);
} );

QUnit.test( 'Emit discard event', ( assert ) => {
	const results = [];
	const model = new mw.echo.dm.CrossWikiNotificationItem( -1 );
	const groupDefinitions = [
		{
			name: 'foo',
			sourceData: {
				title: 'Foo Wiki',
				base: 'http://foo.wiki.sample/$1'
			},
			items: [
				new mw.echo.dm.NotificationItem( 0, { source: 'foo', read: false, seen: false, timestamp: '201601010100' } ),
				new mw.echo.dm.NotificationItem( 1, { source: 'foo', read: false, seen: false, timestamp: '201601010200' } ),
				new mw.echo.dm.NotificationItem( 2, { source: 'foo', read: false, seen: false, timestamp: '201601010300' } )
			]
		},
		{
			name: 'bar',
			sourceData: {
				title: 'Bar Wiki',
				base: 'http://bar.wiki.sample/$1'
			},
			items: [
				new mw.echo.dm.NotificationItem( 3, { source: 'bar', read: false, seen: false, timestamp: '201601020000' } ),
				new mw.echo.dm.NotificationItem( 4, { source: 'bar', read: false, seen: false, timestamp: '201601020100' } ),
				new mw.echo.dm.NotificationItem( 5, { source: 'bar', read: false, seen: false, timestamp: '201601020200' } ),
				new mw.echo.dm.NotificationItem( 6, { source: 'bar', read: false, seen: false, timestamp: '201601020300' } )
			]
		},
		{
			name: 'baz',
			sourceData: {
				title: 'Baz Wiki',
				base: 'http://baz.wiki.sample/$1'
			},
			items: [
				new mw.echo.dm.NotificationItem( 7, { source: 'baz', timestamp: '201601050100' } )
			]
		}
	];

	// Add groups to model
	for ( let i = 0; i < groupDefinitions.length; i++ ) {
		model.getList().addGroup(
			groupDefinitions[ i ].name,
			groupDefinitions[ i ].sourceData,
			groupDefinitions[ i ].items
		);
	}

	// Listen to event
	model.on( 'discard', ( name ) => {
		results.push( name );
	} );

	// Trigger
	model.getList().removeGroup( 'foo' ); // [ 'foo' ]
	// Empty a list
	model.getList().getGroupByName( 'baz' ).discardItems( groupDefinitions[ 2 ].items ); // [ 'foo', 'baz' ]

	assert.deepEqual(
		results,
		[ 'foo', 'baz' ],
		'Discard event emitted'
	);
} );
