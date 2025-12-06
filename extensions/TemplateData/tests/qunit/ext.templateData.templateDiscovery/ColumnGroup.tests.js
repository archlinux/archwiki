'use strict';

const Column = require( 'ext.templateData.templateDiscovery/categories/Column.js' );
const ColumnGroup = require( 'ext.templateData.templateDiscovery/categories/ColumnGroup.js' );

QUnit.module( 'ext.templateData.templateDiscovery.categories', QUnit.newMwEnvironment() );

QUnit.test( 'Adding and removing columns', ( assert ) => {
	// Create a dummy DataStore.
	const dataStore = {
		getColumnData: ( value ) => {
			switch ( value ) {
				case 'Category:Templates':
					return Promise.resolve( [
						{ data: { value: 'Category:Foo' }, label: 'Foo' },
						{ data: { value: 'Category:Bar' }, label: 'Bar' },
						{ data: { value: 'Template:Lorem' }, label: 'Lorem' }
					] );
				case 'Category:Bar':
					return Promise.resolve( [
						{ data: { value: 'Category:Foo2' }, label: 'Foo2' },
						{ data: { value: 'Category:Bar2' }, label: 'Bar2' },
						{ data: { value: 'Template:Lorem2' }, label: 'Lorem2' }
					] );
				default:
					return Promise.reject();
			}
		},
		getItemData: () => {
		}
	};

	// No columns.
	const columnGroup = new ColumnGroup( { dataStore: dataStore } );
	assert.strictEqual( columnGroup.getColumns().length, 0 );

	// Add one column, and retrieve an item from it.
	const col1 = new Column( { dataStore: dataStore } );
	columnGroup.addColumn( col1 );
	col1.loadItems( 'Category:Templates' ).then( () => {
		assert.strictEqual( columnGroup.getColumns().length, 1 );
		assert.strictEqual( columnGroup.getColumns()[ 0 ].getItem( 1 ).label, 'Bar' );
	} );
} );
